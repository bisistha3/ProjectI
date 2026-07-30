-- =============================================
-- HydroFlow — Database Setup
-- Run this script manually in MySQL to create
-- the database and required tables.
-- =============================================

CREATE DATABASE IF NOT EXISTS hydroflow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hydroflow;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    gender ENUM('male', 'female') DEFAULT 'male',
    age INT DEFAULT NULL,
    weight DECIMAL(5,1) DEFAULT NULL,
    height DECIMAL(5,1) DEFAULT NULL
) ENGINE=InnoDB;
