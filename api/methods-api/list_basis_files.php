<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    methods_method_not_allowed(['GET']);
}

$candidateDirs = [
    dirname(dirname(__DIR__)) . '/klanken',
    dirname(__DIR__) . '/klanken',
    dirname(__DIR__, 3) . '/klanken',
    $_SERVER['DOCUMENT_ROOT'] . '/braillestudio/klanken',
    $_SERVER['DOCUMENT_ROOT'] . '/klanken',
];
$items = [];
foreach ($candidateDirs as $dir) {
    $dir = rtrim((string)$dir, '/');
    if ($dir === '' || !is_dir($dir)) {
        continue;
    }

    $files = glob($dir . '/*.json') ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        $items[$name] = [
            'id' => $name,
            'name' => $name,
            'label' => $name,
            'url' => 'https://www.tastenbraille.com/braillestudio/klanken/' . rawurlencode($name),
        ];
    }
}

methods_json_response([
    'ok' => true,
    'items' => array_values($items),
]);
