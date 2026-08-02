<?php
/**
 * HydroFlow — Email Mailer Helper
 *
 * Sends emails using SMTP via PHPMailer (if available) or PHP's mail().
 * For local XAMPP development, configure your mail settings below.
 *
 * OPTION 1 — Gmail SMTP (recommended for real email delivery):
 *   1. Enable 2-Step Verification on your Google account
 *   2. Create an App Password at: https://myaccount.google.com/apppasswords
 *   3. Fill in MAIL_HOST, MAIL_USER, MAIL_PASS below
 *
 * OPTION 2 — Local testing with Mailtrap:
 *   1. Sign up at https://mailtrap.io (free)
 *   2. Go to Email Testing → Inboxes → SMTP Settings
 *   3. Copy credentials below
 *
 * OPTION 3 — MailHog (local dev, no account needed):
 *   1. Install MailHog (https://github.com/mailhog/MailHog)
 *   2. Set MAIL_HOST=localhost, MAIL_PORT=1025, MAIL_ENCRYPTION=''
 */

// ── Mail Configuration ────────────────────────────────────────────────────────

// Set to true to use SMTP, false to use PHP mail() function
define('MAIL_USE_SMTP', true);

// SMTP server settings
define('MAIL_HOST',       'smtp.gmail.com');  // Gmail | 'smtp.mailtrap.io' | 'localhost'
define('MAIL_PORT',       587);               // 587 (TLS) | 465 (SSL) | 1025 (MailHog)
define('MAIL_ENCRYPTION', 'tls');             // 'tls' | 'ssl' | '' (none)
define('MAIL_USER',       '');                // your Gmail/SMTP username
define('MAIL_PASS',       '');                // your App Password or SMTP password

// "From" address shown in the email
define('MAIL_FROM_ADDR', 'noreply@hydroflow.com');
define('MAIL_FROM_NAME', 'HydroFlow');

// OTP settings
define('OTP_EXPIRY_MINUTES', 15);   // OTP valid for 15 minutes
define('OTP_LENGTH', 6);            // 6-digit code


/**
 * Send a plain-text + HTML email.
 *
 * @param string $toAddr   Recipient email
 * @param string $toName   Recipient name
 * @param string $subject  Email subject
 * @param string $htmlBody HTML body
 * @param string $textBody Plain-text fallback
 * @return bool            true on success
 */
function sendMail(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    if (!$textBody) {
        $textBody = strip_tags($htmlBody);
    }

    if (MAIL_USE_SMTP && !empty(MAIL_USER) && !empty(MAIL_PASS)) {
        return sendMailSmtp($toAddr, $toName, $subject, $htmlBody, $textBody);
    }

    // Fallback: PHP built-in mail() — works if server has sendmail configured
    return sendMailPhp($toAddr, $toName, $subject, $htmlBody, $textBody);
}

/**
 * Send via SMTP using a raw socket (no Composer/PHPMailer required).
 */
