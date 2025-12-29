-- Database Schema for Lost & Found Portal
-- Generated on 2025-12-29

CREATE DATABASE IF NOT EXISTS `lost_and_found_2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lost_and_found_2`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `icon` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Categories
INSERT IGNORE INTO `categories` (`name`, `description`, `icon`) VALUES
('Electronics', 'Phones, laptops, tablets, etc.', 'laptop'),
('Documents', 'IDs, passports, certificates', 'file-text'),
('Accessories', 'Jewelry, watches, glasses', 'watch'),
('Clothing', 'Jackets, hats, shoes', 'shirt'),
('Bags', 'Backpacks, wallets, purses', 'briefcase'),
('Keys', 'House keys, car keys', 'key'),
('Other', 'Other items', 'package');

-- Items Table
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `item_type` ENUM('lost', 'found') NOT NULL,
    `status` ENUM('open', 'claimed', 'resolved') DEFAULT 'open',
    `location_found` VARCHAR(255),
    `location_lost` VARCHAR(255),
    `date_reported` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_lost_found` DATETIME NOT NULL,
    `date_resolved` TIMESTAMP NULL DEFAULT NULL,
    `reward` DECIMAL(10,2) DEFAULT 0.00,
    `is_returned` BOOLEAN DEFAULT FALSE,
    `contact_info` VARCHAR(255),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item Images Table
CREATE TABLE IF NOT EXISTS `item_images` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` BOOLEAN DEFAULT FALSE,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Claims Table
CREATE TABLE IF NOT EXISTS `claims` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes
CREATE INDEX IF NOT EXISTS `idx_items_status` ON `items` (`status`);
CREATE INDEX IF NOT EXISTS `idx_items_type` ON `items` (`item_type`);
CREATE INDEX IF NOT EXISTS `idx_items_user` ON `items` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_items_category` ON `items` (`category_id`);
CREATE INDEX IF NOT EXISTS `idx_claims_item` ON `claims` (`item_id`);
CREATE INDEX IF NOT EXISTS `idx_claims_user` ON `claims` (`user_id`);
