<?php
// api/reports_house.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$unom = isset($_GET['unom']) ? (int)$_GET['unom'] : 0;
$days = isset($_GET['days']) ? (int)$_GET['days'] : 14;

if ($unom <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'unom required'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($days < 1) $days = 1;
if ($days > 60) $days = 60;

$db = db();

$stmt = $db->prepare("
  SELECT id, report_date, time_slot, status, comment, created_at
  FROM house_reports
  WHERE unom = ?
    AND report_date >= (CURDATE() - INTERVAL ? DAY)
  ORDER BY report_date DESC, created_at DESC
  LIMIT 200
");
$stmt->bind_param('ii', $unom, $days);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$cnt = ['free'=>0,'medium'=>0,'full'=>0];

while ($row = $res->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'report_date' => $row['report_date'],
        'time_slot' => $row['time_slot'],
        'status' => $row['status'],
        'comment' => $row['comment'],
        'created_at' => $row['created_at'],
    ];
    if (isset($cnt[$row['status']])) $cnt[$row['status']]++;
}

echo json_encode([
    'unom' => $unom,
    'days' => $days,
    'counts' => $cnt,
    'items' => $items
], JSON_UNESCAPED_UNICODE);
