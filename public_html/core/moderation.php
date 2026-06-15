<?php

declare(strict_types=1);

function chat_ip_is_banned(PDO $pdo, string $ip): bool
{
    if ($ip === '') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id FROM banned_ips WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $stmt->execute([$ip]);
    return (bool) $stmt->fetchColumn();
}

