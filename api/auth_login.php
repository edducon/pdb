<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    if (!file_exists(__DIR__ . '/../config/db.php')) {
        throw new Exception('Файл config/db.php не найден');
    }
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/auth.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Нужен метод POST');
    }

    $login = trim($_POST['login'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!$login || !$pass) {
        throw new Exception('Введите логин и пароль');
    }

    $db = db();

    $stmt = $db->prepare("SELECT id, login, password_hash, home_unom FROM users WHERE login = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Ошибка SQL prepare: ' . $db->error);
    }

    $stmt->bind_param('s', $login);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Неверный логин или пароль']);
        exit;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['login'] = $user['login'];
    $_SESSION['home_unom'] = $user['home_unom'];

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}
?>