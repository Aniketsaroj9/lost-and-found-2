<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $conn = lf_get_db_connection();
    
    // Force clean slate (WARNING: DELETES ALL DATA)
    $conn->query("DROP DATABASE IF EXISTS `" . DB_NAME . "`");

    // Create database if not exists
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Select the database
    if (!$conn->select_db(DB_NAME)) {
        throw new Exception("Could not select database: " . $conn->error);
    }
    
    // Start transaction
    $conn->begin_transaction();

    // Create users table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(120) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Create categories table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `icon` VARCHAR(50),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Insert default categories
    $conn->query("
        INSERT IGNORE INTO `categories` (`name`, `description`, `icon`) VALUES
        ('Electronics', 'Phones, laptops, tablets, etc.', 'laptop'),
        ('Documents', 'IDs, passports, certificates', 'file-text'),
        ('Accessories', 'Jewelry, watches, glasses', 'watch'),
        ('Clothing', 'Jackets, hats, shoes', 'shirt'),
        ('Bags', 'Backpacks, wallets, purses', 'briefcase'),
        ('Keys', 'House keys, car keys', 'key'),
        ('Other', 'Other items', 'package');
    ");
    
    // Create items table
    $conn->query("
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
    ");
    
    // Create item_images table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `item_images` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT UNSIGNED NOT NULL,
            `image_path` VARCHAR(255) NOT NULL,
            `is_primary` BOOLEAN DEFAULT FALSE,
            `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Create claims table
    $conn->query("
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
    ");
    
    // Create indexes
    $conn->query("CREATE INDEX IF NOT EXISTS `idx_items_status` ON `items` (`status`)");
    $conn->query("CREATE INDEX IF NOT EXISTS `idx_items_type` ON `items` (`item_type`)");
    $conn->query("CREATE INDEX IF NOT EXISTS `idx_items_user` ON `items` (`user_id`)");
    $conn->query("CREATE INDEX IF NOT EXISTS `idx_items_category` ON `items` (`category_id`)");
    $conn->query("CREATE INDEX IF NOT EXISTS `idx_claims_item` ON `claims` (`item_id`)");
    $conn->query("CREATE INDEX IF NOT EXISTS `idx_claims_user` ON `claims` (`user_id`)");
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Database tables created successfully!']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error setting up database: ' . $e->getMessage()]);
}
