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

$saveDir = blockly_library_data_dir();

if (!is_dir($saveDir)) {
    echo json_encode([
        'ok' => true,
        'items' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$files = glob($saveDir . '/*.json');
$items = [];

foreach ($files as $file) {
    $content = json_decode((string)file_get_contents($file), true);
    if (!is_array($content)) {
        continue;
    }

    $meta = isset($content['meta']) && is_array($content['meta']) ? $content['meta'] : [];
    $items[] = [
        'id' => $content['id'] ?? pathinfo($file, PATHINFO_FILENAME),
        'title' => $content['title'] ?? '',
        'updatedAt' => $content['updatedAt'] ?? '',
        'meta' => [
            'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
            'rootType' => isset($meta['rootType']) ? trim((string)$meta['rootType']) : '',
            'kind' => isset($meta['kind']) ? trim((string)$meta['kind']) : 'compound-block',
        ],
        'filename' => basename($file),
    ];
}

usort($items, function ($a, $b) {
    return strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? ''));
});

echo json_encode([
    'ok' => true,
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
