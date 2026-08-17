<?php
/**
 * HealthFlow — Database Migration
 * Adds quantity units (g / piece / ml) to existing installs.
 *
 * Run once from the browser or CLI:
 *   http://localhost/final/ProjectI/database/migrate.php
 *   php database/migrate.php
 *
 * Idempotent: safe to run multiple times.
 */
require_once __DIR__ . '/../includes/db.php';

$db  = getDB();
$out = [];

$hasCol = function (string $table, string $col) use ($db): bool {
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $col]);
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

// ── foods: rename serving_size_g → serving_qty ──────────────────────────────
if ($hasCol('foods', 'serving_size_g') && !$hasCol('foods', 'serving_qty')) {
    $alter('ALTER TABLE foods CHANGE COLUMN serving_size_g serving_qty DECIMAL(7,1) NOT NULL DEFAULT 100');
} elseif (!$hasCol('foods', 'serving_qty')) {
    $alter('ALTER TABLE foods ADD COLUMN serving_qty DECIMAL(7,1) NOT NULL DEFAULT 100 AFTER food_name');
}

// ── foods: add unit_type ─────────────────────────────────────────────────────
if (!$hasCol('foods', 'unit_type')) {
    $alter("ALTER TABLE foods ADD COLUMN unit_type ENUM('g','piece','ml') NOT NULL DEFAULT 'g' AFTER serving_qty");
    $db->exec("UPDATE foods SET unit_type = 'g' WHERE unit_type = 'g'");
}

// ── food_logs: rename qty_g → qty ───────────────────────────────────────────
if ($hasCol('food_logs', 'qty_g') && !$hasCol('food_logs', 'qty')) {
    $alter('ALTER TABLE food_logs CHANGE COLUMN qty_g qty DECIMAL(7,1) NOT NULL DEFAULT 100');
} elseif (!$hasCol('food_logs', 'qty')) {
    $alter('ALTER TABLE food_logs ADD COLUMN qty DECIMAL(7,1) NOT NULL DEFAULT 100 AFTER meal_type');
}

// ── food_logs: add unit_type ─────────────────────────────────────────────────
if (!$hasCol('food_logs', 'unit_type')) {
    $alter("ALTER TABLE food_logs ADD COLUMN unit_type ENUM('g','piece','ml') NOT NULL DEFAULT 'g' AFTER qty");
}

// ── Backfill known preset foods with sensible units (existing installs) ─────
$db->exec("UPDATE foods SET unit_type = 'piece' WHERE user_id IS NULL AND food_name IN ('Boiled Egg','Apple','Banana','Bread')");
$db->exec("UPDATE foods SET unit_type = 'ml', serving_qty = 244 WHERE user_id IS NULL AND food_name = 'Milk'");
$out[] = 'OK: backfilled preset food units';

header('Content-Type: text/plain; charset=utf-8');
echo implode(PHP_EOL, $out);