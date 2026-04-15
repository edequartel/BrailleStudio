<?php
declare(strict_types=1);

$routesFile = __DIR__ . '/routes.json';

function respond(string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (!is_file($routesFile)) {
    respond('routes.json not found', 500);
}

$json = file_get_contents($routesFile);
if ($json === false) {
    respond('Could not read routes.json', 500);
}

$routes = json_decode($json, true);
if (!is_array($routes)) {
    respond('routes.json is not valid JSON', 500);
}

// Works with /go/page12
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) ? trim($path, '/') : '';

// Example: if path is "go/page12", get "page12"
$segments = explode('/', $path);
$code = end($segments);

if (!$code || !is_string($code)) {
    respond('No code provided', 400);
}

if (!array_key_exists($code, $routes)) {
    respond('Short link not found: ' . $code, 404);
}

$target = $routes[$code];

if (!is_string($target) || trim($target) === '') {
    respond('Invalid target URL for code: ' . $code, 500);
}

// Optional safety check
if (!filter_var($target, FILTER_VALIDATE_URL)) {
    respond('Mapped target is not a valid URL', 500);
}

// Temporary redirect while testing
header('Location: ' . $target, true, 302);
exit;