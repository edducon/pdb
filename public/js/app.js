/* app.js — Main Application Logic */

let map, housesCluster, parkingLayer, selectedHouseLayer;
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

async function apiGet(url, opts={}){
    const r = await fetch(url, opts);
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
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
    const b = map.getBounds();
    const [[lat1, lon1],[lat2, lon2]] = b;
    return [lon1,lat1,lon2,lat2].join(",");
}

/* Управление слоем домов */
function toggleHousesLayer(forceState = null) {
    const btn = $("btnShowHousesMap");

    let wantToShow;
    // Если forceState === null (клик по кнопке), берем ТЕКУЩЕЕ состояние housesHidden.
    // Если скрыто (true) -> хотим показать (true).
    if (forceState === null) {
        wantToShow = housesHidden;
    } else {
        wantToShow = !!forceState;
    }

    if (wantToShow) { // ПОКАЗАТЬ
        housesHidden = false;
        housesCluster.options.set('visible', true);
        if(btn) { btn.classList.add("active"); btn.textContent = "🏠 Скрыть дома"; }
        loadHousesInView();
    } else { // СКРЫТЬ
        housesHidden = true;
        housesCluster.removeAll();
        housesCluster.options.set('visible', false);
        if(btn) { btn.classList.remove("active"); btn.textContent = "🏠 Показать дома"; }
    }
}

/* Главная функция выбора дома */
function setSelectedHouseUI(h){
    // Сброс состояния при смене дома
    parkingLayer.removeAll();
    $("nearList").innerHTML = `<div id="parkPlaceholder" class="text-center text-muted small mt-4">Здесь появится список платных парковок,<br>когда вы нажмете кнопку "Парковки рядом".</div>`;
    $("nearResult").innerHTML = "—";

    const btnHouses = $("btnShowHousesMap");
    if(btnHouses) btnHouses.style.display = "none";

    selectedUnom = h.unom;
    $("badgeUnom").textContent = `ID ${h.unom}`;
    $("houseAddress").textContent = h.address_simple || h.address_full || "—";

    const capBlock = $("capacityBlock");
    const capInput = $("houseCapacityInput");
    const avgLabel = $("avgCapacityVal");
    const callToAction = $("callToActionMyHome");

    // Скрываем все блоки по умолчанию
    if (capBlock) capBlock.classList.add("d-none");
    if (callToAction) callToAction.classList.add("d-none");

    // ЛОГИКА ОТОБРАЖЕНИЯ БЛОКОВ
    if (window.__IS_LOGGED_IN__) {
        if (currentUserHomeUnom && String(currentUserHomeUnom) === String(h.unom)) {
            // Это МОЙ дом -> Показываем ввод вместимости
            if (capBlock) capBlock.classList.remove("d-none");
            if (capInput) capInput.value = (h.my_capacity_vote !== null) ? h.my_capacity_vote : "";
        } else if (!currentUserHomeUnom) {
            // У меня НЕТ дома в профиле -> Предлагаем этот (если смотрю чужой)
            if (callToAction) callToAction.classList.remove("d-none");
        }
        // Если у меня уже есть дом (currentUserHomeUnom != null), то ничего не предлагаем на чужих домах.
    }

    const avgText = (h.courtyard_capacity !== null) ? `${h.courtyard_capacity}` : "—";
    if (avgLabel) avgLabel.textContent = avgText;

    selectedHouseLayer.removeAll();
    if (h.lat != null && h.lon != null){
        const pm = new ymaps.Placemark([h.lat, h.lon], {
            hintContent: "Выбран",
            balloonContent: h.address_simple
        }, { preset:"islands#redHomeIcon", zIndex: 10000 });
        selectedHouseLayer.add(pm);
    }

    // При выборе дома показываем слой домов
    toggleHousesLayer(true);

    refreshReports().catch(()=>{});
    sheetOpen(true);
}

async function loadHousesInView(){
    if (housesHidden) { housesCluster.removeAll(); return; }
    if (map.getZoom() < 14){ housesCluster.removeAll(); return; }

    const url = `../api/houses.php?bbox=${encodeURIComponent(bboxLonLat())}&limit=900`;
    try{
        const data = await apiGet(url);
        housesCluster.removeAll();
        const points = (data.items||[]).map(it => {
            const isSelected = (selectedUnom && String(selectedUnom) === String(it.unom));
            const pm = new ymaps.Placemark([it.lat, it.lon], {
                hintContent: it.address,
                balloonContentHeader: `ID ${it.unom}`,
                unom: it.unom
            }, {
                preset:"islands#blueCircleDotIcon",
                visible: !isSelected
            });
            pm.properties.set('unom', it.unom);
            pm.events.add("click", async (e) => {
                e.preventDefault();
                const h = await apiGet(`../api/house.php?unom=${it.unom}`);
                setSelectedHouseUI(h);
            });
            return pm;
        });
        housesCluster.add(points);
    }catch(e){}
}

