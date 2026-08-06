<?php
/**
 * HydroFlow — Settings Page (server-side rendered, standard POST form)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$success = '';
$errors  = [];

// ── Handle form submission ───────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name']  ?? '');
    $weight    = (float)($_POST['weight']  ?? 0);
    $height    = (float)($_POST['height']  ?? 0);
    $age       = (int)  ($_POST['age']     ?? 0);
    $gender    = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'male';
    $goalMode  = ($_POST['goal_mode'] ?? 'bmi') === 'custom' ? 'custom' : 'bmi';

    // ── BMI-based goal calculation (server-side mirror of the JS calculator) ────────
    if ($goalMode === 'custom') {
        // User typed their own goal
        $goalMlIn = (int)($_POST['custom_goal_ml'] ?? 2500);
    } else {
        // Calculate from BMI + gender + activity
        $heightM  = $height / 100;
        $bmi      = ($heightM > 0) ? $weight / ($heightM ** 2) : 22.0;
        if      ($bmi < 18.5) $bmiMult = 40;  // Underweight — needs more
        elseif  ($bmi < 25.0) $bmiMult = 35;  // Normal weight — standard
        elseif  ($bmi < 30.0) $bmiMult = 30;  // Overweight
        else                  $bmiMult = 25;  // Obese — use lean-adjusted

        $goalMlIn = (int)round($weight * $bmiMult);
        if ($gender === 'female') $goalMlIn = (int)round($goalMlIn * 0.9); // women need ~10% less

        $activityBonus = match($_POST['activity'] ?? 'medium') {
            'low'  => 0,
            'high' => 1000,
            default => 500, // medium
        };
        $goalMlIn = max(1500, min(5000, $goalMlIn + $activityBonus));
    }

    // ── Validation ─────────────────────────────────────────────────────────────────────────
    if (strlen($fullName) < 2)          $errors[] = 'Name must be at least 2 characters.';
    if (preg_match('/^\d/', $fullName))  $errors[] = 'Name cannot start with a number.';
    if ($fullName !== '' && !preg_match("/^[\p{L}][\p{L}\s'\-]*$/u", $fullName))
        $errors[] = 'Name can only contain letters, spaces, apostrophes and hyphens.';
    if ($weight < 10 || $weight > 500)  $errors[] = 'Weight must be between 10 and 500 kg.';
    if ($height < 50 || $height > 300)  $errors[] = 'Height must be between 50 and 300 cm.';
    if ($age < 1    || $age > 120)      $errors[] = 'Age must be between 1 and 120.';
    if ($goalMlIn < 500 || $goalMlIn > 10000) $errors[] = 'Daily goal must be between 500 ml and 10 L.';

    if (empty($errors)) {
        $db->prepare(
            'UPDATE users SET full_name=?, weight=?, height=?, age=?, gender=?, daily_goal_ml=? WHERE user_id=?'
        )->execute([$fullName, $weight, $height, $age, $gender, $goalMlIn, $userId]);

        $_SESSION['full_name'] = $fullName;
        $success = 'Settings saved successfully!';
    }
}

// ── Load current user data ───────────────────────────────────────────────────────────────────────────
$u = $db->prepare('SELECT full_name, email, age, weight, height, gender, daily_goal_ml FROM users WHERE user_id=?');
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

// Pre-compute BMI for display
$bmiVal = ($height > 0) ? round($weight / (($height / 100) ** 2), 1) : 0;
if      ($bmiVal < 18.5) { $bmiCategory = 'Underweight'; $bmiColor = '#f59e0b'; }
elseif  ($bmiVal < 25.0) { $bmiCategory = 'Normal Weight'; $bmiColor = '#10b981'; }
elseif  ($bmiVal < 30.0) { $bmiCategory = 'Overweight';   $bmiColor = '#f97316'; }
elseif  ($bmiVal > 0)    { $bmiCategory = 'Obese';        $bmiColor = '#ef4444'; }
else                     { $bmiCategory = '—';           $bmiColor = 'var(--color-on-surface-variant)'; }
require __DIR__ . '/settings.html';
