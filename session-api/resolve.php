<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$code = session_api_normalize_token((string)($input['code'] ?? ''), 'code', 3, 64);

$session = session_api_load_session_or_fail($sessionId);
if (!($session['active'] ?? true)) {
    session_api_error('Session is inactive', 409, ['sessionId' => $sessionId]);
}

$runtime = session_api_build_runtime_state($session);
if (($runtime['state'] ?? 'idle') === 'active') {
    session_api_respond([
        'ok' => true,
        'accepted' => false,
        'ignored' => true,
        'reason' => 'session_busy',
        'message' => 'Session has an active step.',
        'sessionId' => $sessionId,
        'runtime' => $runtime,
    ]);
}

$stepLink = session_api_load_step_link_or_fail($code);

$resolvedAt = session_api_now_iso();
$resolvedPayload = [
    'code' => $code,
    'stepId' => (string)$stepLink['stepId'],
    'scriptId' => (string)$stepLink['scriptId'],
    'meta' => is_array($stepLink['meta'] ?? null) ? $stepLink['meta'] : new stdClass(),
    'stepInputs' => is_array($stepLink['stepInputs'] ?? null) ? $stepLink['stepInputs'] : new stdClass(),
    'resolvedAt' => $resolvedAt,
];
$session['lastResolvedAt'] = $resolvedAt;
$session['lastResolvedCode'] = $code;
$session['lastResolved'] = $resolvedPayload;
session_api_set_runtime_state($session, 'active', $resolvedPayload);
session_api_write_json_file(session_api_sessions_file($sessionId), $session);

session_api_respond([
    'ok' => true,
    'accepted' => true,
    'sessionId' => $sessionId,
    ...$resolvedPayload,
]);
