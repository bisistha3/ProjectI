<?php
/**
 * HealthFlow — History Page (server-side rendered)
 * Supports ?type=water|food|exercise views.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$type = $_GET['type'] ?? 'water';
if (!in_array($type, ['water', 'food', 'exercise'], true)) $type = 'water';

// ── User goals ─────────────────────────────────────────────────────────────
$u = $db->prepare('SELECT u.full_name, g.daily_goal_ml, g.daily_calorie_goal, g.daily_protein_goal_g,
                          g.daily_fat_goal_g, g.daily_carbs_goal_g, g.daily_exercise_goal_min,
                          u.reminder_enabled, u.reminder_time, u.reminder_interval_min
                          FROM users u
                          LEFT JOIN user_goals g ON g.user_id = u.user_id
                          WHERE u.user_id=?');
$u->execute([$userId]);
$user     = $u->fetch();
$goalMl   = (int)($user['daily_goal_ml'] ?? 2500);
$goalL    = $goalMl / 1000;
$goalLabel = number_format($goalL, 1) . 'L';
$goalKcal = (int)($user['daily_calorie_goal']     ?? 2000);
$goalProt  = (int)($user['daily_protein_goal_g']  ?? 125);
$goalFat   = (int)($user['daily_fat_goal_g']      ?? 67);
$goalCarbs = (int)($user['daily_carbs_goal_g']    ?? 225);
$goalMin   = (int)($user['daily_exercise_goal_min'] ?? 30);
$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');

// ── Water streak (all views — water-based) ──────────────────────────────────
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

$metrics = [];
$weekRaw = [];
$tableRows = [];
$calData  = [];
$chartValue = 0; // which column drives the 7-day chart

if ($type === 'water') {
    // ── Last 7 days data ──────────────────────────────────────────────────────
    $weekly = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
        FROM water_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
        ORDER BY day ASC
    ');
    $weekly->execute([$userId]);
    $weekRaw = $weekly->fetchAll(PDO::FETCH_KEY_PAIR); // [date => ml]

    // Metrics
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
    $chartValue = 'ml';

    // Table rows
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

    // Current month streak calendar
    $calQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
        FROM water_logs
        WHERE user_id=? AND YEAR(logged_at)=YEAR(CURDATE()) AND MONTH(logged_at)=MONTH(CURDATE())
        GROUP BY DATE(logged_at)
    ');
    $calQ->execute([$userId]);
    $calData = $calQ->fetchAll(PDO::FETCH_KEY_PAIR);

} elseif ($type === 'food') {
    // ── Last 7 days food data ─────────────────────────────────────────────────
    $weekly = $db->prepare('
        SELECT DATE(logged_at) AS day,
               SUM(calories) AS total_kcal, SUM(protein_g) AS prot,
               SUM(fat_g) AS fat, SUM(carbs_g) AS carbs, COUNT(*) AS entries
        FROM food_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
        ORDER BY day ASC
    ');
    $weekly->execute([$userId]);
    $weekRaw = [];
    foreach ($weekly->fetchAll() as $r) {
        $weekRaw[$r['day']] = [
            'kcal'  => (int)$r['total_kcal'],
            'prot'  => round((float)$r['prot'], 1),
            'fat'   => round((float)$r['fat'], 1),
            'carbs' => round((float)$r['carbs'], 1),
        ];
    }

    // Metrics
    $metricsQ = $db->prepare('
        SELECT
            ROUND(AVG(daily_kcal), 0)     AS avg_kcal,
            MAX(daily_kcal)               AS best_kcal,
            ROUND(SUM(daily_kcal), 0)     AS total_kcal,
            ROUND(SUM(daily_prot), 1)     AS total_prot,
            ROUND(SUM(daily_fat), 1)      AS total_fat,
            ROUND(SUM(daily_carbs), 1)    AS total_carbs
        FROM (
            SELECT DATE(logged_at) AS d,
                   SUM(calories) AS daily_kcal, SUM(protein_g) AS daily_prot,
                   SUM(fat_g) AS daily_fat, SUM(carbs_g) AS daily_carbs
            FROM food_logs WHERE user_id=?
            GROUP BY DATE(logged_at)
        ) sub
    ');
    $metricsQ->execute([$userId]);
    $metrics = $metricsQ->fetch();
    $chartValue = 'kcal';

    // Table rows (last 7 days)
    $tableQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(calories) AS total_kcal,
               SUM(protein_g) AS prot, SUM(fat_g) AS fat, SUM(carbs_g) AS carbs,
               MIN(meal_type) AS top_source
        FROM food_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
        ORDER BY day DESC
    ');
    $tableQ->execute([$userId]);
    $tableRows = $tableQ->fetchAll();

} else { // exercise
    // ── Last 7 days exercise data ────────────────────────────────────────────
    $weekly = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(duration_min) AS total_min,
               SUM(calories_burned) AS total_burn, COUNT(*) AS sessions
        FROM exercise_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
        ORDER BY day ASC
    ');
    $weekly->execute([$userId]);
    $weekRaw = [];
    foreach ($weekly->fetchAll() as $r) {
        $weekRaw[$r['day']] = [
            'min'  => (int)$r['total_min'],
            'burn' => (int)$r['total_burn'],
        ];
    }

    // Metrics
    $metricsQ = $db->prepare('
        SELECT
            ROUND(AVG(daily_min), 0)   AS avg_min,
            MAX(daily_min)             AS best_min,
            ROUND(SUM(daily_min), 0)   AS total_min,
            ROUND(SUM(daily_burn), 0)  AS total_burn,
            ROUND(SUM(daily_burn)/NULLIF(SUM(daily_min),0), 0) AS kcal_per_min
        FROM (
            SELECT DATE(logged_at) AS d, SUM(duration_min) AS daily_min,
                   SUM(calories_burned) AS daily_burn
            FROM exercise_logs WHERE user_id=?
            GROUP BY DATE(logged_at)
        ) sub
    ');
    $metricsQ->execute([$userId]);
    $metrics = $metricsQ->fetch();
    $chartValue = 'min';

    // Table rows (last 7 days)
    $tableQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(duration_min) AS total_min,
               SUM(calories_burned) AS total_burn, COUNT(*) AS sessions,
               MAX(exercise_type) AS top_source
        FROM exercise_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
        ORDER BY day DESC
    ');
    $tableQ->execute([$userId]);
    $tableRows = $tableQ->fetchAll();
}

// ── Build 7-day array for the bar chart ─────────────────────────────────────
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $raw  = $weekRaw[$date] ?? [];

    if ($type === 'water') {
        $val  = (int)($raw ?? 0);
        $goal = $goalMl;
        $pct  = $goal > 0 ? min(100, round($val / $goal * 100)) : 0;
    } elseif ($type === 'food') {
        $val  = (int)($raw['kcal'] ?? 0);
        $goal = $goalKcal;
        $pct  = $goal > 0 ? min(100, round($val / $goal * 100)) : 0;
    } else {
        $val  = (int)($raw['min'] ?? 0);
        $goal = $goalMin;
        $pct  = $goal > 0 ? min(100, round($val / $goal * 100)) : 0;
    }

    $weekDays[] = [
        'date'     => $date,
        'label'    => date('D', strtotime($date)),
        'value'    => $val,
        'goal'     => $goal,
        'pct'      => $pct,
        'is_today' => $date === date('Y-m-d'),
    ];
}

// Metric labels per type
    $metricMeta = [
        'water'   => ['avg' => ['lbl' => 'Avg. Daily Intake', 'val' => $metrics['avg_l']  ?? '0.0', 'unit' => 'L',
                            'icon' => 'water_ph', 'color' => '#00696d'],
                      'best' => ['lbl' => 'Best Day', 'val' => $metrics['best_l'] ?? '0.0', 'unit' => 'L',
                            'icon' => 'calendar_month', 'color' => '#86c963'],
                      'total' => ['lbl' => 'Total Consumed', 'val' => $metrics['total_l'] ?? '0.0', 'unit' => 'L',
                            'icon' => 'bar_chart', 'color' => '#445f56']],
        'food'    => ['avg' => ['lbl' => 'Avg. Daily Calories', 'val' => number_format((float)($metrics['avg_kcal'] ?? 0)), 'unit' => 'kcal',
                            'icon' => 'restaurant', 'color' => '#3d6b23'],
                      'best' => ['lbl' => 'Best Day', 'val' => number_format((float)($metrics['best_kcal'] ?? 0)), 'unit' => 'kcal',
                            'icon' => 'calendar_month', 'color' => '#86c963'],
                      'total' => ['lbl' => 'Total Consumed', 'val' => number_format((float)($metrics['total_kcal'] ?? 0)), 'unit' => 'kcal',
                            'icon' => 'bar_chart', 'color' => '#445f56']],
        'exercise'=> ['avg' => ['lbl' => 'Avg. Daily Activity', 'val' => $metrics['avg_min'] ?? '0', 'unit' => 'min',
                            'icon' => 'directions_run', 'color' => '#00696d'],
                      'best' => ['lbl' => 'Best Day', 'val' => $metrics['best_min'] ?? '0', 'unit' => 'min',
                            'icon' => 'calendar_month', 'color' => '#86c963'],
                      'total' => ['lbl' => 'Total Activity', 'val' => $metrics['total_min'] ?? '0', 'unit' => 'min',
                            'icon' => 'bar_chart', 'color' => '#445f56']],
    ];
require __DIR__ . '/history.html';