<?php
/**
 * HealthFlow — Shared Food Functions
 * Centralized food operations for global and user-specific foods.
 *
 * Units: g (weighable), piece (countable), ml (liquid).
 * A food stores nutrition per `serving_qty` of its `unit_type`.
 */

const FOOD_UNITS = ['g', 'piece', 'ml'];

function unitLabel(string $unit): string {
    return match ($unit) {
        'piece' => 'piece',
        'ml'    => 'ml',
        default => 'g',
    };
}

function formatQty(float $qty, string $unit): string {
    $q = $qty == (int)$qty ? (string)(int)$qty : rtrim(rtrim(number_format($qty, 1, '.', ''), '0'), '.');
    return $unit === 'piece' ? $q . ' piece' . ($q == 1 ? '' : 's') : $q . ' ' . $unit;
}

function getAvailableFoods(PDO $db, int $userId): array {
    $stmt = $db->prepare('
        SELECT food_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g,
               user_id, created_at
        FROM foods
        WHERE user_id IS NULL OR user_id = ?
        ORDER BY user_id IS NULL DESC, food_name ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getFoodById(PDO $db, int $foodId, int $userId): ?array {
    $stmt = $db->prepare('
        SELECT food_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g,
               user_id, created_at
        FROM foods
        WHERE food_id = ? AND (user_id IS NULL OR user_id = ?)
    ');
    $stmt->execute([$foodId, $userId]);
    $food = $stmt->fetch();
    return $food ?: null;
}

function createUserFood(PDO $db, int $userId, array $data): int {
    $stmt = $db->prepare('
        INSERT INTO foods (user_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId,
        $data['food_name'],
        $data['serving_qty'],
        $data['unit_type'],
        $data['calories'],
        $data['protein_g'],
        $data['fat_g'],
        $data['carbs_g'],
    ]);
    return (int)$db->lastInsertId();
}

function calculateMacros(array $food, float $qty): array {
    $ratio = (float)$food['serving_qty'] > 0 ? $qty / (float)$food['serving_qty'] : 0;
    return [
        'calories'  => (int)round($food['calories'] * $ratio),
        'protein_g' => round($food['protein_g'] * $ratio, 1),
        'fat_g'     => round($food['fat_g'] * $ratio, 1),
        'carbs_g'   => round($food['carbs_g'] * $ratio, 1),
    ];
}

function getUserCustomFoods(PDO $db, int $userId): array {
    $stmt = $db->prepare('
        SELECT food_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g, created_at
        FROM foods
        WHERE user_id = ?
        ORDER BY created_at DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function deleteUserFood(PDO $db, int $foodId, int $userId): bool {
    $stmt = $db->prepare('DELETE FROM foods WHERE food_id = ? AND user_id = ?');
    $stmt->execute([$foodId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Log a food entry with quantity + unit.
 *
 * Known food (global preset or the user's own): only name + qty are required,
 * nutrition is taken from the saved food and scaled by qty / serving_qty.
 * New food: name + qty + unit + nutrition are required; it is auto-saved to
 * My Foods (serving_qty = entered qty) so next time only name + qty are asked.
 *
 * Returns ['ok' => true] or ['ok' => false, 'error' => string].
 */
function logFoodEntry(PDO $db, int $userId, array $post): array {
    $foodName = trim((string)($post['food_name'] ?? ''));
    $mealType = trim((string)($post['meal_type'] ?? 'snack'));
    $mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
    if (!in_array($mealType, $mealTypes)) $mealType = 'snack';

    $qty  = (float)($post['qty'] ?? 0);
    $unit = (string)($post['unit_type'] ?? 'g');
    if (!in_array($unit, FOOD_UNITS)) $unit = 'g';

    if ($foodName === '' || $qty <= 0 || $qty > 100000) {
        return ['ok' => false, 'error' => 'Food name and a valid quantity are required.'];
    }

    // Resolve existing food (global preset or user's own) by name
    $foodQ = $db->prepare('SELECT food_id, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g
                           FROM foods WHERE food_name=? AND (user_id IS NULL OR user_id=?)
                           ORDER BY user_id DESC LIMIT 1');
    $foodQ->execute([$foodName, $userId]);
    $food = $foodQ->fetch();

    if ($food) {
        // Known food — use saved values, scale by quantity, unit is locked
        $foodId = (int)$food['food_id'];
        $unit   = $food['unit_type'];
        $macros = calculateMacros($food, $qty);
    } else {
        // New food — require nutrition details, save to My Foods for next time
        $foodName = mb_substr($foodName, 0, 100);
        $cal     = max(0, min(3000, (int)($post['calories'] ?? 0)));
        $protein = max(0, min(400, (float)($post['protein_g'] ?? 0)));
        $fat     = max(0, min(250, (float)($post['fat_g']     ?? 0)));
        $carbs   = max(0, min(800, (float)($post['carbs_g']   ?? 0)));
        if ($cal <= 0) {
            return ['ok' => false, 'error' => 'Please enter calories for this new food.'];
        }
        $db->prepare('INSERT INTO foods (user_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g)
                      VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$userId, $foodName, $qty, $unit, $cal, $protein, $fat, $carbs]);
        $foodId = (int)$db->lastInsertId();
        $macros = [
            'calories'  => $cal,
            'protein_g' => $protein,
            'fat_g'     => $fat,
            'carbs_g'   => $carbs,
        ];
    }

    // Snapshot the exact nutritional values at log time
    $db->prepare('INSERT INTO food_logs (user_id, food_id, meal_type, qty, unit_type, calories, protein_g, fat_g, carbs_g)
                  VALUES (?,?,?,?,?,?,?,?,?)')
       ->execute([
           $userId, $foodId, $mealType, $qty, $unit,
           $macros['calories'], $macros['protein_g'], $macros['fat_g'], $macros['carbs_g'],
       ]);
    return ['ok' => true];
}