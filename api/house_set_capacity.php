<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login_json();

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$unom = (int)($data['unom'] ?? 0);
$capacity = isset($data['capacity']) ? (int)$data['capacity'] : -1;
$userId = (int)$_SESSION['user_id'];

if ($unom <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Не указан дом']);
    exit;
}

if ($capacity < 0 || $capacity > 999) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректное число (0-999)']);
    exit;
}

$db = db();

$stmt = $db->prepare("
    INSERT INTO house_capacity_votes (unom, user_id, capacity) 
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE capacity = VALUES(capacity)
");
$stmt->bind_param('iii', $unom, $userId, $capacity);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'message' => 'Ваш голос учтен']);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка базы данных']);
}
