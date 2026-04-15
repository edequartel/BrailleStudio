<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$lesson = trim((string)($_GET['lesson'] ?? 'default'));
$session = 'les-' . bin2hex(random_bytes(4));

$data = [
    'session' => $session,
    'lesson' => $lesson,
    'started' => false,
    'startedAt' => null,
    'createdAt' => gmdate('c')
];

$file = $sessionDir . '/' . $session . '.json';
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$qrUrl = 'https://www.tastenbraille.com/braillestudio/qr-start/start.html?session='
    . rawurlencode($session)
    . '&lesson=' . rawurlencode($lesson);

respond([
    'ok' => true,
    'session' => $session,
    'lesson' => $lesson,
    'qrUrl' => $qrUrl
]);