<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$bbox = $_GET['bbox'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1500;
if ($limit > 5000) $limit = 5000;

$parts = array_map('trim', explode(',', $bbox));
if (count($parts) !== 4) { echo json_encode(['items' => []]); exit; }

$lon1 = (float)$parts[0]; $lat1 = (float)$parts[1];
$lon2 = (float)$parts[2]; $lat2 = (float)$parts[3];
$minLon = min($lon1, $lon2); $maxLon = max($lon1, $lon2);
$minLat = min($lat1, $lat2); $maxLat = max($lat1, $lat2);

$db = db();
$today = date('Y-m-d');

$sql = "
  SELECT h.unom, h.address_simple, h.lon, h.lat, d.cluster_id
  FROM houses h
  LEFT JOIN daily_house_features d ON h.unom = d.unom AND d.date = '$today'
  WHERE h.lon BETWEEN $minLon AND $maxLon
    AND h.lat BETWEEN $minLat AND $maxLat
  LIMIT ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'unom' => (int)$row['unom'],
        'address' => $row['address_simple'] ?? '',
        'lon' => (float)$row['lon'],
        'lat' => (float)$row['lat'],
        'cluster_id' => $row['cluster_id'] ? (int)$row['cluster_id'] : null
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
?>