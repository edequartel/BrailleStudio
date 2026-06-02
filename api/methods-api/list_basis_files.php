<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    methods_method_not_allowed(['GET']);
}

methods_require_authentication();

$manifest = methods_load_remote_manifest('klanken', ['klanken.json']);
if (!is_array($manifest)) {
    methods_json_response([
        'ok' => false,
        'error' => 'Remote klanken data manifest not found',
        'source' => methods_remote_data_base_url('klanken'),
        'expected' => methods_remote_manifest_urls('klanken', ['klanken.json']),
    ], 404);
}

$rawItems = is_array($manifest['items'] ?? null) ? $manifest['items'] : $manifest;
$items = [];
foreach ($rawItems as $item) {
    if (is_string($item) || is_numeric($item)) {
        $item = ['name' => (string)$item];
    }
    if (!is_array($item)) {
        continue;
    }
    $name = methods_normalize_basis_filename($item['name'] ?? $item['id'] ?? $item['filename'] ?? '');
    if ($name === '') {
        continue;
    }
    $items[$name] = [
        'id' => $name,
        'name' => $name,
        'label' => methods_normalize_string($item['label'] ?? '') ?: $name,
        'url' => methods_remote_basis_file_url($name),
    ];
}

methods_json_response([
    'ok' => true,
    'items' => array_values($items),
]);
