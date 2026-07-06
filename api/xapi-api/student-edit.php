<?php
require __DIR__ . '/lib.php';

$id = $_GET['id'] ?? '';
$student = null;

if ($id) {
    $res = sb_request('GET', 'students?id=eq.' . urlencode($id) . '&select=*');
    $student = $res['data'][0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'student_code' => trim($_POST['student_code']),
        'display_name' => trim($_POST['display_name']),
        'active' => isset($_POST['active'])
    ];

    if ($_POST['id'] ?? '') {
        sb_request('PATCH', 'students?id=eq.' . urlencode($_POST['id']), $data);
    } else {
        sb_request('POST', 'students', $data);
    }

    header('Location: students.php');
    exit;
}
?>

<!doctype html>
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
    <title><?= h(t('xapi.student_edit.page_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
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
<body>
<div class="page page-center">
    <div class="container container-tight py-4">

        <form method="post" class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?= h($student ? t('xapi.student_edit.edit_title') : t('xapi.students.new_student')) ?>
                </h2>
            </div>

            <div class="card-body">
                <input type="hidden" name="id" value="<?= h($student['id'] ?? '') ?>">

                <div class="mb-3">
                    <label class="form-label"><?= h(t('xapi.students.student_code')) ?></label>
                    <input class="form-control"
                           name="student_code"
                           required
                           value="<?= h($student['student_code'] ?? '') ?>"
                           placeholder="<?= h(t('xapi.student_edit.student_code_placeholder')) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label"><?= h(t('xapi.student_edit.name')) ?></label>
                    <input class="form-control"
                           name="display_name"
                           required
                           value="<?= h($student['display_name'] ?? '') ?>"
                           placeholder="<?= h(t('xapi.student_edit.name_placeholder')) ?>">
                </div>

                <label class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           name="active"
                        <?= !isset($student) || $student['active'] ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= h(t('users.status.active')) ?></span>
                </label>
            </div>

            <div class="card-footer d-flex gap-2">
                <a href="students.php" class="btn btn-secondary w-50"><?= h(t('common.back')) ?></a>
                <button class="btn btn-primary w-50"><?= h(t('common.save')) ?></button>
            </div>
        </form>

    </div>
</div>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
