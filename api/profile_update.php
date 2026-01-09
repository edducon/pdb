<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login_json();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$new_pass = trim($data['new_password'] ?? '');

if (empty($new_pass) || mb_strlen($new_pass) < 4) {
    echo json_encode(['ok' => false, 'error' => 'Пароль должен быть не менее 4 символов'], JSON_UNESCAPED_UNICODE);
    exit;
}

$new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

$stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->bind_param('si', $new_hash, $userId);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'message' => 'Пароль успешно изменен'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['ok' => false, 'error' => 'Ошибка базы данных'], JSON_UNESCAPED_UNICODE);
}
?>
