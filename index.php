<?php
require_once __DIR__ . '/config/auth.php';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Парковки у дома — городской сервис</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/styles.css">
</head>

<body class="app">
<header class="topbar">
    <div class="container-fluid px-3 px-lg-4">
        <div class="row align-items-center g-2">
            <div class="col-12 col-lg-auto">
                <a class="brand d-flex align-items-center gap-2 text-decoration-none" href="index.php">
                    <img src="assets/logo.svg" width="64" height="64" alt="">
                    <div class="lh-sm">
                        <div class="brand-title">Парковки у дома</div>
                        <div class="brand-subtitle">Сервис доступности парковочных мест</div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-lg">
                <div class="searchbox">
                    <div class="ic" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M10 18a8 8 0 1 1 5.3-14A8 8 0 0 1 10 18Zm11 3-6-6 1.4-1.4 6 6L21 21Z"/></svg>
                    </div>
                    <input id="searchInput" class="search-input" placeholder="Введите адрес дома…">
                    <button id="searchBtn" class="btn btn-accent btn-sm px-3 ms-2">Найти</button>
                </div>
                <div id="searchSuggest" class="suggest d-none"></div>
            </div>

            <div class="col-12 col-lg-auto">
                <div id="authArea" class="d-flex gap-2 justify-content-lg-end">
                    <?php if (is_logged_in()): ?>
                        <button class="btn btn-ui" id="btnProfile">Профиль</button>
                        <button class="btn btn-ui" id="btnMyHome">Мой дом</button>
                        <button class="btn btn-accent" id="btnLogout">Выйти</button>
                    <?php else: ?>
                        <button class="btn btn-ui" id="btnLogin">Войти</button>
                        <button class="btn btn-accent" id="btnRegister">Регистрация</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="map-root">
    <div id="map" class="map-full"></div>

    <div class="map-controls-overlay">
        <button class="btn btn-light shadow-sm fw-bold border" id="btnShowHousesMap" style="min-width: 140px; display: none;">
            🏠 Дома
        </button>
    </div>

    <section id="sheet" class="sheet">
        <div id="sheetGrab" class="sheet-grab" title="Потяни вверх/вниз">
            <div class="grab-line"></div>
        </div>

        <div class="sheet-head">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="sheet-title">Выбранный дом</div>
                    <div id="houseAddress" class="sheet-subtitle text-truncate">Выбери дом на карте или через поиск.</div>
                </div>
                <!-- Заменили UNOM на ID -->
                <span id="badgeUnom" class="badge text-bg-secondary rounded-pill">ID —</span>
            </div>

            <div class="sheet-actions mt-3 d-flex gap-2">
                <button class="btn btn-accent btn-sm flex-grow-1" id="btnNearMap">🅿️ Парковки рядом</button>
            </div>

            <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
                <div class="small text-muted" style="white-space: nowrap;">Радиус: <b><span id="rVal">700</span> м</b></div>
                <input id="radius" type="range" class="form-range flex-grow-1" min="100" max="3000" step="100" value="700">
            </div>

            <div id="nearResult" class="small text-muted mt-1">—</div>

            <div class="tabs mt-3">
                <button class="tab active" data-tab="tab-park">Парковки</button>
                <button class="tab" data-tab="tab-reports">Отметки</button>
                <button class="tab" data-tab="tab-history">История</button>
            </div>
        </div>

        <div class="sheet-body">
            <div id="tab-park" class="tab-pane active">
                <div id="nearList" class="list">
                    <div id="parkPlaceholder" class="text-center text-muted small mt-4">
                        Здесь появится список платных парковок.
                    </div>
                </div>
            </div>

            <div id="tab-reports" class="tab-pane">

                <div id="capacityBlock" class="card-ui mb-3 bg-light border-0 d-none">
                    <div class="meta-label text-primary">Мой дом: Вместимость двора</div>
                    <div class="row g-2 align-items-end mt-1">
                        <div class="col-8">
                            <label class="small text-muted d-block mb-1">Ваша оценка (мест):</label>
                            <input id="houseCapacityInput" type="number" class="form-control form-control-sm" placeholder="?">
                        </div>
                        <div class="col-4">
                            <button id="btnSaveCapacity" class="btn btn-primary btn-sm w-100">Сохранить</button>
                        </div>
                    </div>
                </div>

                <div id="callToActionMyHome" class="card-ui mb-3 bg-white border-warning border-opacity-25 d-none">
                    <div class="small text-muted text-center py-2" style="font-size: 13px;">
                        Это ваш дом? <br>
                        <a href="#" class="fw-bold text-decoration-none" onclick="modalProfile.show(); return false;">Укажите это в профиле</a>, <br>
                        чтобы добавить данные о вместимости двора.
                    </div>
                </div>

                <?php if (!is_logged_in()): ?>
                    <div class="card-ui mb-3 bg-light border-0">
                        <div class="small text-muted text-center py-2" style="font-size: 13px;">
                            Хотите влиять на рейтинг вместимости? <br>
                            <a href="#" class="fw-bold text-decoration-none" onclick="modalLogin.show(); return false;">Войдите</a> и укажите домашний адрес.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card-ui mb-3 border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Средняя количество парковочных мест:</span>
                        <span id="avgCapacityVal" class="badge bg-secondary fs-6">?</span>
                    </div>
                </div>

                <div class="card-ui">
                    <div class="meta-label">Оставить отметку заполненности парковочных мест</div>
                    <div class="small text-muted mb-2">Ваши данные помогают людям ориентироваться.</div>

                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Время</label>
                            <select id="repSlot" class="form-select form-select-sm">
                                <option value="morning">Утро</option>
                                <option value="day">День</option>
                                <option value="evening">Вечер</option>
                                <option value="night">Ночь</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Статус</label>
                            <select id="repStatus" class="form-select form-select-sm">
                                <option value="free">Свободно</option>
                                <option value="medium">Средне</option>
                                <option value="full">Мест нет</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">Комментарий (опционально)</label>
                            <input id="repComment" class="form-control form-control-sm" placeholder="Например: вечером мест почти не было">
                        </div>
                        <div class="col-12 d-grid mt-2">
                            <button id="repSubmit" class="btn btn-accent btn-sm">Отправить</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-history" class="tab-pane">
                <div class="card-ui">
                    <div class="meta-label">История отметок по дому</div>
                    <div id="reportsBox" class="list mt-2"></div>
                </div>
            </div>
        </div>
    </section>

    <div id="toast" class="toast-ui d-none">…</div>
    <div style="position: absolute; top: 10px; right: 10px; z-index: 1015; font-size: 10px; background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        На основе датасетов: <a href="https://data.mos.ru/opendata/60562" target="_blank" style="color:#333; text-decoration: underline;">Реестр домов</a>,
        <a href="https://data.mos.ru/opendata/623" target="_blank" style="color:#333; text-decoration: underline;">Парковки</a>
    </div>
