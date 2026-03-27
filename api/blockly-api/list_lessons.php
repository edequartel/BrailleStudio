<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$saveDir = dirname(__DIR__) . '/blockly-saves/lessons';

if (!is_dir($saveDir)) {
    echo json_encode([
        'ok' => true,
        'items' => []
    ]);
    exit;
}

$files = glob($saveDir . '/*.json');
$items = [];

foreach ($files as $file) {
    $content = json_decode(file_get_contents($file), true);

    if (!is_array($content)) {
        continue;
    }

    $items[] = [
        'id' => $content['id'] ?? pathinfo($file, PATHINFO_FILENAME),
        'title' => $content['title'] ?? '',
        'word' => $content['word'] ?? '',
        'updatedAt' => $content['updatedAt'] ?? '',
        'steps' => $content['steps'] ?? [],
        'meta' => $content['meta'] ?? [],
        'filename' => basename($file),
    ];
}

usort($items, function ($a, $b) {
    return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
});

echo json_encode([
    'ok' => true,
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
