<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json');

lf_start_session();

// Debug session info
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

try {
    $conn = lf_get_db_connection();
    
    // Get all users for debugging
    $result = $conn->query("SELECT id, full_name, email, created_at FROM users ORDER BY created_at DESC");
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get current user details if logged in
    $currentUser = null;
    if ($userId) {
        $stmt = $conn->prepare("SELECT id, full_name, email, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $currentUser = $stmt->get_result()->fetch_assoc();
    }
    
    lf_send_json(200, [
        'status' => 'success',
        'message' => 'Debug information retrieved',
        'data' => [
            'session' => [
                'userId' => $userId,
                'userName' => $userName,
                'isAuthenticated' => $userId !== null
            ],
            'allUsers' => $users,
            'currentUser' => $currentUser,
            'database' => DB_NAME,
            'host' => DB_HOST
        ]
    ]);
    
} catch (Exception $e) {
    lf_send_json(500, [
        'status' => 'error',
        'message' => 'Debug failed: ' . $e->getMessage()
    ]);
}
