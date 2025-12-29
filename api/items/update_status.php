<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

lf_require_post();
$userId = lf_require_auth_user();

$data = lf_get_request_body();
$itemId = (int)($data['itemId'] ?? 0);
$newStatus = strtolower(trim((string)($data['status'] ?? '')));
$claimNote = isset($data['claimNote']) ? trim((string)$data['claimNote']) : null;

if ($itemId <= 0 || !in_array($newStatus, LF_ALLOWED_STATUSES, true)) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Invalid item or status.']);
}

$conn = lf_get_db_connection();
lf_ensure_items_table($conn);

$stmt = $conn->prepare('SELECT user_id, claimed_by FROM items WHERE id = ?');
$stmt->bind_param('i', $itemId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
if (!$result) {
    lf_send_json(404, ['status' => 'error', 'message' => 'Item not found.']);
}

$currentClaimedBy = $result['claimed_by'] !== null ? (int)$result['claimed_by'] : null;
$newClaimedBy = $currentClaimedBy;

if ($newStatus === 'claimed') {
    $newClaimedBy = $userId;
} elseif ($newStatus === 'open') {
    $newClaimedBy = null;
}

if ($newClaimedBy === null) {
    $allowed = $conn->prepare('UPDATE items SET status = ?, claim_note = ?, claimed_by = NULL WHERE id = ?');
    $allowed->bind_param('ssi', $newStatus, $claimNote, $itemId);
} else {
    $allowed = $conn->prepare('UPDATE items SET status = ?, claim_note = ?, claimed_by = ? WHERE id = ?');
    $allowed->bind_param('ssii', $newStatus, $claimNote, $newClaimedBy, $itemId);
}
$allowed->execute();

lf_send_json(200, ['status' => 'success', 'message' => 'Status updated.']);
