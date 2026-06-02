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

blockly_api_require_authentication();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($data['id']) ? trim((string)$data['id']) : '';

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

$filePath = blockly_api_find_script_path($safeId);

if ($filePath === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Script not found']);
    exit;
}

if (!unlink($filePath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not delete script']);
    exit;
}

blockly_api_rebuild_manifest(dirname($filePath));

echo json_encode([
    'ok' => true,
    'id' => $safeId
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
