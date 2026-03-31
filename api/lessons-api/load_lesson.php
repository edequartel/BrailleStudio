<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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

    return $normalized;
}

$steps = is_array($content['steps'] ?? null) ? $content['steps'] : [];
$stepConfigs = [];

if (is_array($content['stepConfigs'] ?? null)) {
    $stepConfigs = $content['stepConfigs'];
} elseif (is_array($content['meta']['stepConfigs'] ?? null)) {
    $stepConfigs = $content['meta']['stepConfigs'];
    } else {
    foreach ($steps as $stepId) {
        $stepConfigs[] = [
            'id' => (string)$stepId,
            'inputs' => []
        ];
    }
}

$normalizedStepConfigs = [];
foreach ($stepConfigs as $row) {
    if (!is_array($row)) {
        continue;
    }
    $rowId = trim((string)($row['id'] ?? ''));
    if ($rowId === '') {
        continue;
    }
    $rowVariable = trim((string)($row['variable'] ?? ''));
    $normalizedStepConfigs[] = [
        'id' => $rowId,
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
$out['steps'] = $steps;
$out['stepConfigs'] = $normalizedStepConfigs;

if (!is_array($out['meta'] ?? null)) {
    $out['meta'] = [];
}
$out['meta']['stepConfigs'] = $normalizedStepConfigs;

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
