<?php
declare(strict_types=1);

require_once __DIR__ . '/_instructions_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    method_not_allowed(['GET']);
}

$id = normalize_string($_GET['id'] ?? '');

if ($id === '') {
    json_response([
        'ok' => false,
        'error' => 'Missing id'
    ], 400);
}

$items = load_instructions();
$item = find_instruction_by_id($id, $items);

if (!$item) {
    json_response([
        'ok' => false,
        'error' => 'Instruction not found'
    ], 404);
}

json_response([
    'ok' => true,
    'item' => $item
]);