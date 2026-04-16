<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$state = session_api_normalize_runtime_state((string)($input['state'] ?? ''));

$session = session_api_load_session_or_fail($sessionId);
$runtime = session_api_set_runtime_state($session, $state, [
    'code' => (string)($input['code'] ?? ''),
    'scriptId' => (string)($input['scriptId'] ?? ''),
    'stepId' => (string)($input['stepId'] ?? ''),
]);

session_api_write_json_file(session_api_sessions_file($sessionId), $session);

session_api_respond([
    'ok' => true,
    'sessionId' => $sessionId,
    'runtime' => $runtime,
]);
