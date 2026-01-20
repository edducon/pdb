let map = null;
let housesCluster = null;
let parkingLayer = null;
let selectedHouseLayer = null;

let selectedUnom = null;
let housesHidden = false;
let currentUserHomeUnom = null;

let modalLogin, modalRegister, modalProfile;

let searchAbort = null;

const suggestCache = new Map();
const CACHE_TTL_MS = 2 * 60 * 1000;

function $(id){ return document.getElementById(id); }

function toast(msg, show=true){
    const el = $("toast");
    if (!el) return;
    if (!show){ el.classList.remove("show"); return; }
    el.textContent = msg;
    el.classList.remove("d-none");
    requestAnimationFrame(() => el.classList.add("show"));
    setTimeout(() => {
        el.classList.remove("show");
        setTimeout(() => el.classList.add("d-none"), 300);
    }, 2500);
}

function debounce(fn, ms){
    let t;
    return (...args) => { clearTimeout(t); t=setTimeout(() => fn(...args), ms); };
}
function getClusterPreset(id) {
    if (id === 1) return "islands#greenCircleDotIcon";
    if (id === 2) return "islands#yellowCircleDotIcon";
    if (id === 3) return "islands#redCircleDotIcon";
    return "islands#blueCircleDotIcon"; // Если данных нет
}
function getClusterText(id) {
    if (id === 1) return "<span class='text-success fw-bold'>🟢 Благоприятная обстановка</span><br><small class='text-muted'>Достаточное количество парковочных мест.</small>";
    if (id === 2) return "<span class='text-warning fw-bold'>🟡 Затрудненная парковка</span><br><small class='text-muted'>Высокий спрос в вечернее время.</small>";
    if (id === 3) return "<span class='text-danger fw-bold'>🔴 Критический дефицит</span><br><small class='text-muted'>Систематическая нехватка мест.</small>";
    return "<span class='text-muted'>⚪ Недостаточно данных</span><br><small class='text-muted'>Требуется сбор статистики.</small>";
}

function renderHistogram(data) {
    if (!data || data.length === 0) return '';

    let bars = data.map(d => {
        let color = '#2ecc71';
        if(d.score > 40) color = '#f1c40f';
        if(d.score > 75) color = '#e74c3c';

        return `
            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                <div style="width:100%; display:flex; align-items:flex-end; height:60px; background:#ecf0f1; border-radius:4px; overflow:hidden;">
                    <div style="width:100%; height:${d.score}%; background:${color}; transition:height 0.3s;"></div>
                </div>
                <div style="font-size:10px; color:#95a5a6; margin-top:4px;">${d.date}</div>
            </div>
        `;
    }).join('<div style="width:4px;"></div>');

    return `
        <div class="card-ui mb-3 pt-3">
            <div class="meta-label mb-2">Динамика загруженности (7 дней)</div>
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                ${bars}
            </div>
            <div class="text-center mt-2" style="font-size:10px; color:#bdc3c7;">0% = Свободно &nbsp; • &nbsp; 100% = Мест нет</div>
        </div>
    `;
}

async function apiGet(url, opts={}){
    try {
        const r = await fetch(url, opts);
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
    } catch(e) {
        if (e.name !== 'AbortError') console.error(e);
        throw e;
    }
}

async function apiPost(url, body){
    const r = await fetch(url, { method:"POST", body });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
}

function sheetOpen(open){
    const s = $("sheet");
    if (!s) return;
    if (window.innerWidth < 992) {
        if (open) s.classList.add("open");
        else s.classList.remove("open");
    }
}

function bboxLonLat(){
    if (!map) return null;
    try {
        const b = map.getBounds();
        const [[lat1, lon1],[lat2, lon2]] = b;
        return [lon1,lat1,lon2,lat2].join(",");
    } catch(e) { return null; }
}

function toggleHousesLayer(forceState = null) {
    if (!map || !housesCluster) return;

    const btn = $("btnShowHousesMap");
    let wantToShow;

    if (forceState === null) {
        wantToShow = housesHidden;
    } else {
        wantToShow = !!forceState;
    }

    if (wantToShow) {
        housesHidden = false;
        if (housesCluster) housesCluster.options.set('visible', true);
        if (btn) { btn.classList.add("active"); btn.textContent = "🏠 Скрыть дома"; }
        loadHousesInView();
    } else {
        housesHidden = true;
        if (housesCluster) {
            housesCluster.removeAll();
            housesCluster.options.set('visible', false);
        }
        if (btn) { btn.classList.remove("active"); btn.textContent = "🏠 Показать дома"; }
    }
}

