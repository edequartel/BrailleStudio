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
    echo '<h1>Students error</h1>';
    echo '<pre>' . h((string)($res['raw'] ?? 'Supabase error')) . '</pre>';
    exit;
}

$students = $res['data'] ?? [];

?>

<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Studenten</title>

    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body>
<div class="page">
    <div class="page-wrapper">

        <div class="page-header">
            <div class="container-xl">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Studenten</h1>
                        <div class="text-secondary">
                            BrailleStudio xAPI studentenbeheer
                        </div>
                    </div>

                    <a href="teacher-dashboard.php" class="btn btn-secondary">
                        Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">

                <div class="mb-3">
                    <a href="student-edit.php" class="btn btn-primary w-100">
                        Nieuwe student
                    </a>
                </div>

                <?php if (count($students) === 0): ?>
                    <div class="alert alert-warning">
                        Nog geen studenten gevonden.
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
                                        Code:
                                        <strong><?= h($studentCode) ?></strong>
                                    </div>

                                    <div class="mb-3">
                                        <?php if ($active): ?>
                                            <span class="badge bg-green">actief</span>
                                        <?php else: ?>
                                            <span class="badge bg-red">inactief</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <a class="btn btn-outline-primary"
                                           href="student-edit.php?id=<?= urlencode((string)($student['id'] ?? '')) ?>">
                                            Bewerken
                                        </a>

                                        <a class="btn btn-outline-success"
                                           href="student-analysis.php?student=<?= urlencode($studentCode) ?>">
                                            Analyse
                                        </a>

                                        <form method="post"
                                              onsubmit="return confirm('Student en alle xAPI-events verwijderen?');">

                                            <input type="hidden"
                                                   name="student_code"
                                                   value="<?= h($studentCode) ?>">

                                            <button type="submit"
                                                    name="delete_student"
                                                    class="btn btn-outline-danger w-100">
                                                Verwijderen
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="card-footer text-secondary">
                                    Aangemaakt:
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
</body>
</html>