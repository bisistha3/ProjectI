<?php
/**
 * HydroFlow — Application Configuration
 * ════════════════════════════════════════════════════════
 *
 * ── HOW TO CONFIGURE EMAIL (pick one option) ─────────────
 *
 * OPTION A — Gmail (recommended, works anywhere):
 *   1. Enable 2-Step Verification on your Google account
 *   2. Go to: https://myaccount.google.com/apppasswords
 *   3. Create an App Password (name it "HydroFlow")
 *   4. Fill in MAIL_USER (your Gmail) and MAIL_PASS (app password)
 *
 * OPTION B — Mailtrap (free dev inbox, no real emails sent):
 *   1. Sign up at https://mailtrap.io → free
 *   2. Go to Email Testing → Inboxes → SMTP Settings
 *   3. Change MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS below
 *
 * OPTION C — Dev mode (no email config needed):
 *   Leave MAIL_USER empty. The OTP code will be shown
 *   directly on the verification page instead of emailed.
 * ════════════════════════════════════════════════════════
 */

// ── SMTP Settings ─────────────────────────────────────────────────────────────
define('MAIL_USE_SMTP',    true);
define('MAIL_HOST',        'smtp.gmail.com');   // or 'smtp.mailtrap.io'
define('MAIL_PORT',        587);                // 587=TLS | 465=SSL | 25=plain
define('MAIL_ENCRYPTION',  'tls');              // 'tls' | 'ssl' | ''
define('MAIL_USER',        '');                 // ← your Gmail address or SMTP login
define('MAIL_PASS',        '');                 // ← your App Password or SMTP password

// ── From Address ──────────────────────────────────────────────────────────────
define('MAIL_FROM_ADDR', 'noreply@hydroflow.com');
define('MAIL_FROM_NAME', 'HydroFlow');

// ── OTP Settings ──────────────────────────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 15);   // how long the code stays valid
define('OTP_LENGTH',         6);    // digits in the OTP

// ── AbstractAPI Email Validation (optional upgrade) ───────────────────────────
// Sign up free at https://app.abstractapi.com/api/email-validation
// Free tier: 100 verifications / month (no credit card required)
define('ABSTRACT_EMAIL_API_KEY',     '');   // ← paste your key here (optional)
define('ABSTRACT_EMAIL_API_URL',     'https://emailvalidation.abstractapi.com/v1/');
define('ABSTRACT_EMAIL_API_TIMEOUT', 5);
