<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

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
    $type = $_GET['type'] ?? 'pending';

    if ($type === 'history') {
        // Fetch approved/rejected claims
        $sql = "
            SELECT c.id, c.description, c.created_at as date, c.status,
                   i.title, i.item_type,
                   u.full_name as reporter_name, u.email as reporter_email
            FROM claims c
            JOIN items i ON c.item_id = i.id
            JOIN users u ON c.user_id = u.id
            WHERE c.status != 'pending'
            ORDER BY c.updated_at DESC
        ";
    } else {
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
    }
    
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
    
    if (!$id || !in_array($status, ['approved', 'rejected'])) {
        lf_send_json(422, ['status' => 'error', 'message' => 'Invalid parameters']);
    }

    try {
        $conn->begin_transaction();

        // 1. Update Claim Status
        $stmt = $conn->prepare("UPDATE claims SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update claim status");
        }

        // 2. If Approved, Mark Item as Claimed
        if ($status === 'approved') {
            // Get item_id from claim
            $getClaim = $conn->prepare("SELECT item_id FROM claims WHERE id = ?");
            $getClaim->bind_param("i", $id);
            $getClaim->execute();
            $claimRow = $getClaim->get_result()->fetch_assoc();
            
            if ($claimRow) {
                $itemId = $claimRow['item_id'];
                $updateItem = $conn->prepare("UPDATE items SET status = 'claimed' WHERE id = ?");
                $updateItem->bind_param("i", $itemId);
                if (!$updateItem->execute()) {
                    throw new Exception("Failed to update item status");
                }

                // --- 3. SEND EMAIL NOTIFICATION ---
                // Fetch User (Claimer) and Item details
                $detailsQuery = $conn->prepare("
                    SELECT u.email, u.full_name, i.title
                    FROM claims c
                    JOIN users u ON c.user_id = u.id
                    JOIN items i ON c.item_id = i.id
                    WHERE c.id = ?
                ");
                $detailsQuery->bind_param("i", $id);
                $detailsQuery->execute();
                $details = $detailsQuery->get_result()->fetch_assoc();

                if ($details) {
                    $to = $details['email'];
                    $name = $details['full_name'];
                    $item = $details['title'];
                    $subject = "Claim Approved: $item";
                    $message = "Hello $name,\n\n";
                    $message .= "Good news! Your claim for the item '$item' has been approved by the administrators.\n\n";
                    $message .= "Please visit the Lost & Found office during working hours (9 AM - 5 PM) to collect your item.\n";
                    $message .= "Bring your Student ID for verification.\n\n";
                    $message .= "Regards,\nLost & Found Team";

                    lf_send_email($to, $subject, $message);
                }
            }
        }

        $conn->commit();
        lf_send_json(200, ['status' => 'success', 'message' => 'Status updated']);

    } catch (Exception $e) {
        $conn->rollback();
        lf_send_json(500, ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