function setSelectedHouseUI(h){
    if (parkingLayer && typeof parkingLayer.removeAll === 'function') {
        parkingLayer.removeAll();
    }

    if ($("nearResult")) {
        $("nearResult").innerHTML = getClusterText(h.cluster_id);
    }

    $("nearList").innerHTML = `<div id="parkPlaceholder" class="text-center text-muted small mt-4">Здесь появится список платных парковок,<br>когда вы нажмете кнопку "Парковки рядом".</div>`;

    const btnHouses = $("btnShowHousesMap");
    if(btnHouses) btnHouses.style.display = "none";

    selectedUnom = h.unom;
    $("badgeUnom").textContent = `ID ${h.unom}`;
    $("houseAddress").textContent = h.address_simple || h.address_full || "—";

    const capBlock = $("capacityBlock");
    const capInput = $("houseCapacityInput");
    const avgLabel = $("avgCapacityVal");
    const callToAction = $("callToActionMyHome");

    if (capBlock) capBlock.classList.add("d-none");
    if (callToAction) callToAction.classList.add("d-none");

    if (window.__IS_LOGGED_IN__) {
        if (currentUserHomeUnom && String(currentUserHomeUnom) === String(h.unom)) {
            if (capBlock) capBlock.classList.remove("d-none");
            if (capInput) capInput.value = (h.my_capacity_vote !== null) ? h.my_capacity_vote : "";
        } else if (!currentUserHomeUnom) {
            if (callToAction) callToAction.classList.remove("d-none");
        }
    }

    const avgText = (h.courtyard_capacity !== null) ? `${h.courtyard_capacity}` : "—";
    if (avgLabel) avgLabel.textContent = avgText;

    if (selectedHouseLayer && typeof ymaps !== 'undefined') {
        selectedHouseLayer.removeAll();
        if (h.lat != null && h.lon != null){
            try {
                const pm = new ymaps.Placemark([h.lat, h.lon], {
                    hintContent: "Выбран",
                    balloonContent: h.address_simple
                }, { preset:"islands#redHomeIcon", zIndex: 10000 });
                selectedHouseLayer.add(pm);
            } catch(e) {}
        }
    }

    toggleHousesLayer(true);

    // Грузим историю/графики
    refreshReports().catch(()=>{});

    sheetOpen(true);
}

async function loadHousesInView(){
    if (!map || housesHidden) return;
    if (typeof ymaps === 'undefined') return;

    if (map.getZoom() < 14){
        if(housesCluster) housesCluster.removeAll();
        return;
    }

    const bbox = bboxLonLat();
    if (!bbox) return;

    const url = `api/houses.php?bbox=${encodeURIComponent(bbox)}&limit=900`;
    try{
        const data = await apiGet(url);
        if (!housesCluster) return;

        housesCluster.removeAll();

        const points = (data.items||[]).map(it => {
            const isSelected = (selectedUnom && String(selectedUnom) === String(it.unom));

            const preset = getClusterPreset(it.cluster_id);

            const pm = new ymaps.Placemark([it.lat, it.lon], {
                hintContent: it.address,
                balloonContentHeader: `ID ${it.unom}`,
                unom: it.unom
            }, {
                preset: preset,
                visible: !isSelected
            });

            pm.properties.set('unom', it.unom);

            pm.events.add("click", async (e) => {
                e.preventDefault();
                try {
                    const h = await apiGet(`api/house.php?unom=${it.unom}`);
                    setSelectedHouseUI(h);
                } catch(e) { toast("Ошибка загрузки данных"); }
            });
            return pm;
        });

        housesCluster.add(points);
    }catch(e){}
}

