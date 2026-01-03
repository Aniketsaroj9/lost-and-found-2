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

    // 1. GLOBAL STATS (Campus Pulse)
    // -----------------------------
    
    // Open Reports: Items that are open
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE status = 'open'");
    $stmt->execute();
    $globalOpen = $stmt->get_result()->fetch_assoc()['count'];

    // Matches in Review: Pending claims across the system
    // (Or could be interpreted as 'Found' items that might match 'Lost' items, but 'Review' implies human action, usually claims)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM claims WHERE status = 'pending'");
    $stmt->execute();
    $globalMatches = $stmt->get_result()->fetch_assoc()['count'];

    // Awaiting Pickup: Items that are claimed/approved but not yet resolved (finalized)
    // We'll assume 'claimed' status in items table reflects this state, or approved claims.
    // Based on schema: status ENUM('open', 'claimed', 'resolved')
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE status = 'claimed'");
    $stmt->execute();
    $globalPickup = $stmt->get_result()->fetch_assoc()['count'];


    // 2. USER STATS (Personal Dashboard)
    // ----------------------------------

    // Reports filed by you
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userReports = $stmt->get_result()->fetch_assoc()['count'];

    // Resolved cases (by you)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE user_id = ? AND status = 'resolved'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userResolved = $stmt->get_result()->fetch_assoc()['count'];

    // Pending Verifications:
    // Claims made by OTHERS on items reported by YOU (specifically 'found' items usually, but let's check all claims on user's items)
    // This requires your action to approve/reject.
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM claims c
        JOIN items i ON c.item_id = i.id
        WHERE i.user_id = ? AND c.status = 'pending'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userPending = $stmt->get_result()->fetch_assoc()['count'];


    lf_send_json(200, [
        'status' => 'success',
        'data' => [
            'campus' => [
                'open_reports' => (int)$globalOpen,
                'matches_review' => (int)$globalMatches,
                'awaiting_pickup' => (int)$globalPickup
            ],
            'user' => [
                'reports_filed' => (int)$userReports,
                'resolved_cases' => (int)$userResolved,
                'pending_verifications' => (int)$userPending
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    lf_send_json(500, [
        'status' => 'error',
        'message' => 'Failed to fetch dashboard stats'
    ]);
}
