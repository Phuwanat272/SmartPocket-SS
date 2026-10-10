-- ================================================
-- SmartPocket SS - Term Project by Phuwanat Phuthongkhao 68011211138
-- MySQL Schema - Import via phpMyAdmin on XAMPP
-- ================================================

CREATE DATABASE IF NOT EXISTS `income_expense_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `income_expense_db`;

-- 1. Users Table (Password Hashing & Roles - Requirements 1 & 2)
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories Table (Form 2 & Category Management)
CREATE TABLE IF NOT EXISTS `categories` (
    `id`      INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,  -- NULL = System default, INT = Custom user category
    `name`    VARCHAR(100) NOT NULL,
    `type`    ENUM('income', 'expense') NOT NULL,
    `icon`    VARCHAR(50)  DEFAULT 'bi-tag',
    `color`   VARCHAR(20)  DEFAULT '#4F46E5',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Transactions Table (CRUD Data Ownership - Requirement 3 & Form 1 - Requirement 4)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT           NOT NULL,  -- Owner of data (Data Ownership)
    `title`         VARCHAR(100)  NOT NULL,
    `amount`        DECIMAL(12,2) NOT NULL,
    `type`          ENUM('income', 'expense') NOT NULL,
    `category_name` VARCHAR(100)  NOT NULL,
    `date`          DATE          NOT NULL,
    `note`          TEXT,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- Seed: Default System Categories (ภาษาไทย)
-- ================================================
INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`) VALUES
(NULL, 'เงินเดือน',   'income',  'bi-briefcase',     '#10B981'),
(NULL, 'ธุรกิจ/โบนัส',          'income',  'bi-shop',          '#F59E0B'),
(NULL, 'การลงทุน',              'income',  'bi-graph-up-arrow','#3B82F6'),
(NULL, 'รายรับอื่นๆ',           'income',  'bi-wallet2',       '#8B5CF6'),
(NULL, 'ค่าสาธารณูปโภค',        'expense', 'bi-receipt',       '#6366F1'),
(NULL, 'ค่ารักษาพยาบาล',        'expense', 'bi-heart-pulse',   '#EF4444'),
(NULL, 'ค่าเดินทาง',            'expense', 'bi-car-front',     '#F97316'),
(NULL, 'ค่าบันเทิง/ท่องเที่ยว', 'expense', 'bi-film',          '#EC4899'),
(NULL, 'ค่าอาหาร',              'expense', 'bi-cup-hot',       '#EAB308'),
(NULL, 'รายจ่ายอื่นๆ',          'expense', 'bi-cart',          '#64748B');

-- ================================================
-- Seed: Users
-- Passwords MUST be hashed by PHP — run seed_passwords.php after import
-- Temp plain passwords listed below for reference (change after setup):
--   admin     -> admin123
--   demo_user -> 123456
--   others    -> 123456
-- ================================================
-- NOTE: Hash these with PHP: password_hash('admin123', PASSWORD_DEFAULT)
-- Use the seed_passwords.php helper script to insert correctly hashed users.

-- ================================================
-- Seed: Sample Transactions for demo_user (user_id = 2) — run AFTER seeding users
-- ================================================
-- These will be inserted by seed_passwords.php automatically.

-- ================================================
-- IMPORTANT: After importing this schema, run seed_passwords.php in browser
-- or use the config.php SQLite fallback which seeds automatically.
-- ================================================
