<?php
// Forgot password — request a reset code via email (OTP).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/validate.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect logged-in users
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$info = getFlash('info', '');
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $old['email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    $v = new Validator();
    $v->required('email', $email, 'Email')
      ->email('email', $email);

    if (!$v->passes()) {
        $errors = $v->errors();
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT user_id, full_name, email, is_verified FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => trim($email)]);
            $user = $stmt->fetch();

            if ($user) {
                $otp  = generateOtp();
                saveOtp($db, (int)$user['user_id'], $otp);
                $sent = sendOtpEmail($user['email'], $user['full_name'], $otp);

                $_SESSION['pwd_reset_user_id'] = (int)$user['user_id'];
                $_SESSION['pwd_reset_email']   = $user['email'];

                if (!$sent) {
                    setFlash('warning', 'Could not send the email. Please use the Resend Code button on the next page.');
                }

                session_write_close();
                header('Location: reset-password.php');
                exit;
            }

            // Generic response — do not reveal whether the email exists.
            $info = 'If an account exists for that email, a reset code has been sent.';
        } catch (Exception $e) {
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}
require __DIR__ . '/forgot-password.html';
