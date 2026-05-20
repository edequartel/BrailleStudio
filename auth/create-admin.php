<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$email = trim((string)($argv[1] ?? ''));
$password = (string)($argv[2] ?? '');
$username = trim((string)($argv[3] ?? ''));

if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: php auth/create-admin.php email@example.com password [username]\n");
    exit(1);
}

try {
    $userId = bs_auth()->admin()->createUser($email, $password, $username !== '' ? $username : null);
    bs_auth_set_user_role((int)$userId, 'admin');
    fwrite(STDOUT, "Created admin user {$email} with ID {$userId}\n");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
