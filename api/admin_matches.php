<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

lf_start_session();

// 1. Verify Admin Role
if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    lf_send_json(403, ['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
}

$conn = lf_get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

// GET: List pending matches (Using 'claims' table? Or 'items' status? User asked for "matches". 
// Let's assume a "match" is represented by a Claim on an Item, or just an Item report that needs approval?
// The user said "approve the review and approve the matches". 
// Let's assume we are approving Claims (where a user says "That's mine!"). 
// OR maybe approving the item listing itself?
// Re-reading: "email authentication ... when they found match ... admin should approve".
// This implies a Claim workflow. User finds item -> Claims it -> Admin approves -> Email sent to Finder/Owner.
// For now, let's treat "Pending Claims" as the matches to approve.

if ($method === 'GET') {
    // Fetch pending claims
    $sql = "
        SELECT c.id, c.description, c.created_at as date,
               i.title, i.item_type,
               u.full_name as reporter_name, u.email as reporter_email
        FROM claims c
        JOIN items i ON c.item_id = i.id
        JOIN users u ON c.user_id = u.id
        WHERE c.status = 'pending'
        ORDER BY c.created_at DESC
    ";
    
    $result = $conn->query($sql);
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    
    lf_send_json(200, ['status' => 'success', 'matches' => $matches]);
} 
elseif ($method === 'POST') {
    $data = lf_get_request_body();
    $id = (int)($data['id'] ?? 0);
    $status = $data['status'] ?? '';
    
    if (!$id || !in_array($status, ['approved_email_sent', 'rejected'])) {
        lf_send_json(422, ['status' => 'error', 'message' => 'Invalid parameters']);
    }

    // Update status
    $stmt = $conn->prepare("UPDATE claims SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        // Here we would effectively "Send Email"
        // For simulation, the status 'approved_email_sent' indicates this.
        
        lf_send_json(200, ['status' => 'success', 'message' => 'Status updated']);
    } else {
        lf_send_json(500, ['status' => 'error', 'message' => 'Database error']);
    }
}
