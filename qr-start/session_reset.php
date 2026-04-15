<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$session = trim((string)($_GET['session'] ?? ''));
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

$data['started'] = false;
$data['startedAt'] = null;

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

respond(['ok' => true, 'session' => $session, 'started' => false]);