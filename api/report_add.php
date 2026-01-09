<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$unom = (int)($_POST['unom'] ?? 0);
$status = $_POST['status'] ?? '';
$time_slot = $_POST['time_slot'] ?? '';
$comment = trim($_POST['comment'] ?? '');

if ($unom <= 0) {
    echo json_encode(['error' => 'Ошибка: не выбран дом']);
    exit;
}

// Проверка пользователя (авторизован или нет)
// Если не авторизован — user_id будет NULL (или 0, если БД не позволяет NULL)
// Важно: в базе поле reports.user_id должно разрешать NULL, либо мы ставим 0
$user_id = is_logged_in() ? (int)$_SESSION['user_id'] : null;

// Если юзер не залогинен, коммент помечаем как "Аноним"
if (!$user_id && $comment === '') {
    $comment = 'Анонимная отметка';
}

$today = date('Y-m-d');

$db = db();

// 1. Пишем в агрегированную таблицу (для отображения на сайте)
$stmt = $db->prepare("INSERT INTO house_reports(unom, report_date, time_slot, status, comment) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('issss', $unom, $today, $time_slot, $status, $comment);
$stmt->execute();

// 2. Пишем в лог сырых действий (reports), если структура позволяет
// Если у тебя в reports поле user_id NOT NULL, то этот блок может упасть для анонима.
// Тогда лучше вообще пропустить запись в reports для анонимов.
if ($user_id) {
    $status_code = 0;
    if ($status === 'medium') $status_code = 1;
    if ($status === 'full') $status_code = 2;

    $stmt2 = $db->prepare("INSERT INTO reports(user_id, unom, status_event, comment) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param('iiis', $user_id, $unom, $status_code, $comment);
    $stmt2->execute();
}

echo json_encode(['ok' => true]);
