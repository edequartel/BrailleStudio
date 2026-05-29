<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();

$code = session_api_normalize_token((string)($input['code'] ?? ''), 'code', 3, 64);
$methodIdRaw = trim((string)($input['methodId'] ?? ''));
$methodId = $methodIdRaw !== '' ? session_api_normalize_token($methodIdRaw, 'methodId', 3, 128) : '';

$path = session_api_find_step_link_path($code, $methodId);
if ($path === null || !is_file($path)) {
    session_api_error('Step code not found', 404, ['code' => $code, 'methodId' => $methodId]);
}

$record = session_api_read_json_file($path);
$resolvedMethodId = is_array($record)
    ? trim((string)($record['methodId'] ?? session_api_step_link_method_id_from_path($path)))
    : session_api_step_link_method_id_from_path($path);

if (!unlink($path)) {
    session_api_error('Could not delete step link', 500, ['code' => $code, 'methodId' => $resolvedMethodId]);
}

session_api_respond([
    'ok' => true,
    'deleted' => true,
    'code' => $code,
    'methodId' => $resolvedMethodId,
]);
