<?php
/**
 * HealthFlow — Email Reminder Cron Entry Point
 *
 * Run this script on a schedule (every 15 minutes) via Windows Task Scheduler:
 *   schtasks /create /tn "HealthFlow Reminders" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\ProjectI\reminders\cron.php" /sc minute /mo 15
 *
 * To run manually:
 *   php C:\xampp\htdocs\ProjectI\reminders\cron.php
 *
 * Flags:
 *   --force   ignore the reminder interval and send now
 *   --debug   print SMTP conversation to the PHP error log
 */

// Prevent web access — only allow CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

date_default_timezone_set('Asia/Kathmandu');
ini_set('date.timezone', 'Asia/Kathmandu');

$logFile = __DIR__ . '/reminder_log.txt';
$hfLogging = false;

function hfLog(string $line): void {
    global $logFile, $hfLogging;
    if ($hfLogging) return;
    $hfLogging = true;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    $hfLogging = false;
}

function hfStamp(): string {
    return '[' . date('Y-m-d H:i:s') . ']';
}

set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $names = [
        E_ERROR => 'FATAL', E_WARNING => 'WARNING', E_NOTICE => 'NOTICE',
        E_PARSE => 'PARSE', E_CORE_ERROR => 'CORE FATAL', E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE FATAL', E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER FATAL', E_USER_WARNING => 'USER WARNING', E_USER_NOTICE => 'USER NOTICE',
        E_RECOVERABLE_ERROR => 'RECOVERABLE FATAL', E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER DEPRECATED', E_STRICT => 'STRICT',
    ];
    hfLog(sprintf("%s %s: %s in %s:%d\n", hfStamp(), $names[$severity] ?? 'PHP ERROR', $message, $file, $line));
    return true;
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        hfLog(sprintf("%s FATAL: %s in %s:%d\n", hfStamp(), $err['message'], $err['file'], $err['line']));
    }
});

require_once __DIR__ . '/../includes/email_reminder.php';

$force = in_array('--force', $argv ?? [], true);
$debug = in_array('--debug', $argv ?? [], true);
$timestamp = hfStamp();
$results = sendEmailReminders($force, $debug);

$log = sprintf(
    "%s Sent: %d | Skipped: %d | Errors: %d\n",
    $timestamp,
    $results['sent'],
    $results['skipped'],
    $results['errors']
);

echo $log;
hfLog($log);

foreach ($results['failures'] as $failure) {
    $detail = sprintf(
        "%s ERROR to %s: %s\n",
        $timestamp,
        $failure['email'],
        str_replace(["\r", "\n"], ' ', $failure['error'])
    );
    echo $detail;
    hfLog($detail);
}
