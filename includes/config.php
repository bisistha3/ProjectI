<?php
/**
 * HydroFlow — Application Configuration
 * ════════════════════════════════════════════════════════════════════════════
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │                        CURRENT MODE: DEV MODE                           │
 * │                                                                         │
 * │  OTP codes are displayed on-screen instead of being emailed.            │
 * │  To switch to PRODUCTION (real emails), follow the steps below.         │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ── HOW TO ENABLE REAL EMAIL (PRODUCTION MODE) ───────────────────────────
 *
 * STEP 1 — Choose your email provider:
 *
 *   OPTION A — Gmail (recommended):
 *     1. Enable 2-Step Verification on your Google account.
 *     2. Visit: https://myaccount.google.com/apppasswords
 *     3. Create an App Password (select "Mail" → "Other", name it "HydroFlow").
 *     4. Copy the 16-character password shown.
 *     5. Fill in MAIL_USER (your Gmail address) and MAIL_PASS (the app password).
 *     6. Leave MAIL_HOST = 'smtp.gmail.com', MAIL_PORT = 587, MAIL_ENCRYPTION = 'tls'.
 *
 *   OPTION B — Mailtrap (free safe sandbox, no real emails reach users):
 *     1. Sign up free at https://mailtrap.io
 *     2. Go to: Email Testing → Inboxes → your inbox → SMTP Settings
 *     3. Set MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS from those settings.
 *
 * STEP 2 — After filling in MAIL_USER and MAIL_PASS:
 *   The system will automatically switch to real email mode.
 *   No other code changes needed.
 *
 * STEP 3 — Remove the dev-mode OTP display from the UI:
 *   Search for the comment "DEV MODE — REMOVE IN PRODUCTION" in:
 *     • includes/mailer.php
 *     • verify-email.php
 *     • register.php
 *     • login.php
 *   And delete those clearly marked blocks.
 *
 * ════════════════════════════════════════════════════════════════════════════
 */

// ┌─────────────────────────────────────────────────────────────────────────┐
// │  SMTP SETTINGS — Fill in MAIL_USER + MAIL_PASS to enable real email     │
// └─────────────────────────────────────────────────────────────────────────┘
define('MAIL_USE_SMTP',   true);
define('MAIL_HOST',       'smtp.gmail.com');  // Gmail | or 'smtp.mailtrap.io'
define('MAIL_PORT',       587);               // 587 = TLS (Gmail) | 465 = SSL | 25 = plain
define('MAIL_ENCRYPTION', 'tls');             // 'tls' | 'ssl' | ''

// ─── DEV MODE: Both values are empty → OTP shown on screen ───────────────
// ─── PRODUCTION: Fill in your Gmail address and App Password below ────────
define('MAIL_USER', '');  // ← e.g. 'yourname@gmail.com'
define('MAIL_PASS', '');  // ← e.g. 'abcd efgh ijkl mnop'  (Gmail App Password)

// ─── From Address (shown in recipient's inbox) ────────────────────────────
define('MAIL_FROM_ADDR', 'noreply@hydroflow.com'); // change to a real address in production
define('MAIL_FROM_NAME', 'HydroFlow');

// ─── OTP Settings ─────────────────────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 15);  // How long the OTP code stays valid
define('OTP_LENGTH',          6);  // Number of digits in the OTP

// ─── AbstractAPI Email Validation (optional) ──────────────────────────────
// Sign up free at https://app.abstractapi.com/api/email-validation
// Free tier: 100 checks/month, no credit card required.
define('ABSTRACT_EMAIL_API_KEY',     '');   // ← paste your key here (optional)
define('ABSTRACT_EMAIL_API_URL',     'https://emailvalidation.abstractapi.com/v1/');
define('ABSTRACT_EMAIL_API_TIMEOUT', 5);
