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

$user_id = is_logged_in() ? (int)$_SESSION['user_id'] : null;

if (!$user_id && $comment === '') {
    $comment = 'Анонимная отметка';
}

$today = date('Y-m-d');

$db = db();

$stmt = $db->prepare("INSERT INTO house_reports(unom, report_date, time_slot, status, comment) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('issss', $unom, $today, $time_slot, $status, $comment);
$stmt->execute();

if ($user_id) {
    $status_code = 0;
    if ($status === 'medium') $status_code = 1;
    if ($status === 'full') $status_code = 2;

    $stmt2 = $db->prepare("INSERT INTO reports(user_id, unom, status_event, comment) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param('iiis', $user_id, $unom, $status_code, $comment);
    $stmt2->execute();
}

echo json_encode(['ok' => true]);
