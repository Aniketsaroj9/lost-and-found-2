<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

lf_require_post();
$userId = lf_require_auth_user();

$data = lf_get_request_body();
$type = strtolower(trim((string)($data['type'] ?? 'lost')));
$allowedTypes = ['lost', 'found'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'lost';
}

$name = trim((string)($data['name'] ?? ''));
$category = trim((string)($data['category'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$location = trim((string)($data['location'] ?? ''));
$eventAt = trim((string)($data['eventAt'] ?? ''));
$imageUrl = trim((string)($data['imageUrl'] ?? '')) ?: null;

if ($name === '' || $category === '' || $description === '' || $location === '' || $eventAt === '') {
    lf_send_json(422, ['status' => 'error', 'message' => 'All fields are required.']);
}

$conn = lf_get_db_connection();
lf_ensure_items_table($conn);

$stmt = $conn->prepare('INSERT INTO items (user_id, type, name, category, description, location, event_at, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "open")');
$stmt->bind_param('isssssss', $userId, $type, $name, $category, $description, $location, $eventAt, $imageUrl);
$stmt->execute();

lf_send_json(201, [
    'status' => 'success',
    'message' => 'Item reported successfully.',
    'itemId' => $stmt->insert_id,
]);
