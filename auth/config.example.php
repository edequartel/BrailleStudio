<?php
declare(strict_types=1);

/*
 * Copy this file to a location outside public_html, for example:
 *
 *   /home3/kydjgrmy/braillestudio-auth/config.php
 *
 * The application explicitly looks at that Bluehost path. You can also point
 * BRAILLESTUDIO_AUTH_CONFIG to another full path if needed.
 */
return [
    'pdo' => [
        'dsn' => 'mysql:host=localhost;dbname=braillestudio;charset=utf8mb4',
        'username' => 'braillestudio_user',
        'password' => 'change-me',
        'options' => [],
    ],
    'session' => [
        'name' => 'BRAILLESTUDIO_AUTH',
        'secure' => true,
        'same_site' => 'Lax',
    ],
    'auth' => [
        'table_prefix' => '',
        'throttling' => true,
        'session_resync_interval' => 300,
        'admin_emails' => [
            'admin@example.com',
        ],
    ],
];
