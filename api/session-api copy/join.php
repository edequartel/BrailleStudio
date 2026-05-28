<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$now = session_api_now_iso();

$session = session_api_update_session_file($sessionId, static function (array $session) use ($now): array {
    if (trim((string)($session['joinedAt'] ?? '')) === '') {
        $session['joinedAt'] = $now;
    }
    $session['lastSeenAt'] = $now;
    return $session;
});

session_api_respond([
    'ok' => true,
    'sessionId' => $sessionId,
    'active' => (bool)($session['active'] ?? true),
    'createdAt' => (string)($session['createdAt'] ?? ''),
    'expiresAt' => (string)($session['expiresAt'] ?? ''),
    'joinedAt' => (string)($session['joinedAt'] ?? ''),
    'lastSeenAt' => (string)($session['lastSeenAt'] ?? ''),
]);
