<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login_json();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$new_pass = trim($data['new_password'] ?? '');
$new_login = trim($data['new_login'] ?? '');

$db = db();
$updates = [];
$types = '';
$params = [];

if (!empty($new_login)) {
    if (mb_strlen($new_login) < 3) {
        echo json_encode(['ok' => false, 'error' => 'Логин слишком короткий (мин 3)'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtCk = $db->prepare("SELECT id FROM users WHERE login=? AND id != ? LIMIT 1");
    $stmtCk->bind_param('si', $new_login, $userId);
    $stmtCk->execute();
    if ($stmtCk->get_result()->fetch_assoc()) {
        echo json_encode(['ok' => false, 'error' => 'Логин уже занят'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $updates[] = "login = ?";
    $types .= 's';
    $params[] = $new_login;
}

if (!empty($new_pass)) {
    if (mb_strlen($new_pass) < 4) {
        echo json_encode(['ok' => false, 'error' => 'Пароль слишком короткий (мин 4)'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $updates[] = "password_hash = ?";
    $types .= 's';
    $params[] = $new_hash;
}

if (empty($updates)) {
    echo json_encode(['ok' => false, 'error' => 'Нет данных для обновления'], JSON_UNESCAPED_UNICODE);
    exit;
}

$types .= 'i';
$params[] = $userId;

$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if (!empty($new_login)) {
        $_SESSION['login'] = $new_login;
    }
    echo json_encode(['ok' => true, 'message' => 'Сохранено'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['ok' => false, 'error' => 'Ошибка базы данных'], JSON_UNESCAPED_UNICODE);
}
?>