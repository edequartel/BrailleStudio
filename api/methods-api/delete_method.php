<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    methods_method_not_allowed(['POST']);
}

methods_require_authentication();

$input = methods_get_json_input();
$id = methods_normalize_id($input['id'] ?? ($_POST['id'] ?? ''));

if ($id === '') {
    methods_json_response([
        'ok' => false,
        'error' => 'Missing id'
    ], 400);
}

$items = methods_load_all();
$index = methods_find_method_index_by_id($id, $items);

if ($index < 0) {
    methods_json_response([
        'ok' => false,
        'error' => 'Method not found'
    ], 404);
}

$deleted = $items[$index];
array_splice($items, $index, 1);

if (!methods_save_all($items)) {
    methods_json_response([
        'ok' => false,
        'error' => 'Failed to save methods'
    ], 500);
}

methods_json_response([
    'ok' => true,
    'deleted' => [
        'id' => $deleted['id'],
        'title' => $deleted['title']
    ]
]);
