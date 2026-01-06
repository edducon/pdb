<?php
// api/houses.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$bbox = $_GET['bbox'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1500;
if ($limit < 1) $limit = 1;
if ($limit > 5000) $limit = 5000;

$parts = array_map('trim', explode(',', $bbox));
if (count($parts) !== 4) {
    http_response_code(400);
    echo json_encode(['error' => 'bbox required: lon1,lat1,lon2,lat2'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lon1 = (float)$parts[0];
$lat1 = (float)$parts[1];
$lon2 = (float)$parts[2];
$lat2 = (float)$parts[3];

$minLon = min($lon1, $lon2);
$maxLon = max($lon1, $lon2);
$minLat = min($lat1, $lat2);
$maxLat = max($lat1, $lat2);

$sql = "
  SELECT unom, address_simple, lon, lat
  FROM houses
  WHERE lon IS NOT NULL AND lat IS NOT NULL
    AND obj_type = 'Здание'
    AND lon BETWEEN ? AND ?
    AND lat BETWEEN ? AND ?
  LIMIT ?
";

$stmt = db()->prepare($sql);
$stmt->bind_param('ddddi', $minLon, $maxLon, $minLat, $maxLat, $limit);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'unom' => (int)$row['unom'],
        'address' => $row['address_simple'],
        'lon' => (float)$row['lon'],
        'lat' => (float)$row['lat'],
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);