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

/**
 * Preset food library with calories + macros per serving.
 */
function presetFoods(): array {
    return [
        'White Rice (1 cup)'    => ['calories' => 200, 'protein' => 4.0, 'fat' => 0.4, 'carbs' => 45.0],
        'Boiled Egg'            => ['calories' => 70,  'protein' => 6.0, 'fat' => 5.0, 'carbs' => 0.6],
        'Apple'                 => ['calories' => 95,  'protein' => 0.5, 'fat' => 0.3, 'carbs' => 25.0],
        'Milk (1 cup)'          => ['calories' => 150, 'protein' => 8.0, 'fat' => 8.0, 'carbs' => 12.0],
        'Chicken Breast (100g)' => ['calories' => 165, 'protein' => 31.0, 'fat' => 3.6, 'carbs' => 0.0],
        'Banana'                => ['calories' => 105, 'protein' => 1.3, 'fat' => 0.4, 'carbs' => 27.0],
        'Bread (1 slice)'       => ['calories' => 80,  'protein' => 3.0, 'fat' => 1.0, 'carbs' => 15.0],
        'Oatmeal (1 bowl)'      => ['calories' => 150, 'protein' => 5.0, 'fat' => 3.0, 'carbs' => 27.0],
    ];
}

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

        $foods = presetFoods();
        if (isset($foods[$foodName])) {
            // Preset — look up macros from library
            $f       = $foods[$foodName];
            $cal     = (int)$f['calories'];
            $protein = (float)$f['protein'];
            $fat     = (float)$f['fat'];
            $carbs   = (float)$f['carbs'];
            $qty     = '1 serving';
        } else {
            // Custom entry — check the user's saved My Foods library first
            $savedQ = $db->prepare('SELECT calories, protein_g, fat_g, carbs_g
                                    FROM custom_foods WHERE user_id=? AND food_name=?');
            $savedQ->execute([$userId, $foodName]);
            $saved = $savedQ->fetch();

            if ($saved) {
                $cal     = (int)$saved['calories'];
                $protein = (float)$saved['protein_g'];
                $fat     = (float)$saved['fat_g'];
                $carbs   = (float)$saved['carbs_g'];
                $qty     = '1 serving';
            } else {
                // New custom entry — use user-provided values
                $foodName = mb_substr($foodName, 0, 100);
                $cal     = max(0, min(3000, (int)($_POST['calories'] ?? 0)));
                $protein = max(0, min(400, (float)($_POST['protein_g']  ?? 0)));
                $fat     = max(0, min(250, (float)($_POST['fat_g']      ?? 0)));
                $carbs   = max(0, min(800, (float)($_POST['carbs_g']    ?? 0)));
                $qty     = mb_substr(trim($_POST['qty'] ?? '1 serving'), 0, 50);
                if ($foodName === '' || $cal <= 0) {
                    echo json_encode(['ok' => false]);
                    exit;
                }
                // Auto-save to My Foods for one-click logging next time
                $db->prepare('INSERT INTO custom_foods (user_id, food_name, calories, protein_g, fat_g, carbs_g)
                              VALUES (?,?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE calories=VALUES(calories), protein_g=VALUES(protein_g),
                                      fat_g=VALUES(fat_g), carbs_g=VALUES(carbs_g)')
                   ->execute([$userId, $foodName, $cal, $protein, $fat, $carbs]);
            }
        }

        $db->prepare('INSERT INTO food_logs (user_id, food_name, meal_type, calories, protein_g, fat_g, carbs_g, qty)
                      VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$userId, $foodName, $mealType, $cal, $protein, $fat, $carbs, $qty]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Delete a saved custom food from the user's library ──────────────────
    if ($action === 'delete_custom_food') {
        $foodId = (int)($_POST['food_id'] ?? 0);
        if ($foodId > 0) {
            $db->prepare('DELETE FROM custom_foods WHERE food_id=? AND user_id=?')
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

$foods = presetFoods();

// User's saved custom foods (My Foods library, one-click logging)
$cfQ = $db->prepare('SELECT food_id, food_name, calories, protein_g, fat_g, carbs_g
                     FROM custom_foods WHERE user_id=? ORDER BY created_at DESC');
$cfQ->execute([$userId]);
$customFoods = $cfQ->fetchAll();

$recent = $db->prepare('
    SELECT log_id, calories AS val, food_name AS title, meal_type, protein_g, fat_g, carbs_g, logged_at
    FROM food_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
    ORDER BY logged_at DESC LIMIT 10
');
$recent->execute([$userId]);
$recentLogs = $recent->fetchAll();

require __DIR__ . '/log_nutrition.html';