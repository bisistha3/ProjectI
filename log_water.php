<?php
/**
 * HealthFlow — Log Water Module
 * Handles water logging (AJAX POST action=water) and renders the water page.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── Handle AJAX POST actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'water') {
        $amountMl  = (int)$_POST['amount_ml'];
        $drinkType = trim($_POST['drink_type'] ?? 'Water');
        $allowed   = ['Water', 'Juice', 'Tea', 'Coffee', 'Sports Drink', 'Other'];
        if (!in_array($drinkType, $allowed)) $drinkType = 'Water';
        if ($amountMl > 0 && $amountMl <= 5000) {
            $db->prepare('INSERT INTO water_logs (user_id, amount_ml, drink_type) VALUES (?,?,?)')
               ->execute([$userId, $amountMl, $drinkType]);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    echo json_encode(['ok' => false]);
    exit;
}

// ── Load page data ──────────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT u.full_name, u.weight, g.daily_goal_ml,
           u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
           COALESCE(w.ml, 0) AS today_ml
    FROM users u
    LEFT JOIN user_goals g ON g.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(amount_ml) AS ml FROM water_logs
               WHERE user_id=? AND DATE(logged_at)=CURDATE() GROUP BY user_id) w
           ON w.user_id = u.user_id
    WHERE u.user_id=?
');
$stmt->execute([$userId, $userId]);
$user = $stmt->fetch();

$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$goalMl    = (int)($user['daily_goal_ml'] ?? 2500);
$goalLabel = number_format($goalMl / 1000, 1) . 'L';
$todayMl   = (int)($user['today_ml'] ?? 0);

$drinkIcons = [
    'Water'        => 'water_drop',
    'Juice'        => 'local_drink',
    'Tea'          => 'emoji_food_beverage',
    'Coffee'       => 'coffee',
    'Sports Drink' => 'sports_bar',
    'Other'        => 'water_bottle',
];

$recent = $db->prepare('
    SELECT log_id, amount_ml AS val, drink_type AS title, logged_at
    FROM water_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
    ORDER BY logged_at DESC LIMIT 10
');
$recent->execute([$userId]);
$recentLogs = $recent->fetchAll();

require __DIR__ . '/log_water.html';