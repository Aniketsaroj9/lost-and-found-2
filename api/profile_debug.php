<?php

/**
 * Profile API - Returns user profile data in JSON format
 * Debug version with detailed error reporting
 */

declare(strict_types=1);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/config.php';

// Start session
lf_start_session();

// Get user ID from session
$userId = $_SESSION['user_id'] ?? null;

// Debug: Log session info
error_log("Profile API - Session user_id: " . ($userId ?? 'null'));
error_log("Profile API - Session data: " . json_encode($_SESSION));

// Check if user is authenticated
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated - no session found',
        'debug' => [
            'session_id' => session_id(),
            'session_data' => $_SESSION,
            'cookies' => $_COOKIE
        ]
    ]);
    exit;
}

try {
    // Get database connection
    $conn = lf_get_db_connection();
    
    // Fetch user basic information
    $stmt = $conn->prepare("
        SELECT id, full_name, email, created_at 
        FROM users 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Debug: Log user query result
    error_log("Profile API - User query result: " . json_encode($user));

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'User not found in database',
            'debug' => [
                'user_id' => $userId,
                'query' => "SELECT id, full_name, email, created_at FROM users WHERE id = $userId"
            ]
        ]);
        exit;
    }

    // Fetch user statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN type = 'lost' THEN 1 END) as reports_filed,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as items_recovered,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_claims
        FROM items 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Debug: Log stats result
    error_log("Profile API - Stats result: " . json_encode($stats));

    // Fetch recent reports
    $stmt = $conn->prepare("
        SELECT id, title, type, location, created_at, status
        FROM items 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recentReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Debug: Log recent reports
    error_log("Profile API - Recent reports count: " . count($recentReports));

    // Prepare response data
    $response = [
        'status' => 'success',
        'message' => 'Profile data fetched successfully',
        'user' => [
            'id' => (int)$user['id'],
            'fullName' => $user['full_name'],
            'email' => $user['email'],
            'phone' => 'Not provided',
            'studentId' => 'Not provided',
            'memberSince' => date('F Y', strtotime($user['created_at'])),
            'avatar' => strtoupper(substr($user['full_name'], 0, 2))
        ],
        'stats' => [
            'reportsFiled' => (int)($stats['reports_filed'] ?? 0),
            'itemsRecovered' => (int)($stats['items_recovered'] ?? 0),
            'pendingClaims' => (int)($stats['pending_claims'] ?? 0)
        ],
        'recentReports' => array_map(function($report) {
            return [
                'id' => (int)$report['id'],
                'title' => $report['title'],
                'type' => $report['type'],
                'location' => $report['location'],
                'date' => date('M d, Y', strtotime($report['created_at'])),
                'status' => $report['status'] ?? 'pending'
            ];
        }, $recentReports),
        'debug' => [
            'user_id' => $userId,
            'session_valid' => true,
            'database_connected' => true
        ]
    ];

    // Set headers and return JSON
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log error
    error_log("Profile API Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch profile data: ' . $e->getMessage(),
        'debug' => [
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'user_id' => $userId ?? 'null'
        ]
    ]);
}
?>
