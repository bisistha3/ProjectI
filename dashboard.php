<?php
// Dashboard — today's totals and quick-log actions.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/foods.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Check if daily goal prompt should be shown (first login today)
$showDailyGoalPrompt = false;
if (!empty($_SESSION['_show_daily_goal_prompt'])) {
    $showDailyGoalPrompt = true;
    unset($_SESSION['_show_daily_goal_prompt']);
} else {
    // Also check DB directly in case session flag was lost or user refreshed
    $promptCheck = $db->prepare('SELECT daily_goals_prompted_at FROM users WHERE user_id = ?');
    $promptCheck->execute([$userId]);
    $promptedAt = $promptCheck->fetchColumn();
    if (!$promptedAt || date('Y-m-d', strtotime($promptedAt)) < date('Y-m-d')) {
        $showDailyGoalPrompt = true;
    }
}

// Error from a native (no-JS) goal save, shown inside the prompt modal.
$dailyGoalError = (string)getFlash('goal_error', '');

// MET value for an exercise type
function metValue(string $type): float {
    return match($type) {
        'Walking'        => 3.5,
        'Running'        => 9.8,
        'Yoga'           => 2.5,
        'Gym / Strength' => 5.0,
        default          => 4.0,
    };
}

// Handle AJAX quick-log actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Log water
    if ($action === 'water') {
        $amountMl  = (int)$_POST['amount_ml'];
        $drinkType = trim($_POST['drink_type'] ?? 'Water');
        $allowed   = ['Water', 'Juice', 'Tea', 'Coffee', 'Sports Drink', 'Other'];
        if (!in_array($drinkType, $allowed)) $drinkType = 'Water';
        // reject implausible entries (>5 L in one go)
        if ($amountMl > 0 && $amountMl <= 5000) {
            $db->prepare('INSERT INTO water_logs (user_id, amount_ml, drink_type) VALUES (?,?,?)')
               ->execute([$userId, $amountMl, $drinkType]);
        }
    }

    // Log food
    elseif ($action === 'food') {
        logFoodEntry($db, $userId, $_POST);
    }

    // Log exercise
    elseif ($action === 'exercise') {
        $exType    = trim($_POST['exercise_type'] ?? '');
        $duration  = max(1, min(600, (int)($_POST['duration_min'] ?? 0))); // clamp to 1–600 min instead of rejecting bad input
        $weightQ   = $db->prepare('SELECT weight FROM users WHERE user_id=?');
        $weightQ->execute([$userId]);
        $weightKg  = (float)($weightQ->fetch()['weight'] ?? 70);
        // kcal = MET × weight(kg) × duration(hrs)
        $burned    = (int)round(metValue($exType) * $weightKg * ($duration / 60));
        $db->prepare('INSERT INTO exercise_logs (user_id, exercise_type, duration_min, calories_burned)
                      VALUES (?,?,?,?)')
           ->execute([$userId, $exType, $duration, $burned]);
    }

    // Delete custom food
    elseif ($action === 'delete_custom_food') {
        $foodId = (int)($_POST['food_id'] ?? 0);
        if ($foodId > 0) {
            $db->prepare('DELETE FROM foods WHERE food_id=? AND user_id=?')
               ->execute([$foodId, $userId]);
        }
    }

    // Delete a log entry
    elseif ($action === 'delete_log') {
        $logType = $_POST['log_type'] ?? '';
        $logId   = (int)($_POST['log_id'] ?? 0);
        $tables  = ['water' => 'water_logs', 'food' => 'food_logs', 'exercise' => 'exercise_logs'];
        if ($logId > 0 && isset($tables[$logType])) {
            $db->prepare("DELETE FROM {$tables[$logType]} WHERE log_id=? AND user_id=?")
               ->execute([$logId, $userId]);
        }
    }

    // Edit a log entry
    elseif ($action === 'edit_log') {
        $logType = $_POST['log_type'] ?? '';
        $logId   = (int)($_POST['log_id'] ?? 0);
        $tables  = ['water' => 'water_logs', 'food' => 'food_logs', 'exercise' => 'exercise_logs'];
        
        $ok = false;
        $error = null;
        
        if ($logId > 0 && isset($tables[$logType])) {
            $table = $tables[$logType];
            
            if ($logType === 'water') {
                $amountMl  = (int)($_POST['amount_ml'] ?? 0);
                $drinkType = trim($_POST['drink_type'] ?? 'Water');
                $allowed   = ['Water', 'Juice', 'Tea', 'Coffee', 'Sports Drink', 'Other'];
                if (!in_array($drinkType, $allowed)) $drinkType = 'Water';
                if ($amountMl > 0 && $amountMl <= 5000) {
                    $stmt = $db->prepare("UPDATE $table SET amount_ml=?, drink_type=? WHERE log_id=? AND user_id=?");
                    $stmt->execute([$amountMl, $drinkType, $logId, $userId]);
                    $ok = $stmt->rowCount() > 0;
                    $error = $ok ? null : 'Entry not found or access denied';
                } else {
                    $error = 'Invalid amount (1-5000ml)';
                }
            }
            elseif ($logType === 'food') {
                $mealType = trim($_POST['meal_type'] ?? 'snack');
                $qty      = (float)($_POST['qty'] ?? 0);
                $unit     = $_POST['unit_type'] ?? 'g';
                $validMeals = ['breakfast', 'lunch', 'dinner', 'snack'];
                $validUnits = ['g', 'piece', 'ml'];
                if ($qty > 0 && $qty <= 100000 && in_array($mealType, $validMeals) && in_array($unit, $validUnits)) {
                    $foodQ = $db->prepare('
                        SELECT f.calories, f.protein_g, f.fat_g, f.carbs_g, f.serving_qty, f.unit_type
                        FROM food_logs fl JOIN foods f ON f.food_id = fl.food_id
                        WHERE fl.log_id=? AND fl.user_id=?
                    ');
                    $foodQ->execute([$logId, $userId]);
                    $food = $foodQ->fetch();
                    if ($food) {
                        $ratio = (float)$food['serving_qty'] > 0 ? $qty / (float)$food['serving_qty'] : 0;
                        $cal   = (int)round($food['calories'] * $ratio);
                        $prot  = round($food['protein_g'] * $ratio, 1);
                        $fat   = round($food['fat_g'] * $ratio, 1);
                        $carb  = round($food['carbs_g'] * $ratio, 1);
                        $stmt = $db->prepare("UPDATE $table SET meal_type=?, qty=?, unit_type=?, calories=?, protein_g=?, fat_g=?, carbs_g=? WHERE log_id=? AND user_id=?");
                        $stmt->execute([$mealType, $qty, $unit, $cal, $prot, $fat, $carb, $logId, $userId]);
                        $ok = $stmt->rowCount() > 0;
                        $error = $ok ? null : 'Entry not found or access denied';
                    } else {
                        $error = 'Linked food not found';
                    }
                } else {
                    $error = 'Invalid quantity, meal type, or unit';
                }
            }
            elseif ($logType === 'exercise') {
                $exType   = trim($_POST['exercise_type'] ?? '');
                $duration = max(1, min(600, (int)($_POST['duration_min'] ?? 0)));
                $validExercises = ['Walking', 'Running', 'Yoga', 'Gym / Strength'];
                if ($duration >= 1 && $duration <= 600 && in_array($exType, $validExercises)) {
                    $weightQ  = $db->prepare('SELECT weight FROM users WHERE user_id=?');
                    $weightQ->execute([$userId]);
                    $weightKg = (float)($weightQ->fetch()['weight'] ?? 70);
                    $burned   = (int)round(metValue($exType) * $weightKg * ($duration / 60));
                    $stmt = $db->prepare("UPDATE $table SET exercise_type=?, duration_min=?, calories_burned=? WHERE log_id=? AND user_id=?");
                    $stmt->execute([$exType, $duration, $burned, $logId, $userId]);
                    $ok = $stmt->rowCount() > 0;
                    $error = $ok ? null : 'Entry not found or access denied';
                } else {
                    $error = 'Invalid exercise type or duration (1-600 min)';
                }
            }
        } else {
            $error = 'Invalid log type or ID';
        }
        
        echo json_encode(['ok' => $ok, 'error' => $error]);
        exit;
    }

    // Update daily goals (from daily goal prompt modal).
    // AJAX (fetch) callers get JSON; a native form POST (no-JS fallback) gets
    // a PRG redirect so Save works even when JavaScript never intercepts.
    elseif ($action === 'update_daily_goals') {
        // fetch() + FormData always sends multipart/form-data (old and new JS
        // alike); only a true native form submit sends urlencoded. Detecting by
        // content type keeps stale-cached JS working instead of 302-ing it into
        // a JSON-parse failure ("Network error").
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $isAjax = ($_POST['ajax'] ?? '') === '1'
               || stripos($contentType, 'multipart/form-data') === 0;

        $goalMlIn    = (int)($_POST['daily_goal_ml'] ?? 2500);
        $calorieIn   = (int)($_POST['daily_calorie_goal'] ?? 2000);
        $proteinIn   = (int)($_POST['daily_protein_goal_g'] ?? 125);
        $fatIn       = (int)($_POST['daily_fat_goal_g'] ?? 67);
        $carbsIn     = (int)($_POST['daily_carbs_goal_g'] ?? 225);
        $exerciseIn  = (int)($_POST['daily_exercise_goal_min'] ?? 30);
        $burnIn      = (int)($_POST['daily_burn_goal_kcal'] ?? 300);

        // Validate ranges (WHO-aligned guardrails)
        $errors = [];
        if ($goalMlIn < 1000 || $goalMlIn > 5000)    $errors[] = 'Water goal must be 1000-5000 ml';
        if ($calorieIn < 1200 || $calorieIn > 5000)   $errors[] = 'Calorie goal must be 1200-5000 kcal';
        if ($proteinIn < 30 || $proteinIn > 200)       $errors[] = 'Protein goal must be 30-200 g';
        if ($fatIn < 20 || $fatIn > 150)               $errors[] = 'Fat goal must be 20-150 g';
        if ($carbsIn < 100 || $carbsIn > 700)          $errors[] = 'Carbs goal must be 100-700 g';
        if ($exerciseIn < 10 || $exerciseIn > 120)     $errors[] = 'Exercise goal must be 10-120 min';
        if ($burnIn < 50 || $burnIn > 1500)            $errors[] = 'Burn goal must be 50-1500 kcal';

        if (!empty($errors)) {
            $errText = implode(' ', $errors);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $errText]);
                exit;
            }
            // Native submit: redirect back with the panel forced open + error shown.
            setFlash('goal_error', $errText);
            $_SESSION['_show_daily_goal_prompt'] = true;
            header('Location: dashboard.php');
            exit;
        }

        // Ensure a goals row exists (a bare UPDATE would silently no-op otherwise).
        $db->prepare('INSERT IGNORE INTO user_goals (user_id) VALUES (?)')
           ->execute([$userId]);
        $db->prepare(
            'UPDATE user_goals SET daily_goal_ml=?, daily_calorie_goal=?,
             daily_protein_goal_g=?, daily_fat_goal_g=?, daily_carbs_goal_g=?,
             daily_exercise_goal_min=?, daily_burn_goal_kcal=? WHERE user_id=?'
        )->execute([$goalMlIn, $calorieIn, $proteinIn, $fatIn, $carbsIn, $exerciseIn, $burnIn, $userId]);

        // Mark as prompted today so it doesn't show again
        $db->prepare('UPDATE users SET daily_goals_prompted_at = NOW() WHERE user_id = ?')
           ->execute([$userId]);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        header('Location: dashboard.php');
        exit;
    }

    // Skip daily goals prompt (just mark as prompted)
    elseif ($action === 'skip_daily_goals') {
        header('Content-Type: application/json');
        $db->prepare('UPDATE users SET daily_goals_prompted_at = NOW() WHERE user_id = ?')
           ->execute([$userId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Reload today totals for the response
    $totals = $db->prepare('
        SELECT g.daily_goal_ml, g.daily_calorie_goal, g.daily_protein_goal_g,
           g.daily_fat_goal_g, g.daily_carbs_goal_g, g.daily_exercise_goal_min,
           g.daily_burn_goal_kcal,
               COALESCE(w.ml, 0)   AS today_ml,
               COALESCE(f.kcal, 0) AS today_kcal,
               COALESCE(f.prot, 0) AS today_protein,
               COALESCE(f.fat, 0)  AS today_fat,
               COALESCE(f.carb, 0) AS today_carbs,
               COALESCE(e.min, 0)  AS today_min,
               COALESCE(e.burn, 0) AS today_burn,
               COALESCE(e.walk, 0) AS today_walk,
               COALESCE(e.run, 0)  AS today_run,
               COALESCE(e.yoga, 0) AS today_yoga,
               COALESCE(e.gym, 0)  AS today_gym
        FROM users u
        LEFT JOIN user_goals g ON g.user_id = u.user_id
        LEFT JOIN (SELECT user_id, SUM(amount_ml) AS ml FROM water_logs
                   WHERE user_id=? AND DATE(logged_at)=CURDATE() GROUP BY user_id) w
               ON w.user_id = u.user_id
        LEFT JOIN (SELECT user_id, SUM(calories) AS kcal, SUM(protein_g) AS prot,
                          SUM(fat_g) AS fat, SUM(carbs_g) AS carb
                   FROM food_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
                   GROUP BY user_id) f ON f.user_id = u.user_id
        LEFT JOIN (SELECT user_id, SUM(duration_min) AS min, SUM(calories_burned) AS burn,
                          SUM(CASE WHEN exercise_type="Walking" THEN duration_min ELSE 0 END) AS walk,
                          SUM(CASE WHEN exercise_type="Running" THEN duration_min ELSE 0 END) AS run,
                          SUM(CASE WHEN exercise_type="Yoga" THEN duration_min ELSE 0 END) AS yoga,
                          SUM(CASE WHEN exercise_type="Gym / Strength" THEN duration_min ELSE 0 END) AS gym
                   FROM exercise_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
                   GROUP BY user_id) e ON e.user_id = u.user_id
        WHERE u.user_id=?
    ');
    $totals->execute([$userId, $userId, $userId, $userId]);
    $t = $totals->fetch();

    $ml  = (int)($t['today_ml'] ?? 0);      $gMl = (int)($t['daily_goal_ml'] ?? 2500);
    $kc  = (int)($t['today_kcal'] ?? 0);    $gKc = (int)($t['daily_calorie_goal'] ?? 2000);
    $p   = (float)($t['today_protein'] ?? 0); $gP = (int)($t['daily_protein_goal_g'] ?? 125);
    $ft  = (float)($t['today_fat'] ?? 0);   $gFt = (int)($t['daily_fat_goal_g'] ?? 67);
    $cb  = (float)($t['today_carbs'] ?? 0); $gCb = (int)($t['daily_carbs_goal_g'] ?? 225);
    $mn  = (int)($t['today_min'] ?? 0);     $gMn = (int)($t['daily_exercise_goal_min'] ?? 30);
    $bu  = (int)($t['today_burn'] ?? 0);    $gBu = (int)($t['daily_burn_goal_kcal'] ?? 300);

    echo json_encode([
        'ok' => true,
        'today_ml' => $ml, 'goal_ml' => $gMl,
        'percent' => $gMl > 0 ? min(100, round($ml / $gMl * 100)) : 0,
        'today_kcal' => $kc, 'goal_kcal' => $gKc,
        'kcal_percent' => $gKc > 0 ? min(100, round($kc / $gKc * 100)) : 0,
        'today_protein'  => $p,  'goal_protein'  => $gP,
        'today_fat'      => $ft, 'goal_fat'      => $gFt,
        'today_carbs'    => $cb, 'goal_carbs'    => $gCb,
        'today_min' => $mn, 'goal_min' => $gMn,
        'min_percent' => $gMn > 0 ? min(100, round($mn / $gMn * 100)) : 0,
        'today_burn' => $bu, 'goal_burn' => $gBu,
        'burn_percent' => $gBu > 0 ? min(100, round($bu / $gBu * 100)) : 0,
        'today_walk' => (int)($t['today_walk'] ?? 0),
        'today_run'  => (int)($t['today_run']  ?? 0),
        'today_yoga' => (int)($t['today_yoga'] ?? 0),
        'today_gym'  => (int)($t['today_gym']  ?? 0),
        'time' => date('h:i A'),
    ]);
    exit;
}

// Load today totals for the page
$stmt = $db->prepare('
    SELECT u.full_name, u.weight, g.daily_goal_ml, g.daily_calorie_goal, g.daily_protein_goal_g,
           g.daily_fat_goal_g, g.daily_carbs_goal_g, g.daily_exercise_goal_min,
           g.daily_burn_goal_kcal,
           u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
           COALESCE(w.ml, 0)   AS today_ml,
           COALESCE(f.kcal, 0) AS today_kcal,
           COALESCE(f.prot, 0) AS today_protein,
           COALESCE(f.fat, 0)  AS today_fat,
           COALESCE(f.carb, 0) AS today_carbs,
           COALESCE(e.min, 0)  AS today_min,
           COALESCE(e.burn, 0) AS today_burn,
           COALESCE(e.walk, 0) AS today_walk,
           COALESCE(e.run, 0)  AS today_run,
           COALESCE(e.yoga, 0) AS today_yoga,
           COALESCE(e.gym, 0)  AS today_gym
    FROM users u
    LEFT JOIN user_goals g ON g.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(amount_ml) AS ml FROM water_logs
               WHERE user_id=? AND DATE(logged_at)=CURDATE() GROUP BY user_id) w
           ON w.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(calories) AS kcal, SUM(protein_g) AS prot,
                      SUM(fat_g) AS fat, SUM(carbs_g) AS carb
               FROM food_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
               GROUP BY user_id) f ON f.user_id = u.user_id
    LEFT JOIN (SELECT user_id, SUM(duration_min) AS min, SUM(calories_burned) AS burn,
                      SUM(CASE WHEN exercise_type="Walking" THEN duration_min ELSE 0 END) AS walk,
                      SUM(CASE WHEN exercise_type="Running" THEN duration_min ELSE 0 END) AS run,
                      SUM(CASE WHEN exercise_type="Yoga" THEN duration_min ELSE 0 END) AS yoga,
                      SUM(CASE WHEN exercise_type="Gym / Strength" THEN duration_min ELSE 0 END) AS gym
               FROM exercise_logs WHERE user_id=? AND DATE(logged_at)=CURDATE()
               GROUP BY user_id) e ON e.user_id = u.user_id
    WHERE u.user_id=?
');
$stmt->execute([$userId, $userId, $userId, $userId]);
$user = $stmt->fetch();

$todayMl   = (int)($user['today_ml']        ?? 0);
$goalMl    = (int)($user['daily_goal_ml']   ?? 2500);
$percent   = $goalMl > 0 ? min(100, round($todayMl / $goalMl * 100)) : 0;
$remaining = max(0, $goalMl - $todayMl);
$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$goalLabel = number_format($goalMl / 1000, 1) . 'L';

$todayKcal = (int)($user['today_kcal']   ?? 0);
$goalKcal  = (int)($user['daily_calorie_goal'] ?? 2000);
$kcalPct   = $goalKcal > 0 ? min(100, round($todayKcal / $goalKcal * 100)) : 0;
$todayP    = round((float)($user['today_protein'] ?? 0), 1);
$goalP     = (int)($user['daily_protein_goal_g'] ?? 125);
$todayFt   = round((float)($user['today_fat'] ?? 0), 1);
$goalFt    = (int)($user['daily_fat_goal_g'] ?? 67);
$todayCb   = round((float)($user['today_carbs'] ?? 0), 1);
$goalCb    = (int)($user['daily_carbs_goal_g'] ?? 225);
$macroPct  = fn($eaten, $goal) => $goal > 0 ? min(100, round($eaten / $goal * 100)) : 0;

$todayMin  = (int)($user['today_min'] ?? 0);
$goalMin   = (int)($user['daily_exercise_goal_min'] ?? 30);
$minPct    = $goalMin > 0 ? min(100, round($todayMin / $goalMin * 100)) : 0;
$todayBurn = (int)($user['today_burn'] ?? 0);
$goalBurn  = (int)($user['daily_burn_goal_kcal'] ?? 300);
$burnPct   = $goalBurn > 0 ? min(100, round($todayBurn / $goalBurn * 100)) : 0;

$activityToday = [
    'Walking'        => (int)($user['today_walk'] ?? 0),
    'Running'        => (int)($user['today_run']  ?? 0),
    'Yoga'           => (int)($user['today_yoga'] ?? 0),
    'Gym / Strength' => (int)($user['today_gym']  ?? 0),
];

$reminderEnabled = (int)($user['reminder_enabled'] ?? 0);
$reminderTime    = $user['reminder_time'] ?? '20:00:00';
$reminderInt     = (int)($user['reminder_interval_min'] ?? 0);
$weightKg        = (float)($user['weight'] ?? 70) ?: 70; // ?: also guards against a stored weight of 0, not just a missing value

// Recent logs for today
// Note: all UNION branches share one column shape, so meal_type rides in the 'kcal'
// slot of the food branch (used as the icon key in dashboard.html).
// Added qty and unit_type for food edit functionality.
$recentQ = $db->prepare('
    SELECT * FROM (
        SELECT log_id, user_id, amount_ml AS val, drink_type AS title, NULL AS kcal, NULL AS prot,
               NULL AS fatg, NULL AS carb, 0 AS burn, NULL AS qty, NULL AS unit_type, "water" AS type, logged_at FROM water_logs
        UNION ALL
        SELECT fl.log_id, fl.user_id, fl.calories AS val, f.food_name AS title, fl.meal_type AS kcal, fl.protein_g AS prot,
               fl.fat_g AS fatg, fl.carbs_g AS carb, 0 AS burn, fl.qty, fl.unit_type, "food" AS type, fl.logged_at
        FROM food_logs fl JOIN foods f ON f.food_id = fl.food_id
        UNION ALL
        SELECT log_id, user_id, duration_min AS val, exercise_type AS title, NULL AS kcal, NULL AS prot,
               NULL AS fatg, NULL AS carb, calories_burned AS burn, NULL AS qty, NULL AS unit_type, "exercise" AS type, logged_at FROM exercise_logs
    ) merged
    WHERE user_id=? AND DATE(logged_at)=CURDATE()
    ORDER BY logged_at DESC LIMIT 8
');
$recentQ->execute([$userId]);
$recentLogs = $recentQ->fetchAll();

// Calculate water streak
// Counts back from today only — an unlogged today resets the visible streak to 0.
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
// Default foods for the quick-add picker
$foods  = $db->prepare('SELECT food_name, serving_qty, unit_type, calories FROM foods WHERE user_id IS NULL ORDER BY food_name ASC');
$foods->execute();
$foods  = $foods->fetchAll();
require __DIR__ . '/dashboard.html';
