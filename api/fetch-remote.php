<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
if ($url === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing url parameter'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid URL'], JSON_UNESCAPED_UNICODE);
    exit;
}

$parts = parse_url($url);
$scheme = strtolower((string)($parts['scheme'] ?? ''));
$host = strtolower((string)($parts['host'] ?? ''));

if ($scheme !== 'https' || $host !== 'tastenbraille.com') {
    http_response_code(403);
    echo json_encode(['error' => 'Only https://tastenbraille.com is allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'ignore_errors' => true,
        'header' => "User-Agent: BrailleStudio API Test Proxy\r\n",
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$body = @file_get_contents($url, false, $context);
if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Remote fetch failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$statusCode = 200;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) {
            $statusCode = (int)$m[1];
        }
    }
}

http_response_code($statusCode);
echo $body;
