<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}

$session = trim((string)($input['session'] ?? ''));
$updatedData = trim((string)($input['data'] ?? ''));
if ($session === '') {
    respond(['ok' => false, 'error' => 'Missing session'], 400);
}

$file = __DIR__ . '/sessions/' . basename($session) . '.json';
if (!is_file($file)) {
    respond(['ok' => false, 'error' => 'Session not found'], 404);
}

$data = json_decode((string)file_get_contents($file), true);
if (!is_array($data)) {
    respond(['ok' => false, 'error' => 'Invalid session file'], 500);
}

$data['started'] = true;
$data['startedAt'] = gmdate('c');
$data['data'] = $updatedData;

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

respond([
    'ok' => true,
    'session' => $session,
    'lesson' => $data['lesson'] ?? '',
    'data' => $data['data'] ?? '',
    'started' => true
]);
