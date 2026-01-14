<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 64);

if ($q === '' || mb_strlen($q) < 3) {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = 10;
$like = '%' . $q . '%';

$sql = "
  SELECT unom, address_simple, lon, lat
  FROM houses
  WHERE address_simple LIKE ?
  ORDER BY address_simple
  LIMIT ?
";

$stmt = db()->prepare($sql);
$stmt->bind_param('si', $like, $limit);
$stmt->execute();
$res = $stmt->get_result();

function clean_addr(string $s): string {
    $s = trim($s);
    // убираем ведущие запятые/пробелы/тире/точки
    $s = preg_replace('/^[\s,.\-–—]+/u', '', $s);
    return $s ?? '';
}

$items = [];
while ($row = $res->fetch_assoc()) {
    $addr = (string)($row['address_simple'] ?? '');
    $addr = clean_addr($addr);

    $items[] = [
        'unom' => (int)$row['unom'],
        'address' => $addr,
        'lon' => $row['lon'] !== null ? (float)$row['lon'] : null,
        'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
