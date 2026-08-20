<?php
// Email verification with OTP entry and resend
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

// Require pending verification
if (empty($_SESSION['pending_verify_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId       = (int)$_SESSION['pending_verify_user_id'];
$pendingEmail = $_SESSION['pending_verify_email'] ?? '';

$error   = '';
$info    = getFlash('info', '');
$warning = getFlash('warning', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';

    // Resend OTP
    if ($action === 'resend') {
        try {
            $db = getDB();
            $u = $db->prepare('SELECT full_name, email FROM users WHERE user_id = :id');
            $u->execute([':id' => $userId]);
            $user = $u->fetch();

            if ($user) {
                $otp  = generateOtp();
                saveOtp($db, $userId, $otp);
                $sent = sendOtpEmail($user['email'], $user['full_name'], $otp);
                if ($sent) {
                    $info = 'A new verification code has been sent to your email.';
                } else {
                    $warning = 'Could not send the email. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }

    // Verify submitted OTP
    if ($action === 'verify') {
        $digits = [];
        for ($i = 1; $i <= 6; $i++) {
            $digits[] = preg_replace('/\D/', '', $_POST["digit_$i"] ?? '');
        }
        $submitted = implode('', $digits);

        if (strlen($submitted) !== 6 || !ctype_digit($submitted)) {
            $error = 'Please enter all 6 digits of your verification code.';
        } else {
            try {
                $db = getDB();

                $stmt = $db->prepare('
                    SELECT id FROM email_otps
                    WHERE user_id = :uid
                      AND otp_code = :otp
                      AND used = 0
                      AND expires_at > NOW()
                    ORDER BY id DESC
                    LIMIT 1
                ');
                $stmt->execute([':uid' => $userId, ':otp' => $submitted]);
                $row = $stmt->fetch();

                if (!$row) {
                    $error = 'Invalid or expired code. Please try again or request a new one.';
                } else {
                    $db->prepare('UPDATE email_otps SET used = 1 WHERE id = :id')
                       ->execute([':id' => $row['id']]);

                    $db->prepare('UPDATE users SET is_verified = 1 WHERE user_id = :uid')
                       ->execute([':uid' => $userId]);

                    unset(
                        $_SESSION['pending_verify_user_id'],
                        $_SESSION['pending_verify_email']
                    );

                    setFlash('success', 'Email verified! Your account is now active. Please sign in.');
                    header('Location: login.php');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

// Mask email for display
function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email, 2) + ['', ''];
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}
require __DIR__ . '/verify-email.html';
