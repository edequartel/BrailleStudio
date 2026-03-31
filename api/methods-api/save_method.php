<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    methods_method_not_allowed(['POST']);
}

$input = methods_get_json_input();
if (!$input) {
    $input = $_POST;
}

if (!is_array($input) || count($input) === 0) {
    methods_json_response([
        'ok' => false,
        'error' => 'Missing input data'
    ], 400);
}

$items = methods_load_all();
$id = methods_normalize_id($input['id'] ?? '');
$isUpdate = $id !== '' && methods_find_method_index_by_id($id, $items) >= 0;
$errors = methods_validate_method($input, $items, $isUpdate);

if ($errors) {
    methods_json_response([
        'ok' => false,
        'error' => 'Validation failed',
        'errors' => $errors
    ], 400);
}

$record = methods_normalize_method($input);
$index = methods_find_method_index_by_id($record['id'], $items);

if ($index >= 0) {
    $items[$index] = $record;
    $action = 'updated';
} else {
    $items[] = $record;
    $action = 'created';
}

if (!methods_save_all($items)) {
    methods_json_response([
        'ok' => false,
        'error' => 'Failed to save method'
    ], 500);
}

methods_json_response([
    'ok' => true,
    'action' => $action,
    'item' => $record
]);
