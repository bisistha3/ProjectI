-- =============================================
-- HealthFlow — Database Setup
-- Run this script in phpMyAdmin or MySQL CLI.
-- =============================================

CREATE DATABASE IF NOT EXISTS healthflow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
    
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
) ENGINE=InnoDB;

-- Email OTP verification tokens
CREATE TABLE IF NOT EXISTS email_otps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    otp_code   CHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Water intake logs
CREATE TABLE IF NOT EXISTS water_logs (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    amount_ml  INT NOT NULL,
    drink_type VARCHAR(50) NOT NULL DEFAULT 'Water',
    logged_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Food intake logs (calories + macros per serving)
CREATE TABLE IF NOT EXISTS food_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    food_name   VARCHAR(100) NOT NULL,
    meal_type   ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL DEFAULT 'snack',
    calories    INT NOT NULL,
    protein_g   DECIMAL(5,1) NOT NULL DEFAULT 0,
    fat_g       DECIMAL(5,1) NOT NULL DEFAULT 0,
    carbs_g     DECIMAL(5,1) NOT NULL DEFAULT 0,
    qty         VARCHAR(50) NOT NULL DEFAULT '1 serving',
    logged_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Exercise logs (duration + auto-calculated calories burned)
CREATE TABLE IF NOT EXISTS exercise_logs (
    log_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    exercise_type   VARCHAR(50) NOT NULL,
    duration_min    INT NOT NULL,
    calories_burned INT NOT NULL,
    logged_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User's saved custom foods (auto-saved from the log page for one-click logging)
CREATE TABLE IF NOT EXISTS custom_foods (
    food_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    food_name  VARCHAR(100) NOT NULL,
    calories   INT NOT NULL,
    protein_g  DECIMAL(5,1) NOT NULL DEFAULT 0,
    fat_g      DECIMAL(5,1) NOT NULL DEFAULT 0,
    carbs_g    DECIMAL(5,1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_custom_food (user_id, food_name),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Safe migrations for existing installs (hydroflow → healthflow)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified   TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_goal_ml INT NOT NULL DEFAULT 2500;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_calorie_goal   INT NOT NULL DEFAULT 2000;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_protein_goal_g INT NOT NULL DEFAULT 125;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_fat_goal_g     INT NOT NULL DEFAULT 67;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_carbs_goal_g   INT NOT NULL DEFAULT 225;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_exercise_goal_min INT NOT NULL DEFAULT 30;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reminder_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reminder_time    TIME NOT NULL DEFAULT '20:00:00';
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_burn_goal_kcal INT NOT NULL DEFAULT 300;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reminder_interval_min INT NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS wake_time TIME NOT NULL DEFAULT '07:00:00';
ALTER TABLE users ADD COLUMN IF NOT EXISTS sleep_time TIME NOT NULL DEFAULT '22:00:00';

-- Custom foods library (safe migration)
CREATE TABLE IF NOT EXISTS custom_foods (
    food_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    food_name  VARCHAR(100) NOT NULL,
    calories   INT NOT NULL,
    protein_g  DECIMAL(5,1) NOT NULL DEFAULT 0,
    fat_g      DECIMAL(5,1) NOT NULL DEFAULT 0,
    carbs_g    DECIMAL(5,1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_custom_food (user_id, food_name),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;