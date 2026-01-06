<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$unom = isset($_GET['unom']) ? (int)$_GET['unom'] : 0;
if ($unom <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'unom required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
  SELECT unom, obj_type, address_full, address_simple, district, adm_area, lon, lat
  FROM houses
  WHERE unom = ?
  LIMIT 1
";
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $unom);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'unom' => (int)$row['unom'],
    'obj_type' => $row['obj_type'],
    'address_full' => $row['address_full'],
    'address_simple' => $row['address_simple'],
    'district' => $row['district'],
    'adm_area' => $row['adm_area'],
    'lon' => $row['lon'] !== null ? (float)$row['lon'] : null,
    'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
], JSON_UNESCAPED_UNICODE);
