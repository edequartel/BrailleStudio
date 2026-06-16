<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/bootstrap.php';

$currentUser = bs_auth_require_login(['admin']);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$baseUrl = $scriptDir === '' ? './' : $scriptDir . '/';
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function users_table_name(): string
{
    $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string)(bs_auth_config()['auth']['table_prefix'] ?? ''));
    return $prefix . 'users';
}

function users_fetch_all(): array
{
    $stmt = bs_auth_pdo()->query('SELECT id, email, username, status, verified, roles_mask, registered, last_login FROM ' . users_table_name() . ' ORDER BY id ASC');
    return $stmt->fetchAll();
}

function users_update_profile(int $userId, string $email, string $username): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Onbekende gebruiker.');
    }

    $email = strtolower(trim($email));
    $username = trim($username);
    $usernameValue = $username !== '' ? $username : null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \Delight\Auth\InvalidEmailException();
    }

    $pdo = bs_auth_pdo();
    $table = users_table_name();

    $stmt = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch() === false) {
        throw new \Delight\Auth\UnknownIdException();
    }

    $stmt = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch() !== false) {
        throw new \Delight\Auth\UserAlreadyExistsException();
    }

    if ($usernameValue !== null) {
        $stmt = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$usernameValue, $userId]);
        if ($stmt->fetch() !== false) {
            throw new RuntimeException('Deze gebruikersnaam is al in gebruik.');
        }
    }

    $stmt = $pdo->prepare('UPDATE ' . $table . ' SET email = ?, username = ? WHERE id = ?');
    $stmt->execute([$email, $usernameValue, $userId]);
}

