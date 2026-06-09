<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'json');

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
$allowedHosts = ['tastenbraille.com', 'www.tastenbraille.com'];

if ($scheme !== 'https' || !in_array($host, $allowedHosts, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Only configured TastenBraille hosts are allowed'], JSON_UNESCAPED_UNICODE);
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
$responseHeaders = function_exists('http_get_last_response_headers')
    ? http_get_last_response_headers()
    : [];
if (is_array($responseHeaders)) {
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) {
            $statusCode = (int)$m[1];
        }
    }
}

http_response_code($statusCode);
echo $body;
