<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Method not allowed. Use GET.',
    ], 405);
}

$user = null;
try {
    $user = bs_auth_current_user();
} catch (Throwable $e) {
    $user = null;
}

authenticate_json_response([
    'ok' => true,
    'authenticated' => $user !== null,
    'role' => (string)($user['role'] ?? ''),
    'can_view_logging' => in_array((string)($user['role'] ?? ''), ['admin', 'developer'], true),
]);