function sendMailSmtp(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    $host       = MAIL_HOST;
    $port       = MAIL_PORT;
    $encryption = MAIL_ENCRYPTION;
    $user       = MAIL_USER;
    $pass       = MAIL_PASS;

    try {
        // Build raw MIME message
        $boundary = 'boundary_' . md5(uniqid());
        $headers  = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">",
            "To: $toName <$toAddr>",
            "Subject: $subject",
            "X-Mailer: HydroFlow/1.0",
        ]);
        $body = "--$boundary\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n\r\n$textBody\r\n"
              . "--$boundary\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n\r\n$htmlBody\r\n"
              . "--$boundary--";

        // Open socket
        $context = stream_context_create();
        if ($encryption === 'ssl') {
            $sock = stream_socket_client("ssl://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        } else {
            $sock = stream_socket_client("tcp://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        }
        if (!$sock) return false;

        $read = function() use ($sock) { return fgets($sock, 4096); };
        $send = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

        $read(); // 220 greeting
        $send("EHLO " . (gethostname() ?: 'localhost'));
        $ehloResp = '';
        while (($line = fgets($sock, 4096)) !== false) {
            $ehloResp .= $line;
            if (substr($line, 3, 1) === ' ') break; // last line
        }

        // Upgrade to TLS if needed
        if ($encryption === 'tls') {
            $send("STARTTLS");
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . (gethostname() ?: 'localhost'));
            while (($line = fgets($sock, 4096)) !== false) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }

        // Auth
        $send("AUTH LOGIN");
        $read();
        $send(base64_encode($user));
        $read();
        $send(base64_encode($pass));
        $authResp = $read();
        if (strpos($authResp, '235') === false) { fclose($sock); return false; }

        $send("MAIL FROM:<" . MAIL_FROM_ADDR . ">");
        $read();
        $send("RCPT TO:<$toAddr>");
        $read();
        $send("DATA");
        $read();
        $send("$headers\r\n\r\n$body\r\n.");
        $dataResp = $read();
        $send("QUIT");
        fclose($sock);

        return strpos($dataResp, '250') !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Send via PHP's built-in mail() function.
 */
function sendMailPhp(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    $boundary = 'boundary_' . md5(uniqid());
    $headers  = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">",
        "X-Mailer: HydroFlow/1.0",
    ]);
    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n$textBody\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n$htmlBody\r\n"
          . "--$boundary--";

    return mail("$toName <$toAddr>", $subject, $body, $headers);
}


/**
 * Generate a cryptographically random OTP code.
 */
function generateOtp(): string {
    $length = defined('OTP_LENGTH') ? OTP_LENGTH : 6;
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= random_int(0, 9);
    }
    return $digits;
}


/**
 * Save OTP to DB for a user (invalidates any previous OTPs).
 */
function saveOtp(PDO $db, int $userId, string $otp): void {
    $expiry = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 15;

    // Invalidate old OTPs
    $db->prepare('UPDATE email_otps SET used = 1 WHERE user_id = :uid AND used = 0')
       ->execute([':uid' => $userId]);

    // Insert new OTP
    $db->prepare('INSERT INTO email_otps (user_id, otp_code, expires_at) VALUES (:uid, :otp, :exp)')
       ->execute([
           ':uid' => $userId,
           ':otp' => $otp,
           ':exp' => date('Y-m-d H:i:s', strtotime("+{$expiry} minutes")),
       ]);
}


/**
 * Build and send the OTP verification email to a new user.
 */
function sendOtpEmail(string $toAddr, string $toName, string $otp): bool {
    $expiry  = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 15;
    $subject = 'Your HydroFlow Verification Code';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 0; }
  .wrap { max-width: 480px; margin: 40px auto; background: #161b22; border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #0077ff, #00c6ff); padding: 32px; text-align: center; }
  .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
  .body { padding: 32px; }
  .otp-box { background: #0d1117; border: 2px dashed #0077ff; border-radius: 12px; text-align: center; padding: 24px 0; margin: 24px 0; }
  .otp { font-size: 42px; font-weight: 800; letter-spacing: 12px; color: #00c6ff; font-family: 'Courier New', monospace; }
  .note { font-size: 13px; color: #8b949e; text-align: center; margin-top: 8px; }
  .footer { text-align: center; padding: 16px; font-size: 12px; color: #484f58; border-top: 1px solid #21262d; }
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>💧 HydroFlow — Verify Your Email</h1></div>
  <div class="body">
    <p>Hi <strong>{$toName}</strong>,</p>
    <p>Thanks for signing up! Enter the code below to activate your account:</p>
    <div class="otp-box">
      <div class="otp">{$otp}</div>
      <div class="note">Expires in {$expiry} minutes</div>
    </div>
    <p>If you didn't create a HydroFlow account, you can safely ignore this email.</p>
  </div>
  <div class="footer">HydroFlow · This is an automated message, please do not reply.</div>
</div>
</body>
</html>
HTML;

    $text = "Hi {$toName},\n\nYour HydroFlow verification code is: {$otp}\n\nIt expires in {$expiry} minutes.\n\nIf you didn't sign up, ignore this email.";

    return sendMail($toAddr, $toName, $subject, $html, $text);
}
