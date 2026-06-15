<?php

declare(strict_types=1);

$config = require __DIR__ . '/../core/bootstrap.php';
$pdo = chat_try_pdo($config);
if (!($pdo instanceof PDO)) {
    chat_json_response(['ok' => false, 'error' => 'db_unavailable'], 503);
    exit;
}

if (chat_ip_is_banned($pdo, chat_client_ip())) {
    chat_json_response(['ok' => false, 'error' => 'ip_banned'], 403);
    exit;
}

if (!chat_require_method('POST')) {
    exit;
}

if (!chat_verify_csrf($config)) {
    exit;
}

$body = chat_read_json_body();
$action = $body['action'] ?? 'send';
if (!is_string($action)) {
    $action = 'send';
}

$identity = chat_get_identity();
if (!is_array($identity)) {
    chat_session_regenerate();
    $identity = [
        'is_guest' => true,
        'guest_name' => chat_make_guest_handle(),
        'user_id' => null,
        'role' => 'guest',
    ];
    chat_set_identity($identity);
}

if ($action === 'ack') {
    $messageId = $body['messageId'] ?? 0;
    $status = $body['status'] ?? '';
    if (!is_int($messageId) && !is_string($messageId)) {
        $messageId = 0;
    }
    $messageId = (int) $messageId;
    if (!is_string($status) || !in_array($status, ['delivered', 'read'], true)) {
        chat_json_response(['ok' => false, 'error' => 'invalid_status'], 422);
        exit;
    }
    if ($messageId <= 0) {
        chat_json_response(['ok' => false, 'error' => 'invalid_message_id'], 422);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE messages SET status = ? WHERE id = ?');
    $stmt->execute([$status, $messageId]);
    chat_json_response(['ok' => true]);
    exit;
}

$target = $body['target'] ?? null;

if ($action === 'typing') {
    if (!is_array($target)) {
        chat_json_response(['ok' => false, 'error' => 'invalid_target'], 422);
        exit;
    }
    $type = $target['type'] ?? '';
    if (!is_string($type) || $type !== 'group') {
        chat_json_response(['ok' => false, 'error' => 'invalid_target'], 422);
        exit;
    }
    $gid = $target['groupId'] ?? 0;
    $gid = (int) $gid;
    if ($gid <= 0) {
        $g = chat_ensure_public_group($pdo);
        $gid = (int) $g['id'];
    }
    chat_insert_typing($pdo, $identity, $gid);
    chat_json_response(['ok' => true]);
    exit;
}

$text = $body['text'] ?? '';

if (!is_array($target) || !is_string($text)) {
    chat_json_response(['ok' => false, 'error' => 'invalid_payload'], 422);
    exit;
}

$text = trim($text);
if ($text === '') {
    chat_json_response(['ok' => false, 'error' => 'empty_message'], 422);
    exit;
}
if (mb_strlen($text, 'UTF-8') > (int) ($config['security']['max_message_length'] ?? 2000)) {
    chat_json_response(['ok' => false, 'error' => 'message_too_long'], 422);
    exit;
}

$type = $target['type'] ?? '';
if (!is_string($type)) {
    $type = '';
}

$groupId = null;
$receiverId = null;

if ($type === 'group') {
    $gid = $target['groupId'] ?? null;
    if ($gid === null || $gid === 0 || $gid === '0') {
        $g = chat_ensure_public_group($pdo);
        $groupId = (int) $g['id'];
    } else {
        $groupId = (int) $gid;
    }
} elseif ($type === 'dm') {
    if ((bool) ($identity['is_guest'] ?? false)) {
        chat_json_response(['ok' => false, 'error' => 'guest_dm_forbidden'], 403);
        exit;
    }
    $rid = $target['receiverId'] ?? 0;
    $receiverId = (int) $rid;
    if ($receiverId <= 0) {
        chat_json_response(['ok' => false, 'error' => 'invalid_receiver'], 422);
        exit;
    }
} else {
    chat_json_response(['ok' => false, 'error' => 'invalid_target'], 422);
    exit;
}

$row = chat_insert_message($pdo, $identity, $groupId, $receiverId, $text);
chat_json_response(['ok' => true, 'message' => $row]);
