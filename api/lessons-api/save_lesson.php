<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

lessons_api_require_authentication();

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
$description = isset($data['description']) ? trim((string)$data['description']) : trim((string)(($data['meta']['description'] ?? '')));
$method = isset($data['method']) && is_array($data['method']) ? $data['method'] : [];
$methodId = isset($data['methodId']) ? trim((string)$data['methodId']) : trim((string)($method['id'] ?? ''));
$methodTitle = isset($data['methodTitle']) ? trim((string)$data['methodTitle']) : trim((string)($method['title'] ?? ''));
$methodDataSource = isset($data['methodDataSource']) ? trim((string)$data['methodDataSource']) : trim((string)($method['dataSource'] ?? ''));
$basisIndex = array_key_exists('basisIndex', $data) ? (int)$data['basisIndex'] : -1;
$basisWord = isset($data['basisWord']) ? trim((string)$data['basisWord']) : '';
$lessonNumber = array_key_exists('lessonNumber', $data) ? (int)$data['lessonNumber'] : 1;
$basisRecord = isset($data['basisRecord']) && is_array($data['basisRecord']) ? $data['basisRecord'] : [];
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

    $normalizeStringList = static function ($value): array {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $cleanItem = trim((string)$item);
                    if ($cleanItem !== '') {
                        $items[] = $cleanItem;
                    }
                }
            }
            return $items;
        }
        $parts = preg_split('/[\r\n,]+/', (string)$value) ?: [];
        foreach ($parts as $item) {
            $cleanItem = trim((string)$item);
            if ($cleanItem !== '') {
                $items[] = $cleanItem;
            }
        }
        return $items;
    };

    $normalizeCategoryMap = static function ($value) use ($normalizeStringList): array {
        $source = is_array($value) ? $value : [];
        $normalized = [];
        foreach ($source as $key => $items) {
            $safeKey = trim((string)$key);
            if ($safeKey === '') {
                continue;
            }
            $normalized[$safeKey] = $normalizeStringList($items);
        }
        return $normalized;
    };

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

        if (in_array($safeKey, ['sounds', 'newSounds', 'knownSounds'], true)) {
            $normalized[$safeKey] = $normalizeStringList($value);
            continue;
        }

        if (in_array($safeKey, ['categories', 'newSoundCategories', 'knownSoundCategories'], true)) {
            $normalized[$safeKey] = $normalizeCategoryMap($value);
            continue;
        }

        if ($safeKey === 'repeat') {
            $normalized[$safeKey] = max(1, (int)$value);
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

    if (!array_key_exists('repeat', $normalized)) {
        $normalized['repeat'] = 1;
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
foreach ($steps as $row) {
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
    $cleanSteps[] = [
        'id' => $rowId,
        'title' => trim((string)($row['title'] ?? $row['scriptTitle'] ?? '')),
        'description' => trim((string)($row['description'] ?? $row['scriptDescription'] ?? ($row['meta']['description'] ?? ''))),
        'stepLinkCode' => preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)($row['stepLinkCode'] ?? $row['stepCode'] ?? ''))),
        'inputs' => $rowInputs
    ];
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
    'description' => $description,
    'methodId' => $methodId,
    'method' => [
        'id' => $methodId,
        'title' => $methodTitle,
        'description' => trim((string)($method['description'] ?? '')),
        'imageUrl' => trim((string)($method['imageUrl'] ?? '')),
        'basisFile' => trim((string)($method['basisFile'] ?? '')),
        'dataSource' => $methodDataSource,
    ],
    'basisIndex' => $basisIndex,
    'basisWord' => $basisWord,
    'lessonNumber' => $lessonNumber,
    'basisRecord' => is_array($basisRecord) ? $basisRecord : [],
    'updatedAt' => gmdate('c'),
    'steps' => $cleanSteps,
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
