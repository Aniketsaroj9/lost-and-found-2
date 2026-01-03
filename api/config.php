<?php
/**
 * Shared configuration and helpers for Lost & Found API endpoints.
 */

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'lost_and_found_2';
const DB_USER = 'root';
const DB_PASS = '';

// SMTP Configuration
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'aniketsaroj9@gmail.com'; // REPLACE THIS
const SMTP_PASS = 'wqwv fpxz imvo tspg';    // REPLACE THIS
const SMTP_FROM = 'noreply@lostandfound.com';

/**
 * Start a PHP session if one is not already active.
 */
function lf_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

/**
 * Ensure the incoming method is POST, otherwise bail.
 */
function lf_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        lf_send_json(405, ['status' => 'error', 'message' => 'Method not allowed.']);
    }
}

/**
 * Return a JSON-decoded associative array for the current request body.
 */
function lf_get_request_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Standardized JSON response helper.
 */
function lf_send_json(int $statusCode, array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Create a mysqli connection or throw a JSON error response.
 */
function lf_get_db_connection(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        // First try to connect without database to check if MySQL is running
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            error_log('Database connection failed: ' . $conn->connect_error);
            lf_send_json(500, [
                'status' => 'error',
                'message' => 'Database server is not running or credentials are incorrect. Please start MySQL in XAMPP.'
            ]);
        }
        
        // Create database if not exists
        $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Select the database
        if (!$conn->select_db(DB_NAME)) {
            error_log('Could not select database: ' . $conn->error);
            lf_send_json(500, [
                'status' => 'error',
                'message' => 'Could not select database. Please make sure the database exists.'
            ]);
        }
        
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
