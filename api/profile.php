<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

lf_start_session();

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    lf_send_json(401, [
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

try {
    $conn = lf_get_db_connection();
    
    // Get user basic info
    $stmt = $conn->prepare("
        SELECT id, full_name, email, phone, student_id, created_at 
        FROM users 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        lf_send_json(404, [
            'status' => 'error',
            'message' => 'User not found'
        ]);
        exit;
    }

    // Get user statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN item_type = 'lost' THEN 1 END) as reports_filed,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as items_recovered,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_claims
        FROM items 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Get recent reports
    $stmt = $conn->prepare("
        SELECT id, title, item_type as type, 
               CASE WHEN item_type = 'lost' THEN location_lost ELSE location_found END as location, 
               date_reported as created_at, status
        FROM items 
        WHERE user_id = ? 
        ORDER BY date_reported DESC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recentReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    lf_send_json(200, [
        'status' => 'success',
        'user' => [
            'id' => (int)$user['id'],
            'fullName' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? 'Not provided',
            'studentId' => $user['student_id'] ?? 'Not provided',
            'memberSince' => date('F Y', strtotime($user['created_at'])),
            'avatar' => strtoupper(substr($user['full_name'], 0, 2)) // Generate initials
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
        }, $recentReports)
    ]);

} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    lf_send_json(500, [
        'status' => 'error',
        'message' => 'Failed to fetch profile data: ' . $e->getMessage()
    ]);
}
