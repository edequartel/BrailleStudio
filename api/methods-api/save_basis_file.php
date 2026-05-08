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
if (!$input) {
    $input = $_POST;
}

$fileName = methods_normalize_basis_filename($input['fileName'] ?? '');
$items = methods_normalize_basis_items($input['items'] ?? []);

if ($fileName === '') {
    methods_json_response([
        'ok' => false,
        'error' => 'Invalid fileName'
    ], 400);
}

$path = methods_basis_file_path($fileName);
$dir = dirname($path);
if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    methods_json_response([
        'ok' => false,
        'error' => 'Failed to create basis directory'
    ], 500);
}

$json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    methods_json_response([
        'ok' => false,
        'error' => 'Failed to encode basis file'
    ], 500);
}

if (@file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
    methods_json_response([
        'ok' => false,
        'error' => 'Failed to write basis file'
    ], 500);
}

methods_json_response([
    'ok' => true,
    'fileName' => $fileName,
    'count' => count($items),
]);
