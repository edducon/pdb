<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php'; // Для сессии

$unom = isset($_GET['unom']) ? (int)$_GET['unom'] : 0;
if ($unom <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'unom required']);
    exit;
}

$db = db();

$stmt = $db->prepare("SELECT unom, address_full, address_simple, district, adm_area, lon, lat FROM houses WHERE unom = ? LIMIT 1");
$stmt->bind_param('i', $unom);
$stmt->execute();
$house = $stmt->get_result()->fetch_assoc();

if (!$house) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$stmtCap = $db->prepare("SELECT ROUND(AVG(capacity)) as avg_cap, COUNT(*) as votes_cnt FROM house_capacity_votes WHERE unom = ?");
$stmtCap->bind_param('i', $unom);
$stmtCap->execute();
$capRow = $stmtCap->get_result()->fetch_assoc();

$house['courtyard_capacity'] = $capRow['avg_cap'] !== null ? (int)$capRow['avg_cap'] : null;
$house['capacity_votes'] = (int)$capRow['votes_cnt'];

// 3. Если пользователь авторизован, узнаем, голосовал ли он сам
$user_val = null;
if (is_logged_in()) {
    $uid = $_SESSION['user_id'];
    $stmtMy = $db->prepare("SELECT capacity FROM house_capacity_votes WHERE unom = ? AND user_id = ? LIMIT 1");
    $stmtMy->bind_param('ii', $unom, $uid);
    $stmtMy->execute();
    $myRow = $stmtMy->get_result()->fetch_assoc();
    if ($myRow) $user_val = (int)$myRow['capacity'];
}
$house['my_capacity_vote'] = $user_val;


// Приведение типов координат
$house['unom'] = (int)$house['unom'];
$house['lon'] = $house['lon'] ? (float)$house['lon'] : null;
$house['lat'] = $house['lat'] ? (float)$house['lat'] : null;

echo json_encode($house, JSON_UNESCAPED_UNICODE);
