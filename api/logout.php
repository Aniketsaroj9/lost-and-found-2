<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

lf_require_post();
lf_start_session();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
lf_send_json(200, ['status' => 'success', 'message' => 'Logged out.']);
