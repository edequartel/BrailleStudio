<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

try {
    require __DIR__ . '/lib.php';

    $res = sb_request(
        'GET',
        'xapi_statements?select=student_code,verb_display,lesson_id,success,score_raw,created_at&order=created_at.desc&limit=1000'
    );

    if (($res['status'] ?? 500) >= 400) {
        throw new RuntimeException($res['raw'] ?? 'Supabase error');
    }

    $rows = $res['data'] ?? [];
    $stats = [];

    foreach ($rows as $r) {
        $code = $r['student_code'] ?? 'unknown';
        $verb = $r['verb_display'] ?? '';

        if (!isset($stats[$code])) {
            $stats[$code] = [
                'events' => 0,
                'started' => 0,
                'answered' => 0,
                'completed' => 0,
                'errors' => 0,
                'score_total' => 0,
                'score_count' => 0,
                'last' => $r['created_at'] ?? ''
            ];
        }

        $stats[$code]['events']++;

        if ($verb === 'initialized' || $verb === 'attempted') $stats[$code]['started']++;
        if ($verb === 'answered') $stats[$code]['answered']++;
        if ($verb === 'completed') $stats[$code]['completed']++;
        if (($r['success'] ?? null) === false || $verb === 'made-error') $stats[$code]['errors']++;

        if (($r['score_raw'] ?? null) !== null) {
            $stats[$code]['score_total'] += (float)$r['score_raw'];
            $stats[$code]['score_count']++;
        }
    }

} catch (Throwable $e) {
    echo '<h1>Teacher dashboard error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$selectedStudent = $_GET['student'] ?? '';
$studentCodes = array_keys($stats);
sort($studentCodes);

$filteredRows = $rows;

if ($selectedStudent !== '') {
    $filteredRows = array_filter($rows, function ($r) use ($selectedStudent) {
        return ($r['student_code'] ?? '') === $selectedStudent;
    });
}

$totalEvents = count($filteredRows);
$totalStudents = $selectedStudent !== '' ? 1 : count($stats);
?>

<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
</head>

<body>
<div class="page">
    <div class="page-wrapper">

        <div class="page-header">
            <div class="container-xl">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Teacher dashboard</h1>
                        <div class="text-secondary">BrailleStudio xAPI voortgang</div>
                    </div>

                    <a href="students.php" class="btn btn-primary">Studenten</a>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">

                <form method="get" class="card mb-3">
                    <div class="card-body">
                        <label class="form-label">Selecteer student</label>

                        <select name="student" class="form-select" onchange="this.form.submit()">
                            <option value="">Alle studenten</option>

                            <?php foreach ($studentCodes as $studentCode): ?>
                                <option value="<?= h((string)$studentCode) ?>"
                                    <?= $selectedStudent === $studentCode ? 'selected' : '' ?>>
                                    <?= h((string)$studentCode) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($selectedStudent !== ''): ?>
                            <a class="btn btn-success w-100 mt-3"
                               href="student-analysis.php?student=<?= urlencode($selectedStudent) ?>">
                                Analyseer deze student
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="row row-cards mb-3">
                    <div class="col-6 col-md-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Studenten</div>
                                <div class="h1"><?= $totalStudents ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">xAPI events</div>
                                <div class="h1"><?= $totalEvents ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row row-cards">
                    <?php foreach ($stats as $code => $s): ?>
                        <?php
                        if ($selectedStudent !== '' && $selectedStudent !== $code) continue;

                        $avg = $s['score_count'] > 0
                            ? round(($s['score_total'] / $s['score_count']) * 100)
                            : 0;
                        ?>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title"><?= h((string)$code) ?></h3>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Gemiddelde score</span>
                                            <strong><?= $avg ?>%</strong>
                                        </div>

                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?= $avg ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="row text-center">
                                        <div class="col">
                                            <div class="h2"><?= $s['started'] ?></div>
                                            <div class="text-secondary">gestart</div>
                                        </div>

                                        <div class="col">
                                            <div class="h2"><?= $s['answered'] ?></div>
                                            <div class="text-secondary">antw.</div>
                                        </div>

                                        <div class="col">
                                            <div class="h2"><?= $s['errors'] ?></div>
                                            <div class="text-secondary">fouten</div>
                                        </div>

                                        <div class="col">
                                            <div class="h2"><?= $s['completed'] ?></div>
                                            <div class="text-secondary">klaar</div>
                                        </div>
                                    </div>

                                    <a class="btn btn-outline-success w-100 mt-3"
                                       href="student-analysis.php?student=<?= urlencode((string)$code) ?>">
                                        Analyse
                                    </a>
                                </div>

                                <div class="card-footer text-secondary">
                                    Laatste activiteit: <?= h((string)$s['last']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h2 class="card-title">Laatste xAPI statements</h2>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Student</th>
                                <th>Verb</th>
                                <th>Les</th>
                                <th>Score</th>
                                <th>Succes</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach (array_slice($filteredRows, 0, 25) as $r): ?>
                                <?php $success = $r['success'] ?? null; ?>

                                <tr>
                                    <td><?= h((string)($r['created_at'] ?? '')) ?></td>
                                    <td><?= h((string)($r['student_code'] ?? '')) ?></td>
                                    <td><?= h((string)($r['verb_display'] ?? '')) ?></td>
                                    <td><?= h((string)($r['lesson_id'] ?? '')) ?></td>
                                    <td><?= h((string)($r['score_raw'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($success === true): ?>
                                            <span class="badge bg-green">goed</span>
                                        <?php elseif ($success === false): ?>
                                            <span class="badge bg-red">fout</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">n.v.t.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($filteredRows) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-secondary">
                                        Nog geen statements voor deze selectie.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
</body>
</html>