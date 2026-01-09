<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if (mb_strlen($login) < 3 || mb_strlen($pass) < 6) {
        $error = 'Логин ≥ 3 символов, пароль ≥ 6 символов.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $stmt = db()->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
            $stmt->bind_param('ss', $login, $hash);
            $stmt->execute();
            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Такой логин уже существует.';
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Регистрация</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px;">
    <h3 class="mb-3">Регистрация</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <label class="form-label">Логин</label>
        <input name="login" class="form-control" required>
        <label class="form-label mt-3">Пароль</label>
        <input name="password" type="password" class="form-control" required>
        <button class="btn btn-primary mt-4">Создать аккаунт</button>
        <a class="btn btn-link mt-2" href="login.php">Уже есть аккаунт</a>
    </form>
</div>
</body>
</html>