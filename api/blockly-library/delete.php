<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

blockly_library_require_authentication();

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$safeId = blockly_library_normalize_id((string)($data['id'] ?? ''));
if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filePath = blockly_library_data_dir() . '/' . $safeId . '.json';
if (!is_file($filePath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Compound block not found']);
    exit;
}

if (!unlink($filePath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not delete compound block']);
    exit;
}

echo json_encode([
    'ok' => true,
    'id' => $safeId
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
