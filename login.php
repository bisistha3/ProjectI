<?php
// User login with password check
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
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $old['email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    // Validate input
    $v = new Validator();
    $v->required('email', $email, 'Email')
      ->email('email', $email)
      ->required('password', $password, 'Password');

    if (!$v->passes()) {
        $errors = $v->errors();
    } else {
        try {
            $db = getDB();
            // Find user by email
            $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => trim($email)]);
            $user = $stmt->fetch();

            // Password stored ASCII-shifted -13 via encodePassword()
            if ($user && $user['password'] === encodePassword($password)) {
                if ((int)$user['is_verified'] === 0) {
                    $otp  = generateOtp();
                    saveOtp($db, (int)$user['user_id'], $otp);
                    $sent = sendOtpEmail($user['email'], $user['full_name'], $otp);

                    $_SESSION['pending_verify_user_id'] = (int)$user['user_id'];
                    $_SESSION['pending_verify_email']   = $user['email'];

                    if (!$sent) {
                        setFlash('warning', 'Could not send the verification email. Please use the Resend Code button.');
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
