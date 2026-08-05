<?php
/**
 * HydroFlow â€” Register Handler
 * Handles both GET (show form) and POST (create account + send OTP).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/validate.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect if already logged in
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

    // Preserve old values for repopulating form on error
    $old = [
        'name'   => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'email'  => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
        'gender' => $gender === 'female' ? 'female' : 'male',
        'age'    => htmlspecialchars($age, ENT_QUOTES, 'UTF-8'),
        'weight' => htmlspecialchars($weight, ENT_QUOTES, 'UTF-8'),
        'height' => htmlspecialchars($height, ENT_QUOTES, 'UTF-8'),
    ];

    // Validate all fields
    $v = new Validator();
    $v->required('name', $name, 'Full Name')
      ->minLength('name', $name, 2, 'Full Name')
      ->maxLength('name', $name, 100, 'Full Name');

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
                    // Fully verified account already exists
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    // Unverified account â€” resend a fresh OTP
                    $userId = (int)$existing['user_id'];
                    $otp    = generateOtp();
                    saveOtp($db, $userId, $otp);
                    $sent   = sendOtpEmail(trim($email), trim($name), $otp);

                    $_SESSION['pending_verify_user_id'] = $userId;
                    $_SESSION['pending_verify_email']   = trim($email);

                    // ┌─────────────────────────────────────────────────────┐
                    // │  DEV MODE — REMOVE THIS LINE IN PRODUCTION           │
                    // │  Fill MAIL_USER + MAIL_PASS in config.php to        │
                    // │  send real emails; $sent will be true so this        │
                    // │  line will never run. Delete it for cleanliness.     │
                    // └─────────────────────────────────────────────────────┘
                    if (!$sent) {
                        $_SESSION['dev_otp_fallback'] = $otp; // DEV MODE — remove in production
                    }

                    setFlash('info', 'A new verification code has been sent to your email.');
                    session_write_close();
                    header('Location: verify-email.php');
                    exit;
                }
            } else {
                // New account — insert as UNVERIFIED

                // ── Calculate BMI-based daily goal at registration ──────────────────────
                // Formula: weight × BMI-multiplier, gender-adjusted, medium activity assumed
                $regHeightM  = (float)$height / 100;
                $regBmi      = ($regHeightM > 0) ? (float)$weight / ($regHeightM ** 2) : 22.0;
                if      ($regBmi < 18.5) $regMult = 40;  // Underweight
                elseif  ($regBmi < 25.0) $regMult = 35;  // Normal weight
                elseif  ($regBmi < 30.0) $regMult = 30;  // Overweight
                else                    $regMult = 25;  // Obese

                $regGoal = (int)round((float)$weight * $regMult);
                if ($gender === 'female') $regGoal = (int)round($regGoal * 0.9); // women -10%
                $regGoal = max(1500, min(5000, $regGoal + 500)); // +500 ml medium activity default

                $stmt = $db->prepare('
                    INSERT INTO users (full_name, email, password, gender, age, weight, height, daily_goal_ml, is_verified)
                    VALUES (:name, :email, :password, :gender, :age, :weight, :height, :goal, 0)
                ');
                $stmt->execute([
                    ':name'     => trim($name),
                    ':email'    => trim($email),
                    ':password' => $password,
                    ':gender'   => $gender,
                    ':age'      => (int)$age,
                    ':weight'   => (float)$weight,
                    ':height'   => (float)$height,
                    ':goal'     => $regGoal,
                ]);

                $userId = (int)$db->lastInsertId();

                // Generate OTP, save to DB, send email
                $otp  = generateOtp();
                saveOtp($db, $userId, $otp);
                $sent = sendOtpEmail(trim($email), trim($name), $otp);

                // Store pending verification in session
                $_SESSION['pending_verify_user_id'] = $userId;
                $_SESSION['pending_verify_email']   = trim($email);

                // ┌─────────────────────────────────────────────────────────┐
                // │  DEV MODE — REMOVE THIS BLOCK IN PRODUCTION             │
                // │                                                         │
                // │  If no SMTP credentials are set, $sent = false and     │
                // │  the OTP is stored in session so verify-email.php       │
                // │  can display it on-screen in the yellow dev box.        │
                // │                                                         │
                // │  TO DISABLE: Fill MAIL_USER + MAIL_PASS in config.php. │
                // │  $sent will then be true, this block won't execute,     │
                // │  and the OTP will only arrive via real email.           │
                // └─────────────────────────────────────────────────────────┘
                if (!$sent) {
                    $_SESSION['dev_otp_fallback'] = $otp; // DEV MODE — remove in production
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
