<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

const SESSION_API_WAIT_TIMEOUT_SECONDS = 20;
const SESSION_API_WAIT_POLL_US = 250000; // 250ms

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$since = trim((string)($input['since'] ?? ''));

$startedAt = microtime(true);
$timeoutAt = $startedAt + SESSION_API_WAIT_TIMEOUT_SECONDS;
$session = session_api_load_session_or_fail($sessionId);

while (true) {
    $session = session_api_load_session_or_fail($sessionId);
    $lastResolvedAt = (string)($session['lastResolvedAt'] ?? '');
    $changed = $lastResolvedAt !== '' && $lastResolvedAt !== $since;

    if ($changed || microtime(true) >= $timeoutAt) {
        session_api_respond([
            'ok' => true,
            'sessionId' => $sessionId,
            'active' => (bool)($session['active'] ?? true),
            'createdAt' => (string)($session['createdAt'] ?? ''),
            'expiresAt' => (string)($session['expiresAt'] ?? ''),
            'runtime' => session_api_build_runtime_state($session),
            'lastResolvedAt' => $lastResolvedAt,
            'lastResolvedCode' => (string)($session['lastResolvedCode'] ?? ''),
            'lastResolved' => is_array($session['lastResolved'] ?? null) ? $session['lastResolved'] : null,
            'changed' => $changed,
            'timedOut' => !$changed,
            'waitedMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    usleep(SESSION_API_WAIT_POLL_US);
}
