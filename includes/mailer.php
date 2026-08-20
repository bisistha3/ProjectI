<?php
// Email sending (SMTP or mail()) plus OTP generation and delivery.
if (!defined('MAIL_HOST')) {
    require_once __DIR__ . '/config.php';
}

// Send HTML + plain-text email, choosing SMTP or mail() fallback.
function sendMail(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    if (!$textBody) {
        $textBody = strip_tags($htmlBody);
    }

    if (MAIL_USE_SMTP && !empty(MAIL_USER) && !empty(MAIL_PASS)) {
        return sendMailSmtp($toAddr, $toName, $subject, $htmlBody, $textBody);
    }

    if (empty(MAIL_USER) || empty(MAIL_PASS)) {
        return false;
    }

    return sendMailPhp($toAddr, $toName, $subject, $htmlBody, $textBody);
}

// Send via direct SMTP conversation (Gmail-style credentials).
function sendMailSmtp(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    $host       = MAIL_HOST;
    $port       = MAIL_PORT;
    $encryption = MAIL_ENCRYPTION;
    $user       = MAIL_USER;
    $pass       = MAIL_PASS;

    try {
        $boundary = 'boundary_' . md5(uniqid());
        $headers  = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">",
            "To: $toName <$toAddr>",
            "Subject: $subject",
            "X-Mailer: HealthFlow/1.0",
        ]);
        $body = "--$boundary\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n\r\n$textBody\r\n"
              . "--$boundary\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n\r\n$htmlBody\r\n"
              . "--$boundary--";

        $context = stream_context_create();
        if ($encryption === 'ssl') {
            $sock = stream_socket_client("ssl://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        } else {
            $sock = stream_socket_client("tcp://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        }
        if (!$sock) return false;

        $read = function() use ($sock) { return fgets($sock, 4096); };
        $send = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

        $read();
        $send("EHLO " . (gethostname() ?: 'localhost'));
        while (($line = fgets($sock, 4096)) !== false) {
            if (substr($line, 3, 1) === ' ') break;
        }

        if ($encryption === 'tls') {
            $send("STARTTLS");
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . (gethostname() ?: 'localhost'));
            while (($line = fgets($sock, 4096)) !== false) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }

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

// Send via PHP mail() fallback.
function sendMailPhp(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    $boundary = 'boundary_' . md5(uniqid());
    $headers  = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">",
        "X-Mailer: HealthFlow/1.0",
    ]);
    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n$textBody\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n$htmlBody\r\n"
          . "--$boundary--";

    return mail("$toName <$toAddr>", $subject, $body, $headers);
}

// Generate a random numeric OTP.
function generateOtp(): string {
    $length = defined('OTP_LENGTH') ? OTP_LENGTH : 6;
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= random_int(0, 9);
    }
    return $digits;
}

// Invalidate old codes and store the new OTP with expiry.
function saveOtp(PDO $db, int $userId, string $otp): void {
    $expiry = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 15;

    $db->prepare('UPDATE email_otps SET used = 1 WHERE user_id = :uid AND used = 0')
       ->execute([':uid' => $userId]);

    $db->prepare("INSERT INTO email_otps (user_id, otp_code, expires_at)
                  VALUES (:uid, :otp, NOW() + INTERVAL {$expiry} MINUTE)")
       ->execute([
           ':uid' => $userId,
           ':otp' => $otp,
       ]);
}

// Send verification email with HTML template.
function sendOtpEmail(string $toAddr, string $toName, string $otp): bool {
    $expiry  = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 15;
    $subject = 'Your HealthFlow Verification Code';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 0; }
  .wrap { max-width: 480px; margin: 40px auto; background: #161b22; border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #00696d, #00b8bd); padding: 32px; text-align: center; }
  .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
  .body { padding: 32px; }
  .otp-box { background: #0d1117; border: 2px dashed #00a7ad; border-radius: 12px; text-align: center; padding: 24px 0; margin: 24px 0; }
  .otp { font-size: 42px; font-weight: 800; letter-spacing: 12px; color: #00b8bd; font-family: 'Courier New', monospace; }
  .note { font-size: 13px; color: #8b949e; text-align: center; margin-top: 8px; }
  .footer { text-align: center; padding: 16px; font-size: 12px; color: #484f58; border-top: 1px solid #21262d; }
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>❤️ HealthFlow — Verify Your Email</h1></div>
  <div class="body">
    <p>Hi <strong>{$toName}</strong>,</p>
    <p>Thanks for signing up! Enter the code below to activate your account:</p>
    <div class="otp-box">
      <div class="otp">{$otp}</div>
      <div class="note">Expires in {$expiry} minutes</div>
    </div>
    <p>If you didn't create a HealthFlow account, you can safely ignore this email.</p>
  </div>
  <div class="footer">HealthFlow · This is an automated message, please do not reply.</div>
</div>
</body>
</html>
HTML;

    $text = "Hi {$toName},\n\nYour HealthFlow verification code is: {$otp}\n\nIt expires in {$expiry} minutes.\n\nIf you didn't sign up, ignore this email.";

    return sendMail($toAddr, $toName, $subject, $html, $text);
}
