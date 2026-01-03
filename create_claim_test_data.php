<?php
require_once 'api/config.php';

$conn = lf_get_db_connection();

// 1. Create Test User
$userEmail = 'claimtester@example.com';
$userPass = 'password123';
$hashedPass = password_hash($userPass, PASSWORD_DEFAULT);
$userName = 'Claim Tester';

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'user') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
$stmt->bind_param("sss", $userName, $userEmail, $hashedPass);
$stmt->execute();
$userId = $stmt->insert_id;

// 2. Create Test Found Item (reported by someone else, e.g. admin or another user)
// We'll just assign it to user_id=0 or 1 if exists, or just null if schema allows, but schema likely requires user_id.
// Let's create another dummy user 'finder'
$finderEmail = 'finder@example.com';
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES ('Finder', ?, ?, 'user') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
$stmt->bind_param("ss", $finderEmail, $hashedPass);
$stmt->execute();
$finderId = $stmt->insert_id;

$itemTitle = "Test Found Keys " . rand(1000, 9999);
$stmt = $conn->prepare("INSERT INTO items (user_id, title, description, category, location, date_lost_found, item_type, status) VALUES (?, ?, 'Found these keys', 'Accessories', 'Library', NOW(), 'found', 'open')");
$stmt->bind_param("is", $finderId, $itemTitle);
$stmt->execute();
$itemId = $stmt->insert_id;

echo "User created: $userEmail / $userPass\n";
echo "Found Item created: $itemTitle (ID: $itemId)\n";
?>
