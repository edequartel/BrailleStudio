<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();

$originalCode = session_api_normalize_token((string)($input['originalCode'] ?? ''), 'originalCode', 3, 64);
$methodIdRaw = trim((string)($input['methodId'] ?? ''));
$methodId = $methodIdRaw !== '' ? session_api_normalize_token($methodIdRaw, 'methodId', 3, 128) : '';

$originalPath = session_api_find_step_link_path($originalCode, $methodId);
$existing = $originalPath !== null ? session_api_read_json_file($originalPath) : null;
if (!is_array($existing)) {
    session_api_error('Original step code not found', 404, ['originalCode' => $originalCode, 'methodId' => $methodId]);
}
$methodId = $methodId !== ''
    ? $methodId
    : trim((string)($existing['methodId'] ?? session_api_step_link_method_id_from_path($originalPath)));

$scriptIdRaw = trim((string)($input['scriptId'] ?? ''));
$stepIdRaw = trim((string)($input['stepId'] ?? ''));
$scriptId = $scriptIdRaw !== ''
    ? session_api_normalize_token($scriptIdRaw, 'scriptId', 3, 128)
    : session_api_normalize_token((string)($existing['scriptId'] ?? ''), 'scriptId', 3, 128);
$stepId = $stepIdRaw !== ''
    ? session_api_normalize_token($stepIdRaw, 'stepId', 3, 128)
    : session_api_normalize_token((string)($existing['stepId'] ?? ''), 'stepId', 3, 128);

$requestedCode = trim((string)($input['code'] ?? ''));
$code = $requestedCode !== ''
    ? session_api_normalize_token($requestedCode, 'code', 3, 64)
    : $originalCode;

$active = array_key_exists('active', $input) ? (bool)$input['active'] : (bool)($existing['active'] ?? true);
$meta = session_api_normalize_step_link_meta(isset($input['meta']) && is_array($input['meta']) ? $input['meta'] : []);
$stepInputs = isset($input['stepInputs']) && is_array($input['stepInputs'])
    ? $input['stepInputs']
    : (is_array($existing['stepInputs'] ?? null) ? $existing['stepInputs'] : []);
$stepInputs = session_api_normalize_step_inputs($stepInputs);

$targetPath = $code === $originalCode ? $originalPath : session_api_step_link_file($code, $methodId);
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
    'methodId' => $methodId,
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
    'methodId' => $methodId,
    'record' => $record,
]);

function session_api_normalize_step_inputs(array $stepInputs): array
{
    unset($stepInputs['repeat']);
    return $stepInputs;
}

function session_api_normalize_step_link_meta(array $meta): array
{
    $info = trim((string)($meta['info'] ?? ''));
    return $info !== '' ? ['info' => $info] : [];
}
