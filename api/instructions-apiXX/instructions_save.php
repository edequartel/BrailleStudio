<?php
declare(strict_types=1);

require_once __DIR__ . '/_instructions_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    method_not_allowed(['POST']);
}

$input = get_json_input();

if (!$input) {
    $input = $_POST;
}

if (!is_array($input) || count($input) === 0) {
    json_response([
        'ok' => false,
        'error' => 'Missing input data'
    ], 400);
}

$items = load_instructions();
$id = normalize_string($input['id'] ?? '');
$isUpdate = $id !== '' && instruction_exists($id, $items);

$errors = validate_instruction($input, $items, $isUpdate);

if ($errors) {
    json_response([
        'ok' => false,
        'error' => 'Validation failed',
        'errors' => $errors
    ], 400);
}

$record = normalize_instruction($input);
$index = find_instruction_index_by_id($record['id'], $items);

if ($index >= 0) {
    $items[$index] = $record;
    $action = 'updated';
} else {
    $items[] = $record;
    $action = 'created';
}

if (!save_instructions($items)) {
    json_response([
        'ok' => false,
        'error' => 'Failed to save instructions'
    ], 500);
}

json_response([
    'ok' => true,
    'action' => $action,
    'item' => $record
]);