$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!bs_auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'De sessie is verlopen. Probeer opnieuw.';
    } else {
        $action = isset($_POST['delete_user']) ? 'delete' : trim((string)($_POST['action'] ?? ''));
        try {
            if ($action === 'create') {
                $email = trim((string)($_POST['email'] ?? ''));
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $role = trim((string)($_POST['role'] ?? 'leerling'));
                $userId = bs_auth()->admin()->createUser($email, $password, $username !== '' ? $username : null);
                bs_auth_set_user_role((int)$userId, $role);
                $notice = 'Gebruiker aangemaakt.';
            } elseif ($action === 'role') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $role = trim((string)($_POST['role'] ?? 'leerling'));
                bs_auth_set_user_role($userId, $role);
                $notice = 'Rol bijgewerkt.';
            } elseif ($action === 'profile') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $email = (string)($_POST['email'] ?? '');
                $username = (string)($_POST['username'] ?? '');
                $role = trim((string)($_POST['role'] ?? 'leerling'));
                $password = (string)($_POST['password'] ?? '');
                users_update_profile($userId, $email, $username);
                bs_auth_set_user_role($userId, $role);
                if ($password !== '') {
                    bs_auth()->admin()->changePasswordForUserById($userId, $password);
                }
                if ($userId === (int)$currentUser['id']) {
                    bs_auth_start_session();
                    $_SESSION[\Delight\Auth\UserManager::SESSION_FIELD_EMAIL] = strtolower(trim($email));
                    $_SESSION[\Delight\Auth\UserManager::SESSION_FIELD_USERNAME] = trim($username) !== '' ? trim($username) : null;
                    $_SESSION[\Delight\Auth\UserManager::SESSION_FIELD_ROLES] = bs_auth_role_map()[$role] ?? bs_auth_role_map()[bs_auth_default_role()];
                    $currentUser = bs_auth_current_user() ?? $currentUser;
                }
                $notice = $password !== '' ? 'Gebruiker en wachtwoord opgeslagen.' : 'Gebruiker opgeslagen.';
            } elseif ($action === 'delete') {
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId === (int)$currentUser['id']) {
                    throw new RuntimeException('Je kunt je eigen account hier niet verwijderen.');
                }
                bs_auth()->admin()->deleteUserById($userId);
                $notice = 'Gebruiker verwijderd.';
            }
        } catch (\Delight\Auth\InvalidEmailException $e) {
            $error = 'Ongeldig e-mailadres.';
        } catch (\Delight\Auth\InvalidPasswordException $e) {
            $error = 'Ongeldig wachtwoord.';
        } catch (\Delight\Auth\UserAlreadyExistsException $e) {
            $error = 'Deze gebruiker bestaat al.';
        } catch (\Delight\Auth\UnknownIdException $e) {
            $error = 'Onbekende gebruiker.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

try {
    $users = users_fetch_all();
} catch (Throwable $e) {
    $users = [];
    $error = $error !== '' ? $error : $e->getMessage();
}
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
  <title>Gebruikers - BrailleStudio</title>
  <link rel="stylesheet" href="<?= $html($baseUrl) ?>tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="<?= $html($baseUrl) ?>tabler/icons-webfont/dist/tabler-icons.min.css">
</head>
<body>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <a class="navbar-brand navbar-brand-autodark" href="<?= $html($baseUrl) ?>index.php">
        <img src="style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
      </a>
      <div class="navbar-nav flex-row ms-auto">
        <a class="btn btn-outline-secondary" href="<?= $html($baseUrl) ?>index.php">
          <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
          Start
        </a>
      </div>
    </div>
  </header>

  <main class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="page-pretitle">Admin</div>
        <h1 class="page-title">Gebruikers</h1>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= $html($error) ?></div>
        <?php elseif ($notice !== ''): ?>
          <div class="alert alert-success" role="alert"><?= $html($notice) ?></div>
        <?php endif; ?>

        <div class="row row-cards">
          <div class="col-12 col-lg-4">
            <form class="card" method="post">
              <div class="card-header">
                <h2 class="card-title">Nieuwe gebruiker</h2>
              </div>
              <div class="card-body">
                <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                  <label class="form-label" for="newEmail">E-mail</label>
                  <input id="newEmail" class="form-control" name="email" type="email" autocomplete="off" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="newUsername">Gebruikersnaam</label>
                  <input id="newUsername" class="form-control" name="username" type="text" autocomplete="off">
                </div>
                <div class="mb-3">
                  <label class="form-label" for="newPassword">Wachtwoord</label>
                  <input id="newPassword" class="form-control" name="password" type="password" autocomplete="new-password" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="newRole">Rol</label>
                  <select id="newRole" class="form-select" name="role">
                    <option value="leerling">leerling</option>
                    <option value="docent">docent</option>
                    <option value="developer">developer</option>
                    <option value="admin">admin</option>
                  </select>
                </div>
              </div>
              <div class="card-footer text-end">
                <button class="btn btn-primary" type="submit">
                  <i class="ti ti-user-plus me-2" aria-hidden="true"></i>
                  Aanmaken
                </button>
              </div>
            </form>
          </div>

          <div class="col-12 col-lg-8">
            <div class="accordion" id="usersAccordion">
              <?php foreach ($users as $user): ?>
                <?php
                  $userId = (int)$user['id'];
                  $role = bs_auth_user_role_by_id($userId);
                  $isCurrentUser = $userId === (int)$currentUser['id'];
                  $collapseId = 'userPanel' . $userId;
                  $headingId = 'userHeading' . $userId;
                ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="<?= $html($headingId) ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $html($collapseId) ?>" aria-expanded="false" aria-controls="<?= $html($collapseId) ?>">
                      <span class="me-3 fw-semibold"><?= $html((string)($user['username'] ?: $user['email'])) ?></span>
                      <span class="text-secondary me-3"><?= $html((string)$user['email']) ?></span>
                      <span class="badge bg-blue-lt me-2"><?= $html($role) ?></span>
                      <span class="badge bg-<?= ((int)$user['verified'] === 1) ? 'green' : 'yellow' ?>-lt">
                        <?= ((int)$user['verified'] === 1) ? 'geverifieerd' : 'actief' ?>
                      </span>
                    </button>
                  </h2>
                  <div id="<?= $html($collapseId) ?>" class="accordion-collapse collapse" aria-labelledby="<?= $html($headingId) ?>" data-bs-parent="#usersAccordion">
                    <div class="accordion-body">
                      <form method="post">
                        <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
                        <input type="hidden" name="action" value="profile">
                        <input type="hidden" name="user_id" value="<?= $userId ?>">

                        <div class="mb-3">
                          <label class="form-label" for="username<?= $userId ?>">Gebruikersnaam</label>
                          <input id="username<?= $userId ?>" class="form-control" name="username" type="text" value="<?= $html((string)($user['username'] ?? '')) ?>" autocomplete="off">
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="email<?= $userId ?>">E-mail</label>
                          <input id="email<?= $userId ?>" class="form-control" name="email" type="email" value="<?= $html((string)$user['email']) ?>" autocomplete="off" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="role<?= $userId ?>">Rol</label>
                          <select id="role<?= $userId ?>" class="form-select" name="role">
                            <?php foreach (['leerling', 'docent', 'developer', 'admin'] as $option): ?>
                              <option value="<?= $html($option) ?>"<?= $role === $option ? ' selected' : '' ?>><?= $html($option) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="password<?= $userId ?>">Nieuw wachtwoord</label>
                          <input id="password<?= $userId ?>" class="form-control" name="password" type="password" autocomplete="new-password" placeholder="Leeg laten om niet te wijzigen">
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <button class="btn btn-primary" type="submit">
                            <i class="ti ti-device-floppy me-2" aria-hidden="true"></i>
                            Opslaan
                          </button>
                          <?php if (!$isCurrentUser): ?>
                            <button class="btn btn-outline-danger" type="submit" name="delete_user" value="1" onclick="return confirm('Deze gebruiker verwijderen?');">
                              <i class="ti ti-trash me-2" aria-hidden="true"></i>
                              Verwijderen
                            </button>
                          <?php endif; ?>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= $html($baseUrl) ?>tabler/core/dist/js/tabler.min.js"></script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
