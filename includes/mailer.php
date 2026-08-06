<?php
/**
 * HydroFlow — Email & OTP Helper
 * Requires config.php to be loaded first (handled automatically below).
 */
if (!defined('MAIL_HOST')) {
    require_once __DIR__ . '/config.php';
}


/**
 * Send a plain-text + HTML email.
 *
 * Returns true  → email was sent successfully via SMTP.
 * Returns false → credentials not configured (dev mode) OR SMTP failed.
 *
 * @param string $toAddr   Recipient email
 * @param string $toName   Recipient name
 * @param string $subject  Email subject
 * @param string $htmlBody HTML body
 * @param string $textBody Plain-text fallback (auto-generated if omitted)
 * @return bool
 */
function sendMail(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    if (!$textBody) {
        $textBody = strip_tags($htmlBody);
    }

    // ── PRODUCTION MODE ───────────────────────────────────────────────────
    // When MAIL_USER and MAIL_PASS are filled in config.php, all OTPs are
    // sent as real emails via SMTP and never shown on screen.
    if (MAIL_USE_SMTP && !empty(MAIL_USER) && !empty(MAIL_PASS)) {
        return sendMailSmtp($toAddr, $toName, $subject, $htmlBody, $textBody);
    }

    // No SMTP credentials configured (or SMTP unavailable) → return false.
    // Callers surface a warning and keep the OTP stored in the database.
    if (empty(MAIL_USER) || empty(MAIL_PASS)) {
        return false;
    }

    // Fallback: PHP's built-in mail() — only runs when credentials ARE set
    // but MAIL_USE_SMTP is false. Not recommended on XAMPP/WAMP.
    return sendMailPhp($toAddr, $toName, $subject, $htmlBody, $textBody);
}


/**
 * Send via SMTP using a raw socket (no Composer / PHPMailer required).
 * Used automatically when MAIL_USER and MAIL_PASS are configured.
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
        while (($line = fgets($sock, 4096)) !== false) {
            if (substr($line, 3, 1) === ' ') break; // last EHLO response line
        }

        // Upgrade to TLS if needed (STARTTLS — used by Gmail port 587)
        if ($encryption === 'tls') {
            $send("STARTTLS");
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . (gethostname() ?: 'localhost'));
            while (($line = fgets($sock, 4096)) !== false) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }

        // Authenticate
        $send("AUTH LOGIN");
        $read();
        $send(base64_encode($user));
        $read();
        $send(base64_encode($pass));
        $authResp = $read();
        if (strpos($authResp, '235') === false) { fclose($sock); return false; }

        // Send message
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
 * NOTE: This does NOT work reliably on XAMPP/WAMP without a local mail server.
 *       Use SMTP (above) for reliable delivery.
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
 * Generate a cryptographically random 6-digit OTP code.
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
 * Save a new OTP to the database for a user.
 * Automatically invalidates any previous unused OTPs for that user.
 */
function saveOtp(PDO $db, int $userId, string $otp): void {
    $expiry = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 15;

    // Invalidate all previous unused OTPs for this user
    $db->prepare('UPDATE email_otps SET used = 1 WHERE user_id = :uid AND used = 0')
       ->execute([':uid' => $userId]);

    // Insert the new OTP — use MySQL's NOW() to avoid PHP/MySQL timezone mismatch.
    // If PHP's date() timezone differs from MySQL's, expires_at would be wrong and
    // every OTP would appear expired when verified with NOW().
    $db->prepare("INSERT INTO email_otps (user_id, otp_code, expires_at)
                  VALUES (:uid, :otp, NOW() + INTERVAL {$expiry} MINUTE)")
       ->execute([
           ':uid' => $userId,
           ':otp' => $otp,
       ]);
}


/**
 * Build and send the OTP verification email.
 *
 * In PRODUCTION (MAIL_USER + MAIL_PASS configured):
 *   → Sends a styled HTML email with the 6-digit code.
 *
 * In DEV MODE (credentials empty):
 *   → Returns false. Callers store the OTP in $_SESSION['dev_otp_fallback']
 *     so it can be displayed on the verify-email page.
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
