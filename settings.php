<?php
// User settings: profile, reminders, goals
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/calculator.php';

// Require login
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$success = '';
$errors  = [];

// Handle settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name']  ?? '');
    $weight    = (float)($_POST['weight']  ?? 0);
    $height    = (float)($_POST['height']  ?? 0);
    $age       = (int)  ($_POST['age']     ?? 0);
    $gender    = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'male';
    $activity  = in_array($_POST['activity'] ?? '', ['low','medium','high']) ? $_POST['activity'] : 'medium';
    $goalMode  = ($_POST['goal_mode'] ?? 'bmi') === 'custom' ? 'custom' : 'bmi';
    $nutriMode = ($_POST['nutrition_mode'] ?? 'auto') === 'custom' ? 'custom' : 'auto';

    // Parse reminder settings
    $reminderEnabled  = isset($_POST['reminder_enabled']) ? 1 : 0;
    $reminderInterval = (int)($_POST['reminder_interval_min'] ?? 0);
    if (!in_array($reminderInterval, [0, 60, 120, 180], true)) $reminderInterval = 0;
    $reminderTime    = trim($_POST['reminder_time'] ?? '20:00');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $reminderTime)) $reminderTime = '20:00';
    $reminderTime    .= ':00';

    // Parse wake and sleep times
    $wakeTime  = trim($_POST['wake_time']  ?? '07:00');
    $sleepTime = trim($_POST['sleep_time'] ?? '22:00');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $wakeTime))  $wakeTime  = '07:00';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $sleepTime)) $sleepTime = '22:00';
    $wakeTime  .= ':00';
    $sleepTime .= ':00';

    $burnIn = (int)($_POST['burn_goal_kcal'] ?? 300);

    // Resolve daily water goal
    if ($goalMode === 'custom') {
        $goalMlIn = (int)($_POST['custom_goal_ml'] ?? $_POST['daily_goal_ml'] ?? 2500);
    } else {
        $goalMlIn = calcWaterGoal($weight, $height, $gender, $activity);
    }

    // Resolve nutrition goals
    if ($nutriMode === 'custom') {
        $calorieIn = (int)($_POST['custom_calorie_goal']  ?? 2000);
        $proteinIn = (int)($_POST['custom_protein_goal']  ?? 100);
        $fatIn     = (int)($_POST['custom_fat_goal']      ?? 65);
        $carbsIn   = (int)($_POST['custom_carbs_goal']    ?? 250);
        $exerciseIn= (int)($_POST['exercise_goal_min']    ?? 30);
    } else {
        $nutri     = calcNutritionGoals($weight, $height, $age, $gender, $activity);
        $calorieIn = $nutri['calories'];
        $proteinIn = $nutri['protein_g'];
        $fatIn     = $nutri['fat_g'];
        $carbsIn   = $nutri['carbs_g'];
        $exerciseIn= (int)($_POST['exercise_goal_min'] ?? 30);
    }

    // Validate all inputs
    if (strlen($fullName) < 2)          $errors[] = 'Name must be at least 2 characters.';
    if (preg_match('/^\d/', $fullName))  $errors[] = 'Name cannot start with a number.';
    if ($fullName !== '' && !preg_match("/^[\p{L}][\p{L}\s'\-]*$/u", $fullName))
        $errors[] = 'Name can only contain letters, spaces, apostrophes and hyphens.';
    if ($weight < 10 || $weight > 500)  $errors[] = 'Weight must be between 10 and 500 kg.';
    if ($height < 50 || $height > 300)  $errors[] = 'Height must be between 50 and 300 cm.';
    if ($age < 1    || $age > 120)      $errors[] = 'Age must be between 1 and 120.';
    if ($goalMlIn < 500 || $goalMlIn > 10000) $errors[] = 'Daily water goal must be between 500 ml and 10 L.';
    if ($calorieIn < 1200 || $calorieIn > 5000) $errors[] = 'Daily calorie goal must be between 1200 and 5000 kcal.';
    if ($proteinIn < 20  || $proteinIn > 400)   $errors[] = 'Daily protein goal must be between 20 and 400 g.';
    if ($fatIn < 20      || $fatIn > 250)       $errors[] = 'Daily fat goal must be between 20 and 250 g.';
    if ($carbsIn < 50    || $carbsIn > 800)     $errors[] = 'Daily carbs goal must be between 50 and 800 g.';
    if ($exerciseIn < 5  || $exerciseIn > 600)  $errors[] = 'Daily exercise goal must be between 5 and 600 minutes.';
    if ($burnIn < 50 || $burnIn > 2000) $errors[] = 'Calories burn goal must be between 50 and 2000 kcal.';

    // Save settings
    if (empty($errors)) {
        $db->prepare(
            'UPDATE users SET full_name=?, weight=?, height=?, age=?, gender=?,
             reminder_enabled=?, reminder_time=?, reminder_interval_min=?,
             wake_time=?, sleep_time=? WHERE user_id=?'
        )->execute([$fullName, $weight, $height, $age, $gender,
                    $reminderEnabled, $reminderTime,
                    $reminderInterval, $wakeTime, $sleepTime, $userId]);

        $db->prepare(
            'UPDATE user_goals SET daily_goal_ml=?, daily_calorie_goal=?,
             daily_protein_goal_g=?, daily_fat_goal_g=?, daily_carbs_goal_g=?,
             daily_exercise_goal_min=?, daily_burn_goal_kcal=? WHERE user_id=?'
        )->execute([$goalMlIn, $calorieIn, $proteinIn, $fatIn, $carbsIn,
                    $exerciseIn, $burnIn, $userId]);

        $_SESSION['full_name'] = $fullName;
        $success = 'Settings saved successfully!';
    }
}

