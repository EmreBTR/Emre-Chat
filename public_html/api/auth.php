<?php

declare(strict_types=1);

$config = require __DIR__ . '/../core/bootstrap.php';
$pdo = chat_try_pdo($config);

$ip = chat_client_ip();
if ($pdo instanceof PDO && chat_ip_is_banned($pdo, $ip)) {
    chat_json_response(['ok' => false, 'error' => 'ip_banned'], 403);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;
if (!is_string($action) || $action === '') {
    $body = chat_read_json_body();
    $action = $body['action'] ?? '';
}
if (!is_string($action)) {
    $action = '';
}

if ($method === 'GET' && $action === 'me') {
    $identity = chat_get_identity();
    chat_json_response([
        'ok' => true,
        'csrf' => chat_ensure_csrf_token(),
        'user' => $identity ?: ['is_guest' => true, 'guest_name' => null],
    ]);
    exit;
}

if (!chat_require_method('POST')) {
    exit;
}

$body = chat_read_json_body();
$action = $body['action'] ?? $action;
if (!is_string($action)) {
    $action = '';
}

if ($action === 'guest') {
    $identity = chat_get_identity();
    if (!is_array($identity) || !($identity['is_guest'] ?? false)) {
        chat_session_regenerate();
        $identity = [
            'is_guest' => true,
            'guest_name' => chat_make_guest_handle(),
            'user_id' => null,
            'role' => 'guest',
        ];
        chat_set_identity($identity);
    }

    chat_json_response([
        'ok' => true,
        'csrf' => chat_ensure_csrf_token(),
        'user' => $identity,
    ]);
    exit;
}

if ($action === 'register') {
    if (!($pdo instanceof PDO)) {
        chat_json_response(['ok' => false, 'error' => 'db_unavailable'], 503);
        exit;
    }
    $username = $body['username'] ?? '';
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? '';
    $phone = $body['phone'] ?? null;

    if (!is_string($username) || !preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        chat_json_response(['ok' => false, 'error' => 'invalid_username'], 422);
        exit;
    }
    if (!is_string($password) || strlen($password) < 8) {
        chat_json_response(['ok' => false, 'error' => 'invalid_password'], 422);
        exit;
    }
    if ($email !== null && (!is_string($email) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        chat_json_response(['ok' => false, 'error' => 'invalid_email'], 422);
        exit;
    }
    if ($phone !== null && (!is_string($phone) || $phone === '')) {
        chat_json_response(['ok' => false, 'error' => 'invalid_phone'], 422);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, phone, role, settings_json) VALUES (?, ?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$username, $email, $hash, $phone, 'user', json_encode(new stdClass())]);
    } catch (Throwable $e) {
        $code = (int) ($e->getCode() ?: 0);
        chat_json_response(['ok' => false, 'error' => 'register_failed', 'code' => $code], 409);
        exit;
    }

    $userId = (int) $pdo->lastInsertId();
    chat_session_regenerate();
    $identity = [
        'is_guest' => false,
        'user_id' => $userId,
        'username' => $username,
        'role' => 'user',
    ];
    chat_set_identity($identity);

    chat_json_response(['ok' => true, 'csrf' => chat_ensure_csrf_token(), 'user' => $identity]);
    exit;
}

if ($action === 'login') {
    if (!($pdo instanceof PDO)) {
        chat_json_response(['ok' => false, 'error' => 'db_unavailable'], 503);
        exit;
    }
    $identifier = $body['identifier'] ?? '';
    $password = $body['password'] ?? '';
    if (!is_string($identifier) || $identifier === '' || !is_string($password) || $password === '') {
        chat_json_response(['ok' => false, 'error' => 'invalid_login'], 422);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE email = ? OR username = ? OR phone = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier, $identifier]);
    $row = $stmt->fetch();
    if (!is_array($row) || !is_string($row['password_hash'] ?? null) || !password_verify($password, $row['password_hash'])) {
        chat_json_response(['ok' => false, 'error' => 'auth_failed'], 401);
        exit;
    }

    chat_session_regenerate();
    $identity = [
        'is_guest' => false,
        'user_id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'role' => (string) $row['role'],
    ];
    chat_set_identity($identity);

    chat_json_response(['ok' => true, 'csrf' => chat_ensure_csrf_token(), 'user' => $identity]);
    exit;
}

if ($action === 'otp_start') {
    $phone = $body['phone'] ?? '';
    if (!is_string($phone) || $phone === '') {
        chat_json_response(['ok' => false, 'error' => 'invalid_phone'], 422);
        exit;
    }
    $otp = (string) random_int(100000, 999999);
    $_SESSION['otp'] = ['phone' => $phone, 'code' => $otp, 'ts' => time()];
    chat_json_response(['ok' => true, 'otp' => $otp]);
    exit;
}

if ($action === 'otp_verify') {
    if (!($pdo instanceof PDO)) {
        chat_json_response(['ok' => false, 'error' => 'db_unavailable'], 503);
        exit;
    }
    $code = $body['code'] ?? '';
    $otp = $_SESSION['otp'] ?? null;
    if (!is_string($code) || !is_array($otp) || !is_string($otp['code'] ?? null)) {
        chat_json_response(['ok' => false, 'error' => 'otp_invalid'], 422);
        exit;
    }
    if (!hash_equals($otp['code'], $code)) {
        chat_json_response(['ok' => false, 'error' => 'otp_mismatch'], 401);
        exit;
    }

    $phone = (string) ($otp['phone'] ?? '');
    unset($_SESSION['otp']);

    $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        $username = 'user' . random_int(1000, 9999);
        $stmt = $pdo->prepare('INSERT INTO users (username, phone, role, settings_json) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $phone, 'user', json_encode(new stdClass())]);
        $row = ['id' => (int) $pdo->lastInsertId(), 'username' => $username, 'role' => 'user'];
    }

    chat_session_regenerate();
    $identity = [
        'is_guest' => false,
        'user_id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'role' => (string) $row['role'],
    ];
    chat_set_identity($identity);

    chat_json_response(['ok' => true, 'csrf' => chat_ensure_csrf_token(), 'user' => $identity]);
    exit;
}

if ($action === 'logout') {
    chat_clear_identity();
    unset($_SESSION['csrf']);
    session_destroy();
    chat_json_response(['ok' => true]);
    exit;
}

chat_json_response(['ok' => false, 'error' => 'unknown_action'], 404);
