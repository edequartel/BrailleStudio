<?php
declare(strict_types=1);

$routesFile = __DIR__ . '/routes.json';
$password = 'zeemeeuw2015';

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

$enteredPassword = $_POST['password'] ?? '';
if (!hash_equals($password, (string)$enteredPassword)) {
    respond_html('Geen toegang', 'Het opgegeven wachtwoord is onjuist.', 403);
}

$codes = $_POST['codes'] ?? [];
$urls = $_POST['urls'] ?? [];
$remarks = $_POST['remarks'] ?? [];

if (!is_array($codes) || !is_array($urls) || !is_array($remarks)) {
    respond_html('Ongeldige formulierdata', 'De verzonden routes konden niet worden verwerkt.', 400);
}

$result = [];
$count = max(count($codes), count($urls), count($remarks));

for ($i = 0; $i < $count; $i++) {
    $code = trim((string)($codes[$i] ?? ''));
    $url = trim((string)($urls[$i] ?? ''));
    $remark = trim((string)($remarks[$i] ?? ''));

    if ($code === '' && $url === '' && $remark === '') {
        continue;
    }

    if ($code === '' || $url === '') {
        continue;
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
        continue;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        continue;
    }

    $result[$code] = [
        'url' => $url,
        'remarks' => $remark,
    ];
}

ksort($result);

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    respond_html('JSON fout', 'De routes konden niet worden omgezet naar JSON.', 500);
}

if (file_put_contents($routesFile, $json) === false) {
    respond_html('Opslaan mislukt', 'routes.json kon niet worden geschreven. Controleer de bestandsrechten.', 500);
}

header('Location: admin.php?password=' . urlencode((string)$enteredPassword));
exit;
