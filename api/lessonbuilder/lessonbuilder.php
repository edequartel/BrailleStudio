<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

$authUser = bs_auth_current_user() ?? ['display' => '', 'role' => ''];

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?lessonbuilder$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$lessonBuilderBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$authLoginUrl = $urlFor($appBase, 'authentication.php');
$steps = [
    [
        'step' => 'Stap 1',
        'title' => 'Methode',
        'description' => 'Kies of maak een methode en selecteer het basisbestand.',
        'icon' => 'ti-folders',
        'theme' => 'primary',
        'href' => $urlFor($lessonBuilderBase, 'lessonbuilder-method.php'),
    ],
    [
        'step' => 'Stap 2',
        'title' => 'Lessons',
        'description' => 'Kies een lesson en beheer de gekoppelde records.',
        'icon' => 'ti-list-details',
        'theme' => 'green',
        'href' => $urlFor($lessonBuilderBase, 'lessonbuilder-records.php'),
    ],
    [
        'step' => 'Stap 3',
        'title' => 'Steps',
        'description' => 'Voeg Blockly scripts toe, vul inputs in en run de lesson.',
        'icon' => 'ti-puzzle',
        'theme' => 'purple',
        'href' => $urlFor($lessonBuilderBase, 'lessonbuilder-steps.php'),
    ],
];
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
  <title>BrailleStudio Lesson Builder</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <script src="<?= $htmlUrl($urlFor($appBase, 'api/lessonbuilder/lessonbuilder-shared.js?v=20260612-fast-methods-1')) ?>"></script>
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
        <img src="../../style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="../../style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
        </a>
        <div class="navbar-nav flex-row ms-auto">
          <span class="navbar-text text-secondary me-2">
            Ingelogd als <?= $html($authUser['display']) ?> (<?= $html($authUser['role']) ?>)
          </span>
          <form method="post" action="<?= $htmlUrl($urlFor($appBase, 'authentication.php')) ?>" class="mb-0">
            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="returnTo" value="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
            <button class="btn btn-outline-secondary" type="submit">
              <i class="ti ti-logout me-2" aria-hidden="true"></i>
              Uitloggen
            </button>
          </form>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <h1 class="page-title">Lesson Builder</h1>
              <div class="text-secondary mt-2">Werk in drie aparte stappen. Elke pagina heeft een eigen debuglog zodat je sneller ziet waar iets misgaat.</div>
            </div>
            <div class="col-auto">
              <span class="badge bg-blue-lt">
                <i class="ti ti-route me-2" aria-hidden="true"></i>
                3 stappen
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="row row-cards">
            <?php foreach ($steps as $item): ?>
              <div class="col-12 col-md-4">
                <a class="card card-link card-link-pop h-100 text-decoration-none" href="<?= $htmlUrl($item['href']) ?>">
                  <div class="card-body">
                    <div class="d-flex align-items-start">
                      <span class="avatar bg-<?= htmlspecialchars($item['theme'], ENT_QUOTES, 'UTF-8') ?>-lt text-<?= htmlspecialchars($item['theme'], ENT_QUOTES, 'UTF-8') ?> me-3">
                        <i class="ti <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                      </span>
                      <div class="flex-fill">
                        <div class="subheader"><?= htmlspecialchars($item['step'], ENT_QUOTES, 'UTF-8') ?></div>
                        <h2 class="card-title mb-2"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p>
                      </div>
                      <i class="ti ti-chevron-right ms-auto text-secondary" aria-hidden="true"></i>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="card mt-4">
            <div class="card-header">
              <div>
                <h2 class="card-title">Methodes</h2>
                <div class="card-subtitle">Methodes uit <code>../braillestudio-data/data/methods</code>.</div>
              </div>
              <div class="card-actions">
                <button id="refreshMethodsBtn" class="btn btn-outline-secondary btn-sm" type="button">
                  <i class="ti ti-refresh me-2"></i>
                  Vernieuwen
                </button>
              </div>
            </div>
            <div id="methodsStatus" class="card-body text-secondary">Methodes laden...</div>
            <div id="methodsList" class="list-group list-group-flush"></div>
          </div>

          <div class="card mt-4">
            <div class="card-header">
              <h2 class="card-title">Flow</h2>
            </div>
            <div class="list-group list-group-flush">
              <div class="list-group-item">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <span class="badge bg-primary-lt">1</span>
                  </div>
                  <div class="col text-secondary">
                    Kies een methode en een basisbestand zoals <code>aanvankelijklijst.json</code>.
                  </div>
                </div>
              </div>
              <div class="list-group-item">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <span class="badge bg-green-lt">2</span>
                  </div>
                  <div class="col text-secondary">
                    Kies een lesson zoals <code>bal</code> of <code>kam</code> en open of maak de bijbehorende stappen.
                  </div>
                </div>
              </div>
              <div class="list-group-item">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <span class="badge bg-purple-lt">3</span>
                  </div>
                  <div class="col text-secondary">
                    Bouw de steps van die lesson met gekoppelde Blockly scripts.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <footer class="footer footer-transparent d-print-none">
        <div class="container-xl">
          <div class="row align-items-center">
            <div class="col text-secondary">Lesson Builder voor BrailleStudio</div>
            <div class="col-auto">
              <a class="btn btn-link" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
                <i class="ti ti-home me-2" aria-hidden="true"></i>
                Startpagina
              </a>
            </div>
          </div>
        </div>
      </footer>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
    (() => {
      const shared = window.LessonBuilderShared;
      const methodsList = document.getElementById('methodsList');
      const methodsStatus = document.getElementById('methodsStatus');
      const refreshMethodsBtn = document.getElementById('refreshMethodsBtn');
      const methodPageUrl = <?= $jsValue($urlFor($lessonBuilderBase, 'lessonbuilder-method.php')) ?>;

      function openMethod(item) {
        shared.updateState({
          methodId: item.id || '',
          methodTitle: item.title || '',
          methodDescription: item.description || '',
          methodImageUrl: item.imageUrl || '',
          methodBasisFile: item.basisFile || '',
          methodDataSource: item.dataSource || ''
        });
        window.location.href = methodPageUrl;
      }

      function renderMethods(items) {
        methodsList.replaceChildren();
        if (items.length === 0) {
          methodsStatus.textContent = 'Geen methodes gevonden.';
          methodsStatus.hidden = false;
          return;
        }

        methodsStatus.hidden = true;
        items.forEach((item) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'list-group-item list-group-item-action';

          const row = document.createElement('div');
          row.className = 'd-flex align-items-center';

          const body = document.createElement('div');
          body.className = 'flex-fill';

          const title = document.createElement('div');
          title.className = 'fw-medium';
          title.textContent = item.title || item.id || 'Naamloze methode';

          const details = document.createElement('div');
          details.className = 'text-secondary small';
          details.textContent = [item.id, item.description].filter(Boolean).join(' - ');

          const icon = document.createElement('i');
          icon.className = 'ti ti-chevron-right text-secondary';

          body.append(title, details);
          row.append(body, icon);
          button.append(row);
          button.addEventListener('click', () => openMethod(item));
          methodsList.append(button);
        });
      }

      async function loadMethods() {
        refreshMethodsBtn.disabled = true;
        methodsStatus.hidden = false;
        methodsStatus.textContent = 'Methodes laden...';
        try {
          const items = await shared.listMethods();
          renderMethods(items);
        } catch (err) {
          methodsList.replaceChildren();
          methodsStatus.hidden = false;
          methodsStatus.textContent = `Methodes laden mislukt: ${err.message || String(err)}`;
        } finally {
          refreshMethodsBtn.disabled = false;
        }
      }

      refreshMethodsBtn.addEventListener('click', loadMethods);
      window.addEventListener('load', loadMethods);
    })();
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
