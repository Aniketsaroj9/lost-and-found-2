<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain');

$conn = lf_get_db_connection();

echo "Checking Item Images...\n";
$sql = "
    SELECT i.id, i.title, ii.image_path 
    FROM items i 
    LEFT JOIN item_images ii ON i.id = ii.item_id 
    ORDER BY i.id DESC LIMIT 5
";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "Item ID: {$row['id']}, Title: {$row['title']}, Path: " . ($row['image_path'] ?? 'NULL') . "\n";
    }
} else {
    echo "Query Error: " . $conn->error . "\n";
}

echo "\nChecking Uploads Directory...\n";
$uploadDir = __DIR__ . '/../uploads/';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    print_r($files);
} else {
    echo "Uploads dir not found at: $uploadDir\n";
}
