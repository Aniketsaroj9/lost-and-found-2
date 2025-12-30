<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $conn = lf_get_db_connection();
    
    // Test if users table exists and has data
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $userCount = $result->fetch_assoc()['count'];
    
    // Test if items table exists
    $result = $conn->query("SELECT COUNT(*) as count FROM items");
    $itemCount = $result->fetch_assoc()['count'];
    
    lf_send_json(200, [
        'status' => 'success',
        'message' => 'Database connection successful',
        'data' => [
            'userCount' => (int)$userCount,
            'itemCount' => (int)$itemCount,
            'database' => DB_NAME,
            'host' => DB_HOST
        ]
    ]);
    
} catch (Exception $e) {
    lf_send_json(500, [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
