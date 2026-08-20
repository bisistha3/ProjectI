<?php
/**
 * HealthFlow — Application Configuration
 * ════════════════════════════════════════════════════════════════════════════
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │                       CURRENT MODE: PRODUCTION (Gmail SMTP)             │
 * │                                                                         │
 * │  OTP codes are sent to the user's email via Gmail SMTP.                 │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ── CHANGING EMAIL PROVIDER ───────────────────────────────────────────────
 *
 * STEP 1 — Choose your email provider:
 *
 *   OPTION A — Gmail (recommended):
 *     1. Enable 2-Step Verification on your Google account.
 *     2. Visit: https://myaccount.google.com/apppasswords
 *     3. Create an App Password (select "Mail" → "Other", name it "HealthFlow").
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
 * STEP 3 — (Optional) The on-screen OTP display has been removed.
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

// ─── Gmail SMTP (App Password) ───────────────────────────────────────────
// MAIL_USER = your Gmail address
// MAIL_PASS = 16-character App Password (myaccount.google.com/apppasswords)
define('MAIL_USER', 'kritanniraula@gmail.com');
define('MAIL_PASS', 'gvtx vqah qucq rtvn');

// ─── From Address (shown in recipient's inbox) ────────────────────────────
define('MAIL_FROM_ADDR', 'kritanniraula@gmail.com'); // change to a real address in production
define('MAIL_FROM_NAME', 'HealthFlow');

// ─── OTP Settings ─────────────────────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 15);  // How long the OTP code stays valid
define('OTP_LENGTH',          6);  // Number of digits in the OTP

// ─── AbstractAPI Email Reputation (optional) ──────────────────────────────
// Sign up free at https://app.abstractapi.com/api/email-reputation
// Free tier: 100 checks/month, no credit card required.
define('ABSTRACT_EMAIL_API_KEY',     '77bf053d156942cca457e34a5c1cb04a');   // ← paste your key here (optional)
define('ABSTRACT_EMAIL_API_URL',     'https://emailreputation.abstractapi.com/v1/');
define('ABSTRACT_EMAIL_API_TIMEOUT', 5);
