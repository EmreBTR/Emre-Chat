<?php

declare(strict_types=1);

function chat_get_identity(): ?array
{
    $auth = $_SESSION['auth'] ?? null;
    if (!is_array($auth)) {
        return null;
    }
    return $auth;
}

function chat_set_identity(array $identity): void
{
    $_SESSION['auth'] = $identity;
}

function chat_clear_identity(): void
{
    unset($_SESSION['auth']);
}

function chat_ensure_csrf_token(): string
{
    $token = $_SESSION['csrf'] ?? null;
    if (is_string($token) && $token !== '') {
        return $token;
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf'] = $token;
    return $token;
}

function chat_verify_csrf(array $config): bool
{
    $headerName = $config['security']['csrf_header'] ?? 'X-CSRF-Token';
    $provided = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $headerName))] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        chat_json_response(['ok' => false, 'error' => 'csrf'], 403);
        return false;
    }
    return true;
}

function chat_make_guest_handle(): string
{
    $a = ['Kozmik', 'Kutup', 'Gumus', 'Neon', 'Sessiz', 'Asi', 'Sihirli', 'Bulutlu', 'Parlak', 'Derin'];
    $b = ['Yolcu', 'Kartal', 'Gezgin', 'Kedi', 'Sahin', 'Ruzgar', 'Yildiz', 'Cimsek', 'Kaptan', 'Mimar'];
    $n = random_int(10, 99);
    return '@' . $a[array_rand($a)] . '_' . $b[array_rand($b)] . '_' . $n;
}

