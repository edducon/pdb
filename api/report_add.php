<?php
// api/report_add.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only'], JSON_UNESCAPED_UNICODE);
    exit;
}

$unom = isset($_POST['unom']) ? (int)$_POST['unom'] : 0;
$status = (string)($_POST['status'] ?? '');
$time_slot = (string)($_POST['time_slot'] ?? '');
$comment = trim((string)($_POST['comment'] ?? ''));

if ($unom <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'unom required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedStatus = ['free','medium','full'];
$allowedSlot = ['morning','day','evening','night'];

if (!in_array($status, $allowedStatus, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid status'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($time_slot, $allowedSlot, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid time_slot'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($comment === '') $comment = null;
if ($comment !== null && mb_strlen($comment) > 280) {
    $comment = mb_substr($comment, 0, 280);
}

$today = date('Y-m-d');

$db = db();
$stmt = $db->prepare("
  INSERT INTO house_reports(unom, report_date, time_slot, status, comment)
  VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param('issss', $unom, $today, $time_slot, $status, $comment);
$stmt->execute();

echo json_encode(['ok' => true, 'id' => $db->insert_id], JSON_UNESCAPED_UNICODE);
