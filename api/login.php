<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

lf_require_post();
lf_start_session();

$data = lf_get_request_body();
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

$requestedRole = (string)($data['role'] ?? 'user');

if ($email === '' || $password === '') {
    lf_send_json(422, ['status' => 'error', 'message' => 'Email and password are required.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    lf_send_json(422, ['status' => 'error', 'message' => 'Provide a valid email address.']);
}

$conn = lf_get_db_connection();
// Fetch role as well
$stmt = $conn->prepare('SELECT id, full_name, password_hash, role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    lf_send_json(401, ['status' => 'error', 'message' => 'Invalid email or password.']);
}

// Verify Role
// If user tries to login as admin but is not, deny.
// If admin tries to login as user, allow (or redirect to admin? strictly following requested role is safer for UX intent).
$userRole = $user['role'] ?? 'user';
if ($requestedRole === 'admin' && $userRole !== 'admin') {
     lf_send_json(403, ['status' => 'error', 'message' => 'Access denied. You do not have admin privileges.']);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = (string)$user['full_name'];
$_SESSION['user_role'] = $userRole;

lf_send_json(200, [
    'status' => 'success',
    'message' => 'Logged in successfully.',
    'user' => [
        'id' => (int)$user['id'],
        'name' => (string)$user['full_name'],
        'email' => $email,
        'role' => $userRole
    ],
]);
