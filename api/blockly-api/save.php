<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$saveDir = dirname(__DIR__) . '/blockly-data';

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0775, true);
}

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
$filePath = $saveDir . '/' . $filename;

if (file_exists($filePath) && !$overwrite) {
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
    'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
];

$payload = [
    'id' => $safeId,
    'title' => $title,
    'updatedAt' => gmdate('c'),
    'blockly' => $blockly,
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
    'filename' => $filename,
    'path' => 'blockly-data/' . $filename,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
