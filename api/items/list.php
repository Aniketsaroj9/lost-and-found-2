<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

lf_start_session();

$conn = lf_get_db_connection();
lf_ensure_items_table($conn);

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : null;
$mineOnly = isset($_GET['mine']);
$currentUser = $_SESSION['user_id'] ?? null;

$query = 'SELECT i.*, u.full_name AS reporter_name FROM items i INNER JOIN users u ON i.user_id = u.id';
$conditions = [];
$params = [];
$types = '';

if ($type && in_array($type, ['lost', 'found'], true)) {
    $conditions[] = 'i.type = ?';
    $params[] = $type;
    $types .= 's';
}

if ($mineOnly) {
    if (!$currentUser) {
        lf_send_json(401, ['status' => 'error', 'message' => 'Login required to view your reports.']);
    }
    $conditions[] = 'i.user_id = ?';
    $params[] = $currentUser;
    $types .= 'i';
}

if ($conditions) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}

$query .= ' ORDER BY i.updated_at DESC';

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

lf_send_json(200, ['status' => 'success', 'items' => $items]);
