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

$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!bs_auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'De sessie is verlopen. Probeer opnieuw.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));
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
        <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-braille text-primary" aria-hidden="true"></i></span>
        <span>BrailleStudio</span>
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
            <div class="card">
              <div class="table-responsive">
                <table class="table table-vcenter card-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Gebruiker</th>
                      <th>Rol</th>
                      <th>Status</th>
                      <th class="w-1"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $user): ?>
                      <?php $role = bs_auth_user_role_by_id((int)$user['id']); ?>
                      <tr>
                        <td class="text-secondary"><?= (int)$user['id'] ?></td>
                        <td>
                          <div class="fw-semibold"><?= $html((string)($user['username'] ?: $user['email'])) ?></div>
                          <div class="text-secondary"><?= $html((string)$user['email']) ?></div>
                        </td>
                        <td>
                          <form class="d-flex gap-2" method="post">
                            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                            <select class="form-select" name="role" aria-label="Rol">
                              <?php foreach (['leerling', 'docent', 'admin'] as $option): ?>
                                <option value="<?= $html($option) ?>"<?= $role === $option ? ' selected' : '' ?>><?= $html($option) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="submit" title="Rol opslaan">
                              <i class="ti ti-device-floppy" aria-hidden="true"></i>
                            </button>
                          </form>
                        </td>
                        <td>
                          <span class="badge bg-<?= ((int)$user['verified'] === 1) ? 'green' : 'yellow' ?>-lt">
                            <?= ((int)$user['verified'] === 1) ? 'geverifieerd' : 'actief' ?>
                          </span>
                        </td>
                        <td>
                          <?php if ((int)$user['id'] !== (int)$currentUser['id']): ?>
                            <form method="post" onsubmit="return confirm('Deze gebruiker verwijderen?');">
                              <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                              <button class="btn btn-outline-danger" type="submit" title="Verwijderen">
                                <i class="ti ti-trash" aria-hidden="true"></i>
                              </button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= $html($baseUrl) ?>tabler/core/dist/js/tabler.min.js"></script>
</body>
</html>
