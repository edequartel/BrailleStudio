<?php
declare(strict_types=1);

$routesFile = __DIR__ . '/routes.json';
$password = 'CHANGE_THIS_TO_A_STRONG_PASSWORD';

function respond(string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$enteredPassword = $_POST['password'] ?? '';
if (!hash_equals($password, (string)$enteredPassword)) {
    respond('Unauthorized', 403);
}

$codes = $_POST['codes'] ?? [];
$urls = $_POST['urls'] ?? [];

if (!is_array($codes) || !is_array($urls)) {
    respond('Invalid form data', 400);
}

$result = [];

$count = max(count($codes), count($urls));

for ($i = 0; $i < $count; $i++) {
    $code = trim((string)($codes[$i] ?? ''));
    $url = trim((string)($urls[$i] ?? ''));

    if ($code === '' && $url === '') {
        continue;
    }

    if ($code === '' || $url === '') {
        continue;
    }

    // Keep codes simple and safe
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
        continue;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        continue;
    }

    $result[$code] = $url;
}

ksort($result);

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    respond('Could not encode JSON', 500);
}

if (file_put_contents($routesFile, $json) === false) {
    respond('Could not write routes.json. Check file permissions.', 500);
}

header('Location: admin.php?password=' . urlencode((string)$enteredPassword));
exit;