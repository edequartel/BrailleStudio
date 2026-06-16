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
        $error = 'De sessie is verlopen. Probeer opnieuw.';
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
            $error = 'Ongeldige gebruikersnaam of wachtwoord.';
        } catch (\Delight\Auth\EmailNotVerifiedException $e) {
            $error = 'Dit account is nog niet bevestigd.';
        } catch (\Delight\Auth\TooManyRequestsException $e) {
            $error = 'Te veel pogingen. Probeer later opnieuw.';
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
    $message = 'Ingelogd als ' . $user['display'] . ' (' . $user['role'] . ').';
}
?>
<!DOCTYPE html>
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
  <title>BrailleStudio Login</title>
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
</head>
<body class="bg-body">
  <main class="authentication-page">
  <div class="container container-tight py-4 w-100">
    <div class="text-center mb-4">
      <a class="navbar-brand navbar-brand-autodark justify-content-center" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
        <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-braille text-primary" aria-hidden="true"></i></span>
        <span>BrailleStudio</span>
      </a>
    </div>

    <div class="card card-md">
      <div class="card-body">
        <h1 class="h2 text-center mb-2">Inloggen</h1>
        <p class="text-secondary text-center mb-4">Log in om beveiligde onderdelen van BrailleStudio te gebruiken.</p>

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
              <label class="form-label" for="authIdentifierInput">E-mail of gebruikersnaam</label>
              <input id="authIdentifierInput" class="form-control" name="identifier" type="text" placeholder="naam@example.com" autocomplete="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="authPasswordInput">Wachtwoord</label>
              <input id="authPasswordInput" class="form-control" name="password" type="password" placeholder="Wachtwoord" autocomplete="current-password" required>
            </div>
            <label class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="remember" value="1">
              <span class="form-check-label">Ingelogd blijven</span>
            </label>
            <button class="btn btn-primary w-100" type="submit">
              <i class="ti ti-login me-2" aria-hidden="true"></i>
              Inloggen
            </button>
          </form>
        <?php else: ?>
          <form method="post" class="mb-0">
            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="returnTo" value="<?= $html($returnTo) ?>">
            <button class="btn btn-outline-secondary w-100" type="submit">
              <i class="ti ti-logout me-2" aria-hidden="true"></i>
              Uitloggen
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="text-center text-secondary mt-3">
      Na het inloggen ga je automatisch terug naar de pagina die je wilde openen.
    </div>
    <div class="text-center mt-3">
      <a class="btn btn-link" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">Terug naar home</a>
    </div>
  </div>
  </main>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
