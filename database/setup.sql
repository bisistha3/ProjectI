-- =============================================
-- HydroFlow — Database Setup
-- Run this script in phpMyAdmin or MySQL CLI.
-- =============================================

CREATE DATABASE IF NOT EXISTS hydroflow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hydroflow;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    gender        ENUM('male', 'female') DEFAULT 'male',
    age           INT DEFAULT NULL,
    weight        DECIMAL(5,1) DEFAULT NULL,
    height        DECIMAL(5,1) DEFAULT NULL,
    daily_goal_ml INT NOT NULL DEFAULT 2500,
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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

-- Safe migrations for existing installs
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified   TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_goal_ml INT NOT NULL DEFAULT 2500;
