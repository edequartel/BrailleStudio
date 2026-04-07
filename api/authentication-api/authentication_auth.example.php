<?php
declare(strict_types=1);

return [
    'jwt_secret' => 'replace-with-a-long-random-secret',
    'token_ttl' => 3600,
    'users' => [
        [
            'username' => 'eric',
            'role' => 'admin',
            'password_hash' => 'replace-with-password_hash-output',
        ],
    ],
];