async function showPaidParkingsNear(){
    if (!selectedUnom){ toast("Сначала выбери дом"); return; }
    if (!map) { toast("Карта еще не загружена"); return; }

    const r = $("radius").value;
    const btnHouses = $("btnShowHousesMap");
    if(btnHouses) btnHouses.style.display = "block";

    toggleHousesLayer(false);

    try{
        const data = await apiGet(`api/parkings_near.php?unom=${selectedUnom}&r=${r}`);
        $("nearResult").innerHTML = `<span class="text-muted">Найдено:</span> <b>${data.x2_paid_cnt}</b>`;

        if (parkingLayer) parkingLayer.removeAll();
        const list = $("nearList");
        list.innerHTML = "";

        document.querySelector('[data-tab="tab-park"]').click();
        sheetOpen(true);

        if (!data.items || data.items.length === 0) {
            list.innerHTML = `<div class="p-3 text-center text-muted">Парковок рядом нет :(</div>`;
            return;
        }

        (data.items||[]).forEach((p, idx) => {
            if (map && parkingLayer) {
                const pm = new ymaps.Placemark([p.lat, p.lon], {
                    balloonContentHeader: p.name,
                    balloonContentBody: `Мест: ${p.capacity || "?"}`
                }, { preset: "islands#orangeIcon", zIndex: 9999 });
                parkingLayer.add(pm);
            }
            const div = document.createElement("div");
            div.className = "item";

            const yandexLink = `https://yandex.ru/maps/?rtext=~${p.lat},${p.lon}&rtt=auto`;

            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="t">${idx+1}. ${p.name || "Парковка"}</div>
                        <div class="s">${p.address || ""}</div>
                        <div class="s">~${p.dist_m} м • мест: ${p.capacity ?? "—"}</div>
                    </div>
                    <div class="ms-2">
                        <a href="${yandexLink}" target="_blank" 
                           class="btn btn-sm btn-outline-primary py-1 px-2" 
                           style="font-size: 12px; white-space: nowrap; text-decoration: none;"
                           onclick="event.stopPropagation()">
                           Маршрут &rarr;
                        </a>
                    </div>
                </div>
            `;

            div.onclick = () => {
                if (map && typeof map.setCenter === 'function')
                    map.setCenter([p.lat, p.lon], Math.max(map.getZoom(), 16), {duration:300});
            };
            list.appendChild(div);
        });
        toast(`Найдено ${data.items.length} парковок`);
    }catch(e){
        console.error(e);
        toast("Ошибка поиска");
    }
}

function createSuggestItem(it, onClick) {
    const d = document.createElement("div");
    d.className = "suggest-item";
    d.innerHTML = `<div>${it.address}</div><small class="text-muted">ID ${it.unom}</small>`;
    d.onclick = onClick;
    return d;
}

const doLiveSearchProfile = debounce(async () => {
    const q = $("profileSearchInput").value.trim();
    const box = $("profileSuggest");
    if(q.length<3){ box.classList.add("d-none"); return; }
    try{
        const data = await apiGet(`api/search.php?q=${encodeURIComponent(q)}`);
        const items = data.items || [];
        box.innerHTML = "";
        if (!items.length) { box.classList.add("d-none"); return; }
        items.forEach(it => {
            box.appendChild(createSuggestItem(it, () => {
                $("profileHomeUnom").value = it.unom;
                $("profileSearchInput").value = it.address;
                box.classList.add("d-none");
            }));
        });
        box.classList.remove("d-none");
    }catch(e){}
}, 500);

const doLiveSearch = debounce(async () => {
    const q = $("searchInput").value.trim();
    const box = $("searchSuggest");
    if(q.length<3){ box.classList.add("d-none"); return; }

    if(searchAbort) searchAbort.abort();
    searchAbort = new AbortController();

    try{
        const r = await fetch(`api/search.php?q=${encodeURIComponent(q)}`, {signal: searchAbort.signal});
        if (!r.ok) return;
        const data = await r.json();

        const items = data.items || [];
        box.innerHTML = "";
        if (!items.length){ box.classList.add("d-none"); return; }
        items.forEach(it => {
            box.appendChild(createSuggestItem(it, async () => {
                box.classList.add("d-none");
                try {
                    const h = await apiGet(`api/house.php?unom=${it.unom}`);
                    if(it.lat && map && typeof map.setCenter === 'function')
                        map.setCenter([it.lat,it.lon], 17);
                    setSelectedHouseUI(h);
                } catch(e){}
            }));
        });
        box.classList.remove("d-none");
    }catch(e){
        if (e.name !== 'AbortError') console.error(e);
    }
}, 500);

function statusLabel(s){ if (s === "free") return "<span class='text-success'>Свободно</span>"; if (s === "medium") return "<span class='text-warning'>Средне</span>"; return "<span class='text-danger'>Мест нет</span>"; }
function slotLabel(s){ const m = {morning:"Утро", day:"День", evening:"Вечер", night:"Ночь"}; return m[s] || s; }
async function refreshReports(){
    const box = $("reportsBox");
    if(!box || !selectedUnom) return;

    try {
        const data = await apiGet(`api/reports_house.php?unom=${selectedUnom}&days=14`);

        let html = renderHistogram(data.chart);

        if(!data.items.length) {
            html += "<div class='text-muted p-2'>Нет текстовых отметок</div>";
        } else {
            html += `<div class="meta-label mt-3 mb-2">Лента событий</div>`;
            html += data.items.map(it => `
                <div class="item" style="border-left: 3px solid ${it.status==='free'?'#2ecc71':(it.status==='full'?'#e74c3c':'#f1c40f')}; padding-left:10px;">
                    <div class="d-flex justify-content-between">
                        <div class="t small">${it.report_date}</div>
                        <div class="s small text-uppercase">${slotLabel(it.time_slot)}</div>
                    </div>
                    ${it.comment ? `<div class="text-dark mt-1" style="font-size:13px;">${it.comment}</div>` : "<div class='s'>Без комментария</div>"}
                </div>
            `).join("");
        }

        box.innerHTML = html;
    } catch(e){
        console.error(e);
        box.innerHTML = `<div class="text-danger small">Ошибка загрузки истории</div>`;
    }
}
async function submitReport(){
    if (!selectedUnom){ toast("Сначала выбери дом"); return; }

    const fd = new FormData();
    fd.append("unom", String(selectedUnom));
    fd.append("status", $("repStatus").value);
    fd.append("time_slot", $("repSlot").value);
    fd.append("comment", $("repComment").value);

    try {
        const res = await apiPost(`api/report_add.php`, fd);

        if (res && res.error){
            toast(res.error);
            return;
        }

        $("repComment").value = "";
        toast("Отметка сохранена!");
        await refreshReports();
        document.querySelector('[data-tab="tab-history"]').click();
    } catch(e) {
        toast("Ошибка отправки");
    }
}
function setAuthUI(loggedIn){ window.__IS_LOGGED_IN__ = !!loggedIn; location.reload(); }
async function ajaxLogin(l, p){ const fd = new FormData(); fd.append("login", l); fd.append("password", p); return apiPost("api/auth_login.php", fd); }
async function ajaxRegister(l, p){ const fd = new FormData(); fd.append("login", l); fd.append("password", p); return apiPost("api/auth_register.php", fd); }
async function ajaxLogout(){ return apiGet("api/auth_logout.php"); }
async function profileGet(){ return apiGet("api/profile_get.php"); }
async function profileSetHome(u){ const fd = new FormData(); if (u===null) fd.append("home_unom",""); else fd.append("home_unom", String(u)); return apiPost("api/profile_set_home.php", fd); }

function renderProfileState(homeUnom) {
    const blockAdd = $("profileAddHomeBlock");
    const blockExist = $("profileExistingHomeBlock");

    if (homeUnom) {
        if(blockAdd) blockAdd.classList.add("d-none");
        if(blockExist) {
            blockExist.classList.remove("d-none");
            $("profileExistingAddressText").textContent = `ID ${homeUnom} (загрузка...)`;
            apiGet(`api/house.php?unom=${homeUnom}`)
                .then(h => {
                    $("profileExistingAddressText").textContent = h.address_simple || h.address_full || `Дом ID ${homeUnom}`;
                })
                .catch(() => {
                    $("profileExistingAddressText").textContent = `Дом ID ${homeUnom}`;
                });
        }
    } else {
        if(blockExist) blockExist.classList.add("d-none");
        if(blockAdd) {
            blockAdd.classList.remove("d-none");
            $("profileSearchInput").value = "";
            $("profileHomeUnom").value = "";
        }
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const elL = $("modalLogin"), elR = $("modalRegister"), elP = $("modalProfile");
    if(elL) modalLogin = new bootstrap.Modal(elL);
    if(elR) modalRegister = new bootstrap.Modal(elR);
    if(elP) modalProfile = new bootstrap.Modal(elP);

    if (window.__IS_LOGGED_IN__) {
        profileGet().then(p => {
            if(p.home_unom) currentUserHomeUnom = p.home_unom;
        }).catch(()=>{});
    }

    const btnLogin = $("btnLogin"); if (btnLogin) btnLogin.onclick = () => modalLogin.show();
    const btnRegister = $("btnRegister"); if (btnRegister) btnRegister.onclick = () => modalRegister.show();
    const btnLogout = $("btnLogout"); if (btnLogout) btnLogout.onclick = async () => { await ajaxLogout(); setAuthUI(false); };

    const btnProfile = $("btnProfile");
    if (btnProfile) btnProfile.onclick = async () => {
        try{
            const p = await profileGet();
            renderProfileState(p.home_unom);
            if ($("profileLogin")) $("profileLogin").value = p.login || "";
        }catch(e){ toast("Ошибка профиля"); }
        modalProfile.show();
    };

    const btnMyHome = $("btnMyHome");
    if (btnMyHome) btnMyHome.onclick = async () => {
        try{
            const p = await profileGet();
            if (!p.home_unom){
                toast("Укажите дом в профиле");
                renderProfileState(null);
                modalProfile.show();
                return;
            }
            const h = await apiGet(`api/house.php?unom=${p.home_unom}`);

            if (h.lat != null && h.lon != null && map && typeof map.setCenter === 'function') {
                map.setCenter([h.lat,h.lon], 17, {duration:300});
            } else if (!map) {
                toast("Данные загружены (карта не готова)");
            }

            setSelectedHouseUI(h);
        }catch(e){ toast("Ошибка"); }
    };

    const formLogin = $("formLogin");
    if(formLogin) formLogin.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        $("loginErr").classList.add("d-none");

        const fd = new FormData(ev.target);
        try{
            const res = await ajaxLogin(String(fd.get("login")), String(fd.get("password")));

            if (!res.ok){
                $("loginErr").textContent = res.error || "Ошибка входа";
                $("loginErr").classList.remove("d-none");
                return;
            }

            modalLogin.hide();
            setAuthUI(true);
        }catch(e){
            console.error(e);
            $("loginErr").textContent = "Ошибка соединения (или неверный путь)";
            $("loginErr").classList.remove("d-none");
        }
    });

    const formRegister = $("formRegister");
    if(formRegister) formRegister.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        $("regErr").classList.add("d-none");

        const consent = $("regConsent");
        if (consent && !consent.checked) {
            $("regErr").textContent = "Для регистрации необходимо согласие на обработку данных";
            $("regErr").classList.remove("d-none");
            return;
        }

        const fd = new FormData(ev.target);
        try{
            const res = await ajaxRegister(String(fd.get("login")), String(fd.get("password")));
            if (!res.ok){
                $("regErr").textContent = res.error || "Ошибка";
                $("regErr").classList.remove("d-none");
                return;
            }
            modalRegister.hide();
            await ajaxLogin(String(fd.get("login")), String(fd.get("password")));
            setAuthUI(true);
        }catch(e){
            $("regErr").textContent = "Ошибка сети";
            $("regErr").classList.remove("d-none");
        }
    });

    $("linkToRegister").onclick = (e) => { e.preventDefault(); modalLogin.hide(); modalRegister.show(); };
    $("linkToLogin").onclick = (e) => { e.preventDefault(); modalRegister.hide(); modalLogin.show(); };

    $("btnSaveHome").onclick = async () => {
        let unom = $("profileHomeUnom").value.trim();
        if (!unom){ toast("Введите адрес"); return; }
        try{
            const res = await profileSetHome(unom);
            if (!res.ok){ toast("Ошибка"); return; }
            currentUserHomeUnom = parseInt(unom);
            toast("Сохранено!");
            renderProfileState(currentUserHomeUnom);
            if (selectedUnom && String(selectedUnom) === String(currentUserHomeUnom)) {
                const h = await apiGet(`api/house.php?unom=${selectedUnom}`);
                setSelectedHouseUI(h);
            }
        }catch(e){ toast("Ошибка"); }
    };

    $("btnUseSelectedHouse").onclick = () => {
        if (selectedUnom) { $("profileHomeUnom").value = selectedUnom; $("profileSearchInput").value = $("houseAddress").textContent; }
        else { toast("Сначала выберите дом на карте"); modalProfile.hide(); }
    };
    $("profileSearchInput").addEventListener("input", doLiveSearchProfile);

    $("btnClearHome").onclick = async () => {
        if(!confirm("Удалить домашний адрес?")) return;
        try{
            await profileSetHome(null);
            currentUserHomeUnom = null;
            toast("Удалено");
            renderProfileState(null);
            if (selectedUnom) {
                const h = await apiGet(`api/house.php?unom=${selectedUnom}`);
                setSelectedHouseUI(h);
            }
        }catch(e){ toast("Ошибка"); }
    };

    const btnSaveCreds = $("btnSaveProfileCreds");
    if (btnSaveCreds) {
        btnSaveCreds.onclick = async () => {
            const l = $("profileLogin").value.trim();
            const p = $("profilePass").value.trim();
            try {
                const res = await fetch('api/profile_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ new_login: l, new_password: p })
                });
                const data = await res.json();
                if (data.ok) {
                    toast("Данные обновлены");
                    $("profilePass").value = "";
                } else {
                    toast(data.error || "Ошибка");
                }
            } catch(e) { toast("Ошибка сети"); }
        };
    }

    const btnCap = $("btnSaveCapacity");
    if (btnCap) btnCap.onclick = async () => { if (!selectedUnom) return; const val = $("houseCapacityInput").value.trim(); if (val === "") return; try { const res = await apiPost('api/house_set_capacity.php', JSON.stringify({ unom: selectedUnom, capacity: parseInt(val) })); if (res.ok) { toast("Принято"); const h = await apiGet(`api/house.php?unom=${selectedUnom}`); setSelectedHouseUI(h); } else toast("Ошибка"); } catch (e){} };

    const sheet = $("sheet"); const grab = $("sheetGrab"); if (grab) grab.addEventListener("click", () => sheet.classList.toggle("open"));
    const radius = $("radius"); if(radius) radius.addEventListener("input", () => $("rVal").textContent = radius.value);
    const repSubmit = $("repSubmit"); if(repSubmit) repSubmit.onclick = submitReport;
    const searchInput = $("searchInput"); if(searchInput) searchInput.addEventListener("input", doLiveSearch);
    const searchBtn = $("searchBtn"); if(searchBtn) searchBtn.addEventListener("click", () => liveSearch());
    const btnNearMap = $("btnNearMap"); if(btnNearMap) btnNearMap.onclick = showPaidParkingsNear;

    const btnShowHousesMap = $("btnShowHousesMap");
    if(btnShowHousesMap) {
        btnShowHousesMap.style.display = "none";
        btnShowHousesMap.onclick = () => {
            toggleHousesLayer(null);
        };
    }
});

function initMap() {
    if (typeof ymaps === 'undefined') {
        setTimeout(initMap, 100);
        return;
    }
    ymaps.ready(() => {
        map = new ymaps.Map("map", { center:[55.751244, 37.618423], zoom:12, controls:["zoomControl","geolocationControl"] });
        housesCluster = new ymaps.Clusterer({ preset:"islands#invertedBlueClusterIcons", groupByCoordinates:false, gridSize: 80 });
        parkingLayer = new ymaps.GeoObjectCollection();
        selectedHouseLayer = new ymaps.GeoObjectCollection();
        map.geoObjects.add(housesCluster); map.geoObjects.add(parkingLayer); map.geoObjects.add(selectedHouseLayer);
        toggleHousesLayer(true);
        map.events.add("boundschange", debounce(loadHousesInView, 400));
        map.events.add("click", () => { $("searchSuggest").classList.add("d-none"); });
    });
}
initMap();

document.addEventListener("click", (e) => { const t = e.target.closest(".tab"); if (!t) return; document.querySelectorAll(".tab").forEach(x => x.classList.remove("active")); document.querySelectorAll(".tab-pane").forEach(p => p.classList.remove("active")); t.classList.add("active"); const pane = document.getElementById(t.dataset.tab); if (pane) pane.classList.add("active"); });