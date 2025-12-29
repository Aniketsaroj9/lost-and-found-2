<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $conn = lf_get_db_connection();
    echo json_encode(['status' => 'success', 'message' => 'Database connection successful!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
