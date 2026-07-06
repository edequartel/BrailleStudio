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
    return array_keys(bs_language_available());
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
    $available = bs_language_available();
    $path = (string)($available[$code]['path'] ?? '');
    if ($path === '') {
        return [];
    }
    return bs_language_decode_file($path);
}

$selectedLanguage = tr_admin_selected_language();
$languages = bs_language_available();
$dutch = tr_admin_flatten(tr_admin_read_language('nl'));
$selected = tr_admin_flatten(tr_admin_read_language($selectedLanguage));
$allKeys = array_values(array_unique(array_merge(array_keys($dutch), array_keys($selected))));
sort($allKeys, SORT_NATURAL);
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

        <div class="card">
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
                  <?php $missing = !array_key_exists($key, $selected) || trim($selected[$key]) === ''; ?>
                  <tr>
                    <td><code><?= tr_admin_h($key) ?></code></td>
                    <td><?= tr_admin_h($dutch[$key] ?? '') ?></td>
                    <td><?= tr_admin_h($selected[$key] ?? '') ?></td>
                    <td>
                      <?php if ($missing): ?>
                        <span class="badge bg-warning-lt"><?= tr_admin_h(t('admin.translations.missing')) ?></span>
                      <?php else: ?>
                        <span class="badge bg-green-lt"><?= tr_admin_h(t('admin.translations.present')) ?></span>
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
  </main>
</div>
<script src="../tabler/core/dist/js/tabler.min.js"></script>
</body>
</html>
