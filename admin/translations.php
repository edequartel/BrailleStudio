<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/bootstrap.php';

$currentUser = bs_auth_require_login(['admin']);

function tr_admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function tr_admin_language_codes(): array
{
    return array_keys(tr_admin_available_languages());
}

function tr_admin_valid_language_code(string $code): bool
{
    return preg_match('/^(nl|en|de|fr|es|it|pt|sv|da|no|fi|pl|cs|hu|zh|ja|ko|ar)$/', $code) === 1;
}

function tr_admin_language_path(string $code): string
{
    $code = bs_language_normalize_code($code);
    if (!tr_admin_valid_language_code($code)) {
        throw new RuntimeException(t('admin.translations.errors.invalid_language'));
    }
    return bs_language_dir() . '/' . $code . '.json';
}

function tr_admin_available_languages(): array
{
    $languages = [];
    $files = glob(bs_language_dir() . '/*.json');
    if (!is_array($files)) {
        return [];
    }
    sort($files, SORT_STRING);
    foreach ($files as $path) {
        $code = bs_language_normalize_code((string)pathinfo($path, PATHINFO_FILENAME));
        if (!tr_admin_valid_language_code($code)) {
            continue;
        }
        $data = bs_language_decode_file($path);
        $meta = is_array($data['_meta'] ?? null) ? $data['_meta'] : [];
        $metaCode = bs_language_normalize_code((string)($meta['code'] ?? $code));
        if ($metaCode !== $code) {
            continue;
        }
        $direction = strtolower((string)($meta['direction'] ?? 'ltr'));
        $languages[$code] = [
            'code' => $code,
            'name' => trim((string)($meta['name'] ?? strtoupper($code))) ?: strtoupper($code),
            'native' => trim((string)($meta['native'] ?? strtoupper($code))) ?: strtoupper($code),
            'direction' => $direction === 'rtl' ? 'rtl' : 'ltr',
            'path' => $path,
        ];
    }
    return $languages;
}

function tr_admin_selected_language(): string
{
    $available = tr_admin_language_codes();
    $selected = bs_language_normalize_code((string)($_GET['editor_lang'] ?? $_POST['editor_lang'] ?? 'en'));
    if ($selected !== '' && in_array($selected, $available, true)) {
        return $selected;
    }
    if (in_array('en', $available, true)) {
        return 'en';
    }
    return $available[0] ?? bs_language_fallback_code();
}

function tr_admin_flatten(array $data, string $prefix = ''): array
{
    $flat = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $key = (string)$key;
        $path = $prefix === '' ? $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $flat += tr_admin_flatten($value, $path);
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $flat[$path] = (string)$value;
        }
    }
    return $flat;
}

function tr_admin_read_language(string $code): array
{
    $available = tr_admin_available_languages();
    $path = (string)($available[$code]['path'] ?? '');
    if ($path === '') {
        return [];
    }
    return bs_language_decode_file($path);
}

function tr_admin_unflatten(array $flat): array
{
    $data = [];
    foreach ($flat as $key => $value) {
        $key = trim((string)$key);
        if ($key === '' || $key === '_meta' || str_starts_with($key, '_meta.')) {
            continue;
        }
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $key) !== 1) {
            continue;
        }

        $cursor = &$data;
        foreach (explode('.', $key) as $part) {
            if ($part === '') {
                continue 2;
            }
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = [];
            }
            $cursor = &$cursor[$part];
        }
        $cursor = (string)$value;
        unset($cursor);
    }
    return $data;
}

function tr_admin_json_encode(array $data): string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException(t('admin.translations.errors.encode_failed'));
    }
    return $json . "\n";
}

function tr_admin_backup_language(string $code): void
{
    $path = tr_admin_language_path($code);
    if (!is_file($path)) {
        return;
    }

    $backupDir = bs_language_dir() . '/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException(t('admin.translations.errors.backup_failed'));
    }

    $backupPath = $backupDir . '/' . $code . '-' . date('Y-m-d-His') . '.json';
    if (!copy($path, $backupPath)) {
        throw new RuntimeException(t('admin.translations.errors.backup_failed'));
    }
}

