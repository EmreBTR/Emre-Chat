<?php

declare(strict_types=1);

return [
    'app' => [
        'base_url' => 'https://chat.emrecloud.com.tr',
        'cookie_domain' => 'chat.emrecloud.com.tr',
    ],
    'db' => [
        'host' => getenv('CHAT_DB_HOST') ?: '127.0.0.1',
        'name' => getenv('CHAT_DB_NAME') ?: 'chat_db',
        'user' => getenv('CHAT_DB_USER') ?: 'chat_user',
        'pass' => getenv('CHAT_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'csrf_header' => 'X-CSRF-Token',
        'max_message_length' => 2000,
    ],
];

