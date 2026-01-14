<?php
// api/reports_house.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$unom = isset($_GET['unom']) ? (int)$_GET['unom'] : 0;
if ($unom <= 0) { echo json_encode(['items'=>[], 'chart'=>[]]); exit; }

$db = db();

// 1. Список последних отчетов (Текст)
$stmt = $db->prepare("
  SELECT report_date, time_slot, status, comment
  FROM house_reports
  WHERE unom = ?
  ORDER BY report_date DESC, created_at DESC
  LIMIT 50
");
$stmt->bind_param('i', $unom);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) $items[] = $row;

// 2. Данные для ГИСТОГРАММЫ (последние 7 дней)
// Считаем % загруженности: (Full * 100 + Medium * 50) / Total
$stmtChart = $db->prepare("
    SELECT 
        report_date,
        COUNT(*) as total,
        SUM(CASE WHEN status='full' THEN 1 ELSE 0 END) as cnt_full,
        SUM(CASE WHEN status='medium' THEN 1 ELSE 0 END) as cnt_med,
        SUM(CASE WHEN status='free' THEN 1 ELSE 0 END) as cnt_free
    FROM house_reports
    WHERE unom = ? AND report_date >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY report_date
    ORDER BY report_date ASC
");
$stmtChart->bind_param('i', $unom);
$stmtChart->execute();
$resChart = $stmtChart->get_result();
$chart = [];
while ($row = $resChart->fetch_assoc()) {
    // Вычисляем 'индекс загруженности' от 0 до 100
    // Если все 'full' -> 100. Если все 'free' -> 0.
    $score = ($row['cnt_full']*100 + $row['cnt_med']*50) / $row['total'];
    $chart[] = [
        'date' => date('d.m', strtotime($row['report_date'])),
        'score' => round($score),
        'total' => $row['total']
    ];
}

echo json_encode(['items' => $items, 'chart' => $chart], JSON_UNESCAPED_UNICODE);
?>