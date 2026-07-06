<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require __DIR__ . '/lib.php';

/*
|--------------------------------------------------------------------------
| Delete student
|--------------------------------------------------------------------------
| Deletes the student and all xAPI events belonging to that student.
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_student'])
) {
    $studentCode = $_POST['student_code'] ?? '';

    if ($studentCode !== '') {
        sb_request(
            'DELETE',
            'xapi_statements?student_code=eq.' . urlencode($studentCode)
        );

        sb_request(
            'DELETE',
            'students?student_code=eq.' . urlencode($studentCode)
        );

        header('Location: students.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Load students
|--------------------------------------------------------------------------
*/

$res = sb_request(
    'GET',
    'students?select=*&order=display_name.asc'
);

if (($res['status'] ?? 500) >= 400) {
    echo '<h1>' . h(t('xapi.students.error_title')) . '</h1>';
    echo '<pre>' . h((string)($res['raw'] ?? t('xapi.students.supabase_error'))) . '</pre>';
    exit;
}

$students = $res['data'] ?? [];

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
    <title><?= h(t('xapi.students.title')) ?></title>

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
<div class="page">
    <div class="page-wrapper">

        <div class="page-header">
            <div class="container-xl">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title"><?= h(t('xapi.students.title')) ?></h1>
                        <div class="text-secondary">
                            <?= h(t('xapi.students.subtitle')) ?>
                        </div>
                    </div>

                    <a href="teacher-dashboard.php" class="btn btn-secondary">
                        <?= h(t('xapi.dashboard.short_title')) ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">

                <div class="mb-3">
                    <a href="student-edit.php" class="btn btn-primary w-100">
                        <?= h(t('xapi.students.new_student')) ?>
                    </a>
                </div>

                <?php if (count($students) === 0): ?>
                    <div class="alert alert-warning">
                        <?= h(t('xapi.students.empty')) ?>
                    </div>
                <?php endif; ?>

                <div class="row row-cards">

                    <?php foreach ($students as $student): ?>
                        <?php
                        $studentCode = (string)($student['student_code'] ?? '');
                        $displayName = (string)($student['display_name'] ?? '');
                        $active = (bool)($student['active'] ?? false);
                        ?>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card">

                                <div class="card-body">
                                    <h3 class="card-title mb-1">
                                        <?= h($displayName) ?>
                                    </h3>

                                    <div class="text-secondary mb-3">
                                        <?= h(t('xapi.students.code')) ?>:
                                        <strong><?= h($studentCode) ?></strong>
                                    </div>

                                    <div class="mb-3">
                                        <?php if ($active): ?>
                                            <span class="badge bg-green"><?= h(t('users.status.active')) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-red"><?= h(t('xapi.students.inactive')) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <a class="btn btn-outline-primary"
                                           href="student-edit.php?id=<?= urlencode((string)($student['id'] ?? '')) ?>">
                                            <?= h(t('common.edit')) ?>
                                        </a>

                                        <a class="btn btn-outline-success"
                                           href="student-analysis.php?student=<?= urlencode($studentCode) ?>">
                                            <?= h(t('xapi.analysis.action')) ?>
                                        </a>

                                        <form method="post"
                                              onsubmit="return confirm('<?= h(t('xapi.students.confirm_delete')) ?>');">

                                            <input type="hidden"
                                                   name="student_code"
                                                   value="<?= h($studentCode) ?>">

                                            <button type="submit"
                                                    name="delete_student"
                                                    class="btn btn-outline-danger w-100">
                                                <?= h(t('common.delete')) ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="card-footer text-secondary">
                                    <?= h(t('xapi.students.created_at')) ?>:
                                    <?= h((string)($student['created_at'] ?? '')) ?>
                                </div>

                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            </div>
        </div>

    </div>
</div>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
