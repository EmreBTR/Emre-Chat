<?php

declare(strict_types=1);

$config = require __DIR__ . '/../core/bootstrap.php';
$pdo = chat_try_pdo($config);
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'db_unavailable';
    exit;
}

if (chat_ip_is_banned($pdo, chat_client_ip())) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ip_banned';
    exit;
}

$scope = $_GET['scope'] ?? 'group';
if (!is_string($scope)) {
    $scope = 'group';
}

$groupId = null;
$peerId = null;

if ($scope === 'group') {
    $gid = $_GET['group_id'] ?? null;
    $gid = $gid === null ? 0 : (int) $gid;
    if ($gid <= 0) {
        $g = chat_ensure_public_group($pdo);
        $gid = (int) $g['id'];
    }
    $groupId = $gid;
} elseif ($scope === 'dm') {
    $pid = (int) ($_GET['peer_id'] ?? 0);
    if ($pid <= 0) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'invalid_peer';
        exit;
    }
    $peerId = $pid;
} else {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'invalid_scope';
    exit;
}

$lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? '';
if (!is_string($lastEventId)) {
    $lastEventId = '';
}
$afterId = (int) ($_GET['last_id'] ?? 0);
if ($afterId <= 0 && $lastEventId !== '') {
    $afterId = (int) $lastEventId;
}

session_write_close();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

echo "retry: 3000\n\n";

$didBootstrap = false;
$startedAt = microtime(true);
$lastBeatAt = $startedAt;

for (;;) {
    if (connection_aborted()) {
        break;
    }

    $now = microtime(true);
    if ($now - $startedAt > 65) {
        break;
    }

    if ($now - $lastBeatAt > 20) {
        echo ": heartbeat\n\n";
        $lastBeatAt = $now;
    }

    if (!$didBootstrap && $afterId <= 0 && $groupId !== null) {
        $rows = chat_fetch_recent_group_messages($pdo, (int) $groupId, 50);
        $didBootstrap = true;
    } else {
        $rows = chat_fetch_messages_after($pdo, $groupId, $peerId, $afterId, 50);
    }
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $afterId = $id;
            echo "id: {$id}\n";
            echo "event: message\n";
            echo 'data: ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        continue;
    }

    usleep(700000);
}
