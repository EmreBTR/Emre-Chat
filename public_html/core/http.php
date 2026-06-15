<?php

declare(strict_types=1);

function chat_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function chat_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function chat_require_method(string $method): bool
{
    if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', $method) === 0) {
        return true;
    }
    chat_json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    return false;
}

function chat_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
}

