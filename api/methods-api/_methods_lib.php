<?php
declare(strict_types=1);

function methods_data_file(): string
{
    return __DIR__ . '/methods.json';
}

function methods_save_dir(): string
{
    return dirname(__DIR__) . '/methods-data';
}

function ensure_methods_storage_exists(): void
{
    $dir = methods_save_dir();

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function ensure_methods_file_exists(): void
{
    $file = methods_data_file();
    ensure_methods_storage_exists();

    if (!file_exists($file)) {
        file_put_contents($file, "[]", LOCK_EX);
    }
}

function methods_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function methods_method_not_allowed(array $allowed): never
{
    header('Allow: ' . implode(', ', $allowed));
    methods_json_response([
        'ok' => false,
        'error' => 'Method not allowed'
    ], 405);
}

function methods_get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function methods_normalize_string($value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (is_numeric($value)) {
        return trim((string)$value);
    }
    return '';
}

function methods_normalize_status($value): string
{
    $status = methods_normalize_string($value);
    if (!in_array($status, ['draft', 'active', 'archived'], true)) {
        return 'active';
    }
    return $status;
}

function methods_normalize_id($value): string
{
    $id = methods_normalize_string($value);
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
    return trim((string)$id, '-_');
}

function methods_normalize_method(array $item): array
{
    $id = methods_normalize_id($item['id'] ?? '');
    $basisFile = methods_normalize_string($item['basisFile'] ?? '');
    $dataSource = methods_normalize_string($item['dataSource'] ?? '');
    if ($basisFile !== '' && $dataSource === '') {
        $dataSource = 'https://www.tastenbraille.com/braillestudio/klanken/' . rawurlencode($basisFile);
    }

    return [
        'id' => $id,
        'title' => methods_normalize_string($item['title'] ?? ''),
        'description' => methods_normalize_string($item['description'] ?? ''),
        'basisFile' => $basisFile,
        'dataSource' => $dataSource,
        'status' => methods_normalize_status($item['status'] ?? 'active'),
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}

function methods_validate_method(array $item, array $existing = [], bool $isUpdate = false): array
{
    $errors = [];
    $id = methods_normalize_id($item['id'] ?? '');
    $title = methods_normalize_string($item['title'] ?? '');
    $basisFile = methods_normalize_string($item['basisFile'] ?? '');
    $dataSource = methods_normalize_string($item['dataSource'] ?? '');

    if ($id === '') {
        $errors[] = 'id is required';
    }
    if ($title === '') {
        $errors[] = 'title is required';
    }
    if ($basisFile === '' && $dataSource === '') {
        $errors[] = 'basisFile or dataSource is required';
    }

    if (!$isUpdate && $id !== '' && methods_find_method_index_by_id($id, $existing) >= 0) {
        $errors[] = 'id already exists';
    }

    return $errors;
}

function methods_file_path(string $id): string
{
    return methods_save_dir() . '/' . $id . '.json';
}

function methods_load_legacy_all(): array
{
    ensure_methods_file_exists();
    $json = @file_get_contents(methods_data_file());
    if ($json === false || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $item) {
        if (is_array($item)) {
            $normalized = methods_normalize_method($item);
            if ($normalized['id'] !== '') {
                $items[] = $normalized;
            }
        }
    }
    return $items;
}

function methods_migrate_legacy_if_needed(): void
{
    ensure_methods_storage_exists();
    $existingFiles = glob(methods_save_dir() . '/*.json') ?: [];
    if (count($existingFiles) > 0) {
        return;
    }

    $legacyItems = methods_load_legacy_all();
    foreach ($legacyItems as $item) {
        $id = methods_normalize_id($item['id'] ?? '');
        if ($id === '') {
            continue;
        }
        @file_put_contents(
            methods_file_path($id),
            json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}

function methods_load_all(): array
{
    methods_migrate_legacy_if_needed();
    ensure_methods_storage_exists();

    $items = [];
    $files = glob(methods_save_dir() . '/*.json') ?: [];
    foreach ($files as $file) {
        $json = @file_get_contents($file);
        if ($json === false || trim($json) === '') {
            continue;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            continue;
        }

        $normalized = methods_normalize_method($decoded);
        if ($normalized['id'] !== '') {
            $items[] = $normalized;
        }
    }

    usort($items, static function ($a, $b) {
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    return $items;
}

function methods_save_all(array $items): bool
{
    ensure_methods_storage_exists();
    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $row = methods_normalize_method($item);
            if ($row['id'] !== '') {
                $normalized[] = $row;
            }
        }
    }

    $expectedPaths = [];
    foreach ($normalized as $item) {
        $path = methods_file_path($item['id']);
        $expectedPaths[$path] = true;
        $json = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            return false;
        }
    }

    $files = glob(methods_save_dir() . '/*.json') ?: [];
    foreach ($files as $file) {
        if (!isset($expectedPaths[$file])) {
            @unlink($file);
        }
    }

    return true;
}

function methods_find_method_by_id(string $id, array $items): ?array
{
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

function methods_find_method_index_by_id(string $id, array $items): int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? '') === $id) {
            return (int)$index;
        }
    }
    return -1;
}

function methods_lessons_dir(): string
{
    return dirname(__DIR__) . '/lessons-data';
}

function methods_load_lessons_for_method(string $methodId): array
{
    $methodId = methods_normalize_id($methodId);
    if ($methodId === '') {
        return [];
    }

    $dir = methods_lessons_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $items = [];
    $files = glob($dir . '/*.json') ?: [];
    foreach ($files as $file) {
        $json = @file_get_contents($file);
        if ($json === false || trim($json) === '') {
            continue;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            continue;
        }

        $lessonMethodId = methods_normalize_id($decoded['methodId'] ?? ($decoded['method']['id'] ?? ''));
        if ($lessonMethodId !== $methodId) {
            continue;
        }

        $items[] = [
            'id' => methods_normalize_string($decoded['id'] ?? pathinfo($file, PATHINFO_FILENAME)),
            'title' => methods_normalize_string($decoded['title'] ?? ''),
            'word' => methods_normalize_string($decoded['word'] ?? ''),
            'basisIndex' => array_key_exists('basisIndex', $decoded) ? (int)$decoded['basisIndex'] : (int)($decoded['meta']['basisIndex'] ?? -1),
            'basisWord' => methods_normalize_string($decoded['basisWord'] ?? ($decoded['meta']['basisWord'] ?? '')),
            'lessonNumber' => array_key_exists('lessonNumber', $decoded) ? (int)$decoded['lessonNumber'] : (int)($decoded['meta']['lessonNumber'] ?? 1),
            'updatedAt' => methods_normalize_string($decoded['updatedAt'] ?? ''),
            'stepsCount' => is_array($decoded['steps'] ?? null) ? count($decoded['steps']) : 0,
            'filename' => basename($file),
        ];
    }

    usort($items, static function ($a, $b) {
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

        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    return $items;
}

function methods_enrich_with_lessons(array $method): array
{
    $lessons = methods_load_lessons_for_method((string)($method['id'] ?? ''));
    $method['lessons'] = $lessons;
    $method['lessonsCount'] = count($lessons);
    return $method;
}
