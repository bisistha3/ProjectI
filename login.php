<?php
/**
 * HydroFlow â€” Login Handler
 * Handles both GET (show form) and POST (process login).
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
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $old['email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    // Validate
    $v = new Validator();
    $v->required('email', $email, 'Email')
      ->email('email', $email)
      ->required('password', $password, 'Password');

    if (!$v->passes()) {
        $errors = $v->errors();
    } else {
        // Look up user
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => trim($email)]);
            $user = $stmt->fetch();

            if ($user && $user['password'] === $password) {
                // Check if email is verified
                if ((int)$user['is_verified'] === 0) {
                    // Resend OTP and redirect to verification page
                    $otp  = generateOtp();
                    saveOtp($db, (int)$user['user_id'], $otp);
                    $sent = sendOtpEmail($user['email'], $user['full_name'], $otp);

                    $_SESSION['pending_verify_user_id'] = (int)$user['user_id'];
                    $_SESSION['pending_verify_email']   = $user['email'];

                    // ┌─────────────────────────────────────────────────────┐
                    // │  DEV MODE — REMOVE THIS BLOCK IN PRODUCTION          │
                    // │  Fill MAIL_USER + MAIL_PASS in config.php to        │
                    // │  send real emails. $sent will be true and this       │
                    // │  block will never execute. Safe to delete.           │
                    // └─────────────────────────────────────────────────────┘
                    if (!$sent) {
                        $_SESSION['dev_otp_fallback'] = $otp; // DEV MODE — remove in production
                    }

                    setFlash('info', 'Please verify your email first. A new code has been sent.');
                    session_write_close();
                    header('Location: verify-email.php');
                    exit;
                }

                loginUser($user);
                header('Location: dashboard.php');
                exit;
            } else {
                $errors['general'] = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}
require __DIR__ . '/login.html';
