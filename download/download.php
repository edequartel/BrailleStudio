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

$downloads = [
    [
        'title' => 'BrailleBridge',
        'description' => 'Windows-app om BrailleStudio met een brailleleesregel te verbinden.',
        'file' => 'setup_braillebridge_v20.exe',
        'icon' => 'ti-plug-connected',
    ],
    [
        'title' => 'SAM 299 full',
        'description' => 'Installatiebestand voor SAM 299 full.',
        'file' => 'setup_SAM_299_full.exe',
        'icon' => 'ti-package',
    ],
];
?>
<!doctype html>
<html lang="nl">
<head>
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
            <div class="list-group list-group-flush">
              <?php foreach ($downloads as $download): ?>
                <a
                  class="list-group-item list-group-item-action d-flex align-items-center"
                  href="<?= $html($urlFor($appBase, 'downloads/' . $download['file'])) ?>"
                >
                  <span class="avatar bg-primary-lt text-primary me-3">
                    <i class="ti <?= $html($download['icon']) ?>" aria-hidden="true"></i>
                  </span>
                  <span class="me-3">
                    <span class="fw-semibold d-block"><?= $html($download['title']) ?></span>
                    <span class="text-secondary small d-block"><?= $html($download['description']) ?></span>
                    <span class="text-secondary small font-monospace d-block"><?= $html($download['file']) ?></span>
                  </span>
                  <span class="btn btn-primary ms-auto">
                    <i class="ti ti-download me-2" aria-hidden="true"></i>
                    Download
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $html($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
</body>
</html>
