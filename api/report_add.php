<?php
// api/report_add.php
// Защита от накрутки (Версия v2 - расчет времени внутри БД)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

// 1. Получаем данные
$unom = (int)($_POST['unom'] ?? 0);
$status = $_POST['status'] ?? '';
$time_slot = $_POST['time_slot'] ?? '';
$comment = trim($_POST['comment'] ?? '');

// 2. Кто стучится?
$userId = is_logged_in() ? (int)$_SESSION['user_id'] : null;
$userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 3. Валидация
if ($unom <= 0) { echo json_encode(['ok'=>false, 'error'=>'Ошибка: не выбран дом']); exit; }
if (!in_array($status, ['free', 'medium', 'full'])) { echo json_encode(['ok'=>false, 'error'=>'Некорректный статус']); exit; }
if (!in_array($time_slot, ['morning', 'day', 'evening', 'night'])) { echo json_encode(['ok'=>false, 'error'=>'Некорректное время']); exit; }

$db = db();
$today = date('Y-m-d');

// --- ЗАЩИТА ОТ НАКРУТКИ (ANTI-FRAUD) ---

// Лимиты в секундах
$limitTime = $userId ? 1800 : 3600; // 30 мин (свои) или 60 мин (гости)

// SQL запрос: "Сколько секунд прошло с последнего отзыва?"
// TIMESTAMPDIFF(SECOND, created_at, NOW()) — считает разницу внутри базы, игнорируя настройки PHP
if ($userId) {
    // Проверка по ID
    $stmtCheck = $db->prepare("
        SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as sec_passed 
        FROM house_reports 
        WHERE unom = ? AND user_id = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtCheck->bind_param('ii', $unom, $userId);
} else {
    // Проверка по IP
    $stmtCheck = $db->prepare("
        SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as sec_passed 
        FROM house_reports 
        WHERE unom = ? AND ip_address = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtCheck->bind_param('is', $unom, $userIp);
}

$stmtCheck->execute();
$row = $stmtCheck->get_result()->fetch_assoc();

if ($row) {
    $secondsPassed = (int)$row['sec_passed'];

    // Если прошло меньше времени, чем нужно
    if ($secondsPassed < $limitTime) {
        $minutesLeft = ceil(($limitTime - $secondsPassed) / 60);
        echo json_encode([
            'ok' => false,
            'error' => "Вы уже голосовали. Повторить можно через $minutesLeft мин."
        ]);
        exit;
    }
}

// 4. Сохранение
$stmt = $db->prepare("INSERT INTO house_reports (unom, report_date, time_slot, status, comment, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('issssis', $unom, $today, $time_slot, $status, $comment, $userId, $userIp);

if ($stmt->execute()) echo json_encode(['ok' => true]);
else echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения']);
?>