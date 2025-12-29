<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain');

echo "Testing DB Insertion...\n";

try {
    $conn = lf_get_db_connection();
    echo "DB Connected.\n";

    // 1. Create a dummy user
    $email = 'test_' . uniqid() . '@example.com';
    $conn->query("INSERT INTO users (full_name, email, password_hash) VALUES ('Test User', '$email', 'hash')");
    $userId = $conn->insert_id;
    echo "Created Test User ID: $userId\n";

    // 2. Get a category
    $res = $conn->query("SELECT id FROM categories LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        $catId = $row['id'];
        echo "Using Category ID: $catId\n";
    } else {
        die("No categories found! Run setup_tables.php again.\n");
    }

    // 3. Insert Item
    $stmt = $conn->prepare("INSERT INTO items (user_id, category_id, title, description, item_type, location_lost, date_lost_found) VALUES (?, ?, 'Test Item', 'Test Desc', 'lost', 'Test Loc', NOW())");
    $stmt->bind_param("ii", $userId, $catId);
    
    if ($stmt->execute()) {
        echo "Item Inserted Successfully. ID: " . $stmt->insert_id . "\n";
    } else {
        echo "Insert Failed: " . $stmt->error . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
