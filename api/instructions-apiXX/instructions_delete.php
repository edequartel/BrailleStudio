<?php
declare(strict_types=1);

require_once __DIR__ . '/_instructions_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    method_not_allowed(['POST']);
}

$input = get_json_input();
$id = normalize_string($input['id'] ?? ($_POST['id'] ?? ''));

if ($id === '') {
    json_response([
        'ok' => false,
        'error' => 'Missing id'
    ], 400);
}

$items = load_instructions();
$index = find_instruction_index_by_id($id, $items);

if ($index < 0) {
    json_response([
        'ok' => false,
        'error' => 'Instruction not found'
    ], 404);
}

$deleted = $items[$index];
array_splice($items, $index, 1);

if (!save_instructions($items)) {
    json_response([
        'ok' => false,
        'error' => 'Failed to save instructions'
    ], 500);
}

json_response([
    'ok' => true,
    'deleted' => [
        'id' => $deleted['id'],
        'title' => $deleted['title']
    ]
]);