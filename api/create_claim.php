<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

lf_require_post();
lf_start_session();

// 1. Verify Auth
if (!isset($_SESSION['user_id'])) {
    lf_send_json(401, ['status' => 'error', 'message' => 'Please login to claim an item.']);
}

$userId = (int)$_SESSION['user_id'];
$data = lf_get_request_body();
$itemId = (int)($data['item_id'] ?? 0);
$description = trim((string)($data['description'] ?? ''));

// 2. Validate Inputs
if (!$itemId || empty($description)) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Valid Item ID and description are required.']);
}

$conn = lf_get_db_connection();

// 3. Check if user already claimed this item
$check = $conn->prepare("SELECT id FROM claims WHERE user_id = ? AND item_id = ?");
$check->bind_param("ii", $userId, $itemId);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    lf_send_json(409, ['status' => 'error', 'message' => 'You have already submitted a claim for this item.']);
}

// 4. Create Claim
$stmt = $conn->prepare("INSERT INTO claims (user_id, item_id, description, status) VALUES (?, ?, ?, 'pending')");
$stmt->bind_param("iis", $userId, $itemId, $description);

if ($stmt->execute()) {
    lf_send_json(201, ['status' => 'success', 'message' => 'Claim submitted! An admin will review it shortly.']);
} else {
    lf_send_json(500, ['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
