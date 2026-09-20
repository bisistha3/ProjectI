<?php
/**
 * HealthFlow — Consolidated Schema Migration
 *
 * Idempotent — safe to run multiple times. Each step checks
 * whether the change has already been applied before acting.
 *
 * Run: php database/migrate.php
 */

require_once __DIR__ . '/../includes/db.php';

$db  = getDB();
$out = [];

// ── Helpers ──────────────────────────────────────────────────────

$hasCol = function (string $table, string $col) use ($db): bool {
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
};

$hasTable = function (string $table) use ($db): bool {
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
};

$alter = function (string $sql) use ($db, &$out) {
    try {
        $db->exec($sql);
        $out[] = "OK: {$sql}";
    } catch (PDOException $e) {
        $out[] = "SKIP: {$sql} — " . $e->getMessage();
    }
};

// ══════════════════════════════════════════════════════════════════
// 1. FOODS
// ══════════════════════════════════════════════════════════════════

// Rename foods.serving_size_g → serving_qty
if ($hasCol('foods', 'serving_size_g') && !$hasCol('foods', 'serving_qty')) {
    $alter('ALTER TABLE foods CHANGE COLUMN serving_size_g serving_qty DECIMAL(7,1) NOT NULL DEFAULT 100');
} elseif (!$hasCol('foods', 'serving_qty')) {
    $alter('ALTER TABLE foods ADD COLUMN serving_qty DECIMAL(7,1) NOT NULL DEFAULT 100 AFTER food_name');
}

// Add unit_type column to foods
if (!$hasCol('foods', 'unit_type')) {
    $alter("ALTER TABLE foods ADD COLUMN unit_type ENUM('g','piece','ml') NOT NULL DEFAULT 'g' AFTER serving_qty");
}

// Rename food_logs.qty_g → qty
if ($hasCol('food_logs', 'qty_g') && !$hasCol('food_logs', 'qty')) {
    $alter('ALTER TABLE food_logs CHANGE COLUMN qty_g qty DECIMAL(7,1) NOT NULL DEFAULT 100');
} elseif (!$hasCol('food_logs', 'qty')) {
    $alter('ALTER TABLE food_logs ADD COLUMN qty DECIMAL(7,1) NOT NULL DEFAULT 100 AFTER meal_type');
}

// Add unit_type column to food_logs
if (!$hasCol('food_logs', 'unit_type')) {
    $alter("ALTER TABLE food_logs ADD COLUMN unit_type ENUM('g','piece','ml') NOT NULL DEFAULT 'g' AFTER qty");
}

// Backfill preset food units
$db->exec("UPDATE foods SET unit_type = 'piece' WHERE user_id IS NULL AND food_name IN ('Boiled Egg','Apple','Banana','Bread')");
$db->exec("UPDATE foods SET unit_type = 'ml', serving_qty = 244 WHERE user_id IS NULL AND food_name = 'Milk'");
$out[] = 'OK: backfilled preset food units';

// ══════════════════════════════════════════════════════════════════
// 2. USER GOALS
// ══════════════════════════════════════════════════════════════════

$db->exec("
    CREATE TABLE IF NOT EXISTS user_goals (
        user_id INT PRIMARY KEY,
        daily_goal_ml INT NOT NULL DEFAULT 2500,
        daily_calorie_goal INT NOT NULL DEFAULT 2000,
        daily_protein_goal_g INT NOT NULL DEFAULT 125,
        daily_fat_goal_g INT NOT NULL DEFAULT 67,
        daily_carbs_goal_g INT NOT NULL DEFAULT 225,
        daily_exercise_goal_min INT NOT NULL DEFAULT 30,
        daily_burn_goal_kcal INT NOT NULL DEFAULT 300,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )
");

if ($hasCol('users', 'daily_goal_ml')) {
    $db->exec("
        INSERT IGNORE INTO user_goals
            (user_id, daily_goal_ml, daily_calorie_goal, daily_protein_goal_g,
             daily_fat_goal_g, daily_carbs_goal_g, daily_exercise_goal_min, daily_burn_goal_kcal)
        SELECT user_id, daily_goal_ml, daily_calorie_goal, daily_protein_goal_g,
               daily_fat_goal_g, daily_carbs_goal_g, daily_exercise_goal_min, daily_burn_goal_kcal
        FROM users
    ");
    $out[] = 'OK: user_goals backfilled from users';
} else {
    $out[] = 'OK: user_goals backfill skipped (legacy columns already dropped)';
}

// Drop legacy goal columns from users
foreach (['daily_goal_ml', 'daily_calorie_goal', 'daily_protein_goal_g', 'daily_fat_goal_g',
          'daily_carbs_goal_g', 'daily_exercise_goal_min', 'daily_burn_goal_kcal'] as $goalCol) {
    if ($hasCol('users', $goalCol)) {
        $alter("ALTER TABLE users DROP COLUMN {$goalCol}");
    }
}

// Add daily_goals_prompted_at column
if (!$hasCol('users', 'daily_goals_prompted_at')) {
    $alter('ALTER TABLE users ADD COLUMN daily_goals_prompted_at DATETIME DEFAULT NULL AFTER is_verified');
}

// ══════════════════════════════════════════════════════════════════
// 3. REMINDERS
// ══════════════════════════════════════════════════════════════════

// Create reminders table (final schema includes last_email_sent_at)
$db->exec('
    CREATE TABLE IF NOT EXISTS reminders (
        user_id INT PRIMARY KEY,
        reminder_enabled TINYINT(1) NOT NULL DEFAULT 0,
        reminder_interval_min INT NOT NULL DEFAULT 60,
        email_reminder_enabled TINYINT(1) NOT NULL DEFAULT 0,
        last_email_sent_at DATETIME NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )
');
$out[] = 'OK: reminders table ensured';

// Backfill reminder data from users → reminders (for old installs)
if ($hasCol('users', 'reminder_enabled')) {
    $db->exec('INSERT IGNORE INTO reminders (user_id, reminder_enabled, reminder_interval_min)
               SELECT user_id, reminder_enabled, reminder_interval_min FROM users');
    $out[] = 'OK: backfilled reminders from users';
}

// Drop old reminder columns from users
foreach (['reminder_enabled', 'reminder_time', 'reminder_interval_min'] as $col) {
    if ($hasCol('users', $col)) {
        $alter("ALTER TABLE users DROP COLUMN {$col}");
    }
}

// Drop reminder_time from reminders (interval-only reminders)
if ($hasCol('reminders', 'reminder_time')) {
    $alter('ALTER TABLE reminders DROP COLUMN reminder_time');
}

// Ensure default interval is 60 (was 0 for old custom-time users)
$db->exec('UPDATE reminders SET reminder_interval_min = 60 WHERE reminder_interval_min = 0');
$out[] = 'OK: default intervals set to 60 minutes';

// Add last_email_sent_at if missing (old installs that predate this column)
if (!$hasCol('reminders', 'last_email_sent_at')) {
    $alter('ALTER TABLE reminders ADD COLUMN last_email_sent_at DATETIME NULL DEFAULT NULL');
}

// ══════════════════════════════════════════════════════════════════
// 4. EMAIL REMINDERS CLEANUP
// ══════════════════════════════════════════════════════════════════

if ($hasTable('email_reminders')) {
    $db->exec('DROP TABLE email_reminders');
    $out[] = 'OK: dropped email_reminders table';
}

// ── Done ─────────────────────────────────────────────────────────

header('Content-Type: text/plain; charset=utf-8');
echo implode(PHP_EOL, $out);
