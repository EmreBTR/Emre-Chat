<?php

declare(strict_types=1);

function chat_ensure_public_group(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, slug, type FROM `groups` WHERE slug = 'global' LIMIT 1");
    $row = $stmt->fetch();
    if (is_array($row)) {
        return $row;
    }

    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $creatorId = (int) ($stmt->fetchColumn() ?: 0);
    if ($creatorId <= 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, role, settings_json) VALUES (?, 'admin', ?)");
        $stmt->execute(['system', json_encode(new stdClass())]);
        $creatorId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("INSERT INTO `groups` (name, slug, description, type, created_by) VALUES (?, 'global', ?, 'public', ?)");
    $stmt->execute(['Global', 'Herkese açık global kanal', $creatorId]);
    $id = (int) $pdo->lastInsertId();
    return ['id' => $id, 'name' => 'Global', 'slug' => 'global', 'type' => 'public'];
}

function chat_insert_message(PDO $pdo, array $identity, ?int $groupId, ?int $receiverId, string $text): array
{
    $isGuest = (bool) ($identity['is_guest'] ?? false);
    $senderId = $isGuest ? null : (int) ($identity['user_id'] ?? 0);
    $guestName = $isGuest ? (string) ($identity['guest_name'] ?? '') : null;

    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, is_guest_sender, guest_name, receiver_id, group_id, message_text, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$senderId, $isGuest ? 1 : 0, $guestName, $receiverId, $groupId, $text, 'sent']);

    $id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT m.id, m.sender_id, u.username AS sender_username, m.is_guest_sender, m.guest_name, m.receiver_id, m.group_id, m.message_text, m.status, m.created_at FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : ['id' => $id];
}

function chat_fetch_messages_after(PDO $pdo, ?int $groupId, ?int $peerId, int $afterId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));

    if ($groupId !== null) {
        $stmt = $pdo->prepare('SELECT m.id, m.sender_id, u.username AS sender_username, m.is_guest_sender, m.guest_name, m.receiver_id, m.group_id, m.message_text, m.status, m.created_at FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.group_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT ' . $limit);
        $stmt->execute([$groupId, $afterId]);
        return $stmt->fetchAll();
    }

    if ($peerId !== null) {
        $stmt = $pdo->prepare('SELECT m.id, m.sender_id, u.username AS sender_username, m.is_guest_sender, m.guest_name, m.receiver_id, m.group_id, m.message_text, m.status, m.created_at FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.receiver_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT ' . $limit);
        $stmt->execute([$peerId, $afterId]);
        return $stmt->fetchAll();
    }

    return [];
}

function chat_fetch_recent_group_messages(PDO $pdo, int $groupId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare('SELECT m.id, m.sender_id, u.username AS sender_username, m.is_guest_sender, m.guest_name, m.receiver_id, m.group_id, m.message_text, m.status, m.created_at FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.group_id = ? ORDER BY m.id DESC LIMIT ' . $limit);
    $stmt->execute([$groupId]);
    $rows = $stmt->fetchAll();
    return array_reverse($rows);
}
