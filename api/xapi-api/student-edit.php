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
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student bewerken</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>
<body>
<div class="page page-center">
    <div class="container container-tight py-4">

        <form method="post" class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?= $student ? 'Student bewerken' : 'Nieuwe student' ?>
                </h2>
            </div>

            <div class="card-body">
                <input type="hidden" name="id" value="<?= h($student['id'] ?? '') ?>">

                <div class="mb-3">
                    <label class="form-label">Studentcode</label>
                    <input class="form-control"
                           name="student_code"
                           required
                           value="<?= h($student['student_code'] ?? '') ?>"
                           placeholder="bijv. STU001">
                </div>

                <div class="mb-3">
                    <label class="form-label">Naam</label>
                    <input class="form-control"
                           name="display_name"
                           required
                           value="<?= h($student['display_name'] ?? '') ?>"
                           placeholder="bijv. Tinus">
                </div>

                <label class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           name="active"
                        <?= !isset($student) || $student['active'] ? 'checked' : '' ?>>
                    <span class="form-check-label">Actief</span>
                </label>
            </div>

            <div class="card-footer d-flex gap-2">
                <a href="students.php" class="btn btn-secondary w-50">Terug</a>
                <button class="btn btn-primary w-50">Opslaan</button>
            </div>
        </form>

    </div>
</div>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>