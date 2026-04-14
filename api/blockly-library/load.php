<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

blockly_library_require_authentication();

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$safeId = blockly_library_normalize_id($id);

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filePath = blockly_library_data_dir() . '/' . $safeId . '.json';
if (!is_file($filePath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Compound block not found']);
    exit;
}

$content = json_decode((string)file_get_contents($filePath), true);
if (!is_array($content)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Invalid stored JSON']);
    exit;
}

$meta = isset($content['meta']) && is_array($content['meta']) ? $content['meta'] : [];
$content['meta'] = [
    'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
    'rootType' => isset($meta['rootType']) ? trim((string)$meta['rootType']) : '',
    'kind' => isset($meta['kind']) ? trim((string)$meta['kind']) : 'compound-block',
];

echo json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
