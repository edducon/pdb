<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE login=? LIMIT 1");
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['login'] = $login;
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный логин или пароль.';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px;">
    <h3 class="mb-3">Вход</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <label class="form-label">Логин</label>
        <input name="login" class="form-control" required>
        <label class="form-label mt-3">Пароль</label>
        <input name="password" type="password" class="form-control" required>
        <button class="btn btn-primary mt-4">Войти</button>
        <a class="btn btn-link mt-2" href="register.php">Создать аккаунт</a>
    </form>
</div>
</body>
</html>