function tr_admin_validate_translation_tree(array $data, bool $root = true): void
{
    foreach ($data as $key => $value) {
        $key = (string)$key;
        if ($root && $key === '_meta') {
            if (!is_array($value)) {
                throw new RuntimeException(t('admin.translations.errors.invalid_translation_file'));
            }
            continue;
        }
        if ($key === '' || preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
            throw new RuntimeException(t('admin.translations.errors.invalid_translation_file'));
        }
        if (is_array($value)) {
            tr_admin_validate_translation_tree($value, false);
            continue;
        }
        if (!is_scalar($value) && $value !== null) {
            throw new RuntimeException(t('admin.translations.errors.invalid_translation_file'));
        }
    }
}

function tr_admin_write_language(string $code, array $data): void
{
    $path = tr_admin_language_path($code);
    tr_admin_validate_translation_tree($data);
    $json = tr_admin_json_encode($data);
    json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    tr_admin_backup_language($code);

    $handle = fopen($path, 'c');
    if ($handle === false) {
        throw new RuntimeException(t('admin.translations.errors.write_failed'));
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException(t('admin.translations.errors.lock_failed'));
        }
        ftruncate($handle, 0);
        rewind($handle);
        if (fwrite($handle, $json) === false) {
            throw new RuntimeException(t('admin.translations.errors.write_failed'));
        }
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function tr_admin_language_data_from_flat(string $code, array $flat): array
{
    $current = tr_admin_read_language($code);
    $meta = is_array($current['_meta'] ?? null) ? $current['_meta'] : [];
    $meta['code'] = $code;
    $meta['name'] = trim((string)($meta['name'] ?? strtoupper($code))) ?: strtoupper($code);
    $meta['native'] = trim((string)($meta['native'] ?? strtoupper($code))) ?: strtoupper($code);
    $meta['direction'] = strtolower((string)($meta['direction'] ?? 'ltr')) === 'rtl' ? 'rtl' : 'ltr';
    return ['_meta' => $meta] + tr_admin_unflatten($flat);
}

function tr_admin_valid_import_data(array $data, string $code): array
{
    $meta = is_array($data['_meta'] ?? null) ? $data['_meta'] : null;
    if ($meta === null) {
        throw new RuntimeException(t('admin.translations.errors.meta_required'));
    }
    $metaCode = bs_language_normalize_code((string)($meta['code'] ?? ''));
    if ($metaCode !== $code) {
        throw new RuntimeException(t('admin.translations.errors.import_code_mismatch'));
    }
    $direction = strtolower((string)($meta['direction'] ?? 'ltr'));
    $data['_meta']['code'] = $code;
    $data['_meta']['name'] = trim((string)($meta['name'] ?? strtoupper($code))) ?: strtoupper($code);
    $data['_meta']['native'] = trim((string)($meta['native'] ?? strtoupper($code))) ?: strtoupper($code);
    $data['_meta']['direction'] = $direction === 'rtl' ? 'rtl' : 'ltr';
    tr_admin_validate_translation_tree($data);
    return $data;
}

if (($_GET['action'] ?? '') === 'export') {
    $exportLanguage = tr_admin_selected_language();
    $path = tr_admin_language_path($exportLanguage);
    if (!is_file($path)) {
        http_response_code(404);
        echo tr_admin_h(t('admin.translations.errors.language_not_found'));
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $exportLanguage . '.json"');
    readfile($path);
    exit;
}

$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        if (!bs_auth_verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException(t('errors.session_expired'));
        }

        if ($action === 'save') {
            $code = bs_language_normalize_code((string)($_POST['editor_lang'] ?? ''));
            if (!in_array($code, tr_admin_language_codes(), true)) {
                throw new RuntimeException(t('admin.translations.errors.invalid_language'));
            }
            $translations = is_array($_POST['translations'] ?? null) ? $_POST['translations'] : [];
            tr_admin_write_language($code, tr_admin_language_data_from_flat($code, $translations));
            $notice = t('admin.translations.notices.saved', ['code' => $code]);
        } elseif ($action === 'add_language') {
            $code = bs_language_normalize_code((string)($_POST['new_code'] ?? ''));
            if (!tr_admin_valid_language_code($code)) {
                throw new RuntimeException(t('admin.translations.errors.invalid_language'));
            }
            if (is_file(tr_admin_language_path($code))) {
                throw new RuntimeException(t('admin.translations.errors.language_exists'));
            }
            $direction = strtolower((string)($_POST['new_direction'] ?? 'ltr')) === 'rtl' ? 'rtl' : 'ltr';
            $meta = [
                'code' => $code,
                'name' => trim((string)($_POST['new_name'] ?? strtoupper($code))) ?: strtoupper($code),
                'native' => trim((string)($_POST['new_native'] ?? strtoupper($code))) ?: strtoupper($code),
                'direction' => $direction,
            ];
            $body = isset($_POST['copy_dutch']) ? tr_admin_unflatten(tr_admin_flatten(tr_admin_read_language('nl'))) : [];
            tr_admin_write_language($code, ['_meta' => $meta] + $body);
            $notice = t('admin.translations.notices.language_added', ['code' => $code]);
            $_POST['editor_lang'] = $code;
        } elseif ($action === 'import') {
            $code = bs_language_normalize_code((string)($_POST['editor_lang'] ?? ''));
            if (!in_array($code, tr_admin_language_codes(), true)) {
                throw new RuntimeException(t('admin.translations.errors.invalid_language'));
            }
            $upload = $_FILES['import_file'] ?? null;
            if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException(t('admin.translations.errors.import_failed'));
            }
            if ((int)($upload['size'] ?? 0) > 1024 * 1024) {
                throw new RuntimeException(t('admin.translations.errors.import_too_large'));
            }
            $json = file_get_contents((string)$upload['tmp_name']);
            if (!is_string($json)) {
                throw new RuntimeException(t('admin.translations.errors.import_failed'));
            }
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException(t('admin.translations.errors.invalid_json'));
            }
            tr_admin_write_language($code, tr_admin_valid_import_data($data, $code));
            $notice = t('admin.translations.notices.imported', ['code' => $code]);
        }
    } catch (JsonException $e) {
        $error = t('admin.translations.errors.invalid_json');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$selectedLanguage = tr_admin_selected_language();
$languages = tr_admin_available_languages();
$dutch = tr_admin_flatten(tr_admin_read_language('nl'));
$selected = tr_admin_flatten(tr_admin_read_language($selectedLanguage));
$allKeys = array_values(array_unique(array_merge(array_keys($dutch), array_keys($selected))));
sort($allKeys, SORT_NATURAL);
$missingCount = 0;
$changedCount = 0;
$selectedOnlyCount = 0;
foreach ($allKeys as $key) {
    $hasDutch = array_key_exists($key, $dutch);
    $hasSelected = array_key_exists($key, $selected);
    if (!$hasSelected || trim($selected[$key]) === '') {
        $missingCount++;
    }
    if ($hasDutch && $hasSelected && (string)$dutch[$key] !== (string)$selected[$key]) {
        $changedCount++;
    }
    if (!$hasDutch && $hasSelected) {
        $selectedOnlyCount++;
    }
}
?>
<!doctype html>
<html <?= bs_language_html_attrs() ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= tr_admin_h(t('admin.translations.title')) ?></title>
  <link rel="stylesheet" href="../tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="../tabler/icons-webfont/dist/tabler-icons.min.css">
</head>
<body>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <a class="navbar-brand navbar-brand-autodark" href="../index.php">
        <img src="../style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="../style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
      </a>
      <div class="navbar-nav flex-row align-items-center ms-auto">
        <?= language_switcher('me-2') ?>
        <a class="btn btn-outline-secondary" href="../index.php">
          <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
          <?= tr_admin_h(t('common.back_home')) ?>
        </a>
      </div>
    </div>
  </header>

  <main class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="page-pretitle"><?= tr_admin_h(t('admin.translations.pretitle')) ?></div>
        <h1 class="page-title"><?= tr_admin_h(t('admin.translations.title')) ?></h1>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= tr_admin_h($error) ?></div>
        <?php elseif ($notice !== ''): ?>
          <div class="alert alert-success" role="alert"><?= tr_admin_h($notice) ?></div>
        <?php endif; ?>

        <form method="get" class="card mb-3">
          <div class="card-body">
            <label class="form-label" for="editor_lang"><?= tr_admin_h(t('admin.translations.language_selector')) ?></label>
            <select id="editor_lang" class="form-select" name="editor_lang" onchange="this.form.submit()">
              <?php foreach ($languages as $code => $meta): ?>
                <option value="<?= tr_admin_h($code) ?>"<?= $selectedLanguage === $code ? ' selected' : '' ?>>
                  <?= tr_admin_h($meta['native'] . ' (' . $code . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <div class="row row-cards mb-3">
          <div class="col-12 col-lg-6">
            <form method="post" class="card">
              <div class="card-header">
                <h2 class="card-title"><?= tr_admin_h(t('admin.translations.add_language')) ?></h2>
              </div>
              <div class="card-body">
                <input type="hidden" name="csrf" value="<?= tr_admin_h(bs_auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="add_language">
                <div class="row g-2">
                  <div class="col-sm-3">
                    <label class="form-label" for="new_code"><?= tr_admin_h(t('admin.translations.language_code')) ?></label>
                    <input id="new_code" class="form-control" name="new_code" type="text" maxlength="2" pattern="[A-Za-z]{2}" placeholder="de" required>
                  </div>
                  <div class="col-sm-3">
                    <label class="form-label" for="new_name"><?= tr_admin_h(t('admin.translations.language_name')) ?></label>
                    <input id="new_name" class="form-control" name="new_name" type="text" placeholder="German" required>
                  </div>
                  <div class="col-sm-3">
                    <label class="form-label" for="new_native"><?= tr_admin_h(t('admin.translations.language_native')) ?></label>
                    <input id="new_native" class="form-control" name="new_native" type="text" placeholder="Deutsch" required>
                  </div>
                  <div class="col-sm-3">
                    <label class="form-label" for="new_direction"><?= tr_admin_h(t('admin.translations.direction')) ?></label>
                    <select id="new_direction" class="form-select" name="new_direction">
                      <option value="ltr">ltr</option>
                      <option value="rtl">rtl</option>
                    </select>
                  </div>
                </div>
                <label class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" name="copy_dutch" value="1">
                  <span class="form-check-label"><?= tr_admin_h(t('admin.translations.copy_dutch')) ?></span>
                </label>
              </div>
              <div class="card-footer text-end">
                <button class="btn btn-primary" type="submit"><?= tr_admin_h(t('admin.translations.add_language')) ?></button>
              </div>
            </form>
          </div>

          <div class="col-12 col-lg-6">
            <div class="card">
              <div class="card-header">
                <h2 class="card-title"><?= tr_admin_h(t('admin.translations.import_export')) ?></h2>
              </div>
              <div class="card-body">
                <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
                  <a class="btn btn-outline-secondary" href="?editor_lang=<?= tr_admin_h($selectedLanguage) ?>&amp;action=export">
                    <i class="ti ti-download me-2" aria-hidden="true"></i>
                    <?= tr_admin_h(t('admin.translations.export_json')) ?>
                  </a>
                </div>
                <form method="post" enctype="multipart/form-data">
                  <input type="hidden" name="csrf" value="<?= tr_admin_h(bs_auth_csrf_token()) ?>">
                  <input type="hidden" name="action" value="import">
                  <input type="hidden" name="editor_lang" value="<?= tr_admin_h($selectedLanguage) ?>">
                  <label class="form-label" for="import_file"><?= tr_admin_h(t('admin.translations.import_json')) ?></label>
                  <div class="input-group">
                    <input id="import_file" class="form-control" name="import_file" type="file" accept="application/json,.json" required>
                    <button class="btn btn-primary" type="submit"><?= tr_admin_h(t('admin.translations.import_button')) ?></button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <form method="post" class="card">
          <input type="hidden" name="csrf" value="<?= tr_admin_h(bs_auth_csrf_token()) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="editor_lang" value="<?= tr_admin_h($selectedLanguage) ?>">
          <div class="card-header">
            <div>
              <h2 class="card-title"><?= tr_admin_h(t('admin.translations.editor_for', ['code' => $selectedLanguage])) ?></h2>
              <div class="card-subtitle">
                <?= tr_admin_h(t('admin.translations.summary', [
                    'total' => (string)count($allKeys),
                    'missing' => (string)$missingCount,
                    'changed' => (string)$changedCount,
                    'extra' => (string)$selectedOnlyCount,
                ])) ?>
              </div>
            </div>
            <div class="card-actions">
              <button class="btn btn-primary" type="submit">
                <i class="ti ti-device-floppy me-2" aria-hidden="true"></i>
                <?= tr_admin_h(t('admin.translations.save')) ?>
              </button>
            </div>
          </div>
          <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-lg-6">
                <label class="form-label" for="translationSearch"><?= tr_admin_h(t('admin.translations.search')) ?></label>
                <input id="translationSearch" class="form-control" type="search" placeholder="<?= tr_admin_h(t('admin.translations.search_placeholder')) ?>">
              </div>
              <div class="col-12 col-lg-6">
                <div class="d-flex flex-wrap gap-3">
                  <label class="form-check mb-0">
                    <input id="filterMissing" class="form-check-input" type="checkbox">
                    <span class="form-check-label"><?= tr_admin_h(t('admin.translations.filter_missing')) ?></span>
                  </label>
                  <label class="form-check mb-0">
                    <input id="filterChanged" class="form-check-input" type="checkbox">
                    <span class="form-check-label"><?= tr_admin_h(t('admin.translations.filter_changed')) ?></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead>
                <tr>
                  <th><?= tr_admin_h(t('admin.translations.key')) ?></th>
                  <th><?= tr_admin_h(t('admin.translations.dutch_source')) ?></th>
                  <th><?= tr_admin_h(t('admin.translations.selected_translation')) ?></th>
                  <th><?= tr_admin_h(t('admin.translations.status')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allKeys as $key): ?>
                  <?php
                    $hasDutch = array_key_exists($key, $dutch);
                    $hasSelected = array_key_exists($key, $selected);
                    $missing = !$hasSelected || trim($selected[$key]) === '';
                    $changed = $hasDutch && $hasSelected && (string)$dutch[$key] !== (string)$selected[$key];
                    $selectedOnly = !$hasDutch && $hasSelected;
                    $searchText = strtolower($key . ' ' . ($dutch[$key] ?? '') . ' ' . ($selected[$key] ?? ''));
                  ?>
                  <tr
                    data-translation-row
                    data-search="<?= tr_admin_h($searchText) ?>"
                    data-missing="<?= $missing ? '1' : '0' ?>"
                    data-changed="<?= $changed ? '1' : '0' ?>"
                  >
                    <td><code><?= tr_admin_h($key) ?></code></td>
                    <td><?= tr_admin_h($dutch[$key] ?? '') ?></td>
                    <td>
                      <textarea
                        class="form-control"
                        name="translations[<?= tr_admin_h($key) ?>]"
                        rows="2"
                        aria-label="<?= tr_admin_h(t('admin.translations.edit_key', ['key' => $key])) ?>"
                      ><?= tr_admin_h($selected[$key] ?? '') ?></textarea>
                    </td>
                    <td>
                      <?php if ($missing): ?>
                        <span class="badge bg-warning-lt"><?= tr_admin_h(t('admin.translations.missing')) ?></span>
                      <?php elseif ($selectedOnly): ?>
                        <span class="badge bg-purple-lt"><?= tr_admin_h(t('admin.translations.selected_only')) ?></span>
                      <?php elseif (!$hasSelected): ?>
                        <span class="badge bg-warning-lt"><?= tr_admin_h(t('admin.translations.missing')) ?></span>
                      <?php elseif ($changed): ?>
                        <span class="badge bg-blue-lt"><?= tr_admin_h(t('admin.translations.changed')) ?></span>
                      <?php else: ?>
                        <span class="badge bg-green-lt"><?= tr_admin_h(t('admin.translations.present')) ?></span>
                      <?php endif; ?>
                      <?php if (!$hasDutch): ?>
                        <span class="badge bg-red-lt"><?= tr_admin_h(t('admin.translations.source_missing')) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer text-end">
            <button class="btn btn-primary" type="submit">
              <i class="ti ti-device-floppy me-2" aria-hidden="true"></i>
              <?= tr_admin_h(t('admin.translations.save')) ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>
<script src="../tabler/core/dist/js/tabler.min.js"></script>
<script>
  const searchInput = document.getElementById('translationSearch');
  const missingInput = document.getElementById('filterMissing');
  const changedInput = document.getElementById('filterChanged');
  const rows = Array.from(document.querySelectorAll('[data-translation-row]'));

  function applyTranslationFilters() {
    const query = (searchInput?.value || '').trim().toLowerCase();
    const missingOnly = Boolean(missingInput?.checked);
    const changedOnly = Boolean(changedInput?.checked);

    rows.forEach((row) => {
      const matchesSearch = query === '' || String(row.dataset.search || '').includes(query);
      const matchesMissing = !missingOnly || row.dataset.missing === '1';
      const matchesChanged = !changedOnly || row.dataset.changed === '1';
      row.hidden = !(matchesSearch && matchesMissing && matchesChanged);
    });
  }

  searchInput?.addEventListener('input', applyTranslationFilters);
  missingInput?.addEventListener('change', applyTranslationFilters);
  changedInput?.addEventListener('change', applyTranslationFilters);
</script>
</body>
</html>
