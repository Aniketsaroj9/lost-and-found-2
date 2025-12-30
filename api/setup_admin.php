<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $conn = lf_get_db_connection();
    
    // 1. Add role column to users
    $checkRole = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($checkRole->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER email");
        echo json_encode(['status' => 'info', 'message' => 'Added role column using ENUM']);
    }

    // 2. Update claims status ENUM
    // We need to modify the column to include new statuses
    $conn->query("ALTER TABLE claims MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'approved_email_sent') DEFAULT 'pending'");

    // 3. Seed Admin User
    $adminEmail = 'admin@lostfound.edu';
    $adminPass = 'admin123';
    $adminHash = password_hash($adminPass, PASSWORD_DEFAULT);
    
    $checkAdmin = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkAdmin->bind_param("s", $adminEmail);
    $checkAdmin->execute();
    if ($checkAdmin->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES ('System Admin', ?, ?, 'admin')");
        $stmt->bind_param("ss", $adminEmail, $adminHash);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Admin user seeded: ' . $adminEmail]);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Admin user already exists']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
