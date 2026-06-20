<?php
/**
 * Shared configuration and helpers for Lost & Found API endpoints.
 */

declare(strict_types=1);

// Load .env file if present (for local dev convenience; in production these
// should be set as real environment variables by the hosting platform).
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

function lf_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

define('DB_HOST', lf_env('DB_HOST', 'localhost'));
define('DB_PORT', (int) lf_env('DB_PORT', '3306'));
define('DB_NAME', lf_env('DB_NAME', 'lost_and_found_2'));
define('DB_USER', lf_env('DB_USER', 'root'));
define('DB_PASS', lf_env('DB_PASS', ''));

// SMTP Configuration
define('SMTP_HOST', lf_env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) lf_env('SMTP_PORT', '587'));
define('SMTP_USER', lf_env('SMTP_USER', ''));
define('SMTP_PASS', lf_env('SMTP_PASS', ''));
define('SMTP_FROM', lf_env('SMTP_FROM', 'noreply@lostandfound.com'));

// URL of the Python ML matching microservice
define('ML_SERVICE_URL', lf_env('ML_SERVICE_URL', 'http://localhost:5000/match'));

/**
 * Build this app's own public base URL (scheme + host), so relative upload
 * paths can be turned into full URLs the (potentially remote) ML service
 * can fetch over HTTP.
 */
function lf_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host";
}

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
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
        
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
