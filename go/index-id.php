<?php
declare(strict_types=1);

function respond(string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) ? trim($path, '/') : '';

$segments = explode('/', $path);
$id = end($segments);

if (!$id || !preg_match('/^[0-9]+$/', $id)) {
    respond('Invalid activity id', 400);
}

$target = 'https://www.tastenbraille.com/braillestudio/activities/' . $id . '.json';

header('Location: ' . $target, true, 302);
exit;