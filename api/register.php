<?php

declare(strict_types=1);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

require __DIR__ . '/config.php';

// Set JSON content type header
header('Content-Type: application/json');

lf_require_post();
lf_start_session();

$data = lf_get_request_body();
$fullName = trim((string)($data['fullName'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if ($fullName === '' || $email === '' || $password === '') {
    lf_send_json(422, ['status' => 'error', 'message' => 'All fields are required.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Provide a valid email address.']);
}

if (strlen($password) < 6) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
}

try {
    $conn = lf_get_db_connection();

    // Ensure the users table exists (idempotent check for local setups).
    $createTableQuery = 'CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if (!$conn->query($createTableQuery)) {
        throw new Exception('Failed to create users table: ' . $conn->error);
    }

// Ensure duplicate emails are rejected.
    $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if ($checkStmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $checkStmt->bind_param('s', $email);
    if (!$checkStmt->execute()) {
        throw new Exception('Execute failed: ' . $checkStmt->error);
    }
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        lf_send_json(409, ['status' => 'error', 'message' => 'Email is already registered.']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insertStmt = $conn->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
    if ($insertStmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $insertStmt->bind_param('sss', $fullName, $email, $hash);
    if (!$insertStmt->execute()) {
        throw new Exception('Execute failed: ' . $insertStmt->error);
    }

    $userId = $insertStmt->insert_id;
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $fullName;

    lf_send_json(201, [
        'status' => 'success',
        'message' => 'Account created successfully.',
        'user' => [
            'id' => $userId,
            'name' => $fullName,
            'email' => $email,
        ],
    ]);

} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Send a proper JSON error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred during registration. Please try again later.',
        'debug' => (ini_get('display_errors') === '1') ? $e->getMessage() : null
    ]);
    exit;
}
