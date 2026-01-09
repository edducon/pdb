<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

$login = trim($_POST['login'] ?? '');
$pass = $_POST['password'] ?? '';

if (!$login || !$pass) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Введите логин и пароль']);
    exit;
}

$stmt = db()->prepare("SELECT id, password_hash FROM users WHERE login=? LIMIT 1");
$stmt->bind_param('s', $login);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if ($row && password_verify($pass, $row['password_hash'])) {
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['login'] = $login;
    echo json_encode(['ok'=>true]);
} else {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'Неверный логин или пароль']);
}
