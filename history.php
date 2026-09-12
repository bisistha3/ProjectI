<?php
// History — streaks, weekly data, and metrics.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['water', 'food', 'exercise', 'all'], true)) $type = 'all';

// Calendar month navigation (additive: defaults to current month, preserving prior behavior).
$nowYear  = (int)date('Y');
$nowMonth = (int)date('n');
$calYear  = isset($_GET['year']) ? (int)$_GET['year'] : $nowYear;
$calMonth = isset($_GET['month']) ? (int)$_GET['month'] : $nowMonth;
if ($calMonth < 1 || $calMonth > 12) $calMonth = $nowMonth;
if ($calYear < 1970 || $calYear > 2100) $calYear = $nowYear;
$calLabel = date('F Y', mktime(0, 0, 0, $calMonth, 1, $calYear));
$prevMonthTs = mktime(0, 0, 0, $calMonth - 1, 1, $calYear);
$nextMonthTs = mktime(0, 0, 0, $calMonth + 1, 1, $calYear);
$prevMonth = (int)date('n', $prevMonthTs);
$prevYear  = (int)date('Y', $prevMonthTs);
$nextMonth = (int)date('n', $nextMonthTs);
$nextYear  = (int)date('Y', $nextMonthTs);
$isCurrentMonth = ($calYear === $nowYear && $calMonth === $nowMonth);
$isFutureMonth  = ($calYear > $nowYear) || ($calYear === $nowYear && $calMonth > $nowMonth);
$todayNum = $isCurrentMonth ? (int)date('j') : 0;

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

// Streaks per type (water, food, exercise).
// Counts back from today only — an unlogged today resets the visible streak to 0.
$streakTables = ['water' => 'water_logs', 'food' => 'food_logs', 'exercise' => 'exercise_logs'];
$streaks = ['water' => 0, 'food' => 0, 'exercise' => 0];
foreach ($streakTables as $skey => $stable) {
    $streakQ = $db->prepare(
        "SELECT DATE(logged_at) AS day FROM $stable WHERE user_id=? GROUP BY DATE(logged_at) ORDER BY day DESC"
    );
    $streakQ->execute([$userId]);
    $sdays = $streakQ->fetchAll(PDO::FETCH_COLUMN);
    $check = new DateTime('today');
    foreach ($sdays as $day) {
        if ($day === $check->format('Y-m-d')) { $streaks[$skey]++; $check->modify('-1 day'); }
        else break;
    }
}
$streak = $streaks['water'];

// Per-type daily goal, used by the streak calendar dots.
$calGoals = ['water' => $goalMl, 'food' => $goalKcal, 'exercise' => $goalMin];

$metrics = [];
$weekRaw = [];
$tableRows = [];
$calData  = [];
$chartValue = 0;

