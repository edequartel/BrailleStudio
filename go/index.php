<?php
declare(strict_types=1);

$routesFile = __DIR__ . '/routes.json';

function respond_html(string $title, string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
  <style>
    :root {
      --bg: #eef3f9;
      --panel: #ffffff;
      --border: #d8e1ee;
      --text: #142033;
      --muted: #64748b;
      --shadow: 0 18px 42px rgba(15, 23, 42, 0.10);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 20px;
      background:
        radial-gradient(circle at top left, rgba(29, 78, 216, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
      color: var(--text);
      font-family: "Segoe UI", Arial, sans-serif;
    }
    .card {
      width: min(720px, 100%);
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 28px;
      box-shadow: var(--shadow);
    }
    h1 {
      margin: 0 0 12px;
      font-size: 30px;
      letter-spacing: -0.03em;
    }
    p {
      margin: 0;
      color: var(--muted);
      line-height: 1.6;
      font-size: 16px;
    }
  </style>
</head>
<body>
  <section class="card">
    <h1>{$safeTitle}</h1>
    <p>{$safeMessage}</p>
  </section>
</body>
</html>
HTML;
    exit;
}

if (!is_file($routesFile)) {
    respond_html('Configuratie ontbreekt', 'routes.json is niet gevonden.', 500);
}

$json = file_get_contents($routesFile);
if ($json === false) {
    respond_html('Leesfout', 'routes.json kon niet worden gelezen.', 500);
}

$routes = json_decode($json, true);
if (!is_array($routes)) {
    respond_html('JSON fout', 'routes.json bevat geen geldige JSON.', 500);
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) ? trim($path, '/') : '';
$segments = explode('/', $path);
$code = end($segments);

if (!$code || !is_string($code)) {
    respond_html('Geen code gevonden', 'Er is geen short code opgegeven in deze go-link.', 400);
}

if (!array_key_exists($code, $routes)) {
    respond_html('Short link niet gevonden', 'Voor deze short code bestaat nog geen doel-URL.', 404);
}

$target = $routes[$code];
if (is_array($target)) {
    $target = trim((string)($target['url'] ?? ''));
}

if (!is_string($target) || trim($target) === '') {
    respond_html('Ongeldige configuratie', 'De gekoppelde doel-URL voor deze short code is leeg.', 500);
}

if (!filter_var($target, FILTER_VALIDATE_URL)) {
    respond_html('Ongeldige doel-URL', 'De gekoppelde doel-URL is geen geldige URL.', 500);
}

header('Location: ' . $target, true, 302);
exit;
