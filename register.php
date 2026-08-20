<?php
// User registration with email verification
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/validate.php';
require_once __DIR__ . '/includes/calculator.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect logged-in users
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = [
    'name'   => '',
    'email'  => '',
    'gender' => 'male',
    'age'    => '',
    'weight' => '',
    'height' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = $_POST['name'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $gender   = $_POST['gender'] ?? 'male';
    $age      = $_POST['age'] ?? '';
    $weight   = $_POST['weight'] ?? '';
    $height   = $_POST['height'] ?? '';

    $old = [
        'name'   => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'email'  => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
        'gender' => $gender === 'female' ? 'female' : 'male',
        'age'    => htmlspecialchars($age, ENT_QUOTES, 'UTF-8'),
        'weight' => htmlspecialchars($weight, ENT_QUOTES, 'UTF-8'),
        'height' => htmlspecialchars($height, ENT_QUOTES, 'UTF-8'),
    ];

    // Validate input
    $v = new Validator();
    $v->required('name', $name, 'Full Name')
      ->minLength('name', $name, 2, 'Full Name')
      ->maxLength('name', $name, 100, 'Full Name')
      ->name('name', $name, 'Full Name');

    $v->required('email', $email, 'Email')
      ->email('email', $email)
      ->emailDomain('email', $email);

    $v->required('password', $password, 'Password')
      ->password('password', $password);

    $v->inList('gender', $gender, ['male', 'female'], 'Gender');

    $v->required('age', $age, 'Age')
      ->numericRange('age', $age, 1, 120, 'Age');

    $v->required('weight', $weight, 'Weight')
      ->numericRange('weight', $weight, 1, 300, 'Weight');

    $v->required('height', $height, 'Height')
      ->numericRange('height', $height, 50, 250, 'Height');

    if (!$v->passes()) {
        $errors = $v->errors();
    } else {
        try {
            $db = getDB();

            // Check if email already exists
            $check = $db->prepare('SELECT user_id, is_verified FROM users WHERE email = :email');
            $check->execute([':email' => trim($email)]);
            $existing = $check->fetch();

            if ($existing) {
                if ((int)$existing['is_verified'] === 1) {
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    // Resend OTP for unverified account
                    $userId = (int)$existing['user_id'];
                    $otp    = generateOtp();
                    saveOtp($db, $userId, $otp);
                    $sent   = sendOtpEmail(trim($email), trim($name), $otp);

                    $_SESSION['pending_verify_user_id'] = $userId;
                    $_SESSION['pending_verify_email']   = trim($email);

                    if (!$sent) {
                        setFlash('warning', 'Could not send the verification email. Please try the Resend Code button.');
                    }

                    setFlash('info', 'A new verification code has been sent to your email.');
                    session_write_close();
                    header('Location: verify-email.php');
                    exit;
                }
            } else {
                // Calculate default goals
                $regGoal   = calcWaterGoal((float)$weight, (float)$height, $gender, 'medium');
                $nutri     = calcNutritionGoals((float)$weight, (float)$height, (int)$age, $gender, 'medium');

                // Insert new user
                $stmt = $db->prepare('
                    INSERT INTO users (full_name, email, password, gender, age, weight, height,
                                       is_verified)
                    VALUES (:name, :email, :password, :gender, :age, :weight, :height, 0)
                ');
                $stmt->execute([
                    ':name'     => trim($name),
                    ':email'    => trim($email),
                    ':password' => encodePassword($password),
                    ':gender'   => $gender,
                    ':age'      => (int)$age,
                    ':weight'   => (float)$weight,
                    ':height'   => (float)$height,
                ]);

                $userId = (int)$db->lastInsertId();

                // Insert calculated daily goals
                $db->prepare('
                    INSERT INTO user_goals (user_id, daily_goal_ml, daily_calorie_goal,
                                            daily_protein_goal_g, daily_fat_goal_g,
                                            daily_carbs_goal_g, daily_exercise_goal_min,
                                            daily_burn_goal_kcal)
                    VALUES (?, ?, ?, ?, ?, ?, 30, 300)
                ')->execute([
                    $userId,
                    $regGoal,
                    $nutri['calories'],
                    $nutri['protein_g'],
                    $nutri['fat_g'],
                    $nutri['carbs_g'],
                ]);

                // Send verification email
                $otp  = generateOtp();
                saveOtp($db, $userId, $otp);
                $sent = sendOtpEmail(trim($email), trim($name), $otp);

                $_SESSION['pending_verify_user_id'] = $userId;
                $_SESSION['pending_verify_email']   = trim($email);

                if (!$sent) {
                    setFlash('warning', 'Could not send the verification email. Please use the Resend Code button on the next page.');
                }

                session_write_close();
                header('Location: verify-email.php');
                exit;
            }
        } catch (Exception $e) {
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}
require __DIR__ . '/register.html';
