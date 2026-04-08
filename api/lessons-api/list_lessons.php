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
$filterMethodId = trim((string)($_GET['methodId'] ?? ''));
$filterBasisIndex = array_key_exists('basisIndex', $_GET) ? (int)$_GET['basisIndex'] : null;

if (!is_dir($saveDir)) {
    echo json_encode([
        'ok' => true,
        'items' => []
    ]);
    exit;
}

$files = glob($saveDir . '/*.json');
$items = [];

function normalize_list_step_inputs($inputs, $fallbackVariable = '')
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

foreach ($files as $file) {
    $content = json_decode(file_get_contents($file), true);

    if (!is_array($content)) {
        continue;
    }

    $rawSteps = is_array($content['steps'] ?? null) ? $content['steps'] : [];
    $normalizedSteps = [];
    foreach ($rawSteps as $row) {
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
            'inputs' => normalize_list_step_inputs($row['inputs'] ?? [], $rowVariable),
        ];
    }

    $items[] = [
        'id' => $content['id'] ?? pathinfo($file, PATHINFO_FILENAME),
        'title' => trim((string)($content['title'] ?? '')),
        'description' => trim((string)($content['description'] ?? '')),
        'methodId' => trim((string)($content['methodId'] ?? (($content['method']['id'] ?? '')))),
        'method' => is_array($content['method'] ?? null) ? $content['method'] : [
            'id' => trim((string)($content['methodId'] ?? '')),
            'title' => '',
            'description' => '',
            'imageUrl' => '',
            'basisFile' => '',
            'dataSource' => '',
        ],
        'basisIndex' => array_key_exists('basisIndex', $content) ? (int)$content['basisIndex'] : -1,
        'basisWord' => trim((string)($content['basisWord'] ?? '')),
        'lessonNumber' => array_key_exists('lessonNumber', $content) ? (int)$content['lessonNumber'] : 1,
        'basisRecord' => is_array($content['basisRecord'] ?? null) ? $content['basisRecord'] : [],
        'updatedAt' => $content['updatedAt'] ?? '',
        'steps' => $normalizedSteps,
        'filename' => basename($file),
    ];
}

if ($filterMethodId !== '') {
    $items = array_values(array_filter($items, static function (array $item) use ($filterMethodId): bool {
        return trim((string)($item['methodId'] ?? '')) === $filterMethodId;
    }));
}

if ($filterBasisIndex !== null) {
    $items = array_values(array_filter($items, static function (array $item) use ($filterBasisIndex): bool {
        return (int)($item['basisIndex'] ?? -1) === $filterBasisIndex;
    }));
}

usort($items, function ($a, $b) {
    $aIndex = (int)($a['basisIndex'] ?? -1);
    $bIndex = (int)($b['basisIndex'] ?? -1);
    if ($aIndex >= 0 && $bIndex >= 0 && $aIndex !== $bIndex) {
        return $aIndex <=> $bIndex;
    }
    $aLessonNumber = (int)($a['lessonNumber'] ?? 1);
    $bLessonNumber = (int)($b['lessonNumber'] ?? 1);
    if ($aLessonNumber !== $bLessonNumber) {
        return $aLessonNumber <=> $bLessonNumber;
    }
    return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
});

echo json_encode([
    'ok' => true,
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
