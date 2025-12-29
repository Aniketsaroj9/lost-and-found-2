<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain');

$conn = lf_get_db_connection();
$res = $conn->query("SELECT COUNT(*) as count FROM items");
$row = $res->fetch_assoc();
echo "Total Items in DB: " . $row['count'] . "\n";

$res2 = $conn->query("SELECT id, title, user_id FROM items ORDER BY id DESC LIMIT 5");
while ($r = $res2->fetch_assoc()) {
    echo "Item ID: {$r['id']}, Title: {$r['title']}, User ID: {$r['user_id']}\n";
}
