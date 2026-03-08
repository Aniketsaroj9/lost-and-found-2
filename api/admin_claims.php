<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

lf_start_session();

if (!isset($_SESSION['user_id'])) {
    lf_send_json(401, ['status' => 'error', 'message' => 'Unauthorized']);
}

$userId = (int)$_SESSION['user_id'];
$conn = lf_get_db_connection();

// Verify Admin Role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$roleResult = $stmt->get_result();
if ($roleResult->num_rows === 0) {
    lf_send_json(401, ['status' => 'error', 'message' => 'Unauthorized']);
}
$userRow = $roleResult->fetch_assoc();
if (($userRow['role'] ?? '') !== 'admin') {
    lf_send_json(403, ['status' => 'error', 'message' => 'Forbidden']);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $type = $_GET['type'] ?? 'pending';
    
    if ($type === 'history') {
        $sql = "
            SELECT c.id as claim_id, c.description, c.status, c.created_at as claim_date,
                   i.id as item_id, i.title, i.item_type as type,
                   u.full_name as reporter_name, u.email as reporter_email
            FROM claims c
            JOIN items i ON c.item_id = i.id
            JOIN users u ON c.user_id = u.id
            WHERE c.status != 'pending'
            ORDER BY c.updated_at DESC
        ";
    } else {
        $sql = "
            SELECT c.id as claim_id, c.description, c.status, c.created_at as claim_date,
                   i.id as item_id, i.title, i.item_type as type,
                   u.full_name as reporter_name, u.email as reporter_email
            FROM claims c
            JOIN items i ON c.item_id = i.id
            JOIN users u ON c.user_id = u.id
            WHERE c.status = 'pending'
            ORDER BY c.created_at DESC
        ";
    }
    
    $res = $conn->query($sql);
    $claims = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $claims[] = [
                'claim_id'       => $row['claim_id'],
                'item_id'        => $row['item_id'],
                'title'          => $row['title'],
                'description'    => $row['description'],
                'status'         => $row['status'],
                'reporter_name'  => $row['reporter_name'],
                'reporter_email' => $row['reporter_email'],
                'item_type'      => ucfirst(strtolower($row['type'])),
                'date'           => date('M j, Y', strtotime($row['claim_date']))
            ];
        }
    }
    
    lf_send_json(200, ['status' => 'success', 'claims' => $claims]);

} elseif ($method === 'POST') {
    $claimId = (int)($_POST['claim_id'] ?? 0);
    $newStatus = strtolower(trim($_POST['status'] ?? ''));
    
    if (!$claimId || !in_array($newStatus, ['approved', 'rejected'])) {
        lf_send_json(422, ['status' => 'error', 'message' => 'Invalid parameters.']);
    }
    
    $stmt = $conn->prepare("UPDATE claims SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $claimId);
    
    if ($stmt->execute()) {
        if ($newStatus === 'approved') {
            // Also close the item since it's approved and handed over
            $stmt2 = $conn->prepare("
                UPDATE items 
                SET status = 'resolved', date_resolved = NOW(), is_returned = 1
                WHERE id = (SELECT item_id FROM claims WHERE id = ?)
            ");
            $stmt2->bind_param("i", $claimId);
            $stmt2->execute();
            
            // Send email
            $verify = $conn->prepare("
                SELECT u.email, u.full_name, i.title, i.item_type 
                FROM claims c 
                JOIN users u ON c.user_id = u.id 
                JOIN items i ON c.item_id = i.id 
                WHERE c.id = ?
            ");
            $verify->bind_param("i", $claimId);
            $verify->execute();
            $result = $verify->get_result();
            if ($result && $result->num_rows > 0) {
                $detail = $result->fetch_assoc();
                $subject = "Your {$detail['item_type']} claim was approved!";
                $msg = "Hello {$detail['full_name']},\n\nGood news! Your manual ownership claim for '{$detail['title']}' has been reviewed and approved by the admin. Please proceed to the campus desk to retrieve your item.";
                lf_send_email($detail['email'], $subject, $msg);
            }
        }
        lf_send_json(200, ['status' => 'success']);
    } else {
        lf_send_json(500, ['status' => 'error', 'message' => 'DB error.']);
    }
}
