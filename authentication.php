<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$appBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$returnTo = bs_auth_safe_return_to($_POST['returnTo'] ?? $_GET['returnTo'] ?? $urlFor($appBase, 'index.php'));
$error = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'login'));
    if (!bs_auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = t('errors.session_expired');
    } elseif ($action === 'logout') {
        try {
            bs_auth_logout();
            header('Location: ' . ($returnTo !== '' ? $returnTo : $urlFor($appBase, 'index.php')));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        try {
            bs_auth_login_identifier($identifier, $password, $remember);
            header('Location: ' . ($returnTo !== '' ? $returnTo : $urlFor($appBase, 'index.php')));
            exit;
        } catch (\Delight\Auth\InvalidEmailException|\Delight\Auth\InvalidPasswordException|\Delight\Auth\UnknownUsernameException|\Delight\Auth\AmbiguousUsernameException $e) {
            $error = t('auth.errors.invalid_credentials');
        } catch (\Delight\Auth\EmailNotVerifiedException $e) {
            $error = t('auth.errors.email_not_verified');
        } catch (\Delight\Auth\TooManyRequestsException $e) {
            $error = t('auth.errors.too_many_requests');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
} elseif (trim((string)($_GET['action'] ?? '')) === 'logout') {
    try {
        bs_auth_logout();
        header('Location: ' . ($returnTo !== '' ? $returnTo : $urlFor($appBase, 'index.php')));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$user = null;
try {
    $user = bs_auth_current_user();
} catch (Throwable $e) {
    $error = $error !== '' ? $error : $e->getMessage();
}

if ($user !== null && $message === '') {
    $message = t('auth.status.logged_in_as', ['display' => $user['display'], 'role' => $user['role']]);
}
?>
<!DOCTYPE html>
<html <?= bs_language_html_attrs() ?>>
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $html(t('auth.page_title')) ?></title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <style>
    .authentication-page {
      min-height: 100vh;
      display: grid;
      place-items: center;
      width: 100%;
      padding: 1.5rem;
    }

    .authentication-page .container-tight {
      margin: 0 auto;
    }
  </style>
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:secure_url" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1729">
  <meta property="og:image:height" content="910">
  <meta property="og:image:alt" content="BrailleStudio">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta name="twitter:image:alt" content="BrailleStudio">
</head>
<body class="bg-body">
  <main class="authentication-page">
  <div class="container container-tight py-4 w-100">
    <div class="text-center mb-4">
      <a class="navbar-brand navbar-brand-autodark justify-content-center" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
        <img src="style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
      </a>
    </div>
    <div class="d-flex justify-content-center mb-3">
      <?= language_switcher() ?>
    </div>

    <div class="card card-md">
      <div class="card-body">
        <h1 class="h2 text-center mb-2"><?= $html(t('auth.login.title')) ?></h1>
        <p class="text-secondary text-center mb-4"><?= $html(t('auth.login.subtitle')) ?></p>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= $html($error) ?></div>
        <?php elseif ($message !== ''): ?>
          <div class="alert alert-success" role="alert"><?= $html($message) ?></div>
        <?php endif; ?>

        <?php if ($user === null): ?>
          <form method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="returnTo" value="<?= $html($returnTo) ?>">

            <div class="mb-3">
              <label class="form-label" for="authIdentifierInput"><?= $html(t('auth.login.identifier')) ?></label>
              <input id="authIdentifierInput" class="form-control" name="identifier" type="text" placeholder="naam@example.com" autocomplete="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="authPasswordInput"><?= $html(t('auth.login.password')) ?></label>
              <input id="authPasswordInput" class="form-control" name="password" type="password" placeholder="<?= $html(t('auth.login.password')) ?>" autocomplete="current-password" required>
            </div>
            <label class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="remember" value="1">
              <span class="form-check-label"><?= $html(t('auth.login.remember')) ?></span>
            </label>
            <button class="btn btn-primary w-100" type="submit">
              <i class="ti ti-login me-2" aria-hidden="true"></i>
              <?= $html(t('auth.login.button')) ?>
            </button>
          </form>
        <?php else: ?>
          <form method="post" class="mb-0">
            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="returnTo" value="<?= $html($returnTo) ?>">
            <button class="btn btn-outline-secondary w-100" type="submit">
              <i class="ti ti-logout me-2" aria-hidden="true"></i>
              <?= $html(t('auth.logout.button')) ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="text-center text-secondary mt-3">
      <?= $html(t('auth.login.return_hint')) ?>
    </div>
    <div class="text-center mt-3">
      <a class="btn btn-link" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>"><?= $html(t('common.back_home')) ?></a>
    </div>
  </div>
  </main>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
