<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    methods_method_not_allowed(['GET']);
}

$dir = dirname(dirname(__DIR__)) . '/klanken';
$items = [];

if (is_dir($dir)) {
    $files = glob($dir . '/*.json') ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        $items[] = [
            'id' => $name,
            'name' => $name,
            'label' => $name,
            'url' => 'https://www.tastenbraille.com/braillestudio/klanken/' . rawurlencode($name),
        ];
    }
}

methods_json_response([
    'ok' => true,
    'items' => $items,
]);
