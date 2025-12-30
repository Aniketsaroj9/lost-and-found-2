<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

lf_start_session();

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

lf_send_json(200, [
    'status' => 'success',
    'authenticated' => $userId !== null,
    'user' => $userId ? [
        'id' => (int)$userId,
        'name' => (string)$userName,
        'role' => (string)($_SESSION['user_role'] ?? 'user'),
    ] : null,
]);