async function showPaidParkingsNear(){
    if (!selectedUnom){ toast("Сначала выбери дом"); return; }
    const r = $("radius").value;

    const btnHouses = $("btnShowHousesMap");
    if(btnHouses) btnHouses.style.display = "block";

    toggleHousesLayer(false);

    try{
        const data = await apiGet(`../api/parkings_near.php?unom=${selectedUnom}&r=${r}`);
        $("nearResult").innerHTML = `<span class="text-muted">Найдено:</span> <b>${data.x2_paid_cnt}</b>`;

        parkingLayer.removeAll();
        const list = $("nearList");
        list.innerHTML = "";

        document.querySelector('[data-tab="tab-park"]').click();
        sheetOpen(true);

        if (!data.items || data.items.length === 0) {
            list.innerHTML = `<div class="p-3 text-center text-muted">Парковок рядом нет :(</div>`;
            return;
        }

        (data.items||[]).forEach((p, idx) => {
            const pm = new ymaps.Placemark([p.lat, p.lon], {
                balloonContentHeader: p.name,
                balloonContentBody: `Мест: ${p.capacity || "?"}`
            }, { preset: "islands#orangeIcon", zIndex: 9999 });
            parkingLayer.add(pm);
            const div = document.createElement("div");
            div.className = "item";
            div.innerHTML = `
                <div class="t">${idx+1}. ${p.name || "Парковка"}</div>
                <div class="s">${p.address || ""}</div>
                <div class="s">~${p.dist_m} м • мест: ${p.capacity ?? "—"}</div>
            `;
            div.onclick = () => map.setCenter([p.lat, p.lon], Math.max(map.getZoom(), 16), {duration:300});
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

async function liveSearchProfile(){
    const q = $("profileSearchInput").value.trim();
    const box = $("profileSuggest");
    if(q.length<3){ box.classList.add("d-none"); return; }
    try{
        const data = await apiGet(`../api/search.php?q=${encodeURIComponent(q)}`);
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
}
const doLiveSearchProfile = debounce(liveSearchProfile, 500);

async function liveSearch(){
    const q = $("searchInput").value.trim();
    const box = $("searchSuggest");
    if(q.length<3){ box.classList.add("d-none"); return; }
    if(searchAbort)searchAbort.abort(); searchAbort=new AbortController();
    try{
        const data=await apiGet(`../api/search.php?q=${encodeURIComponent(q)}`,{signal:searchAbort.signal});
        const items = data.items || [];
        box.innerHTML = "";
        if (!items.length){ box.classList.add("d-none"); return; }
        items.forEach(it => {
            box.appendChild(createSuggestItem(it, async () => {
                box.classList.add("d-none");
                const h = await apiGet(`../api/house.php?unom=${it.unom}`);
                if(it.lat) map.setCenter([it.lat,it.lon], 17);
                setSelectedHouseUI(h);
            }));
        });
        box.classList.remove("d-none");
    }catch(e){}
}
const doLiveSearch = debounce(liveSearch, 500);

function statusLabel(s){ if (s === "free") return "<span class='text-success'>Свободно</span>"; if (s === "medium") return "<span class='text-warning'>Средне</span>"; return "<span class='text-danger'>Мест нет</span>"; }
function slotLabel(s){ const m = {morning:"Утро", day:"День", evening:"Вечер", night:"Ночь"}; return m[s] || s; }
async function refreshReports(){
    const box = $("reportsBox"); if (!box) return; if (!selectedUnom){ box.innerHTML = ``; return; }
    const data = await apiGet(`../api/reports_house.php?unom=${selectedUnom}&days=14`);
    const items = data.items || [];
    if (!items.length){ box.innerHTML = `<div class="small text-muted p-2">Пока нет отметок.</div>`; return; }
    let html = items.map(it => `<div class="item"><div class="t">${it.report_date} • ${slotLabel(it.time_slot)}</div><div class="s">Статус: ${statusLabel(it.status)}</div>${it.comment ? `<div class="s text-dark mt-1">"${it.comment}"</div>` : ""}</div>`).join("");
    box.innerHTML = html;
}
async function submitReport(){
    if (!selectedUnom){ toast("Сначала выбери дом"); return; }
    const fd = new FormData(); fd.append("unom", String(selectedUnom)); fd.append("status", $("repStatus").value); fd.append("time_slot", $("repSlot").value); fd.append("comment", $("repComment").value);
    try{ const res = await apiPost(`../api/report_add.php`, fd); if (res && res.error){ toast("Ошибка"); return; } $("repComment").value = ""; toast("Отметка сохранена!"); await refreshReports(); document.querySelector('[data-tab="tab-history"]').click(); }catch(e){ toast("Ошибка отправки"); }
}
function setAuthUI(loggedIn){ window.__IS_LOGGED_IN__ = !!loggedIn; location.reload(); }
async function ajaxLogin(l, p){ const fd = new FormData(); fd.append("login", l); fd.append("password", p); return apiPost("../api/auth_login.php", fd); }
async function ajaxRegister(l, p){ const fd = new FormData(); fd.append("login", l); fd.append("password", p); return apiPost("../api/auth_register.php", fd); }
async function ajaxLogout(){ return apiGet("../api/auth_logout.php"); }
async function profileGet(){ return apiGet("../api/profile_get.php"); }
async function profileSetHome(u){ const fd = new FormData(); if (u===null) fd.append("home_unom",""); else fd.append("home_unom", String(u)); return apiPost("../api/profile_set_home.php", fd); }
async function profileChangePassword(p){ return fetch('../api/profile_update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ new_password: p }) }).then(r => r.json()); }

// --- RENDER PROFILE STATE ---
function renderProfileState(homeUnom) {
    const blockAdd = $("profileAddHomeBlock");
    const blockExist = $("profileExistingHomeBlock");

    if (homeUnom) {
        // Дом есть
        if(blockAdd) blockAdd.classList.add("d-none");
        if(blockExist) {
            blockExist.classList.remove("d-none");
            $("profileExistingAddressText").textContent = `ID ${homeUnom} (загрузка...)`;
            apiGet(`../api/house.php?unom=${homeUnom}`)
                .then(h => {
                    $("profileExistingAddressText").textContent = h.address_simple || h.address_full || `Дом ID ${homeUnom}`;
                })
                .catch(() => {
                    $("profileExistingAddressText").textContent = `Дом ID ${homeUnom}`;
                });
        }
    } else {
        // Дома нет
        if(blockExist) blockExist.classList.add("d-none");
        if(blockAdd) {
            blockAdd.classList.remove("d-none");
            $("profileSearchInput").value = "";
            $("profileHomeUnom").value = "";
        }
    }
}

document.addEventListener("DOMContentLoaded", () => {
    modalLogin = new bootstrap.Modal($("modalLogin"));
    modalRegister = new bootstrap.Modal($("modalRegister"));
    modalProfile = new bootstrap.Modal($("modalProfile"));

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
            const h = await apiGet(`../api/house.php?unom=${p.home_unom}`);
            if (h.lat != null && h.lon != null) map.setCenter([h.lat,h.lon], 17, {duration:300});
            setSelectedHouseUI(h);
            toast("Мой дом");
        }catch(e){ toast("Ошибка"); }
    };

    const formLogin = $("formLogin");
    if(formLogin) formLogin.addEventListener("submit", async (ev) => {
        ev.preventDefault(); $("loginErr").classList.add("d-none");
        const fd = new FormData(ev.target);
        try{
            const res = await ajaxLogin(String(fd.get("login")), String(fd.get("password")));
            if (!res.ok){ $("loginErr").textContent = res.error || "Ошибка"; $("loginErr").classList.remove("d-none"); return; }
            modalLogin.hide(); setAuthUI(true);
        }catch(e){ $("loginErr").classList.remove("d-none"); }
    });

    const formRegister = $("formRegister");
    if(formRegister) formRegister.addEventListener("submit", async (ev) => {
        ev.preventDefault(); $("regErr").classList.add("d-none");
        const fd = new FormData(ev.target);
        try{
            const res = await ajaxRegister(String(fd.get("login")), String(fd.get("password")));
            if (!res.ok){ $("regErr").textContent = res.error || "Ошибка"; $("regErr").classList.remove("d-none"); return; }
            modalRegister.hide(); await ajaxLogin(String(fd.get("login")), String(fd.get("password"))); setAuthUI(true);
        }catch(e){ $("regErr").classList.remove("d-none"); }
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
                const h = await apiGet(`../api/house.php?unom=${selectedUnom}`);
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
                const h = await apiGet(`../api/house.php?unom=${selectedUnom}`);
                setSelectedHouseUI(h);
            }
        }catch(e){ toast("Ошибка"); }
    };

    const btnChangePass = $("btnChangePass");
    if (btnChangePass) btnChangePass.onclick = async () => { const newPass = prompt("Новый пароль:"); if (!newPass) return; try { const res = await profileChangePassword(newPass); if(res.ok) { toast("ОК"); modalProfile.hide(); } else toast("Ошибка"); } catch(e){} };
    const btnCap = $("btnSaveCapacity");
    if (btnCap) btnCap.onclick = async () => { if (!selectedUnom) return; const val = $("houseCapacityInput").value.trim(); if (val === "") return; try { const res = await apiPost('../api/house_set_capacity.php', JSON.stringify({ unom: selectedUnom, capacity: parseInt(val) })); if (res.ok) { toast("Принято"); const h = await apiGet(`../api/house.php?unom=${selectedUnom}`); setSelectedHouseUI(h); } else toast("Ошибка"); } catch (e){} };

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
document.addEventListener("click", (e) => { const t = e.target.closest(".tab"); if (!t) return; document.querySelectorAll(".tab").forEach(x => x.classList.remove("active")); document.querySelectorAll(".tab-pane").forEach(p => p.classList.remove("active")); t.classList.add("active"); const pane = document.getElementById(t.dataset.tab); if (pane) pane.classList.add("active"); });
