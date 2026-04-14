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

blockly_library_require_authentication();

$saveDir = blockly_library_data_dir();
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0775, true);
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($data['id']) ? trim((string)$data['id']) : '';
$title = isset($data['title']) ? trim((string)$data['title']) : '';
$snippet = $data['snippet'] ?? null;
$meta = $data['meta'] ?? [];
$overwrite = array_key_exists('overwrite', $data) ? (bool)$data['overwrite'] : true;

$safeId = blockly_library_normalize_id($id);
if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

if (!is_array($snippet)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing snippet']);
    exit;
}

$snippetXml = trim((string)($snippet['xml'] ?? ''));
if ($snippetXml === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing snippet xml']);
    exit;
}

$filePath = $saveDir . '/' . $safeId . '.json';
if (is_file($filePath) && !$overwrite) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Compound block already exists']);
    exit;
}

$meta = is_array($meta) ? $meta : [];
$normalizedMeta = [
    'title' => isset($meta['title']) ? trim((string)$meta['title']) : ($title !== '' ? $title : $safeId),
    'rootType' => isset($meta['rootType']) ? trim((string)$meta['rootType']) : trim((string)($snippet['blockType'] ?? '')),
    'kind' => isset($meta['kind']) ? trim((string)$meta['kind']) : 'compound-block',
];

$payload = [
    'id' => $safeId,
    'title' => $title !== '' ? $title : $safeId,
    'updatedAt' => gmdate('c'),
    'snippet' => [
        'xml' => $snippetXml,
        'blockType' => trim((string)($snippet['blockType'] ?? '')),
    ],
    'meta' => $normalizedMeta,
];

$written = file_put_contents(
    $filePath,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

echo json_encode([
    'ok' => true,
    'id' => $safeId,
    'filename' => basename($filePath),
    'path' => 'blockly-library-data/' . basename($filePath),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
