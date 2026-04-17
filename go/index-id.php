<?php
declare(strict_types=1);

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

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) ? trim($path, '/') : '';
$segments = explode('/', $path);
$id = end($segments);

if (!$id || !preg_match('/^[0-9]+$/', $id)) {
    respond_html('Ongeldige activity id', 'Deze route verwacht een numerieke activity id.', 400);
}

$target = 'https://www.tastenbraille.com/braillestudio/activities/' . $id . '.json';

header('Location: ' . $target, true, 302);
exit;
