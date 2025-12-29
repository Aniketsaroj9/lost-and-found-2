<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

const LF_ALLOWED_STATUSES = ['open', 'matched', 'claimed', 'closed'];

function lf_require_auth_user(): int
{
    lf_start_session();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        lf_send_json(401, ['status' => 'error', 'message' => 'Authentication required.']);
    }
    return (int)$userId;
}

function lf_ensure_items_table(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type ENUM(\'lost\', \'found\') NOT NULL,
            name VARCHAR(120) NOT NULL,
            category VARCHAR(60) NOT NULL,
            description TEXT NOT NULL,
            location VARCHAR(150) NOT NULL,
            event_at DATETIME NOT NULL,
            image_url VARCHAR(255) DEFAULT NULL,
            status ENUM(\'open\', \'matched\', \'claimed\', \'closed\') NOT NULL DEFAULT \'open\',
            claim_note TEXT DEFAULT NULL,
            claimed_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}
