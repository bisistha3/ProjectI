<?php
/**
 * HealthFlow — Log Nutrition Module
 * Handles food logging (AJAX POST action=food), saved custom foods (My Foods)
 * and renders the nutrition page.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── Handle AJAX POST actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // ── Log food (preset / My Foods / custom + auto-save) ────────────────────
    if ($action === 'food') {
        $foodName  = trim($_POST['food_name']  ?? '');
        $mealType  = trim($_POST['meal_type']  ?? 'snack');
        $mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
        if (!in_array($mealType, $mealTypes)) $mealType = 'snack';

        // Resolve existing food (global preset or user's own) by name
        $foodQ = $db->prepare('SELECT food_id, serving_size_g, calories, protein_g, fat_g, carbs_g
                               FROM foods WHERE food_name=? AND (user_id IS NULL OR user_id=?)
                               ORDER BY user_id DESC LIMIT 1');
        $foodQ->execute([$foodName, $userId]);
        $food = $foodQ->fetch();

        if ($food) {
            // Found — log one serving using the stored values
            $foodId  = (int)$food['food_id'];
            $cal     = (int)$food['calories'];
            $protein = (float)$food['protein_g'];
            $fat     = (float)$food['fat_g'];
            $carbs   = (float)$food['carbs_g'];
            $qtyG    = (float)$food['serving_size_g'];
        } else {
            // New custom entry — use user-provided values
            $foodName = mb_substr($foodName, 0, 100);
            $cal     = max(0, min(3000, (int)($_POST['calories'] ?? 0)));
            $protein = max(0, min(400, (float)($_POST['protein_g']  ?? 0)));
            $fat     = max(0, min(250, (float)($_POST['fat_g']      ?? 0)));
            $carbs   = max(0, min(800, (float)($_POST['carbs_g']    ?? 0)));
            if ($foodName === '' || $cal <= 0) {
                echo json_encode(['ok' => false]);
                exit;
            }
            // Auto-save to My Foods for one-click logging next time
            $db->prepare('INSERT INTO foods (user_id, food_name, serving_size_g, calories, protein_g, fat_g, carbs_g)
                          VALUES (?,?,?,?,?,?,?)')
               ->execute([$userId, $foodName, 100, $cal, $protein, $fat, $carbs]);
            $foodId = (int)$db->lastInsertId();
            $qtyG   = 100;
        }

        // Snapshot the exact nutritional values at log time
        $db->prepare('INSERT INTO food_logs (user_id, food_id, meal_type, qty_g, calories, protein_g, fat_g, carbs_g)
                      VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$userId, $foodId, $mealType, $qtyG, $cal, $protein, $fat, $carbs]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Delete a saved custom food from the user's library ──────────────────
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

// ── Load page data ──────────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT u.full_name, u.daily_calorie_goal, u.daily_protein_goal_g,
           u.daily_fat_goal_g, u.daily_carbs_goal_g,
           u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
           COALESCE(f.kcal, 0) AS today_kcal,
           COALESCE(f.prot, 0) AS today_protein,
           COALESCE(f.fat, 0)  AS today_fat,
           COALESCE(f.carb, 0) AS today_carbs
    FROM users u
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

$foodsQ = $db->prepare('SELECT food_name, serving_size_g, calories FROM foods WHERE user_id IS NULL ORDER BY food_name ASC');
$foodsQ->execute();
$foods = $foodsQ->fetchAll();

// User's saved custom foods (My Foods library, one-click logging)
$cfQ = $db->prepare('SELECT food_id, food_name, calories, protein_g, fat_g, carbs_g
                     FROM foods WHERE user_id=? ORDER BY created_at DESC');
$cfQ->execute([$userId]);
$customFoods = $cfQ->fetchAll();

$recent = $db->prepare('
    SELECT fl.log_id, fl.calories AS val, f.food_name AS title, fl.meal_type, fl.protein_g, fl.fat_g, fl.carbs_g, fl.logged_at
    FROM food_logs fl JOIN foods f ON f.food_id = fl.food_id
    WHERE fl.user_id=? AND DATE(fl.logged_at)=CURDATE()
    ORDER BY fl.logged_at DESC LIMIT 10
');
$recent->execute([$userId]);
$recentLogs = $recent->fetchAll();

require __DIR__ . '/log_nutrition.html';