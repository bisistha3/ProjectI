<?php
/**
 * HealthFlow — Email Reminder Sender
 *
 * Finds users with email reminders enabled, checks their daily stats,
 * and sends a personalized nudge email via PHPMailer.
 *
 * Designed to be called by reminders/cron.php on a schedule.
 */
if (!defined('MAIL_HOST')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmailReminders(bool $force = false): array {
    $db = getDB();
    $results = ['sent' => 0, 'skipped' => 0, 'errors' => 0];

    $users = $db->query(
        "SELECT u.user_id, u.full_name, u.email, r.reminder_id, r.reminder_interval_min
         FROM users u
         INNER JOIN reminders r ON r.reminder_id = (
             SELECT MAX(r2.reminder_id)
             FROM reminders r2
             WHERE r2.user_id = u.user_id
         )
         WHERE r.reminder_enabled = 1
           AND r.email_reminder_enabled = 1
           AND u.is_verified = 1"
    )->fetchAll();

    foreach ($users as $user) {
        $userId = (int)$user['user_id'];
        $name = $user['full_name'];
        $email = $user['email'];
        $intervalMin = (int)$user['reminder_interval_min'];

        // Check last sent time — skip if interval hasn't elapsed yet
        if (!$force) {
            $elapsedStmt = $db->prepare(
                "SELECT UNIX_TIMESTAMP(sent_at) FROM reminders WHERE reminder_id = ?"
            );
            $elapsedStmt->execute([(int)$user['reminder_id']]);
            $lastSentUnix = (int)$elapsedStmt->fetchColumn();

            if ($lastSentUnix > 0) {
                $elapsed = (time() - $lastSentUnix) / 60;
                if ($elapsed < $intervalMin) {
                    $results['skipped']++;
                    continue;
                }
            }
        }

        $stats = getUserDailyStats($db, $userId);
        $sent = sendReminderEmail($email, $name, $stats);

        if ($sent) {
            $db->prepare(
                "INSERT INTO reminders
                    (user_id, reminder_enabled, reminder_interval_min,
                     email_reminder_enabled, sent_at)
                 SELECT user_id, reminder_enabled, reminder_interval_min,
                        email_reminder_enabled, NOW()
                 FROM reminders
                 WHERE reminder_id = ? AND user_id = ?"
            )->execute([(int)$user['reminder_id'], $userId]);
            $results['sent']++;
        } else {
            $results['errors']++;
        }
    }

    return $results;
}

function getUserDailyStats(PDO $db, int $userId): array {
    $stmt = $db->prepare('
        SELECT
            g.daily_goal_ml,
            g.daily_calorie_goal,
            g.daily_exercise_goal_min,
            COALESCE(w.ml, 0) AS today_ml,
            COALESCE(f.kcal, 0) AS today_kcal,
            COALESCE(f.prot, 0) AS today_protein,
            COALESCE(e.min, 0) AS today_min
        FROM users u
        LEFT JOIN user_goals g ON g.user_id = u.user_id
        LEFT JOIN (
            SELECT user_id, SUM(amount_ml) AS ml
            FROM water_logs WHERE user_id = ? AND DATE(logged_at) = CURDATE()
            GROUP BY user_id
        ) w ON w.user_id = u.user_id
        LEFT JOIN (
            SELECT user_id, SUM(calories) AS kcal, SUM(protein_g) AS prot
            FROM food_logs WHERE user_id = ? AND DATE(logged_at) = CURDATE()
            GROUP BY user_id
        ) f ON f.user_id = u.user_id
        LEFT JOIN (
            SELECT user_id, SUM(duration_min) AS min
            FROM exercise_logs WHERE user_id = ? AND DATE(logged_at) = CURDATE()
            GROUP BY user_id
        ) e ON e.user_id = u.user_id
        WHERE u.user_id = ?
    ');
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $row = $stmt->fetch();

    $goalMl = (int)($row['daily_goal_ml'] ?? 2500);
    $goalCal = (int)($row['daily_calorie_goal'] ?? 2000);
    $goalEx = (int)($row['daily_exercise_goal_min'] ?? 30);

    return [
        'today_ml' => (int)($row['today_ml'] ?? 0),
        'goal_ml' => $goalMl,
        'pct_ml' => $goalMl > 0 ? min(100, round((int)($row['today_ml'] ?? 0) / $goalMl * 100)) : 0,
        'today_kcal' => (int)($row['today_kcal'] ?? 0),
        'goal_kcal' => $goalCal,
        'pct_kcal' => $goalCal > 0 ? min(100, round((int)($row['today_kcal'] ?? 0) / $goalCal * 100)) : 0,
        'today_protein' => round((float)($row['today_protein'] ?? 0), 1),
        'today_min' => (int)($row['today_min'] ?? 0),
        'goal_min' => $goalEx,
        'pct_min' => $goalEx > 0 ? min(100, round((int)($row['today_min'] ?? 0) / $goalEx * 100)) : 0,
    ];
}

function sendReminderEmail(string $toAddr, string $toName, array $stats): bool {
    $subject = "Time to log your health data!";

    $pctMl = $stats['pct_ml'];
    $pctCal = $stats['pct_kcal'];
    $pctMin = $stats['pct_min'];

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; color: #1a1a2e; margin: 0; padding: 0; }
  .wrap { max-width: 480px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e0e0e0; }
  .header { background: linear-gradient(135deg, #00696d, #00b8bd); padding: 32px; text-align: center; }
  .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
  .body { padding: 32px; }
  .stat { margin-bottom: 16px; }
  .stat-label { font-size: 13px; color: #666; margin-bottom: 4px; font-weight: 600; }
  .stat-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; color: #1a1a2e; }
  .bar-bg { background: #e9ecef; border-radius: 8px; height: 10px; overflow: hidden; }
  .bar-fill { height: 100%; border-radius: 8px; transition: width 0.3s; }
  .bar-water { background: #3b82f6; }
  .bar-food { background: #f59e0b; }
  .bar-exercise { background: #10b981; }
  .cta { display: block; text-align: center; background: linear-gradient(135deg, #00696d, #00b8bd); color: #fff; text-decoration: none; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; margin: 24px 0; }
  .footer { text-align: center; padding: 16px; font-size: 12px; color: #888; border-top: 1px solid #e9ecef; }
  @media (prefers-color-scheme: dark) {
    body { background: #0d1117; }
    .wrap { background: #161b22; border-color: #30363d; }
    .stat-row { color: #c9d1d9; }
    .stat-label { color: #8b949e; }
    .bar-bg { background: #21262d; }
    .footer { color: #484f58; border-color: #21262d; }
  }
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>&#10084;&#65039; HealthFlow — Reminder</h1></div>
  <div class="body">
    <p>Hi <strong>{$toName}</strong>,</p>
    <p>Time to log your water, meals and exercise!</p>

    <div class="stat">
      <div class="stat-label">Water</div>
      <div class="stat-row"><span>{$stats['today_ml']} ml</span> / <span>{$stats['goal_ml']} ml goal</span></div>
      <div class="bar-bg"><div class="bar-fill bar-water" style="width:{$pctMl}%"></div></div>
    </div>

    <div class="stat">
      <div class="stat-label">Calories</div>
      <div class="stat-row"><span>{$stats['today_kcal']} kcal</span> / <span>{$stats['goal_kcal']} kcal goal</span></div>
      <div class="bar-bg"><div class="bar-fill bar-food" style="width:{$pctCal}%"></div></div>
    </div>

    <div class="stat">
      <div class="stat-label">Exercise</div>
      <div class="stat-row"><span>{$stats['today_min']} min</span> / <span>{$stats['goal_min']} min goal</span></div>
      <div class="bar-bg"><div class="bar-fill bar-exercise" style="width:{$pctMin}%"></div></div>
    </div>

    <a href="http://localhost/ProjectI/log.php?type=water" class="cta">Log Now</a>
    <p style="font-size:13px; color:#666; text-align:center;">Keep up the streak — consistency is key!</p>
  </div>
  <div class="footer">HealthFlow &middot; This is an automated message, please do not reply.</div>
</div>
</body>
</html>
HTML;

    $text = "Hi {$toName},\n\nTime to log your health data!\n\n"
          . "Water: {$stats['today_ml']}/{$stats['goal_ml']} ml\n"
          . "Calories: {$stats['today_kcal']}/{$stats['goal_kcal']} kcal\n"
          . "Exercise: {$stats['today_min']}/{$stats['goal_min']} min\n\n"
          . "Log now: http://localhost/ProjectI/log.php?type=water\n";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDR, MAIL_FROM_NAME);
        $mail->addAddress($toAddr, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email reminder failed for {$toAddr}: " . $mail->ErrorInfo);
        return false;
    }
}