</main>

<!-- LOGIN MODAL -->
<div class="modal fade" id="modalLogin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Вход</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div id="loginErr" class="alert alert-danger d-none"></div>
                <form id="formLogin">
                    <label class="form-label text-muted small">Логин</label>
                    <input name="login" class="form-control" required>
                    <label class="form-label mt-3 text-muted small">Пароль</label>
                    <input name="password" type="password" class="form-control" required>
                    <button class="btn btn-accent w-100 mt-4">Войти</button>
                </form>
                <div class="small text-muted mt-3 text-center">
                    Нет аккаунта? <a href="#" id="linkToRegister" class="text-decoration-none">Зарегистрироваться</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REGISTER MODAL -->
<div class="modal fade" id="modalRegister" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Регистрация</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div id="regErr" class="alert alert-danger d-none"></div>
                <form id="formRegister">
                    <label class="form-label text-muted small">Придумайте Логин</label>
                    <input name="login" class="form-control" required>
                    <label class="form-label mt-3 text-muted small">Придумайте Пароль</label>
                    <input name="password" type="password" class="form-control" required>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="regConsent" required>
                        <label class="form-check-label small text-muted lh-sm" for="regConsent" style="font-size: 11px;">
                            Я согласен на обработку
                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalPrivacy">персональных данных</a>
                        </label>
                    </div>
                    <button class="btn btn-accent w-100 mt-4">Создать аккаунт</button>
                </form>
                <div class="small text-muted mt-3 text-center">
                    Уже есть аккаунт? <a href="#" id="linkToLogin" class="text-decoration-none">Войти</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPrivacy" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content card-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Политика конфиденциальности</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body text-muted" style="font-size: 13px; line-height: 1.6;">
                <p><strong>1. Общие положения</strong><br>
                    Настоящая политика описывает, как сервис «Парковки у дома» собирает, использует и защищает информацию пользователей.</p>

                <p><strong>2. Какие данные мы собираем</strong><br>
                    Для функционирования сервиса мы обрабатываем следующие данные:
                <ul class="ps-3 mb-2">
                    <li><strong>Логин:</strong> используется для идентификации пользователя в системе.</li>
                    <li><strong>Пароль:</strong> хранится в базе данных исключительно в зашифрованном виде (хэш). Администрация не имеет доступа к исходным паролям.</li>
                    <li><strong>IP-адрес:</strong> используется в целях безопасности для предотвращения накруток голосов и защиты от спам-атак.</li>
                    <li><strong>Cookie-файлы:</strong> используются для поддержания сессии авторизации.</li>
                </ul>
                </p>

                <p><strong>3. Цели обработки</strong><br>
                    Мы используем данные исключительно для:
                <ul class="ps-3 mb-2">
                    <li>Предоставления доступа к личному кабинету;</li>
                    <li>Обеспечения честного голосования за доступность парковок;</li>
                    <li>Анализа статистики загруженности парковочных мест.</li>
                </ul>
                </p>

                <p><strong>4. Защита информации</strong><br>
                    Мы принимаем необходимые меры для защиты данных от несанкционированного доступа. Мы не передаем ваши данные третьим лицам, за исключением случаев, предусмотренных законодательством РФ.</p>

                <p class="mb-0"><em>Регистрируясь на сайте, вы подтверждаете свое согласие с данными условиями.</em></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-accent btn-sm w-100" data-bs-target="#modalRegister" data-bs-toggle="modal">Понятно</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProfile" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Профиль</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">

                <div class="card-ui bg-light border-0 mb-3 p-3">
                    <div class="meta-label mb-2">Учетная запись</div>

                    <label class="form-label small text-muted mb-1">Ваш Логин</label>
                    <input id="profileLogin" class="form-control form-control-sm mb-2" value="<?= htmlspecialchars($_SESSION['login'] ?? '') ?>">

                    <label class="form-label small text-muted mb-1">Пароль (оставьте поле пустым, если не хотите менять)</label>
                    <input id="profilePass" type="password" class="form-control form-control-sm mb-2" placeholder="********">

                    <button id="btnSaveProfileCreds" class="btn btn-primary btn-sm w-100 mt-1">Сохранить изменения</button>
                </div>

                <div class="card-ui bg-light border-0 mb-3">
                    <div class="meta-label mb-2">Домашний адрес</div>

                    <div id="profileAddHomeBlock">
                        <div class="position-relative">
                            <input id="profileSearchInput" class="form-control mb-2" placeholder="Начните вводить адрес...">
                            <div id="profileSuggest" class="suggest d-none" style="top: 100%; width: 100%;"></div>
                        </div>
                        <input id="profileHomeUnom" type="hidden">

                        <div class="d-flex gap-2">
                            <button id="btnSaveHome" class="btn btn-accent flex-grow-1">Сохранить адрес</button>
                            <button id="btnUseSelectedHouse" class="btn btn-outline-secondary" title="Взять выбранный на карте">📍</button>
                        </div>
                    </div>

                    <div id="profileExistingHomeBlock" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="fs-5">🏠</div>
                            <div class="fw-bold text-dark" id="profileExistingAddressText">Адрес загружается...</div>
                        </div>
                        <button id="btnClearHome" class="btn btn-outline-danger btn-sm w-100">Удалить домашний адрес</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast-ui d-none">…</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://api-maps.yandex.ru/2.1/?apikey=9e226a60-c4ba-4fd7-a1ba-eb528cb89743&lang=ru_RU" async defer></script>

<script>
    window.__IS_LOGGED_IN__ = <?= is_logged_in() ? 'true' : 'false' ?>;
</script>
<script src="js/app.js"></script>
</body>
</html>
