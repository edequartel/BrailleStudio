<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    methods_method_not_allowed(['GET']);
}

$id = methods_normalize_id($_GET['id'] ?? '');
if ($id === '') {
    methods_json_response([
        'ok' => false,
        'error' => 'Missing id'
    ], 400);
}

$items = methods_load_all();
$item = methods_find_method_by_id($id, $items);
if (!$item) {
    methods_json_response([
        'ok' => false,
        'error' => 'Method not found'
    ], 404);
}

$item = methods_enrich_with_lessons($item);

methods_json_response([
    'ok' => true,
    'item' => $item
]);
