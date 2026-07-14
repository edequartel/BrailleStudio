<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/bootstrap.php';
require_once __DIR__ . '/includes/site-usage.php';

$currentUser = bs_auth_require_login(['admin', 'developer']);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$baseUrl = $scriptDir === '' ? './' : $scriptDir . '/';
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$usageStats = bs_site_usage_stats(50);
?>
<!doctype html>
<html <?= bs_language_html_attrs() ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Website usage - BrailleStudio</title>
  <link rel="icon" href="<?= $html($baseUrl) ?>favicon.ico" sizes="any">
  <link rel="stylesheet" href="<?= $html($baseUrl) ?>tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="<?= $html($baseUrl) ?>tabler/icons-webfont/dist/tabler-icons.min.css">
  <style>
    @font-face {
      font-family: "Noto Sans";
      src: url("<?= $html($baseUrl) ?>fonts/NotoSans-Regular.woff2") format("woff2");
      font-weight: 400;
      font-style: normal;
      font-display: swap;
    }

    @font-face {
      font-family: "Noto Sans";
      src: url("<?= $html($baseUrl) ?>fonts/NotoSans-SemiBold.woff2") format("woff2");
      font-weight: 600;
      font-style: normal;
      font-display: swap;
    }

    :root {
      --tblr-font-sans-serif: "Noto Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
  </style>
</head>
<body>
<a class="visually-hidden-focusable" href="#main-content"><?= $html(t('common.skip_to_content')) ?></a>

<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <a class="navbar-brand navbar-brand-autodark pe-0 pe-md-3" href="<?= $html($baseUrl) ?>index.php">
        <img src="<?= $html($baseUrl) ?>style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="<?= $html($baseUrl) ?>style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
      </a>
      <div class="navbar-nav flex-row align-items-center order-md-last ms-auto">
        <div class="nav-item me-2">
          <span class="navbar-text text-secondary">
            <?= $html(t('auth.status.logged_in_as', ['display' => $currentUser['display'], 'role' => $currentUser['role']])) ?>
          </span>
        </div>
        <?= language_switcher('nav-item me-2') ?>
        <div class="nav-item">
          <a class="btn btn-outline-secondary" href="<?= $html($baseUrl) ?>index.php">
            <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
            Back
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="page-wrapper" id="main-content">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row g-2 align-items-center">
          <div class="col">
            <div class="page-pretitle">Dashboard</div>
            <h1 class="page-title">Website usage</h1>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <div class="row row-cards mb-3">
          <div class="col-sm-4">
            <div class="card">
              <div class="card-body">
                <div class="subheader">Total opens</div>
                <div class="h1 mb-0"><?= $html(number_format((int)$usageStats['total'], 0, ',', '.')) ?></div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="card">
              <div class="card-body">
                <div class="subheader">Unique visitors</div>
                <div class="h1 mb-0"><?= $html(number_format((int)$usageStats['unique'], 0, ',', '.')) ?></div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="card">
              <div class="card-body">
                <div class="subheader">Last open</div>
                <div class="h2 mb-0"><?= $html($usageStats['lastOpenedAt'] !== '' ? bs_site_usage_format_time((string)$usageStats['lastOpenedAt']) : '-') ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Recent opens</h2>
              <div class="card-subtitle">Last 50 homepage opens recorded locally.</div>
            </div>
          </div>
          <?php if ($usageStats['recent'] === []): ?>
            <div class="card-body">
              <p class="text-secondary mb-0">No opens logged yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                <tr>
                  <th>When</th>
                  <th>Who</th>
                  <th>Role</th>
                  <th>Page</th>
                  <th>Browser</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($usageStats['recent'] as $visit): ?>
                  <tr>
                    <td class="text-nowrap"><?= $html(bs_site_usage_format_time((string)($visit['openedAt'] ?? ''))) ?></td>
                    <td>
                      <div class="fw-semibold"><?= $html((string)($visit['display'] ?? 'Guest')) ?></div>
                      <?php if (trim((string)($visit['email'] ?? '')) !== ''): ?>
                        <div class="text-secondary small"><?= $html((string)$visit['email']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= $html((string)($visit['role'] ?? 'public')) ?></td>
                    <td class="text-truncate" style="max-width: 16rem;"><?= $html((string)($visit['path'] ?? '')) ?></td>
                    <td class="text-truncate text-secondary" style="max-width: 22rem;"><?= $html((string)($visit['userAgent'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="<?= $html($baseUrl) ?>tabler/core/dist/js/tabler.min.js"></script>
</body>
</html>
