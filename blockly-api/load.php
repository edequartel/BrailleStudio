<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/json-guard.php';

braillestudio_json_guard_run(static function (): void {
    require_once __DIR__ . '/../auth/bootstrap.php';
    bs_auth_require_login(['admin', 'docent'], 'json');
    require __DIR__ . '/../api/blockly-api/load.php';
});
