<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$unom = isset($_GET['unom']) ? (int)$_GET['unom'] : 0;
$r = isset($_GET['r']) ? (int)$_GET['r'] : 700;

if ($unom <= 0) {
    echo json_encode(['error' => 'unom required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ограничим радиус разумными пределами
if ($r < 50) $r = 50;
if ($r > 3000) $r = 3000;

$db = db();

// 1. Координаты дома
$stmt = $db->prepare("SELECT lon, lat, address_simple FROM houses WHERE unom = ? LIMIT 1");
$stmt->bind_param('i', $unom);
$stmt->execute();
$house = $stmt->get_result()->fetch_assoc();

if (!$house || $house['lon'] === null || $house['lat'] === null) {
    echo json_encode(['error' => 'Дом не найден или нет координат'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hlon = (float)$house['lon'];
$hlat = (float)$house['lat'];

// 2. Bounding Box для грубой фильтрации (оптимизация)
// 1 градус широты ~ 111 км.
$degLat = $r / 111000.0;
// Поправка на широту для долготы
$degLon = $r / (111000.0 * cos(deg2rad($hlat)));

$minLat = $hlat - $degLat;
$maxLat = $hlat + $degLat;
$minLon = $hlon - $degLon;
$maxLon = $hlon + $degLon;

// 3. Выборка кандидатов
// Мы не считаем точное расстояние в WHERE/HAVING, чтобы не мучить базу сложной математикой
// Сделаем это на PHP - это проще и часто быстрее для небольших выборок
$sql = "SELECT id, name, address, capacity, capacity_disabled, lon, lat, tariffs 
        FROM paid_parkings 
        WHERE lat BETWEEN ? AND ? 
          AND lon BETWEEN ? AND ? 
        LIMIT 300";

$stmt = $db->prepare($sql);
$stmt->bind_param('dddd', $minLat, $maxLat, $minLon, $maxLon);
$stmt->execute();
$res = $stmt->get_result();

$items = [];

while ($row = $res->fetch_assoc()) {
    $plat = (float)$row['lat'];
    $plon = (float)$row['lon'];

    // Формула Haversine (расстояние на сфере)
    $earthRadius = 6371000;
    $dLat = deg2rad($plat - $hlat);
    $dLon = deg2rad($plon - $hlon);

    $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($hlat)) * cos(deg2rad($plat)) *
        sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $dist = round($earthRadius * $c);

    if ($dist <= $r) {
        $items[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'], // в БД у тебя поле 'name', проверь это! В прошлом дампе было 'name', в твоем коде было 'parking_name'
            'address' => $row['address'],
            'capacity' => $row['capacity'],
            'dist_m' => $dist,
            'lat' => $plat,
            'lon' => $plon
        ];
    }
}

// Сортируем по дальности
usort($items, function($a, $b) {
    return $a['dist_m'] <=> $b['dist_m'];
});

echo json_encode([
    'unom' => $unom,
    'r' => $r,
    'x2_paid_cnt' => count($items),
    'items' => $items
], JSON_UNESCAPED_UNICODE);
