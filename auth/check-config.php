<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

try {
    $configPath = bs_auth_config_path();
    echo 'Config: ' . ($configPath ?: 'not found') . PHP_EOL;
    bs_auth_require_vendor();
    echo 'Vendor: ok' . PHP_EOL;
    $pdo = bs_auth_pdo();
    echo 'PDO: ok' . PHP_EOL;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)(bs_auth_config()['auth']['table_prefix'] ?? '')) . 'users';
    $stmt = $pdo->query('SELECT COUNT(*) AS count FROM ' . $table);
    $count = (int)($stmt->fetch()['count'] ?? 0);
    echo 'PHP-Auth users table: ok (' . $count . ' users)' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
