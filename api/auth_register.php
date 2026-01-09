<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

$login = trim($_POST['login'] ?? '');
$pass = $_POST['password'] ?? '';

if (mb_strlen($login) < 3) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Логин слишком короткий (мин 3)']);
    exit;
}
if (mb_strlen($pass) < 4) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Пароль слишком короткий (мин 4)']);
    exit;
}

// Проверка на занятость логина
$stmt = db()->prepare("SELECT id FROM users WHERE login=? LIMIT 1");
$stmt->bind_param('s', $login);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Логин занят']);
    exit;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = db()->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
$stmt->bind_param('ss', $login, $hash);

if ($stmt->execute()) {
    // Сразу логиним
    $_SESSION['user_id'] = $stmt->insert_id;
    $_SESSION['login'] = $login;
    echo json_encode(['ok'=>true]);
} else {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Ошибка базы данных']);
}
