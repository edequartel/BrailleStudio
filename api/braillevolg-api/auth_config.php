<?php
declare(strict_types=1);

return [
    'retention_days' => 1825,
    'purge_after_soft_delete_days' => 90,
    'users' => [
        ['username' => 'Gerda', 'role' => 'editor', 'password_hash' => '$2y$10$zP0B1JwuikBSLJvj9/9icuW5HG.tKjiRZO8DlaKSytt7Zv2XIjEFS'],
        ['username' => 'Manon', 'role' => 'editor', 'password_hash' => '$2y$10$AndwvpYAq3zlE2S0GYpe/uopE0YvF6KJWL1fQhl03quWKeX9qHPyG'],
        ['username' => 'Eric', 'role' => 'admin', 'password_hash' => '$2y$10$UBx2jqC/x9BharozayKl5u0GJsgVptd41i9kAH2Uo/1id2g9JE4H.'],
        ['username' => 'bartimeus', 'role' => 'viewer', 'password_hash' => '$2y$10$U5YkVNKWbOFwynZfQAaSdeq9DL6QLiSoy0.uEdlquSxg1ndgarqX.'],
    ],
];
