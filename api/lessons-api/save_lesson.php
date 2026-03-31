<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$saveDir = dirname(__DIR__) . '/lessons-data';

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0775, true);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($data['id']) ? trim((string)$data['id']) : '';
$title = isset($data['title']) ? trim((string)$data['title']) : '';
$method = isset($data['method']) && is_array($data['method']) ? $data['method'] : [];
$methodId = isset($data['methodId']) ? trim((string)$data['methodId']) : trim((string)($method['id'] ?? ''));
$methodTitle = isset($data['methodTitle']) ? trim((string)$data['methodTitle']) : trim((string)($method['title'] ?? ''));
$methodDataSource = isset($data['methodDataSource']) ? trim((string)$data['methodDataSource']) : trim((string)($method['dataSource'] ?? ''));
$meta = $data['meta'] ?? [];
$basisIndex = array_key_exists('basisIndex', $data) ? (int)$data['basisIndex'] : (int)($meta['basisIndex'] ?? -1);
$basisWord = isset($data['basisWord']) ? trim((string)$data['basisWord']) : trim((string)($meta['basisWord'] ?? ''));
$lessonNumber = array_key_exists('lessonNumber', $data) ? (int)$data['lessonNumber'] : (int)($meta['lessonNumber'] ?? 1);
$basisRecord = isset($data['basisRecord']) && is_array($data['basisRecord']) ? $data['basisRecord'] : (is_array($meta['basisRecord'] ?? null) ? $meta['basisRecord'] : []);
$word = isset($data['word']) ? trim((string)$data['word']) : '';
$steps = $data['steps'] ?? [];
$overwrite = array_key_exists('overwrite', $data) ? (bool)$data['overwrite'] : true;

function normalize_lesson_input_key($key)
{
    $key = trim((string)$key);
    $key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    return trim((string)$key, '-_');
}

function normalize_lesson_step_inputs($inputs, $fallbackVariable = '')
{
    if (!is_array($inputs)) {
        $inputs = [];
    }

    $normalized = [];

    foreach ($inputs as $key => $value) {
        $safeKey = normalize_lesson_input_key($key);
        if ($safeKey === '') {
            continue;
        }

        if ($safeKey === 'letters') {
            $letters = [];
            if (is_array($value)) {
                foreach ($value as $letter) {
                    $letter = trim((string)$letter);
                    if ($letter !== '') {
                        $letters[] = $letter;
                    }
                }
            } else {
                $parts = preg_split('/[\r\n,]+/', (string)$value) ?: [];
                foreach ($parts as $letter) {
                    $letter = trim((string)$letter);
                    if ($letter !== '') {
                        $letters[] = $letter;
                    }
                }
            }
            $normalized[$safeKey] = $letters;
            continue;
        }

        if (is_array($value)) {
            $cleanList = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $cleanItem = trim((string)$item);
                    if ($cleanItem !== '') {
                        $cleanList[] = $cleanItem;
                    }
                }
            }
            $normalized[$safeKey] = $cleanList;
            continue;
        }

        $normalized[$safeKey] = trim((string)$value);
    }

    if ($normalized === [] && $fallbackVariable !== '') {
        $normalized['value'] = trim((string)$fallbackVariable);
    }

    return $normalized;
}

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

if (!is_array($steps)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Steps must be an array']);
    exit;
}

if ($lessonNumber < 1) {
    $lessonNumber = 1;
}

$cleanSteps = [];
foreach ($steps as $stepId) {
    $stepId = trim((string)$stepId);
    $stepId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $stepId);
    $stepId = trim($stepId, '-_');
    if ($stepId !== '') {
        $cleanSteps[] = $stepId;
    }
}

$incomingStepConfigs = [];
if (is_array($data['stepConfigs'] ?? null)) {
    $incomingStepConfigs = $data['stepConfigs'];
} elseif (is_array($meta) && is_array($meta['stepConfigs'] ?? null)) {
    $incomingStepConfigs = $meta['stepConfigs'];
}

$cleanStepConfigs = [];
foreach ($incomingStepConfigs as $row) {
    if (!is_array($row)) {
        continue;
    }
    $rowId = trim((string)($row['id'] ?? ''));
    $rowId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $rowId);
    $rowId = trim($rowId, '-_');
    if ($rowId === '') {
        continue;
    }
    $rowVariable = trim((string)($row['variable'] ?? ''));
    $rowInputs = normalize_lesson_step_inputs($row['inputs'] ?? [], $rowVariable);
    $cleanStepConfigs[] = [
        'id' => $rowId,
        'inputs' => $rowInputs
    ];
}

if (count($cleanStepConfigs) === 0 && count($cleanSteps) > 0) {
    foreach ($cleanSteps as $stepId) {
        $cleanStepConfigs[] = [
            'id' => $stepId,
            'inputs' => []
        ];
    }
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filename = $safeId . '.json';
$filePath = $saveDir . '/' . $filename;

if (file_exists($filePath) && !$overwrite) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'error' => 'Lesson already exists'
    ]);
    exit;
}

$payload = [
    'id' => $safeId,
    'title' => $title,
    'methodId' => $methodId,
    'method' => [
        'id' => $methodId,
        'title' => $methodTitle,
        'dataSource' => $methodDataSource,
    ],
    'basisIndex' => $basisIndex,
    'basisWord' => $basisWord,
    'lessonNumber' => $lessonNumber,
    'basisRecord' => is_array($basisRecord) ? $basisRecord : [],
    'word' => $word,
    'updatedAt' => gmdate('c'),
    'steps' => $cleanSteps,
    'stepConfigs' => $cleanStepConfigs,
    'meta' => array_merge(
        is_array($meta) ? $meta : [],
        [
            'method' => [
                'id' => $methodId,
                'title' => $methodTitle,
                'dataSource' => $methodDataSource,
            ],
            'basisIndex' => $basisIndex,
            'basisWord' => $basisWord,
            'lessonNumber' => $lessonNumber,
            'basisRecord' => is_array($basisRecord) ? $basisRecord : [],
            'stepConfigs' => $cleanStepConfigs
        ]
    ),
];

$written = file_put_contents(
    $filePath,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save lesson']);
    exit;
}

echo json_encode([
    'ok' => true,
    'id' => $safeId,
    'filename' => $filename,
    'path' => 'lessons-data/' . $filename
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
