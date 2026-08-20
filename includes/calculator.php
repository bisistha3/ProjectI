<?php
// Health & nutrition calculators

// Daily water goal from BMI
function calcWaterGoal(float $weight, float $height, string $gender, string $activity = 'medium'): int {
    $heightM = $height / 100;
    $bmi     = ($heightM > 0) ? $weight / ($heightM ** 2) : 22.0;
    if      ($bmi < 18.5) $mult = 40;
    elseif  ($bmi < 25.0) $mult = 35;
    elseif  ($bmi < 30.0) $mult = 30;
    else                  $mult = 25;

    $goal = (int)round($weight * $mult);
    if ($gender === 'female') $goal = (int)round($goal * 0.9);

    $bonus = match($activity) {
        'low'  => 0,
        'high' => 1000,
        default => 500,
    };
    return max(1500, min(5000, $goal + $bonus));
}

// BMR — Mifflin-St Jeor
function calcBmr(float $weight, float $height, int $age, string $gender): float {
    $base = 10 * $weight + 6.25 * $height - 5 * $age;
    return $gender === 'female' ? $base - 161 : $base + 5;
}

// TDEE = BMR x activity factor
function calcTdee(float $weight, float $height, int $age, string $gender, string $activity = 'medium'): int {
    $factors = ['low' => 1.375, 'medium' => 1.55, 'high' => 1.725];
    $factor  = $factors[$activity] ?? 1.55;
    return (int)round(calcBmr($weight, $height, $age, $gender) * $factor);
}

// Calorie + macro split from TDEE
function calcNutritionGoals(float $weight, float $height, int $age, string $gender, string $activity = 'medium'): array {
    $tdee      = calcTdee($weight, $height, $age, $gender, $activity);
    $calories  = (int)round($tdee / 5) * 5;
    $protein   = (int)round(($calories * 0.25) / 4 / 5) * 5;
    $fat       = (int)round(($calories * 0.30) / 9 / 5) * 5;
    $carbs     = (int)round(($calories * 0.45) / 4 / 5) * 5;

    $calories = max(1200, min(5000, $calories));
    $protein  = max(40,  min(300, $protein));
    $fat      = max(20,  min(150, $fat));
    $carbs    = max(100, min(600, $carbs));

    return [
        'calories'   => $calories,  
        'protein_g'  => $protein,
        'fat_g'      => $fat,
        'carbs_g'    => $carbs,
    ];
}