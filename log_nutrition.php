<?php
// Nutrition logging: log foods, view today's intake
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/foods.php';

// Require login
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Handle AJAX actions (log food, delete custom food)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'food') {
        echo json_encode(logFoodEntry($db, $userId, $_POST));
        exit;
    }

    if ($action === 'delete_custom_food') {
        $foodId = (int)($_POST['food_id'] ?? 0);
        if ($foodId > 0) {
            $db->prepare('DELETE FROM foods WHERE food_id=? AND user_id=?')
               ->execute([$foodId, $userId]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false]);
    exit;
}

// Load user data and today's intake
$stmt = $db->prepare('
    SELECT u.full_name, g.daily_calorie_goal, g.daily_protein_goal_g,
           g.daily_fat_goal_g, g.daily_carbs_goal_g,
           u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
           COALESCE(f.kcal, 0) AS today_kcal,
           COALESCE(f.prot, 0) AS today_protein,
           COALESCE(f.fat, 0)  AS today_fat,
           COALESCE(f.carb, 0) AS today_carbs
    FROM users u
    LEFT JOIN user_goals g ON g.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(calories) AS kcal, SUM(protein_g) AS prot,
                      SUM(fat_g) AS fat, SUM(carbs_g) AS carb
               FROM food_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
               GROUP BY user_id) f ON f.user_id = u.user_id
    WHERE u.user_id=?
');
$stmt->execute([$userId, $userId]);
$user = $stmt->fetch();

$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$goalKcal  = (int)($user['daily_calorie_goal'] ?? 2000);
$todayKcal = (int)($user['today_kcal'] ?? 0);
$goalP     = (int)($user['daily_protein_goal_g'] ?? 125);
$todayP    = round((float)($user['today_protein'] ?? 0), 1);
$goalFt    = (int)($user['daily_fat_goal_g'] ?? 67);
$todayFt   = round((float)($user['today_fat'] ?? 0), 1);
$goalCb    = (int)($user['daily_carbs_goal_g'] ?? 225);
$todayCb   = round((float)($user['today_carbs'] ?? 0), 1);
$macroPct  = fn($eaten, $goal) => $goal > 0 ? min(100, round($eaten / $goal * 100)) : 0;

$foodIcons = [
    'breakfast' => 'bakery_dining',
    'lunch'     => 'lunch_dining',
    'dinner'    => 'dinner_dining',
    'snack'     => 'cookie',
];

// Load food options and custom foods
$foodsQ = $db->prepare('SELECT food_name, serving_qty, unit_type, calories FROM foods WHERE user_id IS NULL ORDER BY food_name ASC');
$foodsQ->execute();
$foods = $foodsQ->fetchAll();

$cfQ = $db->prepare('SELECT food_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g
                     FROM foods WHERE user_id=? ORDER BY created_at DESC');
$cfQ->execute([$userId]);
$customFoods = $cfQ->fetchAll();

// Load today's recent food logs
$recent = $db->prepare('
    SELECT fl.log_id, fl.calories AS val, f.food_name AS title, fl.meal_type,
           fl.protein_g, fl.fat_g, fl.carbs_g, fl.qty, fl.unit_type, fl.logged_at
    FROM food_logs fl JOIN foods f ON f.food_id = fl.food_id
    WHERE fl.user_id=? AND DATE(fl.logged_at)=CURDATE()
    ORDER BY fl.logged_at DESC LIMIT 10
');
$recent->execute([$userId]);
$recentLogs = $recent->fetchAll();

require __DIR__ . '/log_nutrition.html';