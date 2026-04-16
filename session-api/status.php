<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$session = session_api_load_session_or_fail($sessionId);

session_api_respond([
    'ok' => true,
    'sessionId' => $sessionId,
    'active' => (bool)($session['active'] ?? true),
    'createdAt' => (string)($session['createdAt'] ?? ''),
    'expiresAt' => (string)($session['expiresAt'] ?? ''),
    'runtime' => session_api_build_runtime_state($session),
    'lastResolvedAt' => (string)($session['lastResolvedAt'] ?? ''),
    'lastResolvedCode' => (string)($session['lastResolvedCode'] ?? ''),
    'lastResolved' => is_array($session['lastResolved'] ?? null) ? $session['lastResolved'] : null,
]);
