<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

lf_require_post();
lf_start_session();

$data = lf_get_request_body();
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if ($email === '' || $password === '') {
    lf_send_json(422, ['status' => 'error', 'message' => 'Email and password are required.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Provide a valid email address.']);
}

$conn = lf_get_db_connection();
$stmt = $conn->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    lf_send_json(401, ['status' => 'error', 'message' => 'Invalid email or password.']);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = (string)$user['full_name'];

lf_send_json(200, [
    'status' => 'success',
    'message' => 'Logged in successfully.',
    'user' => [
        'id' => (int)$user['id'],
        'name' => (string)$user['full_name'],
        'email' => $email,
    ],
]);
