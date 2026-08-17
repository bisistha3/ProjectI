-- =============================================
-- HealthFlow — Database Setup
-- Run this script in phpMyAdmin or MySQL CLI.
-- =============================================

CREATE DATABASE IF NOT EXISTS healthflow;

USE healthflow;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name            VARCHAR(100) NOT NULL,
    email                VARCHAR(255) NOT NULL UNIQUE,
    password             VARCHAR(255) NOT NULL,
    gender               ENUM('male', 'female') DEFAULT 'male',
    age                  INT DEFAULT NULL,
    weight               DECIMAL(5,1) DEFAULT NULL,
    height               DECIMAL(5,1) DEFAULT NULL,
    daily_goal_ml        INT NOT NULL DEFAULT 2500,
    daily_calorie_goal   INT NOT NULL DEFAULT 2000,
    daily_protein_goal_g INT NOT NULL DEFAULT 125,
    daily_fat_goal_g     INT NOT NULL DEFAULT 67,
    daily_carbs_goal_g   INT NOT NULL DEFAULT 225,
    daily_exercise_goal_min INT NOT NULL DEFAULT 30,
    daily_burn_goal_kcal    INT NOT NULL DEFAULT 300,
    reminder_enabled       TINYINT(1) NOT NULL DEFAULT 0,
    reminder_time          TIME NOT NULL DEFAULT '20:00:00',
    reminder_interval_min  INT NOT NULL DEFAULT 0,
    wake_time              TIME NOT NULL DEFAULT '07:00:00',
    sleep_time             TIME NOT NULL DEFAULT '22:00:00',
    is_verified            TINYINT(1) NOT NULL DEFAULT 0,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Email OTP verification tokens
CREATE TABLE IF NOT EXISTS email_otps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    otp_code   CHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Water intake logs
CREATE TABLE IF NOT EXISTS water_logs (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    amount_ml  INT NOT NULL,
    drink_type VARCHAR(50) NOT NULL DEFAULT 'Water',
    logged_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Foods table (global + user-specific)
-- unit_type: g (weighable), piece (countable), ml (liquid)
CREATE TABLE IF NOT EXISTS foods (
    food_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,
    food_name       VARCHAR(100) NOT NULL,
    serving_qty     DECIMAL(7,1) NOT NULL DEFAULT 100,
    unit_type       ENUM('g','piece','ml') NOT NULL DEFAULT 'g',
    calories        INT NOT NULL,
    protein_g       DECIMAL(5,1) NOT NULL DEFAULT 0,
    fat_g           DECIMAL(5,1) NOT NULL DEFAULT 0,
    carbs_g         DECIMAL(5,1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Food intake logs (preserves exact nutritional values at time of logging)
CREATE TABLE IF NOT EXISTS food_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    food_id     INT NOT NULL,
    meal_type   ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL DEFAULT 'snack',
    qty         DECIMAL(7,1) NOT NULL DEFAULT 100,
    unit_type   ENUM('g','piece','ml') NOT NULL DEFAULT 'g',
    calories    INT NOT NULL,
    protein_g   DECIMAL(5,1) NOT NULL DEFAULT 0,
    fat_g       DECIMAL(5,1) NOT NULL DEFAULT 0,
    carbs_g     DECIMAL(5,1) NOT NULL DEFAULT 0,
    logged_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (food_id) REFERENCES foods(food_id) ON DELETE RESTRICT
);

-- Exercise logs (duration + auto-calculated calories burned)
CREATE TABLE IF NOT EXISTS exercise_logs (
    log_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    exercise_type   VARCHAR(50) NOT NULL,
    duration_min    INT NOT NULL,
    calories_burned INT NOT NULL,
    logged_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Preset foods (global, user_id = NULL)
INSERT IGNORE INTO foods (user_id, food_name, serving_qty, unit_type, calories, protein_g, fat_g, carbs_g) VALUES
(NULL, 'White Rice', 158, 'g', 200, 4.0, 0.4, 45.0),
(NULL, 'Boiled Egg', 1, 'piece', 70, 6.0, 5.0, 0.6),
(NULL, 'Apple', 1, 'piece', 95, 0.5, 0.3, 25.0),
(NULL, 'Milk', 244, 'ml', 150, 8.0, 8.0, 12.0),
(NULL, 'Chicken Breast', 100, 'g', 165, 31.0, 3.6, 0.0),
(NULL, 'Banana', 1, 'piece', 105, 1.3, 0.4, 27.0),
(NULL, 'Bread', 1, 'piece', 80, 3.0, 1.0, 15.0),
(NULL, 'Oatmeal', 234, 'g', 150, 5.0, 3.0, 27.0);