if ($type === 'water') {
    $weekly = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
        FROM water_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
        ORDER BY day ASC
    ');
    $weekly->execute([$userId]);
    $weekRaw = $weekly->fetchAll(PDO::FETCH_KEY_PAIR);

    // Averages cover logged days only; days with no entries count as nothing.
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

    // Week totals for the water summary strip.
    $wstatQ = $db->prepare('
        SELECT SUM(amount_ml) AS ml, COUNT(*) AS n
        FROM water_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ');
    $wstatQ->execute([$userId]);
    $wstat = $wstatQ->fetch();
    $metrics['week_ml'] = (int)($wstat['ml'] ?? 0);
    $metrics['week_n']  = (int)($wstat['n'] ?? 0);

    // MIN/MAX over text picks one arbitrary value as a placeholder label — not the actual most-logged item.
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

    $calQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
        FROM water_logs
        WHERE user_id=? AND YEAR(logged_at)=? AND MONTH(logged_at)=?
        GROUP BY DATE(logged_at)
    ');
    $calQ->execute([$userId, $calYear, $calMonth]);
    $calData['water'] = $calQ->fetchAll(PDO::FETCH_KEY_PAIR);

} elseif ($type === 'food') {
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

    // Averages cover logged days only; days with no entries count as nothing.
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

    // MIN/MAX over text picks one arbitrary value as a placeholder label — not the actual most-logged item.
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

    $calQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(calories) AS total_kcal
        FROM food_logs
        WHERE user_id=? AND YEAR(logged_at)=? AND MONTH(logged_at)=?
        GROUP BY DATE(logged_at)
    ');
    $calQ->execute([$userId, $calYear, $calMonth]);
    $calData['food'] = $calQ->fetchAll(PDO::FETCH_KEY_PAIR);

} elseif ($type === 'exercise') {
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

    // Averages cover logged days only; days with no entries count as nothing.
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

    // MIN/MAX over text picks one arbitrary value as a placeholder label — not the actual most-logged item.
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

    $calQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(duration_min) AS total_min
        FROM exercise_logs
        WHERE user_id=? AND YEAR(logged_at)=? AND MONTH(logged_at)=?
        GROUP BY DATE(logged_at)
    ');
    $calQ->execute([$userId, $calYear, $calMonth]);
    $calData['exercise'] = $calQ->fetchAll(PDO::FETCH_KEY_PAIR);
} else { // ---- ALL: combined water + food + exercise ----
    // Last-7-day daily aggregates per type.
    $wQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml, COUNT(*) AS entries
        FROM water_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
    ');
    $wQ->execute([$userId]);
    $waterByDay = [];
    foreach ($wQ->fetchAll() as $r) $waterByDay[$r['day']] = $r;

    $fQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(calories) AS total_kcal,
               SUM(protein_g) AS prot, SUM(fat_g) AS fat, SUM(carbs_g) AS carbs,
               COUNT(*) AS entries
        FROM food_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
    ');
    $fQ->execute([$userId]);
    $foodByDay = [];
    foreach ($fQ->fetchAll() as $r) $foodByDay[$r['day']] = $r;

    $eQ = $db->prepare('
        SELECT DATE(logged_at) AS day, SUM(duration_min) AS total_min,
               SUM(calories_burned) AS total_burn, COUNT(*) AS entries
        FROM exercise_logs
        WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(logged_at)
    ');
    $eQ->execute([$userId]);
    $exByDay = [];
    foreach ($eQ->fetchAll() as $r) $exByDay[$r['day']] = $r;

    // Merge by date. The chart covers all 7 days; the table lists logged days only.
    $weekRaw = [];
    $tableRows = [];
    $weekMl = $weekKcal = $weekMin = $weekBurn = 0;
    $weekProt = $weekFat = $weekCarbs = 0.0;
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $w = $waterByDay[$date] ?? null;
        $f = $foodByDay[$date] ?? null;
        $e = $exByDay[$date] ?? null;

        $ml   = (int)($w['total_ml'] ?? 0);
        $kcal = (int)($f['total_kcal'] ?? 0);
        $min  = (int)($e['total_min'] ?? 0);
        $burn = (int)($e['total_burn'] ?? 0);
        $weekRaw[$date] = ['water_ml' => $ml, 'food_kcal' => $kcal, 'ex_min' => $min];

        $weekMl += $ml; $weekKcal += $kcal; $weekMin += $min; $weekBurn += $burn;
        $weekProt  += (float)($f['prot'] ?? 0);
        $weekFat   += (float)($f['fat'] ?? 0);
        $weekCarbs += (float)($f['carbs'] ?? 0);

        if (!$w && !$f && !$e) continue;
        $tableRows[] = [
            'day'       => $date,
            'water_ml'  => $ml,
            'food_kcal' => $kcal,
            'ex_min'    => $min,
            'water_pct' => $goalMl   > 0 ? min(100, round($ml / $goalMl * 100)) : 0,
            'food_pct'  => $goalKcal > 0 ? min(100, round($kcal / $goalKcal * 100)) : 0,
            'ex_pct'    => $goalMin  > 0 ? min(100, round($min / $goalMin * 100)) : 0,
            'goals_met' => ($ml >= $goalMl ? 1 : 0) + ($kcal >= $goalKcal ? 1 : 0) + ($min >= $goalMin ? 1 : 0),
        ];
    }
    // Table shows newest first.
    $tableRows = array_reverse($tableRows);

    // All-time headline metrics.
    $nQ = $db->prepare('SELECT (SELECT COUNT(*) FROM water_logs WHERE user_id=?)
                             + (SELECT COUNT(*) FROM food_logs WHERE user_id=?)
                             + (SELECT COUNT(*) FROM exercise_logs WHERE user_id=?) AS total_logs');
    $nQ->execute([$userId, $userId, $userId]);
    $totalLogs = (int)$nQ->fetchColumn();

    $aQ = $db->prepare('SELECT COUNT(*) FROM (
                             SELECT DATE(logged_at) AS day FROM water_logs WHERE user_id=?
                             UNION
                             SELECT DATE(logged_at) FROM food_logs WHERE user_id=?
                             UNION
                             SELECT DATE(logged_at) FROM exercise_logs WHERE user_id=?
                         ) d');
    $aQ->execute([$userId, $userId, $userId]);
    $activeDays = (int)$aQ->fetchColumn();

    // A "perfect day" meets all three daily goals.
    $pQ = $db->prepare('SELECT COUNT(*) FROM (
                             SELECT days.day
                             FROM (
                                 SELECT DATE(logged_at) AS day FROM water_logs WHERE user_id=?
                                 UNION
                                 SELECT DATE(logged_at) FROM food_logs WHERE user_id=?
                                 UNION
                                 SELECT DATE(logged_at) FROM exercise_logs WHERE user_id=?
                             ) days
                             LEFT JOIN (SELECT DATE(logged_at) AS day, SUM(amount_ml) AS ml
                                        FROM water_logs WHERE user_id=? GROUP BY DATE(logged_at)) w ON w.day = days.day
                             LEFT JOIN (SELECT DATE(logged_at) AS day, SUM(calories) AS kcal
                                        FROM food_logs WHERE user_id=? GROUP BY DATE(logged_at)) f ON f.day = days.day
                             LEFT JOIN (SELECT DATE(logged_at) AS day, SUM(duration_min) AS mn
                                        FROM exercise_logs WHERE user_id=? GROUP BY DATE(logged_at)) e ON e.day = days.day
                             WHERE COALESCE(w.ml, 0) >= ? AND COALESCE(f.kcal, 0) >= ? AND COALESCE(e.mn, 0) >= ?
                         ) p');
    $pQ->execute([$userId, $userId, $userId, $userId, $userId, $userId, $goalMl, $goalKcal, $goalMin]);
    $perfectDays = (int)$pQ->fetchColumn();

    $metrics = [
        'active_days'  => $activeDays,
        'total_logs'   => $totalLogs,
        'perfect_days' => $perfectDays,
        'week_ml'      => $weekMl,
        'week_kcal'    => $weekKcal,
        'week_min'     => $weekMin,
        'week_burn'    => $weekBurn,
        'week_prot'    => round($weekProt, 1),
        'week_fat'     => round($weekFat, 1),
        'week_carbs'   => round($weekCarbs, 1),
    ];
    $chartValue = 'all';
}

// Build week chart data
// $weekRaw holds a scalar per day for water (FETCH_KEY_PAIR) but an array for food/exercise.
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $raw  = $weekRaw[$date] ?? [];

    if ($type === 'all') {
        // $weekRaw holds a per-type array for 'all'; each bar scales to its own goal.
        $w = (int)($raw['water_ml'] ?? 0);
        $f = (int)($raw['food_kcal'] ?? 0);
        $e = (int)($raw['ex_min'] ?? 0);
        $weekDays[] = [
            'date'      => $date,
            'label'     => date('D', strtotime($date)),
            'water_ml'  => $w,
            'food_kcal' => $f,
            'ex_min'    => $e,
            'pcts'      => [
                'water'    => $goalMl   > 0 ? min(100, round($w / $goalMl * 100)) : 0,
                'food'     => $goalKcal > 0 ? min(100, round($f / $goalKcal * 100)) : 0,
                'exercise' => $goalMin  > 0 ? min(100, round($e / $goalMin * 100)) : 0,
            ],
            'is_today'  => $date === date('Y-m-d'),
        ];
        continue;
    } elseif ($type === 'water') {
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

// Metric cards metadata
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
        'all'     => ['avg' => ['lbl' => 'Active Days', 'val' => $metrics['active_days'] ?? '0', 'unit' => 'days',
                            'icon' => 'event_available', 'color' => '#00696d'],
                      'best' => ['lbl' => 'Total Logs', 'val' => number_format((float)($metrics['total_logs'] ?? 0)), 'unit' => 'logs',
                            'icon' => 'receipt_long', 'color' => '#f97316'],
                      'total' => ['lbl' => 'Perfect Days', 'val' => $metrics['perfect_days'] ?? '0', 'unit' => 'days',
                            'icon' => 'emoji_events', 'color' => '#445f56']],
    ];
require __DIR__ . '/history.html';