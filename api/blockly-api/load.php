<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$saveDir = dirname(__DIR__) . '/blockly-data';

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filePath = $saveDir . '/' . $safeId . '.json';

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Script not found']);
    exit;
}

$content = json_decode(file_get_contents($filePath), true);

if (!is_array($content)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Invalid stored JSON']);
    exit;
}

$meta = isset($content['meta']) && is_array($content['meta']) ? $content['meta'] : [];
$content['meta'] = [
    'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
    'description' => isset($meta['description']) ? trim((string)$meta['description']) : trim((string)($content['description'] ?? '')),
    'instruction' => isset($meta['instruction']) ? trim((string)$meta['instruction']) : trim((string)($content['instruction'] ?? '')),
    'prompt' => isset($meta['prompt']) ? trim((string)$meta['prompt']) : trim((string)($content['prompt'] ?? '')),
    'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
];

echo json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
