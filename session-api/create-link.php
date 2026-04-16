<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();

$scriptId = session_api_normalize_token((string)($input['scriptId'] ?? ''), 'scriptId', 3, 128);
$stepId = session_api_normalize_token((string)($input['stepId'] ?? ''), 'stepId', 3, 128);

$requestedCode = trim((string)($input['code'] ?? ''));
$code = $requestedCode !== ''
    ? session_api_normalize_token($requestedCode, 'code', 3, 64)
    : session_api_generate_step_code();

$active = array_key_exists('active', $input) ? (bool)$input['active'] : true;
$overwrite = array_key_exists('overwrite', $input) ? (bool)$input['overwrite'] : false;
$meta = isset($input['meta']) && is_array($input['meta']) ? $input['meta'] : [];
$stepInputs = isset($input['stepInputs']) && is_array($input['stepInputs']) ? $input['stepInputs'] : [];

$path = session_api_step_link_file($code);
$existing = session_api_read_json_file($path);
if (is_array($existing) && !$overwrite) {
    session_api_error('Step code already exists', 409, ['code' => $code]);
}

$now = session_api_now_iso();
$record = [
    'code' => $code,
    'active' => $active,
    'stepId' => $stepId,
    'scriptId' => $scriptId,
    'createdAt' => is_array($existing) ? (string)($existing['createdAt'] ?? $now) : $now,
    'updatedAt' => $now,
    'meta' => $meta,
    'stepInputs' => $stepInputs,
];

session_api_write_json_file($path, $record);

session_api_respond([
    'ok' => true,
    'code' => $code,
    'created' => !is_array($existing),
    'record' => $record,
]);
