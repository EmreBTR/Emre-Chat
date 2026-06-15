<?php

declare(strict_types=1);

function chat_start_session(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $config['app']['cookie_domain'] ?? '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('chat_sid');
    session_start();
}

function chat_session_regenerate(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    session_regenerate_id(true);
}

