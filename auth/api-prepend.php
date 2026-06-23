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

if (bs_auth_is_public_session_api_script($script)) {
    return;
}

if (
    strpos($script, '/api/xapi-api/') !== false
    && basename($script) === 'xapi.php'
) {
    return;
}

if (strpos($script, '/api/') !== false) {
    bs_auth_require_login(['admin', 'developer', 'docent', 'leerling'], 'json');
}
