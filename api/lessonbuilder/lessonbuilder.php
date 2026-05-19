<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?lessonbuilder$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$lessonBuilderBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BrailleStudio Lesson Builder</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <script>
    (function () {
      const AUTH_TOKEN_KEYS = ['braillestudioAuthToken', 'elevenlabsAuthToken'];
      const AUTH_LOGIN_URL = <?= $jsValue($authLoginUrl) ?>;
      const PRODUCTION_ORIGIN = 'https://www.tastenbraille.com';

      function getAuthToken() {
        for (const key of AUTH_TOKEN_KEYS) {
          const sessionValue = String(sessionStorage.getItem(key) || '').trim();
          if (sessionValue) return sessionValue;
          const localValue = String(localStorage.getItem(key) || '').trim();
          if (localValue) return localValue;
        }
        return '';
      }

      if (String(window.location.origin || '') !== PRODUCTION_ORIGIN) {
        return;
      }
      if (getAuthToken()) {
        return;
      }

      const url = new URL(AUTH_LOGIN_URL, window.location.href);
      url.searchParams.set('returnTo', window.location.href);
      window.location.replace(url.toString());
    })();
  </script>
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row ms-auto">
          <div class="nav-item">
            <button id="authBtn" class="btn btn-outline-primary" type="button">
              <i class="ti ti-login me-2" aria-hidden="true"></i>
              <span>Authentication</span>
            </button>
          </div>
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
    (function () {
      const AUTH_TOKEN_KEYS = ['braillestudioAuthToken', 'elevenlabsAuthToken'];
      const AUTH_LOGIN_URL = <?= $jsValue($authLoginUrl) ?>;
      const authBtn = document.getElementById('authBtn');

      function getAuthToken() {
        for (const key of AUTH_TOKEN_KEYS) {
          const sessionValue = String(sessionStorage.getItem(key) || '').trim();
          if (sessionValue) return sessionValue;
          const localValue = String(localStorage.getItem(key) || '').trim();
          if (localValue) return localValue;
        }
        return '';
      }

      function clearAuthTokens() {
        for (const key of AUTH_TOKEN_KEYS) {
          sessionStorage.removeItem(key);
          localStorage.removeItem(key);
        }
      }

      function renderAuthButton() {
        if (!authBtn) return;
        const authenticated = Boolean(getAuthToken());
        const label = authBtn.querySelector('span');
        authBtn.className = authenticated ? 'btn btn-outline-danger' : 'btn btn-outline-primary';
        if (label) {
          label.textContent = authenticated ? 'Logout' : 'Authentication';
        }
        authBtn.title = authenticated ? 'Authenticated.' : 'Not authenticated.';
      }

      authBtn?.addEventListener('click', () => {
        if (getAuthToken()) {
          clearAuthTokens();
          renderAuthButton();
          return;
        }
        const url = new URL(AUTH_LOGIN_URL, window.location.href);
        url.searchParams.set('returnTo', window.location.href);
        window.location.assign(url.toString());
      });

      renderAuthButton();
      window.addEventListener('storage', renderAuthButton);
      window.addEventListener('focus', renderAuthButton);
    })();
  </script>
</body>
</html>
