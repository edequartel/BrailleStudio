<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

require_once __DIR__ . '/bootstrap.php';

$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
if (strpos($script, '/api/authentication-api/') !== false) {
    return;
}

if (strpos($script, '/api/session-api/') !== false) {
    $publicSessionApiFiles = [
        'create-session.php',
        'delete-session.php',
        'laptop.html',
        'laptop.php',
        'list-links.php',
        'mark-session-open.php',
        'phone.html',
        'script-meta.php',
        'send-step-link.php',
        'session-state.php',
    ];

    if (in_array(basename($script), $publicSessionApiFiles, true)) {
        return;
    }
}

if (strpos($script, '/api/') !== false) {
    bs_auth_require_login(['admin', 'docent', 'leerling'], 'json');
}
