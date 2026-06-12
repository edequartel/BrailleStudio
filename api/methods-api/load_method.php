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

$id = methods_normalize_id($_GET['id'] ?? '');
if ($id === '') {
    methods_json_response([
        'ok' => false,
        'error' => 'Missing id'
    ], 400);
}

$item = methods_load_by_id($id);
if (!$item) {
    methods_json_response([
        'ok' => false,
        'error' => 'Method not found',
        'source' => methods_save_dir() . '/' . $id . '.json',
    ], 404);
}

$compact = filter_var($_GET['compact'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$compact) {
    $item = methods_enrich_with_lessons($item);
}
$item['url'] = methods_remote_method_url($id);

methods_json_response([
    'ok' => true,
    'item' => $item
]);
