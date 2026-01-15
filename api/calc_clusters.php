<?php
// api/calc_clusters.php
// Скрипт для расчета K-means кластеризации на текущую дату
// Учитывает 4 фактора: Загруженность, Окружение, Активность, Вместимость

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = db();
$today = date('Y-m-d');

// =========================================================
// 1. ОЧИСТКА СТАРЫХ ДАННЫХ (чтобы можно было пересчитывать)
// =========================================================
$stmtDel = $db->prepare("DELETE FROM daily_house_features WHERE date = ?");
$stmtDel->bind_param('s', $today);
$stmtDel->execute();

// =========================================================
// 2. СБОР ДАННЫХ (Feature Extraction)
// =========================================================
// X1: Средний статус по отчетам (0=free, 1=medium, 2=full)
// X2: Количество платных парковок в радиусе ~700-1000м
// X3: Активность (количество жалоб/отчетов)
// X4: Вместимость двора (среднее по голосованию жильцов)

$sql = "
    SELECT 
        h.unom, h.lat, h.lon,
        -- X1: Средний статус
        AVG(CASE 
            WHEN hr.status = 'free' THEN 0 
            WHEN hr.status = 'medium' THEN 1 
            WHEN hr.status = 'full' THEN 2 
            ELSE 1 END
        ) as x1_score,
        -- X3: Количество отчетов
        COUNT(hr.id) as x3_activity,
        -- X4: Вместимость (берем из таблицы голосования)
        (SELECT AVG(capacity) FROM house_capacity_votes v WHERE v.unom = h.unom) as x4_capacity
    FROM houses h
    JOIN house_reports hr ON h.unom = hr.unom
    WHERE hr.report_date = ?
    GROUP BY h.unom
";

$stmt = $db->prepare($sql);
$stmt->bind_param('s', $today);
$stmt->execute();
$res = $stmt->get_result();

$dataset = [];
$limits = ['max_x2' => 0, 'max_x3' => 0, 'max_x4' => 0];

while ($row = $res->fetch_assoc()) {
    $unom = (int)$row['unom'];
    $lat = (float)$row['lat'];
    $lon = (float)$row['lon'];

    $qPark = "SELECT COUNT(*) as cnt FROM paid_parkings 
              WHERE lat BETWEEN ".($lat-0.01)." AND ".($lat+0.01)."
                AND lon BETWEEN ".($lon-0.015)." AND ".($lon+0.015);

    $resP = $db->query($qPark)->fetch_assoc();
    $x2 = (int)$resP['cnt'];

    $x3 = (int)$row['x3_activity'];
    $x4 = $row['x4_capacity'] ? (int)$row['x4_capacity'] : 20;

    if ($x2 > $limits['max_x2']) $limits['max_x2'] = $x2;
    if ($x3 > $limits['max_x3']) $limits['max_x3'] = $x3;
    if ($x4 > $limits['max_x4']) $limits['max_x4'] = $x4;

    $dataset[$unom] = [
        'x1' => (float)$row['x1_score'],
        'x2' => $x2,
        'x3' => $x3,
        'x4' => $x4
    ];
}

if (empty($dataset)) {
    echo json_encode(['ok' => false, 'msg' => 'Нет данных (отчетов) за сегодня. Добавьте отчеты через сайт.']);
    exit;
}

$K = 3;
$points = [];

$divX2 = $limits['max_x2'] ?: 1;
$divX3 = $limits['max_x3'] ?: 1;
$divX4 = $limits['max_x4'] ?: 1;

foreach ($dataset as $u => $d) {
    $points[$u] = [
        $d['x1'] / 2.0,
        $d['x2'] / $divX2,
        $d['x3'] / $divX3,
        1 - ($d['x4'] / $divX4)
    ];
}

$centroids = array_slice($points, 0, $K);
$clusters = [];

for ($iter = 0; $iter < 10; $iter++) {
    $clusters = array_fill(0, $K, []);

    foreach ($points as $u => $p) {
        $bestK = 0;
        $minDist = 9999;

        foreach ($centroids as $k => $c) {
            // Евклидово расстояние в 4D пространстве
            $dist = 0;
            for($j=0; $j<4; $j++) $dist += pow($p[$j] - $c[$j], 2);
            $dist = sqrt($dist);

            if ($dist < $minDist) {
                $minDist = $dist;
                $bestK = $k;
            }
        }
        $clusters[$bestK][] = $u;
    }

    foreach ($centroids as $k => &$c) {
        if (empty($clusters[$k])) continue;

        $sum = [0, 0, 0, 0];
        $cnt = count($clusters[$k]);

        foreach ($clusters[$k] as $u) {
            for($j=0; $j<4; $j++) $sum[$j] += $points[$u][$j];
        }

        $c = array_map(fn($val) => $val / $cnt, $sum);
    }
}

uasort($centroids, function($a, $b) {
    return array_sum($a) <=> array_sum($b);
});

$mapping = array_flip(array_keys($centroids));

$stmt = $db->prepare("
    INSERT INTO daily_house_features 
    (date, unom, x1_status, x2_parks, x3_activity, x4_capacity, cluster_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$savedCount = 0;

foreach ($dataset as $u => $d) {
    $oldK = -1;
    foreach ($clusters as $k => $arr) {
        if (in_array($u, $arr)) { $oldK = $k; break; }
    }

    // Преобразуем в красивый ID (1, 2, 3)
    $finalCluster = $mapping[$oldK] + 1;

    // Подготовка данных для insert (округляем статус до целого)
    $r1 = (int)round($d['x1']);
    $r2 = (int)$d['x2'];
    $r3 = (int)$d['x3'];
    $r4 = (int)$d['x4'];

    // s = string, i = int (6 штук)
    $stmt->bind_param('siiiiii', $today, $u, $r1, $r2, $r3, $r4, $finalCluster);
    $stmt->execute();
    $savedCount++;
}

echo json_encode([
    'ok' => true,
    'processed_houses' => $savedCount,
    'msg' => "Расчет завершен. Дома распределены по 3 кластерам."
], JSON_UNESCAPED_UNICODE);
?>