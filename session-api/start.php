<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();

$teacherId = '';
if (array_key_exists('teacherId', $input)) {
    $teacherId = session_api_normalize_token((string)$input['teacherId'], 'teacherId', 3, 64);
}

$context = [];
if (isset($input['context']) && is_array($input['context'])) {
    $context = $input['context'];
}

$sessionId = session_api_generate_session_id();
$record = [
    'sessionId' => $sessionId,
    'active' => true,
    'teacherId' => $teacherId,
    'context' => $context,
    'createdAt' => session_api_now_iso(),
    'expiresAt' => session_api_expiry_iso(),
];

session_api_write_json_file(session_api_sessions_file($sessionId), $record);

session_api_respond([
    'ok' => true,
    'sessionId' => $sessionId,
    'expiresAt' => $record['expiresAt'],
]);

