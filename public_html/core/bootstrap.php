<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/http.php';
require __DIR__ . '/session.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/moderation.php';
require __DIR__ . '/chat.php';

chat_start_session($config);

return $config;
