<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$saveDir = dirname(__DIR__) . '/blockly-saves/lessons';

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0775, true);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($data['id']) ? trim((string)$data['id']) : '';
$title = isset($data['title']) ? trim((string)$data['title']) : '';
$word = isset($data['word']) ? trim((string)$data['word']) : '';
$steps = $data['steps'] ?? [];
$meta = $data['meta'] ?? [];
$overwrite = array_key_exists('overwrite', $data) ? (bool)$data['overwrite'] : true;

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

if (!is_array($steps)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Steps must be an array']);
    exit;
}

$cleanSteps = [];
foreach ($steps as $stepId) {
    $stepId = trim((string)$stepId);
    $stepId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $stepId);
    $stepId = trim($stepId, '-_');
    if ($stepId !== '') {
        $cleanSteps[] = $stepId;
    }
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filename = $safeId . '.json';
$filePath = $saveDir . '/' . $filename;

if (file_exists($filePath) && !$overwrite) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'error' => 'Lesson already exists'
    ]);
    exit;
}

$payload = [
    'id' => $safeId,
    'title' => $title,
    'word' => $word,
    'updatedAt' => gmdate('c'),
    'steps' => $cleanSteps,
    'meta' => is_array($meta) ? $meta : [],
];

$written = file_put_contents(
    $filePath,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save lesson']);
    exit;
}

echo json_encode([
    'ok' => true,
    'id' => $safeId,
    'filename' => $filename,
    'path' => 'blockly-saves/lessons/' . $filename
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);