// Load user data
$u = $db->prepare('SELECT u.full_name, u.email, u.age, u.weight, u.height, u.gender,
                          g.daily_goal_ml, g.daily_calorie_goal, g.daily_protein_goal_g,
                          g.daily_fat_goal_g, g.daily_carbs_goal_g, g.daily_exercise_goal_min,
                          g.daily_burn_goal_kcal,
                          u.reminder_enabled, u.reminder_time, u.reminder_interval_min,
                          u.wake_time, u.sleep_time
                          FROM users u
                          LEFT JOIN user_goals g ON g.user_id = u.user_id
                          WHERE u.user_id=?');
$u->execute([$userId]);
$user      = $u->fetch();
$fullName  = htmlspecialchars($user['full_name']  ?? '', ENT_QUOTES, 'UTF-8');
$email     = htmlspecialchars($user['email']      ?? '', ENT_QUOTES, 'UTF-8');
$age       = (int)($user['age']        ?? 0);
$weight    = (float)($user['weight']   ?? 0);
$height    = (float)($user['height']   ?? 0);
$gender    = $user['gender']           ?? 'male';
$goalMl    = (int)($user['daily_goal_ml'] ?? 2500);
$goalLabel = number_format($goalMl / 1000, 1) . 'L';

$calorieGoal = (int)($user['daily_calorie_goal']   ?? 2000);
$proteinGoal = (int)($user['daily_protein_goal_g'] ?? 125);
$fatGoal     = (int)($user['daily_fat_goal_g']     ?? 67);
$carbsGoal   = (int)($user['daily_carbs_goal_g']   ?? 225);
$exerciseMin = (int)($user['daily_exercise_goal_min'] ?? 30);
$burnGoal    = (int)($user['daily_burn_goal_kcal'] ?? 300);
$reminderOn  = (int)($user['reminder_enabled'] ?? 0);
$reminderTm  = $user['reminder_time'] ?? '20:00:00';
$reminderTm  = substr($reminderTm, 0, 5);
$reminderInt = (int)($user['reminder_interval_min'] ?? 0);
$wakeTm      = substr($user['wake_time']  ?? '07:00:00', 0, 5);
$sleepTm     = substr($user['sleep_time'] ?? '22:00:00', 0, 5);

$nutriRec = calcNutritionGoals($weight, $height, $age, $gender, 'medium');

// Calculate BMI
$bmiVal = ($height > 0) ? round($weight / (($height / 100) ** 2), 1) : 0;
if      ($bmiVal < 18.5) { $bmiCategory = 'Underweight'; $bmiColor = '#f59e0b'; }
elseif  ($bmiVal < 25.0) { $bmiCategory = 'Normal Weight'; $bmiColor = '#10b981'; }
elseif  ($bmiVal < 30.0) { $bmiCategory = 'Overweight';   $bmiColor = '#f97316'; }
elseif  ($bmiVal > 0)    { $bmiCategory = 'Obese';        $bmiColor = '#ef4444'; }
else                     { $bmiCategory = '—';           $bmiColor = '#3f4852'; }
require __DIR__ . '/settings.html';
