<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 3) {
    echo json_encode(['items' => []]);
    exit;
}

$db = db();

$queryParam = $q . '%';

$stmt = $db->prepare("
    SELECT unom, address_simple, lat, lon 
    FROM houses 
    WHERE address_simple LIKE ? 
    LIMIT 10
");

$stmt->bind_param('s', $queryParam);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'unom' => (int)$row['unom'],
        'address' => $row['address_simple'],
        'lat' => $row['lat'],
        'lon' => $row['lon']
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
?>