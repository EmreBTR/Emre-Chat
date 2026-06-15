<?php

declare(strict_types=1);

$config = require __DIR__ . '/../core/bootstrap.php';
$pdo = chat_try_pdo($config);

$ip = chat_client_ip();
if ($pdo instanceof PDO && chat_ip_is_banned($pdo, $ip)) {
    chat_json_response(['ok' => false, 'error' => 'ip_banned'], 403);
    exit;
}

$identity = chat_get_identity();
$isGuest = !is_array($identity) || (bool) ($identity['is_guest'] ?? true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isGuest) {
        $s = $_SESSION['guest_settings'] ?? null;
        chat_json_response(['ok' => true, 'settings' => is_array($s) ? $s : new stdClass()]);
        exit;
    }
    if (!($pdo instanceof PDO)) {
        chat_json_response(['ok' => false, 'error' => 'db_unavailable'], 503);
        exit;
    }

    $stmt = $pdo->prepare('SELECT settings_json FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $identity['user_id']]);
    $json = (string) ($stmt->fetchColumn() ?: '{}');
    $settings = json_decode($json, true);
    chat_json_response(['ok' => true, 'settings' => is_array($settings) ? $settings : new stdClass()]);
    exit;
}

if (!chat_require_method('POST')) {
    exit;
}

if (!chat_verify_csrf($config)) {
    exit;
}

$body = chat_read_json_body();
$incoming = $body['settings'] ?? null;
if (!is_array($incoming)) {
    chat_json_response(['ok' => false, 'error' => 'invalid_settings'], 422);
    exit;
}

$allowed = [
    'theme' => ['light', 'dark_blue', 'cyberpunk', 'amoled'],
    'fontScale' => null,
    'wallpaper' => null,
    'sounds' => null,
    'compact' => null,
    'reduceMotion' => null,
    'accent' => null,
];

$clean = [];
foreach ($allowed as $k => $rule) {
    if (!array_key_exists($k, $incoming)) {
        continue;
    }
    $v = $incoming[$k];
    if ($k === 'theme') {
        if (is_string($v) && in_array($v, $rule, true)) {
            $clean[$k] = $v;
        }
        continue;
    }
    if ($k === 'fontScale') {
        $n = is_numeric($v) ? (float) $v : 1.0;
        $clean[$k] = max(0.85, min(1.25, $n));
        continue;
    }
    if ($k === 'wallpaper') {
        if (is_string($v) && $v !== '' && strlen($v) <= 200) {
            $clean[$k] = $v;
        }
        continue;
    }
    if ($k === 'sounds') {
        $clean[$k] = (bool) $v;
        continue;
    }
    if ($k === 'compact') {
        $clean[$k] = (bool) $v;
        continue;
    }
    if ($k === 'reduceMotion') {
        $clean[$k] = (bool) $v;
        continue;
    }
    if ($k === 'accent') {
        if (is_string($v) && preg_match('/^[a-z0-9_]{3,24}$/', $v)) {
            $clean[$k] = $v;
        }
        continue;
    }
}

if ($isGuest) {
    $_SESSION['guest_settings'] = array_merge(is_array($_SESSION['guest_settings'] ?? null) ? $_SESSION['guest_settings'] : [], $clean);
    chat_json_response(['ok' => true, 'settings' => $_SESSION['guest_settings']]);
    exit;
}
if (!($pdo instanceof PDO)) {
    chat_json_response(['ok' => false, 'error' => 'db_unavailable'], 503);
    exit;
}

$stmt = $pdo->prepare('UPDATE users SET settings_json = JSON_MERGE_PATCH(COALESCE(settings_json, JSON_OBJECT()), ?) WHERE id = ?');
$stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $identity['user_id']]);

$stmt = $pdo->prepare('SELECT settings_json FROM users WHERE id = ? LIMIT 1');
$stmt->execute([(int) $identity['user_id']]);
$json = (string) ($stmt->fetchColumn() ?: '{}');
$settings = json_decode($json, true);
chat_json_response(['ok' => true, 'settings' => is_array($settings) ? $settings : new stdClass()]);
