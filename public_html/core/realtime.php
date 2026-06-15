<?php

declare(strict_types=1);

function chat_insert_typing(PDO $pdo, array $identity, int $groupId): void
{
    $isGuest = (bool) ($identity['is_guest'] ?? false);
    $userId = $isGuest ? null : (int) ($identity['user_id'] ?? 0);
    $guestName = $isGuest ? (string) ($identity['guest_name'] ?? '') : null;

    $stmt = $pdo->prepare('INSERT INTO realtime_events (event_type, group_id, user_id, is_guest, guest_name, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 6 SECOND))');
    $stmt->execute(['typing', $groupId, $userId, $isGuest ? 1 : 0, $guestName]);
}

function chat_fetch_typing_after(PDO $pdo, int $groupId, int $afterId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare('SELECT id, event_type, group_id, user_id, is_guest, guest_name, created_at FROM realtime_events WHERE group_id = ? AND event_type = ? AND expires_at > NOW() AND id > ? ORDER BY id ASC LIMIT ' . $limit);
    $stmt->execute([$groupId, 'typing', $afterId]);
    return $stmt->fetchAll();
}

function chat_upsert_presence(PDO $pdo, int $groupId, string $presenceKey, array $identity): void
{
    $isGuest = (bool) ($identity['is_guest'] ?? true);
    $userId = $isGuest ? null : (int) ($identity['user_id'] ?? 0);
    $guestName = $isGuest ? (string) ($identity['guest_name'] ?? '') : null;

    $stmt = $pdo->prepare('INSERT INTO presence (group_id, presence_key, user_id, is_guest, guest_name, last_seen_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at), user_id = VALUES(user_id), is_guest = VALUES(is_guest), guest_name = VALUES(guest_name)');
    $stmt->execute([$groupId, $presenceKey, $userId, $isGuest ? 1 : 0, $guestName]);
}

function chat_count_presence(PDO $pdo, int $groupId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM presence WHERE group_id = ? AND last_seen_at > DATE_SUB(NOW(), INTERVAL 45 SECOND)');
    $stmt->execute([$groupId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

