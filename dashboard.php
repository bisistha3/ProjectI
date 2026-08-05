<?php
/**
 * HydroFlow — Dashboard
 * Fully server-side rendered. One POST action for quick-log (AJAX).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── Handle Quick Log POST (returns JSON for fetch, then exits) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount_ml'])) {
    header('Content-Type: application/json');
    $amountMl  = (int)$_POST['amount_ml'];
    $drinkType = trim($_POST['drink_type'] ?? 'Water');
    $allowed   = ['Water', 'Juice', 'Tea', 'Coffee', 'Sports Drink', 'Other'];
    if (!in_array($drinkType, $allowed)) $drinkType = 'Water';

    if ($amountMl > 0 && $amountMl <= 5000) {
        $db->prepare('INSERT INTO water_logs (user_id, amount_ml, drink_type) VALUES (?,?,?)')
           ->execute([$userId, $amountMl, $drinkType]);
    }

    // Return updated today totals
    $row = $db->prepare('
        SELECT COALESCE(SUM(amount_ml),0) AS today_ml, u.daily_goal_ml
        FROM users u
        LEFT JOIN water_logs w ON w.user_id=u.user_id AND DATE(w.logged_at)=CURDATE()
        WHERE u.user_id=?
        GROUP BY u.daily_goal_ml
    ');
    $row->execute([$userId]);
    $t    = $row->fetch();
    $tMl  = (int)($t['today_ml'] ?? 0);
    $gMl  = (int)($t['daily_goal_ml'] ?? 2500);
    $pct  = $gMl > 0 ? min(100, round($tMl / $gMl * 100)) : 0;
    echo json_encode(['ok' => true, 'today_ml' => $tMl, 'goal_ml' => $gMl, 'percent' => $pct,
                      'amount_ml' => $amountMl, 'drink_type' => $drinkType, 'time' => date('h:i A')]);
    exit;
}

// ── Load page data ────────────────────────────────────────────────────────────
// User info + today total
$stmt = $db->prepare('
    SELECT u.full_name, u.daily_goal_ml,
           COALESCE(SUM(w.amount_ml),0) AS today_ml
    FROM users u
    LEFT JOIN water_logs w ON w.user_id=u.user_id AND DATE(w.logged_at)=CURDATE()
    WHERE u.user_id=?
    GROUP BY u.full_name, u.daily_goal_ml
');
$stmt->execute([$userId]);
$user    = $stmt->fetch();
$todayMl = (int)($user['today_ml']      ?? 0);
$goalMl  = (int)($user['daily_goal_ml'] ?? 2500);
$percent = $goalMl > 0 ? min(100, round($todayMl / $goalMl * 100)) : 0;
$remaining = max(0, $goalMl - $todayMl);
$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$goalLabel = number_format($goalMl / 1000, 1) . 'L';

// Recent logs (today, last 5)
$logs = $db->prepare('
    SELECT log_id, amount_ml, drink_type, logged_at
    FROM water_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
    ORDER BY logged_at DESC LIMIT 5
');
$logs->execute([$userId]);
$recentLogs = $logs->fetchAll();

// Streak
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

// Icon map
$drinkIcons = [
    'Water'        => 'water_drop',
    'Juice'        => 'local_drink',
    'Tea'          => 'emoji_food_beverage',
    'Coffee'       => 'coffee',
    'Sports Drink' => 'sports_bar',
    'Other'        => 'water_bottle',
];
require __DIR__ . '/dashboard.html';
