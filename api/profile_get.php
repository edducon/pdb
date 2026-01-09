<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login_json();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['user_id'];

$stmt = db()->prepare("SELECT id, login, home_unom FROM users WHERE id=? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'ok' => true,
    'id' => (int)$row['id'],
    'login' => $row['login'],
    'home_unom' => $row['home_unom'] !== null ? (int)$row['home_unom'] : null
], JSON_UNESCAPED_UNICODE);
