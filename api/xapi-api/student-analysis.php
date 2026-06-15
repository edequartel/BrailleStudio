<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

try {
    require __DIR__ . '/lib.php';

    $student = $_GET['student'] ?? '';

    if ($student === '') {
        throw new RuntimeException('Geen student geselecteerd.');
    }

    $query = 'xapi_statements?select=student_code,verb_display,lesson_id,success,score_raw,response,correct_response,duration_seconds,created_at,statement'
        . '&student_code=eq.' . urlencode($student)
        . '&order=created_at.asc'
        . '&limit=5000';

    $res = sb_request('GET', $query);

    if (($res['status'] ?? 500) >= 400) {
        throw new RuntimeException($res['raw'] ?? 'Supabase error');
    }

    $rows = $res['data'] ?? [];

} catch (Throwable $e) {
    echo '<h1>Student analysis error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$totalEvents = count($rows);
$answered = 0;
$completed = 0;
$errors = 0;
$hints = 0;
$scoreTotal = 0;
$scoreCount = 0;

$lessonStats = [];
$verbStats = [];
$dayStats = [];
$letterErrors = [];

foreach ($rows as $r) {
    $verb = $r['verb_display'] ?? '';
    $lesson = $r['lesson_id'] ?? 'unknown';
    $day = substr((string)($r['created_at'] ?? ''), 0, 10);

    if (!isset($verbStats[$verb])) {
        $verbStats[$verb] = 0;
    }
    $verbStats[$verb]++;

    if (!isset($dayStats[$day])) {
        $dayStats[$day] = 0;
    }
    $dayStats[$day]++;

    if (!isset($lessonStats[$lesson])) {
        $lessonStats[$lesson] = [
            'answered' => 0,
            'errors' => 0,
            'score_total' => 0,
            'score_count' => 0
        ];
    }

    if ($verb === 'answered') {
        $answered++;
        $lessonStats[$lesson]['answered']++;
    }

    if ($verb === 'completed') {
        $completed++;
    }

    if ($verb === 'used-hint') {
        $hints++;
    }

    if (($r['success'] ?? null) === false || $verb === 'made-error') {
        $errors++;
        $lessonStats[$lesson]['errors']++;

        $statement = $r['statement'] ?? null;

        if (is_array($statement)) {
            $extensions = $statement['context']['extensions'] ?? [];
            foreach ($extensions as $key => $value) {
                if (str_contains((string)$key, 'letter') && $value) {
                    if (!isset($letterErrors[$value])) {
                        $letterErrors[$value] = 0;
                    }
                    $letterErrors[$value]++;
                }
            }
        }
    }

    if (($r['score_raw'] ?? null) !== null) {
        $scoreTotal += (float)$r['score_raw'];
        $scoreCount++;
        $lessonStats[$lesson]['score_total'] += (float)$r['score_raw'];
        $lessonStats[$lesson]['score_count']++;
    }
}

$avgScore = $scoreCount > 0 ? round(($scoreTotal / $scoreCount) * 100) : 0;
$errorRate = $answered > 0 ? round(($errors / $answered) * 100) : 0;

ksort($dayStats);
ksort($lessonStats);
arsort($letterErrors);

$lessonLabels = [];
$lessonScores = [];
$lessonErrors = [];

foreach ($lessonStats as $lesson => $s) {
    $lessonLabels[] = $lesson;
    $lessonScores[] = $s['score_count'] > 0
        ? round(($s['score_total'] / $s['score_count']) * 100)
        : 0;
    $lessonErrors[] = $s['errors'];
}

$dayLabels = array_keys($dayStats);
$dayValues = array_values($dayStats);

$verbLabels = array_keys($verbStats);
$verbValues = array_values($verbStats);

$letterLabels = array_slice(array_keys($letterErrors), 0, 10);
$letterValues = array_slice(array_values($letterErrors), 0, 10);
?>

<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Studentanalyse</title>

    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="page">
    <div class="page-wrapper">

        <div class="page-header">
            <div class="container-xl">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Analyse student <?= h((string)$student) ?></h1>
                        <div class="text-secondary">xAPI analyse uit BrailleStudio</div>
                    </div>

                    <a href="teacher-dashboard.php?student=<?= urlencode($student) ?>" class="btn btn-secondary">
                        Terug
                    </a>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">

                <div class="row row-cards mb-3">
                    <div class="col-6 col-md-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Events</div>
                                <div class="h1"><?= $totalEvents ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Antwoorden</div>
                                <div class="h1"><?= $answered ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Fouten</div>
                                <div class="h1"><?= $errors ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Hints</div>
                                <div class="h1"><?= $hints ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Gem. score</div>
                                <div class="h1"><?= $avgScore ?>%</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary">Foutpercentage</div>
                                <div class="h1"><?= $errorRate ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($totalEvents === 0): ?>
                    <div class="alert alert-warning">
                        Geen data voor deze student.
                    </div>
                <?php endif; ?>

                <div class="row row-cards">

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Activiteit per dag</h2>
                            </div>
                            <div class="card-body">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Score per les</h2>
                            </div>
                            <div class="card-body">
                                <canvas id="scoreChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Fouten per les</h2>
                            </div>
                            <div class="card-body">
                                <canvas id="errorChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">xAPI verbs</h2>
                            </div>
                            <div class="card-body">
                                <canvas id="verbChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Moeilijke letters</h2>
                            </div>
                            <div class="card-body">
                                <canvas id="letterChart"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<script>
const dayLabels = <?= json_encode($dayLabels) ?>;
const dayValues = <?= json_encode($dayValues) ?>;

const lessonLabels = <?= json_encode($lessonLabels) ?>;
const lessonScores = <?= json_encode($lessonScores) ?>;
const lessonErrors = <?= json_encode($lessonErrors) ?>;

const verbLabels = <?= json_encode($verbLabels) ?>;
const verbValues = <?= json_encode($verbValues) ?>;

const letterLabels = <?= json_encode($letterLabels) ?>;
const letterValues = <?= json_encode($letterValues) ?>;

function makeBarChart(id, labels, data, label) {
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function makeLineChart(id, labels, data, label) {
    new Chart(document.getElementById(id), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

makeLineChart('activityChart', dayLabels, dayValues, 'Events');
makeBarChart('scoreChart', lessonLabels, lessonScores, 'Score %');
makeBarChart('errorChart', lessonLabels, lessonErrors, 'Fouten');
makeBarChart('verbChart', verbLabels, verbValues, 'Aantal');
makeBarChart('letterChart', letterLabels, letterValues, 'Fouten');
</script>

  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>