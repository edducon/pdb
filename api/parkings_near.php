<?php
// api/parkings_near.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$unom = isset($_GET['unom']) ? (int)$_GET['unom'] : 0;
$r = isset($_GET['r']) ? (int)$_GET['r'] : 700;

if ($unom <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'unom required'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($r < 50) $r = 50;
if ($r > 5000) $r = 5000;

function price_hint(?string $tar): string {
    if (!$tar) return '—';
    $t = trim($tar);

    // 1) если встречается "руб" или "₽"
    if (preg_match('/(\d{1,4})\s*(?:руб|₽)/ui', $t, $m)) {
        return 'от ' . $m[1] . ' ₽/час';
    }

    // 2) если просто встречается число, часто в тарифах оно есть
    if (preg_match('/\b(\d{1,4})\b/u', $t, $m)) {
        return 'от ' . $m[1] . ' ₽/час';
    }

    return 'тарифы есть';
}

$db = db();

// 1) координаты дома
$stmt = $db->prepare("SELECT lon, lat, address_simple FROM houses WHERE unom = ? LIMIT 1");
$stmt->bind_param('i', $unom);
$stmt->execute();
$house = $stmt->get_result()->fetch_assoc();

if (!$house || $house['lon'] === null || $house['lat'] === null) {
    http_response_code(404);
    echo json_encode(['error' => 'house not found or no coordinates'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hlon = (float)$house['lon'];
$hlat = (float)$house['lat'];

// 2) грубый bbox-фильтр, чтобы не считать расстояние для всей Москвы
$degLat = $r / 111000.0;
$degLon = $r / (111000.0 * max(0.2, cos(deg2rad($hlat))));

$minLon = $hlon - $degLon;
$maxLon = $hlon + $degLon;
$minLat = $hlat - $degLat;
$maxLat = $hlat + $degLat;

// 3) достаём парковки в bbox, расстояние считаем в SQL (haversine)
$sql = "
  SELECT
    p.id, p.parking_name, p.address, p.capacity, p.capacity_disabled,
    p.lon, p.lat,
    p.tariffs_raw,
    ROUND(
      6371000 * 2 * ASIN(
        SQRT(
          POWER(SIN(RADIANS(p.lat - ?) / 2), 2) +
          COS(RADIANS(?)) * COS(RADIANS(p.lat)) *
          POWER(SIN(RADIANS(p.lon - ?) / 2), 2)
        )
      )
    ) AS dist_m
  FROM paid_parkings p
  WHERE p.lon IS NOT NULL AND p.lat IS NOT NULL
    AND p.lon BETWEEN ? AND ?
    AND p.lat BETWEEN ? AND ?
  HAVING dist_m <= ?
  ORDER BY dist_m ASC
  LIMIT 200
";

$stmt = $db->prepare($sql);
$stmt->bind_param(
    'ddddddddi',
    $hlat, $hlat, $hlon,
    $minLon, $maxLon,
    $minLat, $maxLat,
    $r
);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $tar = $row['tariffs_raw'] ?? null;

    $items[] = [
        'id' => (int)$row['id'],
        'name' => $row['parking_name'],
        'address' => $row['address'],
        'capacity' => $row['capacity'] !== null ? (int)$row['capacity'] : null,
        'capacity_disabled' => $row['capacity_disabled'] !== null ? (int)$row['capacity_disabled'] : null,
        'lon' => (float)$row['lon'],
        'lat' => (float)$row['lat'],
        'dist_m' => (int)$row['dist_m'],
        'tariffs_raw' => $tar,
        'price_hint' => price_hint($tar),
    ];
}

echo json_encode([
    'unom' => $unom,
    'r' => $r,
    'house' => [
        'lon' => $hlon,
        'lat' => $hlat,
        'address' => $house['address_simple'] ?? null
    ],
    'x2_paid_cnt' => count($items),
    'items' => $items
], JSON_UNESCAPED_UNICODE);