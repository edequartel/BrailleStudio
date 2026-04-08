<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

lessons_api_require_authentication();

$saveDir = dirname(__DIR__) . '/lessons-data';

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
$safeId = trim($safeId, '-_');

if ($safeId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$filePath = $saveDir . '/' . $safeId . '.json';

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Lesson not found']);
    exit;
}

$content = json_decode(file_get_contents($filePath), true);
if (!is_array($content)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Invalid lesson JSON']);
    exit;
}

function normalize_loaded_step_inputs($inputs, $fallbackVariable = '')
{
    if (!is_array($inputs)) {
        $inputs = [];
    }

    $normalizeStringList = static function ($value): array {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                $cleanItem = trim((string)$item);
                if ($cleanItem !== '') {
                    $items[] = $cleanItem;
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
        $safeKey = trim((string)$key);
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
                $item = trim((string)$item);
                if ($item !== '') {
                    $cleanList[] = $item;
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

$steps = is_array($content['steps'] ?? null) ? $content['steps'] : [];
$normalizedSteps = [];
foreach ($steps as $row) {
    if (!is_array($row)) {
        continue;
    }
    $rowId = trim((string)($row['id'] ?? ''));
    if ($rowId === '') {
        continue;
    }
    $rowVariable = trim((string)($row['variable'] ?? ''));
    $normalizedSteps[] = [
        'id' => $rowId,
        'title' => trim((string)($row['title'] ?? $row['scriptTitle'] ?? '')),
        'description' => trim((string)($row['description'] ?? $row['scriptDescription'] ?? ($row['meta']['description'] ?? ''))),
        'inputs' => normalize_loaded_step_inputs($row['inputs'] ?? [], $rowVariable)
    ];
}

$out = $content;
$out['ok'] = true;
$out['id'] = $content['id'] ?? $safeId;
$methodData = is_array($content['method'] ?? null) ? $content['method'] : [];
$out['methodId'] = trim((string)($content['methodId'] ?? ($methodData['id'] ?? '')));
$out['method'] = [
    'id' => $out['methodId'],
    'title' => trim((string)($methodData['title'] ?? '')),
    'dataSource' => trim((string)($methodData['dataSource'] ?? '')),
];
$out['basisIndex'] = array_key_exists('basisIndex', $content) ? (int)$content['basisIndex'] : (int)($out['meta']['basisIndex'] ?? -1);
$out['basisWord'] = trim((string)($content['basisWord'] ?? ($out['meta']['basisWord'] ?? '')));
$out['lessonNumber'] = array_key_exists('lessonNumber', $content) ? (int)$content['lessonNumber'] : (int)($out['meta']['lessonNumber'] ?? 1);
$out['basisRecord'] = is_array($content['basisRecord'] ?? null) ? $content['basisRecord'] : (is_array($out['meta']['basisRecord'] ?? null) ? $out['meta']['basisRecord'] : []);
$out['steps'] = $normalizedSteps;

if (!is_array($out['meta'] ?? null)) {
    $out['meta'] = [];
}
$out['meta']['title'] = trim((string)($out['meta']['title'] ?? ($out['title'] ?? '')));
$out['meta']['description'] = trim((string)($out['meta']['description'] ?? ($out['description'] ?? '')));
$out['meta']['basisIndex'] = $out['basisIndex'];
$out['meta']['basisWord'] = $out['basisWord'];
$out['meta']['lessonNumber'] = $out['lessonNumber'];
$out['meta']['basisRecord'] = $out['basisRecord'];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
