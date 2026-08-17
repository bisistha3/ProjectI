<?php
/**
 * HealthFlow — Log Page (water / nutrition / exercise)
 * One shared page; the ?type= parameter selects the logging section.
 * Logging POSTs go to dashboard.php (AJAX, action=water|food|exercise).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/foods.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$type = $_GET['type'] ?? 'water';
if (!in_array($type, ['water', 'nutrition', 'exercise'])) $type = 'water';

// ── User + today totals ────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT u.full_name, u.weight, u.daily_goal_ml, u.daily_calorie_goal, u.daily_protein_goal_g,
           u.daily_fat_goal_g, u.daily_carbs_goal_g, u.daily_exercise_goal_min,
           u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
           COALESCE(w.ml, 0)   AS today_ml,
           COALESCE(f.kcal, 0) AS today_kcal,
           COALESCE(f.prot, 0) AS today_protein,
           COALESCE(f.fat, 0)  AS today_fat,
           COALESCE(f.carb, 0) AS today_carbs,
           COALESCE(e.min, 0)  AS today_min,
           COALESCE(e.burn, 0) AS today_burn
    FROM users u
    LEFT JOIN (SELECT user_id, SUM(amount_ml) AS ml FROM water_logs
               WHERE user_id=? AND DATE(logged_at)=CURDATE() GROUP BY user_id) w
           ON w.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(calories) AS kcal, SUM(protein_g) AS prot,
                      SUM(fat_g) AS fat, SUM(carbs_g) AS carb
               FROM food_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
               GROUP BY user_id) f ON f.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(duration_min) AS min, SUM(calories_burned) AS burn
               FROM exercise_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
               GROUP BY user_id) e ON e.user_id = u.user_id
    WHERE u.user_id=?
');
$stmt->execute([$userId, $userId, $userId, $userId]);
$user = $stmt->fetch();

$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$goalMl    = (int)($user['daily_goal_ml']   ?? 2500);
$goalLabel = number_format($goalMl / 1000, 1) . 'L';
$todayMl   = (int)($user['today_ml'] ?? 0);
$goalKcal  = (int)($user['daily_calorie_goal'] ?? 2000);
$todayKcal = (int)($user['today_kcal'] ?? 0);
$goalP     = (int)($user['daily_protein_goal_g'] ?? 125);
$todayP    = round((float)($user['today_protein'] ?? 0), 1);
$goalFt    = (int)($user['daily_fat_goal_g'] ?? 67);
$todayFt   = round((float)($user['today_fat'] ?? 0), 1);
$goalCb    = (int)($user['daily_carbs_goal_g'] ?? 225);
$todayCb   = round((float)($user['today_carbs'] ?? 0), 1);
$goalMin   = (int)($user['daily_exercise_goal_min'] ?? 30);
$todayMin  = (int)($user['today_min'] ?? 0);
$todayBurn = (int)($user['today_burn'] ?? 0);
$weightKg  = (float)($user['weight'] ?? 70) ?: 70;
$macroPct  = fn($eaten, $goal) => $goal > 0 ? min(100, round($eaten / $goal * 100)) : 0;

// ── Recent logs for the selected type ──────────────────────────────────────
if ($type === 'water') {
    $recent = $db->prepare('
        SELECT log_id, amount_ml AS val, drink_type AS title, logged_at
        FROM water_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
        ORDER BY logged_at DESC LIMIT 10
    ');
    $recent->execute([$userId]);
} elseif ($type === 'nutrition') {
    $recent = $db->prepare('
        SELECT fl.log_id, fl.calories AS val, f.food_name AS title, fl.meal_type, fl.protein_g, fl.fat_g, fl.carbs_g,
               fl.qty, fl.unit_type, fl.logged_at
        FROM food_logs fl JOIN foods f ON f.food_id = fl.food_id
        WHERE fl.user_id=? AND DATE(fl.logged_at)=CURDATE()
        ORDER BY fl.logged_at DESC LIMIT 10
    ');
    $recent->execute([$userId]);
} else {
    $recent = $db->prepare('
        SELECT log_id, duration_min AS val, exercise_type AS title, calories_burned AS burn, logged_at
        FROM exercise_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
        ORDER BY logged_at DESC LIMIT 10
    ');
    $recent->execute([$userId]);
}
$recentLogs = $recent->fetchAll();

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

// Preset foods (global library from the foods table)
$foodsQ = $db->prepare('SELECT food_name, serving_qty, unit_type, calories FROM foods WHERE user_id IS NULL ORDER BY food_name ASC');
$foodsQ->execute();
$foods = $foodsQ->fetchAll();

// User's saved custom foods (My Foods library, one-click logging)
$cfQ = $db->prepare('SELECT food_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g
                     FROM foods WHERE user_id=? ORDER BY created_at DESC');
$cfQ->execute([$userId]);
$customFoods = $cfQ->fetchAll();
$drinkIcons = [
    'Water'        => 'water_drop',
    'Juice'        => 'local_drink',
    'Tea'          => 'emoji_food_beverage',
    'Coffee'       => 'coffee',
    'Sports Drink' => 'sports_bar',
    'Other'        => 'water_bottle',
];
$foodIcons = [
    'breakfast' => 'bakery_dining',
    'lunch'     => 'lunch_dining',
    'dinner'    => 'dinner_dining',
    'snack'     => 'cookie',
];
$exerciseIcons = [
    'Walking'        => 'directions_walk',
    'Running'        => 'directions_run',
    'Yoga'           => 'self_improvement',
    'Gym / Strength' => 'fitness_center',
];
$exerciseTypes = array_keys($exerciseIcons);

// ── Handle AJAX POST actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // ── Water logging ────────────────────────────────────────────────────────
    if ($action === 'water') {
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

    // ── Food logging (known food: name + qty; new food: name + qty + unit + nutrition) ──
    if ($action === 'food') {
        echo json_encode(logFoodEntry($db, $userId, $_POST));
        exit;
    }

    // ── Delete a saved custom food from My Foods ────────────────────────────
    if ($action === 'delete_custom_food') {
        $foodId = (int)($_POST['food_id'] ?? 0);
        if ($foodId > 0) {
            $db->prepare('DELETE FROM foods WHERE food_id=? AND user_id=?')
               ->execute([$foodId, $userId]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Exercise logging (MET-based kcal) ───────────────────────────────────
    if ($action === 'exercise') {
        $exType   = trim($_POST['exercise_type'] ?? '');
        $duration = max(1, min(600, (int)($_POST['duration_min'] ?? 0)));
        $burned   = (int)round(metValue($exType) * $weightKg * ($duration / 60));
        $db->prepare('INSERT INTO exercise_logs (user_id, exercise_type, duration_min, calories_burned)
                      VALUES (?,?,?,?)')
           ->execute([$userId, $exType, $duration, $burned]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false]);
    exit;
}

require __DIR__ . '/log.html';