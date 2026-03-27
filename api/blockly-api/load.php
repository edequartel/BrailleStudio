<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$saveDir = dirname(__DIR__) . '/blockly-saves/scripts';

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filePath = $saveDir . '/' . $safeId . '.json';

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Script not found']);
    exit;
}

readfile($filePath);