<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login_json();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['user_id'];
$home = trim((string)($_POST['home_unom'] ?? ''));

if ($home === '') {
    $stmt = db()->prepare("UPDATE users SET home_unom=NULL WHERE id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^\d{1,20}$/', $home)) {
    echo json_encode(['ok'=>false, 'error'=>'UNOM должен быть числом'], JSON_UNESCAPED_UNICODE);
    exit;
}

$unom = (int)$home;

$stmt0 = db()->prepare("SELECT unom FROM houses WHERE unom=? LIMIT 1");
$stmt0->bind_param('i', $unom);
$stmt0->execute();
$exists = $stmt0->get_result()->fetch_assoc();

if (!$exists) {
    echo json_encode(['ok'=>false, 'error'=>'Такого UNOM нет в базе домов'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare("UPDATE users SET home_unom=? WHERE id=?");
$stmt->bind_param('ii', $unom, $userId);
$stmt->execute();

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
