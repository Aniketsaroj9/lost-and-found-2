<?php
require __DIR__ . '/config.php';

header('Content-Type: text/plain');

try {
    $conn = lf_get_db_connection();
    echo "Connected to database: " . DB_NAME . "\n\n";

    echo "Tables:\n";
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "\n";
        
        // simple check query
        $check = $conn->query("SELECT COUNT(*) FROM `" . $row[0] . "`");
        if ($check) {
            $count = $check->fetch_row()[0];
            echo "  (OK, Rows: $count)\n";
        } else {
            echo "  (ERROR: " . $conn->error . ")\n";
        }
    }
    
    if ($result->num_rows === 0) {
        echo "No tables found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
