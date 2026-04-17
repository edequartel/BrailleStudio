<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();

$originalCode = session_api_normalize_token((string)($input['originalCode'] ?? ''), 'originalCode', 3, 64);
$scriptId = session_api_normalize_token((string)($input['scriptId'] ?? ''), 'scriptId', 3, 128);
$stepId = session_api_normalize_token((string)($input['stepId'] ?? ''), 'stepId', 3, 128);

$requestedCode = trim((string)($input['code'] ?? ''));
$code = $requestedCode !== ''
    ? session_api_normalize_token($requestedCode, 'code', 3, 64)
    : $originalCode;

$active = array_key_exists('active', $input) ? (bool)$input['active'] : true;
$meta = isset($input['meta']) && is_array($input['meta']) ? $input['meta'] : [];
$stepInputs = isset($input['stepInputs']) && is_array($input['stepInputs']) ? $input['stepInputs'] : [];

$originalPath = session_api_step_link_file($originalCode);
$existing = session_api_read_json_file($originalPath);
if (!is_array($existing)) {
    session_api_error('Original step code not found', 404, ['originalCode' => $originalCode]);
}

$targetPath = session_api_step_link_file($code);
if ($code !== $originalCode) {
    $collision = session_api_read_json_file($targetPath);
    if (is_array($collision)) {
        session_api_error('Target step code already exists', 409, ['code' => $code]);
    }
}

$now = session_api_now_iso();
$record = [
    'code' => $code,
    'active' => $active,
    'stepId' => $stepId,
    'scriptId' => $scriptId,
    'createdAt' => (string)($existing['createdAt'] ?? $now),
    'updatedAt' => $now,
    'meta' => $meta,
    'stepInputs' => $stepInputs,
];

session_api_write_json_file($targetPath, $record);

if ($code !== $originalCode && is_file($originalPath)) {
    @unlink($originalPath);
}

session_api_respond([
    'ok' => true,
    'updated' => true,
    'originalCode' => $originalCode,
    'code' => $code,
    'record' => $record,
]);
