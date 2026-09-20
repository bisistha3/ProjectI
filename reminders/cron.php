<?php
/**
 * HealthFlow — Email Reminder Cron Entry Point
 *
 * Run this script on a schedule (every 15 minutes) via Windows Task Scheduler:
 *   schtasks /create /tn "HealthFlow Reminders" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\ProjectI\reminders\cron.php" /sc minute /mo 15
 *
 * To run manually:
 *   php C:\xampp\htdocs\ProjectI\reminders\cron.php
 */

// Prevent web access — only allow CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/email_reminder.php';

$force = in_array('--force', $argv ?? []);
$timestamp = date('Y-m-d H:i:s');
$results = sendEmailReminders($force);

$log = sprintf(
    "[%s] Sent: %d | Skipped: %d | Errors: %d\n",
    $timestamp,
    $results['sent'],
    $results['skipped'],
    $results['errors']
);

echo $log;

// Also append to a log file for review
$logFile = __DIR__ . '/reminder_log.txt';
file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
