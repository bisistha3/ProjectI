<?php
/**
 * HydroFlow — History Page (server-side rendered)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── User goal ─────────────────────────────────────────────────────────────────
$u = $db->prepare('SELECT full_name, daily_goal_ml FROM users WHERE user_id=?');
$u->execute([$userId]);
$user     = $u->fetch();
$goalMl   = (int)($user['daily_goal_ml'] ?? 2500);
$goalL    = $goalMl / 1000;
$goalLabel = number_format($goalL, 1) . 'L';
$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');

// ── Last 7 days data ──────────────────────────────────────────────────────────
$weekly = $db->prepare('
    SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
    FROM water_logs
    WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(logged_at)
    ORDER BY day ASC
');
$weekly->execute([$userId]);
$weeklyRaw = $weekly->fetchAll(PDO::FETCH_KEY_PAIR); // [date => ml]

// Build 7-day array always showing all 7 days
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $ml   = (int)($weeklyRaw[$date] ?? 0);
    $weekDays[] = [
        'date'    => $date,
        'label'   => date('D', strtotime($date)), // Mon, Tue...
        'ml'      => $ml,
        'pct'     => $goalMl > 0 ? min(100, round($ml / $goalMl * 100)) : 0,
        'is_today'=> $date === date('Y-m-d'),
    ];
}

// ── Metrics ───────────────────────────────────────────────────────────────────
$metricsQ = $db->prepare('
    SELECT
        ROUND(AVG(daily_total)/1000, 1)   AS avg_l,
        ROUND(MAX(daily_total)/1000, 1)   AS best_l,
        ROUND(SUM(daily_total)/1000, 1)   AS total_l
    FROM (
        SELECT DATE(logged_at) AS d, SUM(amount_ml) AS daily_total
        FROM water_logs WHERE user_id=?
        GROUP BY DATE(logged_at)
    ) sub
');
$metricsQ->execute([$userId]);
$metrics = $metricsQ->fetch();

// ── Streak ────────────────────────────────────────────────────────────────────
$streakQ = $db->prepare('
    SELECT DATE(logged_at) AS day FROM water_logs
    WHERE user_id=? GROUP BY DATE(logged_at) ORDER BY day DESC
');
$streakQ->execute([$userId]);
$days   = $streakQ->fetchAll(PDO::FETCH_COLUMN);
$streak = 0;
$check  = new DateTime('today');
foreach ($days as $day) {
    if ($day === $check->format('Y-m-d')) { $streak++; $check->modify('-1 day'); }
    else break;
}

// ── Last 7 days table rows ────────────────────────────────────────────────────
$tableQ = $db->prepare('
    SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml,
           MAX(drink_type) AS top_source
    FROM water_logs
    WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(logged_at)
    ORDER BY day DESC
');
$tableQ->execute([$userId]);
$tableRows = $tableQ->fetchAll();

// ── Current month streak calendar ─────────────────────────────────────────────
$calQ = $db->prepare('
    SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
    FROM water_logs
    WHERE user_id=? AND YEAR(logged_at)=YEAR(CURDATE()) AND MONTH(logged_at)=MONTH(CURDATE())
    GROUP BY DATE(logged_at)
');
$calQ->execute([$userId]);
$calData = $calQ->fetchAll(PDO::FETCH_KEY_PAIR); // [date => ml]
require __DIR__ . '/history.html';
