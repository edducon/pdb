<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

// 1. Получаем данные
$unom = (int)($_POST['unom'] ?? 0);
$status = $_POST['status'] ?? '';
$time_slot = $_POST['time_slot'] ?? '';
$comment = trim($_POST['comment'] ?? '');

// 2. Определяем кто стучится (ID или IP)
$userId = is_logged_in() ? (int)$_SESSION['user_id'] : null;
$userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 3. Валидация
if ($unom <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Ошибка: не выбран дом']);
    exit;
}
if (!in_array($status, ['free', 'medium', 'full'])) {
    echo json_encode(['ok' => false, 'error' => 'Некорректный статус']);
    exit;
}
if (!in_array($time_slot, ['morning', 'day', 'evening', 'night'])) {
    echo json_encode(['ok' => false, 'error' => 'Некорректное время']);
    exit;
}

$db = db();
$today = date('Y-m-d');

$limitTime = 3600;
if ($userId) {
    $limitTime = 1800;
}

if ($userId) {
    $stmtCheck = $db->prepare("SELECT created_at FROM house_reports WHERE unom = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmtCheck->bind_param('ii', $unom, $userId);
} else {
    $stmtCheck = $db->prepare("SELECT created_at FROM house_reports WHERE unom = ? AND ip_address = ? ORDER BY created_at DESC LIMIT 1");
    $stmtCheck->bind_param('is', $unom, $userIp);
}

$stmtCheck->execute();
$lastReport = $stmtCheck->get_result()->fetch_assoc();

if ($lastReport) {
    $secondsPassed = time() - strtotime($lastReport['created_at']);

    if ($secondsPassed < $limitTime) {
        $minutesLeft = ceil(($limitTime - $secondsPassed) / 60);
        echo json_encode([
            'ok' => false,
            'error' => "Вы уже голосовали. Повторить можно через $minutesLeft мин."
        ]);
        exit;
    }
}

$stmt = $db->prepare("
    INSERT INTO house_reports (unom, report_date, time_slot, status, comment, user_id, ip_address) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param('issssis', $unom, $today, $time_slot, $status, $comment, $userId, $userIp);

if ($stmt->execute()) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения']);
}
?>