<?php
declare(strict_types=1);

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$appBase = preg_replace('~/download$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};

$html = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$formatBytes = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float)$bytes;
    foreach ($units as $index => $unit) {
        if ($value < 1024 || $index === count($units) - 1) {
            return ($index === 0 ? (string)(int)$value : number_format($value, 1, ',', '.')) . ' ' . $unit;
        }
        $value /= 1024;
    }
    return $bytes . ' B';
};

$titleFromFile = static function (string $file): string {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $name = preg_replace('/[_-]+/', ' ', $name) ?? $name;
    $name = trim($name);
    return $name !== '' ? ucwords(strtolower($name)) : $file;
};

$iconForFile = static function (string $file): string {
    return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'exe', 'msi' => 'ti-brand-windows',
        'zip', '7z', 'rar' => 'ti-file-zip',
        'pdf' => 'ti-file-type-pdf',
        'dmg', 'pkg' => 'ti-package',
        default => 'ti-file-download',
    };
};

$projectRoot = dirname(__DIR__);
$centralDownloadDir = dirname($projectRoot) . '/braillestudio-data/downloads';
$usesCentralDownloads = is_dir($centralDownloadDir) && is_readable($centralDownloadDir);
$downloadDir = $usesCentralDownloads ? $centralDownloadDir : $projectRoot . '/downloads';
$downloads = [];
if (is_dir($downloadDir) && is_readable($downloadDir)) {
    $entries = scandir($downloadDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }
        $path = $downloadDir . '/' . $entry;
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $downloads[] = [
            'title' => $titleFromFile($entry),
            'file' => $entry,
            'size' => $formatBytes((int)filesize($path)),
            'modified' => date('d-m-Y H:i', (int)filemtime($path)),
            'icon' => $iconForFile($entry),
        ];
    }
}

usort($downloads, static function (array $a, array $b): int {
    return strnatcasecmp((string)$a['title'], (string)$b['title']);
});

$downloadUrl = static function (string $file): string {
    return 'https://www.tastenbraille.com/braillestudio-data/downloads/' . rawurlencode($file);
};
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Downloads | BrailleStudio</title>
  <link rel="stylesheet" href="<?= $html($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $html($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark pe-0 pe-md-3" href="<?= $html($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row align-items-center order-md-last ms-auto">
          <div class="nav-item">
            <a class="btn btn-outline-secondary" href="<?= $html($urlFor($appBase, 'index.php')) ?>">
              <i class="ti ti-home me-2" aria-hidden="true"></i>
              Start
            </a>
          </div>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-body">
        <div class="container-xl">
          <div class="card card-lg">
            <div class="card-body p-4 p-md-5 text-center">
              <span class="avatar avatar-xl bg-primary-lt mb-3">
                <i class="ti ti-download fs-1" aria-hidden="true"></i>
              </span>
              <h1 class="h2 mb-2">Downloads</h1>
              <p class="text-secondary mb-4">
                Download de beschikbare installatiebestanden voor BrailleStudio en bijbehorende hulpmiddelen.
              </p>
            </div>
            <?php if ($downloads): ?>
              <div class="list-group list-group-flush">
                <?php foreach ($downloads as $download): ?>
                  <div class="list-group-item d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <div class="d-flex align-items-center flex-fill min-w-0">
                      <span class="avatar bg-primary-lt text-primary me-3">
                        <i class="ti <?= $html($download['icon']) ?>" aria-hidden="true"></i>
                      </span>
                      <span class="min-w-0">
                        <span class="fw-semibold d-block text-truncate"><?= $html($download['title']) ?></span>
                        <span class="text-secondary small font-monospace d-block text-truncate"><?= $html($download['file']) ?></span>
                        <span class="text-secondary small d-block"><?= $html($download['size']) ?> · gewijzigd <?= $html($download['modified']) ?></span>
                      </span>
                    </div>
                    <a class="btn btn-primary ms-md-auto" href="<?= $html($downloadUrl($download['file'])) ?>" download>
                      <i class="ti ti-download me-2" aria-hidden="true"></i>
                      Download
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="card-body border-top">
                <div class="empty">
                  <div class="empty-icon">
                    <i class="ti ti-folder-open"></i>
                  </div>
                  <p class="empty-title">Geen downloads gevonden</p>
                  <p class="empty-subtitle text-secondary">
                    Plaats bestanden in de centrale map <code>braillestudio-data/downloads</code> om ze hier te tonen.
                  </p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $html($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
