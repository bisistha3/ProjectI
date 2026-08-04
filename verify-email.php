<?php
/**
 * HydroFlow — Email OTP Verification Page
 * User must enter the 6-digit code sent to their email to activate their account.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

// Must have a pending verification in session
if (empty($_SESSION['pending_verify_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId       = (int)$_SESSION['pending_verify_user_id'];
$pendingEmail = $_SESSION['pending_verify_email'] ?? '';

// ┌───────────────────────────────────────────────────────────────────────┐
// │  DEV MODE — REMOVE THIS LINE IN PRODUCTION                            │
// │  Reads the OTP from session to display it on-screen.                  │
// │  When MAIL_USER + MAIL_PASS are configured, this will always be null  │
// │  because dev_otp_fallback is never set. Safe to delete.               │
// └───────────────────────────────────────────────────────────────────────┘
$devOtp = $_SESSION['dev_otp_fallback'] ?? null; // DEV MODE — remove in production

$error   = '';
$info    = getFlash('info', '');
$warning = getFlash('warning', '');

// ── Handle form submissions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';

    // ── Resend OTP ────────────────────────────────────────────────────────────
    if ($action === 'resend') {
        try {
            $db = getDB();
            // Fetch user name for the email
            $u = $db->prepare('SELECT full_name, email FROM users WHERE user_id = :id');
            $u->execute([':id' => $userId]);
            $user = $u->fetch();

            if ($user) {
                $otp  = generateOtp();
                saveOtp($db, $userId, $otp);
                $sent = sendOtpEmail($user['email'], $user['full_name'], $otp);
                if ($sent) {
                    // ┌─────────────────────────────────────────────────────┐
                    // │  DEV MODE — REMOVE THESE 2 LINES IN PRODUCTION      │
                    // │  They clear the on-screen OTP after a successful    │
                    // │  resend. Not needed once real email is working.     │
                    // └─────────────────────────────────────────────────────┘
                    unset($_SESSION['dev_otp_fallback']); // DEV MODE — remove in production
                    $devOtp = null;                       // DEV MODE — remove in production
                    $info   = 'A new verification code has been sent to your email.';
                } else {
                    // ┌─────────────────────────────────────────────────────┐
                    // │  DEV MODE — REMOVE THIS ELSE BLOCK IN PRODUCTION    │
                    // │  When real email is configured, $sent = true and    │
                    // │  this else branch will never run. Safe to delete.   │
                    // └─────────────────────────────────────────────────────┘
                    $_SESSION['dev_otp_fallback'] = $otp; // DEV MODE — remove in production
                    $devOtp  = $otp;                      // DEV MODE — remove in production
                    $warning = 'Could not send email. Use the code displayed below.';
                }
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
        // Fall through to render page with the updated messages
    }

    // ── Verify OTP ────────────────────────────────────────────────────────────
    if ($action === 'verify') {
        // Collect 6 individual digit inputs into one code string
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

                // Look up a valid, unused OTP
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
                    // Mark OTP as used
                    $db->prepare('UPDATE email_otps SET used = 1 WHERE id = :id')
                       ->execute([':id' => $row['id']]);

                    // Activate the account
                    $db->prepare('UPDATE users SET is_verified = 1 WHERE user_id = :uid')
                       ->execute([':uid' => $userId]);

                    // Clear pending session data
                    unset(
                        $_SESSION['pending_verify_user_id'],
                        $_SESSION['pending_verify_email'],
                        $_SESSION['dev_otp_fallback']
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

// Mask email for display: s***@gmail.com
function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email, 2) + ['', ''];
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email - HydroFlow</title>
  <meta name="description" content="Enter your 6-digit verification code to activate your HydroFlow account.">
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    /* OTP digit inputs */
    .otp-inputs {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 24px 0;
    }
    .otp-digit {
      width: 52px;
      height: 60px;
      text-align: center;
      font-size: 28px;
      font-weight: 700;
      font-family: 'Courier New', monospace;
      border: 2px solid var(--color-outline, #bec7d4);
      border-radius: 12px;
      background: var(--color-surface-container-low, #f2f4f6);
      color: var(--color-on-surface, #191c1e);
      caret-color: var(--color-primary, #00629d);
      transition: border-color 0.2s, box-shadow 0.2s, transform 0.1s;
      outline: none;
    }
    .otp-digit:focus {
      border-color: var(--color-primary, #00629d);
      box-shadow: 0 0 0 3px rgba(0, 98, 157, 0.18);
      transform: scale(1.07);
    }
    .otp-digit.filled {
      border-color: var(--color-primary-container, #00a3ff);
      background: var(--color-primary-fixed, #cfe5ff);
    }
    .otp-digit.error-digit {
      border-color: var(--color-error, #ba1a1a);
      background: var(--color-error-container, #ffdad6);
      animation: shake 0.35s ease;
    }
    @keyframes shake {
      0%,100%{transform:translateX(0);}
      25%{transform:translateX(-5px);}
      75%{transform:translateX(5px);}
    }
    .dev-otp-box {
      background: linear-gradient(135deg, #fff3cd, #ffe08a);
      border: 1.5px solid #f0ad4e;
      border-radius: 12px;
      padding: 14px 20px;
      text-align: center;
      margin-bottom: 16px;
    }
    .dev-otp-box p { margin: 0; font-size: 13px; color: #7a5200; }
    .dev-otp-code {
      font-size: 32px;
      font-weight: 800;
      letter-spacing: 10px;
      color: #5a3500;
      font-family: 'Courier New', monospace;
      margin: 6px 0 0;
    }
    .resend-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 13px;
      color: var(--color-on-surface-variant, #3f4852);
    }
    .resend-btn {
      background: none;
      border: none;
      color: var(--color-primary, #00629d);
      font-weight: 700;
      font-size: 13px;
      cursor: pointer;
      padding: 2px 4px;
      border-radius: 4px;
      transition: background 0.15s;
    }
    .resend-btn:hover { background: var(--color-primary-fixed, #cfe5ff); }
    .email-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--color-surface-container, #eceef0);
      border-radius: 100px;
      padding: 4px 14px 4px 10px;
      font-size: 13px;
      font-weight: 600;
      color: var(--color-on-surface, #191c1e);
      margin: 8px 0 4px;
    }
  </style>
</head>
<body class="auth-layout">

  <main class="auth-card">
    <div class="decor-blob"></div>

    <!-- Header -->
    <header class="text-center">
      <div style="font-size: 48px; margin-bottom: 8px;">📧</div>
      <h1 class="text-headline-lg-responsive text-primary mb-2">Check Your Email</h1>
      <p class="text-body-md text-on-surface-variant">We sent a 6-digit verification code to</p>
      <div style="display:flex;justify-content:center;">
        <span class="email-chip">
          <span class="material-symbols-outlined" style="font-size:16px;">mail</span>
          <?= htmlspecialchars(maskEmail($pendingEmail)) ?>
        </span>
      </div>
    </header>

    <!-- Flash messages -->
    <?php if ($info): ?>
      <div class="alert alert--info" style="margin-top:12px;">
        <span class="material-symbols-outlined" style="font-size:18px;">info</span>
        <?= htmlspecialchars($info) ?>
      </div>
    <?php endif; ?>

    <?php if ($warning): ?>
      <div class="alert alert--warning" style="margin-top:12px;">
        <span class="material-symbols-outlined" style="font-size:18px;">warning</span>
        <?= htmlspecialchars($warning) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error" style="margin-top:12px;">
        <span class="material-symbols-outlined" style="font-size:18px;">error</span>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>


    <?php
    /*
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │  DEV MODE — REMOVE THIS ENTIRE BLOCK IN PRODUCTION                  │
     * │                                                                     │
     * │  This yellow box displays the OTP code directly on-screen.         │
     * │  It only appears when $devOtp is set (i.e. email is NOT configured).│
     * │                                                                     │
     * │  TO DISABLE:                                                        │
     * │    1. Fill MAIL_USER + MAIL_PASS in includes/config.php            │
     * │    2. Delete this entire <?php if ($devOtp): ?> block below        │
     * │    3. Also delete the $devOtp line near the top of this file       │
     * │    4. Also delete the dev_otp_fallback blocks in:                  │
     * │         • register.php                                              │
     * │         • login.php                                                 │
     * │         • includes/mailer.php                                       │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    ?>
    <?php if ($devOtp): /* DEV MODE — remove this block in production */ ?>
      <div class="dev-otp-box" style="margin-top:16px;">
        <p>⚠️ Email not configured — use this code for development:</p>
        <div class="dev-otp-code"><?= htmlspecialchars($devOtp) ?></div>
      </div>
    <?php endif; /* END DEV MODE */ ?>


    <!-- OTP Verification Form -->
    <form id="verify-form" method="POST" action="verify-email.php" autocomplete="off" style="margin-top:8px;">
      <input type="hidden" name="action" value="verify">

      <div class="otp-inputs" id="otp-inputs" role="group" aria-label="Enter your 6-digit code">
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <input
            class="otp-digit<?= $error ? ' error-digit' : '' ?>"
            id="digit-<?= $i ?>"
            name="digit_<?= $i ?>"
            type="text"
            inputmode="numeric"
            maxlength="1"
            pattern="[0-9]"
            placeholder="·"
            aria-label="Digit <?= $i ?>"
          >
        <?php endfor; ?>
      </div>

      <button class="btn-gradient w-full" type="submit" id="verify-btn">
        Verify &amp; Activate Account
      </button>
    </form>

    <!-- Resend -->
    <form method="POST" action="verify-email.php" style="margin-top:16px;">
      <input type="hidden" name="action" value="resend">
      <div class="resend-row">
        <span>Didn't receive the code?</span>
        <button class="resend-btn" type="submit" id="resend-btn">Resend Code</button>
      </div>
    </form>

    <p class="text-center text-body-md text-on-surface-variant" style="margin-top:12px; font-size:12px;">
      Wrong email?
      <a class="text-primary font-bold" href="register.php" style="text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Go back</a>
    </p>
  </main>

  <script>
  (function () {
    const inputs = Array.from(document.querySelectorAll('.otp-digit'));

    // Auto-focus first input
    if (inputs[0]) inputs[0].focus();

    inputs.forEach((inp, idx) => {
      inp.addEventListener('input', (e) => {
        // Strip non-digits
        inp.value = inp.value.replace(/\D/g, '').slice(-1);
        inp.classList.toggle('filled', inp.value !== '');
        inp.classList.remove('error-digit');

        if (inp.value && idx < inputs.length - 1) {
          inputs[idx + 1].focus();
        }

        // Auto-submit when all 6 filled
        if (inputs.every(i => i.value !== '')) {
          setTimeout(() => document.getElementById('verify-form').submit(), 120);
        }
      });

      inp.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !inp.value && idx > 0) {
          inputs[idx - 1].focus();
          inputs[idx - 1].value = '';
          inputs[idx - 1].classList.remove('filled');
        }
        // Arrow navigation
        if (e.key === 'ArrowLeft' && idx > 0) inputs[idx - 1].focus();
        if (e.key === 'ArrowRight' && idx < inputs.length - 1) inputs[idx + 1].focus();
      });

      inp.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
          .getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, i) => {
          if (inputs[i]) {
            inputs[i].value = ch;
            inputs[i].classList.add('filled');
          }
        });
        const next = inputs[Math.min(pasted.length, inputs.length - 1)];
        if (next) next.focus();
        if (inputs.every(i => i.value !== '')) {
          setTimeout(() => document.getElementById('verify-form').submit(), 120);
        }
      });
    });

    // Resend cooldown (30 seconds)
    const resendBtn = document.getElementById('resend-btn');
    if (resendBtn) {
      let countdown = 0;
      const startCooldown = (sec) => {
        countdown = sec;
        resendBtn.disabled = true;
        const tick = setInterval(() => {
          resendBtn.textContent = `Resend Code (${countdown}s)`;
          if (--countdown <= 0) {
            clearInterval(tick);
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend Code';
          }
        }, 1000);
      };
      // Start 30s cooldown on page load (just sent a code)
      startCooldown(30);

      resendBtn.closest('form').addEventListener('submit', () => {
        startCooldown(60);
      });
    }
  })();
  </script>
</body>
</html>
