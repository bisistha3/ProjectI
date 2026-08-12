<?php
/**
 * HealthFlow — Log Exercise Module
 * Handles exercise logging (AJAX POST action=exercise, MET-based kcal calc)
 * and renders the exercise page.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

/**
 * MET values (generic) for calorie burn: kcal = MET × weight(kg) × hours.
 */
function metValue(string $type): float {
    return match($type) {
        'Walking'        => 3.5,
        'Running'        => 9.8,
        'Yoga'           => 2.5,
        'Gym / Strength' => 5.0,
        default          => 4.0,
    };
}

// ── Handle AJAX POST actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'exercise') {
        $exType    = trim($_POST['exercise_type'] ?? '');
        $duration  = max(1, min(600, (int)($_POST['duration_min'] ?? 0)));
        $weightQ   = $db->prepare('SELECT weight FROM users WHERE user_id=?');
        $weightQ->execute([$userId]);
        $weightKg  = (float)($weightQ->fetch()['weight'] ?? 70);
        $burned    = (int)round(metValue($exType) * $weightKg * ($duration / 60));
        $db->prepare('INSERT INTO exercise_logs (user_id, exercise_type, duration_min, calories_burned)
                      VALUES (?,?,?,?)')
           ->execute([$userId, $exType, $duration, $burned]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false]);
    exit;
}

// ── Load page data ──────────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT u.full_name, u.weight, u.daily_exercise_goal_min,
           u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
           COALESCE(e.min, 0)  AS today_min,
           COALESCE(e.burn, 0) AS today_burn
    FROM users u
    LEFT JOIN (SELECT user_id, SUM(duration_min) AS min, SUM(calories_burned) AS burn
               FROM exercise_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
               GROUP BY user_id) e ON e.user_id = u.user_id
    WHERE u.user_id=?
');
$stmt->execute([$userId, $userId]);
$user = $stmt->fetch();

$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$goalMin   = (int)($user['daily_exercise_goal_min'] ?? 30);
$todayMin  = (int)($user['today_min'] ?? 0);
$todayBurn = (int)($user['today_burn'] ?? 0);

$exerciseIcons = [
    'Walking'        => 'directions_walk',
    'Running'        => 'directions_run',
    'Yoga'           => 'self_improvement',
    'Gym / Strength' => 'fitness_center',
];
$exerciseTypes = array_keys($exerciseIcons);

$recent = $db->prepare('
    SELECT log_id, duration_min AS val, exercise_type AS title, calories_burned AS burn, logged_at
    FROM exercise_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
    ORDER BY logged_at DESC LIMIT 10
');
$recent->execute([$userId]);
$recentLogs = $recent->fetchAll();

require __DIR__ . '/log_exercise.html';