<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

blockly_api_require_authentication();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($data['id']) ? trim((string)$data['id']) : '';
$title = isset($data['title']) ? trim((string)$data['title']) : '';
$blockly = $data['blockly'] ?? null;
$meta = $data['meta'] ?? [];
$overwrite = array_key_exists('overwrite', $data) ? (bool)$data['overwrite'] : true;

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

if ($blockly === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing blockly']);
    exit;
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filename = $safeId . '.json';
$existingPath = blockly_api_find_script_path($safeId);
$saveDir = $existingPath !== null && is_writable(dirname($existingPath))
    ? dirname($existingPath)
    : blockly_api_writable_data_dir();

if ($saveDir === null) {
    blockly_api_json_error('No writable Blockly data directory found.', 500, [
        'checked' => blockly_api_data_dirs(),
    ]);
}

$filePath = $saveDir . '/' . $filename;

if (($existingPath !== null || file_exists($filePath)) && !$overwrite) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'error' => 'Script already exists'
    ]);
    exit;
}

$meta = is_array($meta) ? $meta : [];
$normalizedMeta = [
    'title' => isset($meta['title']) ? trim((string)$meta['title']) : $title,
    'description' => isset($meta['description']) ? trim((string)$meta['description']) : '',
    'instruction' => isset($meta['instruction']) ? trim((string)$meta['instruction']) : '',
    'memo' => isset($meta['memo']) ? trim((string)$meta['memo']) : '',
    'prompt' => isset($meta['prompt']) ? trim((string)$meta['prompt']) : '',
    'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
];

$payload = [
    'id' => $safeId,
    'title' => $title,
    'updatedAt' => gmdate('c'),
    'blockly' => $blockly,
    'meta' => $normalizedMeta,
];

$encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encodedPayload)) {
    blockly_api_json_error('Failed to encode Blockly script JSON: ' . json_last_error_msg());
}

$written = file_put_contents(
    $filePath,
    $encodedPayload,
    LOCK_EX
);

if ($written === false) {
    blockly_api_json_error('Failed to save file. Check directory permissions.', 500, [
        'path' => $filePath,
        'directory' => $saveDir,
        'writable' => is_writable($saveDir),
    ]);
}

blockly_api_rebuild_manifest($saveDir);

echo json_encode([
    'ok' => true,
    'id' => $safeId,
    'filename' => $filename,
    'path' => '../braillestudio-data/data/blockly/' . $filename,
    'url' => blockly_api_remote_script_url($safeId),
    'manifest' => 'temp/manifests/blockly.json',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
