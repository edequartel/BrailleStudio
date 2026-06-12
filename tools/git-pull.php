<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/bootstrap.php';

$user = bs_auth_require_login(['admin', 'developer']);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$csrfValid = $isPost && bs_auth_verify_csrf((string)($_POST['csrf'] ?? ''));
$result = null;

if ($csrfValid) {
    $startedAt = microtime(true);
    $command = ['git', 'pull', '--ff-only'];
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        $command,
        $descriptorSpec,
        $pipes,
        dirname(__DIR__),
        ['GIT_TERMINAL_PROMPT' => '0']
    );

    if (!is_resource($process)) {
        $result = [
            'ok' => false,
            'exitCode' => null,
            'output' => ['Could not start git pull process.'],
            'durationMs' => (int)round((microtime(true) - $startedAt) * 1000),
        ];
    } else {
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $lines = array_values(array_filter(
            preg_split('/\R/', trim((string)$stdout . "\n" . (string)$stderr)) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        ));
        $result = [
            'ok' => $exitCode === 0,
            'exitCode' => $exitCode,
            'output' => $lines !== [] ? $lines : ['git pull returned no output.'],
            'durationMs' => (int)round((microtime(true) - $startedAt) * 1000),
        ];
    }
}

$appBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$urlFor = static fn (string $path): string => ($appBase === '' ? '' : $appBase) . '/' . ltrim($path, '/');
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$title = !$isPost
    ? 'Git pull niet uitgevoerd'
    : (!$csrfValid ? 'Git pull geweigerd' : ($result['ok'] ? 'Git pull voltooid' : 'Git pull mislukt'));
$kind = $csrfValid && ($result['ok'] ?? false) ? 'success' : 'danger';
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($title) ?> - BrailleStudio</title>
  <link rel="stylesheet" href="<?= $h($urlFor('tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $h($urlFor('tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <style>
    .git-pull-container {
      max-width: 72rem;
    }
  </style>
</head>
<body class="bg-body">
  <main class="container git-pull-container py-5">
    <div class="card">
      <div class="card-body">
        <h1 class="h2"><?= $h($title) ?></h1>
        <?php if (!$isPost): ?>
          <p class="text-secondary">Git pull wordt alleen uitgevoerd na het indrukken van de knop in Techniek / Tools.</p>
        <?php elseif (!$csrfValid): ?>
          <p class="text-secondary">De aanvraag bevatte geen geldige beveiligingstoken. Er is niets uitgevoerd.</p>
        <?php else: ?>
          <div class="alert alert-<?= $h($kind) ?>">
            Exitcode: <?= $h((string)($result['exitCode'] ?? 'n/a')) ?>,
            duur: <?= $h((string)($result['durationMs'] ?? 0)) ?> ms
          </div>
          <pre class="form-control font-monospace mb-3" style="min-height: 12rem; white-space: pre-wrap;"><?= $h(implode("\n", $result['output'] ?? [])) ?></pre>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= $h($urlFor('index.php')) ?>">
          <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
          Terug naar BrailleStudio
        </a>
      </div>
    </div>
  </main>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
