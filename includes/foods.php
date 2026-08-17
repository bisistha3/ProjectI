<?php
/**
 * HealthFlow — Shared Food Functions
 * Centralized food operations for global and user-specific foods.
 */

function getAvailableFoods(PDO $db, int $userId): array {
    $stmt = $db->prepare('
        SELECT food_id, food_name, serving_size_g, calories, protein_g, fat_g, carbs_g,
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
        SELECT food_id, food_name, serving_size_g, calories, protein_g, fat_g, carbs_g,
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
        INSERT INTO foods (user_id, food_name, serving_size_g, calories, protein_g, fat_g, carbs_g)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId,
        $data['food_name'],
        $data['serving_size_g'],
        $data['calories'],
        $data['protein_g'],
        $data['fat_g'],
        $data['carbs_g'],
    ]);
    return (int)$db->lastInsertId();
}

function calculateMacros(array $food, float $qty_g): array {
    $ratio = $qty_g / (float)$food['serving_size_g'];
    return [
        'calories'  => (int)round($food['calories'] * $ratio),
        'protein_g' => round($food['protein_g'] * $ratio, 1),
        'fat_g'     => round($food['fat_g'] * $ratio, 1),
        'carbs_g'   => round($food['carbs_g'] * $ratio, 1),
    ];
}

function getUserCustomFoods(PDO $db, int $userId): array {
    $stmt = $db->prepare('
        SELECT food_id, food_name, serving_size_g, calories, protein_g, fat_g, carbs_g, created_at
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