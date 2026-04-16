<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$state = session_api_normalize_runtime_state((string)($input['state'] ?? ''));

$runtime = [];
session_api_update_session_file($sessionId, function (array $session) use ($sessionId, $state, $input, &$runtime): array {
    if (!($session['active'] ?? true)) {
        session_api_error('Session is inactive', 409, ['sessionId' => $sessionId]);
    }

    $runtime = session_api_set_runtime_state($session, $state, [
        'code' => (string)($input['code'] ?? ''),
        'scriptId' => (string)($input['scriptId'] ?? ''),
        'stepId' => (string)($input['stepId'] ?? ''),
    ]);

    return $session;
});

session_api_respond([
    'ok' => true,
    'sessionId' => $sessionId,
    'runtime' => $runtime,
]);
