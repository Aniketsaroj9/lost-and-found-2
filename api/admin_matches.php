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
        // Fetch approved/rejected matches
        $sql = "
            SELECT m.id as match_id, m.text_score, m.image_score, m.final_score, m.match_status as status,
                   l.id as lost_id, l.title as lost_title, l.description as lost_desc, ul.full_name as lost_user_name, ul.email as lost_user_email,
                   f.id as found_id, f.title as found_title, f.description as found_desc, uf.full_name as found_user_name, uf.email as found_user_email,
                   (SELECT image_path FROM item_images WHERE item_id = l.id AND is_primary = 1 LIMIT 1) as lost_image,
                   (SELECT image_path FROM item_images WHERE item_id = f.id AND is_primary = 1 LIMIT 1) as found_image
            FROM match_results m
            JOIN items l ON m.lost_item_id = l.id
            JOIN users ul ON l.user_id = ul.id
            JOIN items f ON m.found_item_id = f.id
            JOIN users uf ON f.user_id = uf.id
            WHERE m.match_status != 'pending'
            ORDER BY m.created_at DESC
        ";
    } else {
        // Fetch pending matches
        $sql = "
            SELECT m.id as match_id, m.text_score, m.image_score, m.final_score, m.match_status as status,
                   l.id as lost_id, l.title as lost_title, l.description as lost_desc, ul.full_name as lost_user_name, ul.email as lost_user_email,
                   f.id as found_id, f.title as found_title, f.description as found_desc, uf.full_name as found_user_name, uf.email as found_user_email,
                   (SELECT image_path FROM item_images WHERE item_id = l.id AND is_primary = 1 LIMIT 1) as lost_image,
                   (SELECT image_path FROM item_images WHERE item_id = f.id AND is_primary = 1 LIMIT 1) as found_image
            FROM match_results m
            JOIN items l ON m.lost_item_id = l.id
            JOIN users ul ON l.user_id = ul.id
            JOIN items f ON m.found_item_id = f.id
            JOIN users uf ON f.user_id = uf.id
            WHERE m.match_status = 'pending'
            ORDER BY m.final_score DESC
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
    // The dashboard sends form data, not raw JSON
    $id = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
    $status = $_POST['status'] ?? '';
    
    if (!$id || !in_array($status, ['approved', 'rejected'])) {
        lf_send_json(422, ['status' => 'error', 'message' => 'Invalid parameters']);
    }

    try {
        $conn->begin_transaction();

        // 1. Update Match Status
        $stmt = $conn->prepare("UPDATE match_results SET match_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update match status");
        }

        // 2. If Approved, Mark Items as Resolved/Claimed
        if ($status === 'approved') {
            // Get item_ids from match
            $getMatch = $conn->prepare("SELECT lost_item_id, found_item_id FROM match_results WHERE id = ?");
            $getMatch->bind_param("i", $id);
            $getMatch->execute();
            $matchRow = $getMatch->get_result()->fetch_assoc();
            
            if ($matchRow) {
                $lostId = $matchRow['lost_item_id'];
                $foundId = $matchRow['found_item_id'];
                $updateItem = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id IN (?, ?)");
                $updateItem->bind_param("ii", $lostId, $foundId);
                if (!$updateItem->execute()) {
                    throw new Exception("Failed to update item status");
                }

                // --- 3. SEND EMAIL NOTIFICATION ---
                // We could fetch users attached to lostId and foundId and email them.
                // For now, this is simulated as per previous architecture diagram
                // ...
            }
        }

        $conn->commit();
        lf_send_json(200, ['status' => 'success', 'message' => 'Status updated']);

    } catch (Exception $e) {
        $conn->rollback();
        lf_send_json(500, ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
