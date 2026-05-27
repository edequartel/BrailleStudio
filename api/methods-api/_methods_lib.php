<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/json-guard.php';
braillestudio_json_guard_start();

require_once dirname(__DIR__) . '/authentication-api/_bootstrap.php';

function methods_data_file(): string
{
    foreach (methods_data_file_candidates() as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return methods_data_file_candidates()[0];
}

function methods_data_file_candidates(): array
{
    return array_values(array_unique([
        dirname(__DIR__, 2) . '/methods.json',
        dirname(__DIR__) . '/methods.json',
        __DIR__ . '/methods.json',
    ]));
}

function methods_save_dirs(): array
{
    return array_values(array_unique([
        dirname(__DIR__, 2) . '/methods-data',
        dirname(__DIR__) . '/methods-data',
    ]));
}

function methods_save_dir(): string
{
    foreach (methods_save_dirs() as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return methods_save_dirs()[0];
}

function methods_writable_save_dir(): ?string
{
    foreach (methods_save_dirs() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    foreach (methods_save_dirs() as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (@mkdir($dir, 0775, true) && is_writable($dir)) {
            return $dir;
        }
    }

    return null;
}

function ensure_methods_storage_exists(): void
{
    $dir = methods_writable_save_dir();
    if ($dir === null) {
        methods_json_response([
            'ok' => false,
            'error' => 'No writable methods data directory found',
            'checked' => methods_save_dirs(),
        ], 500);
    }
}

function ensure_methods_file_exists(): void
{
    $file = methods_data_file();
    ensure_methods_storage_exists();

    if (!file_exists($file)) {
        @file_put_contents($file, "[]", LOCK_EX);
    }
}

function methods_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function methods_require_authentication(): array
{
    return authenticate_require_bearer_token_for_audiences([
        'braillestudio-api',
        'braillestudio-elevenlabs-api',
    ]);
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
    $imageUrl = methods_normalize_string($item['imageUrl'] ?? '');
    if ($basisFile !== '' && $dataSource === '') {
        $dataSource = 'https://www.tastenbraille.com/braillestudio/klanken/' . rawurlencode($basisFile);
    }
    if ($basisFile === '') {
        $dataSource = '';
    }

    return [
        'id' => $id,
        'title' => methods_normalize_string($item['title'] ?? ''),
        'description' => methods_normalize_string($item['description'] ?? ''),
        'basisFile' => $basisFile,
        'dataSource' => $dataSource,
        'imageUrl' => $imageUrl,
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

    if ($id === '') {
        $errors[] = 'id is required';
    }
    if ($title === '') {
        $errors[] = 'title is required';
    }

    if (!$isUpdate && $id !== '' && methods_find_method_index_by_id($id, $existing) >= 0) {
        $errors[] = 'id already exists';
    }

    return $errors;
}

function methods_file_path(string $id): string
{
    return (methods_writable_save_dir() ?? methods_save_dir()) . '/' . $id . '.json';
}

function methods_basis_dir_candidates(): array
{
    return [
        dirname(dirname(__DIR__)) . '/klanken',
        dirname(__DIR__) . '/klanken',
        dirname(__DIR__, 3) . '/klanken',
        rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/braillestudio/klanken',
        rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/klanken',
    ];
}

function methods_basis_dir(): string
{
    foreach (methods_basis_dir_candidates() as $dir) {
        $dir = rtrim((string)$dir, '/');
        if ($dir !== '' && is_dir($dir)) {
            return $dir;
        }
    }

    $fallback = rtrim((string)(methods_basis_dir_candidates()[0] ?? ''), '/');
    if ($fallback !== '' && !is_dir($fallback)) {
        @mkdir($fallback, 0775, true);
    }
    return $fallback;
}

function methods_normalize_basis_filename($value): string
{
    $name = basename(methods_normalize_string($value));
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
    if (!is_string($name)) {
        return '';
    }
    $name = trim($name);
    if ($name === '' || !str_ends_with(strtolower($name), '.json')) {
        return '';
    }
    return $name;
}

function methods_basis_file_path(string $fileName): string
{
    return methods_basis_dir() . '/' . $fileName;
}

function methods_normalize_basis_sound_list($value): array
{
    $items = [];
    if (is_array($value)) {
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                $clean = trim((string)$item);
                if ($clean !== '') {
                    $items[] = $clean;
                }
            }
        }
        return array_values(array_unique($items));
    }

    $parts = preg_split('/[\r\n,]+/', (string)$value) ?: [];
    foreach ($parts as $item) {
        $clean = trim((string)$item);
        if ($clean !== '') {
            $items[] = $clean;
        }
    }
    return array_values(array_unique($items));
}

function methods_normalize_basis_category_map($value): array
{
    $keys = ['korteKlinkers', 'langeKlinkers', 'tweetekenklanken', 'medeklinkers', 'medeklinkerclusters', 'drietekenklanken'];
    $source = is_array($value) ? $value : [];
    $normalized = [];
    foreach ($keys as $key) {
        $normalized[$key] = methods_normalize_basis_sound_list($source[$key] ?? []);
    }
    return $normalized;
}

function methods_normalize_basis_record($value): array
{
    $source = is_array($value) ? $value : [];
    return [
        'word' => methods_normalize_string($source['word'] ?? ''),
        'sounds' => methods_normalize_basis_sound_list($source['sounds'] ?? []),
        'newSounds' => methods_normalize_basis_sound_list($source['newSounds'] ?? []),
        'knownSounds' => methods_normalize_basis_sound_list($source['knownSounds'] ?? []),
        'categories' => methods_normalize_basis_category_map($source['categories'] ?? []),
        'newSoundCategories' => methods_normalize_basis_category_map($source['newSoundCategories'] ?? []),
        'knownSoundCategories' => methods_normalize_basis_category_map($source['knownSoundCategories'] ?? []),
    ];
}

function methods_normalize_basis_items($value): array
{
    $items = [];
    if (!is_array($value)) {
        return $items;
    }

    foreach ($value as $item) {
        $normalized = methods_normalize_basis_record($item);
        $items[] = $normalized;
    }
    return $items;
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
    $existingFiles = [];
    foreach (methods_save_dirs() as $dir) {
        if (is_dir($dir)) {
            $existingFiles = array_merge($existingFiles, glob($dir . '/*.json') ?: []);
        }
    }
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
    $seen = [];
    foreach (methods_save_dirs() as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
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

            $normalized = methods_normalize_method($decoded);
            if ($normalized['id'] !== '' && !isset($seen[$normalized['id']])) {
                $seen[$normalized['id']] = true;
                $items[] = $normalized;
            }
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
    $saveDir = methods_writable_save_dir();
    if ($saveDir === null) {
        return false;
    }
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
        $path = $saveDir . '/' . $item['id'] . '.json';
        $expectedPaths[$path] = true;
        $json = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            return false;
        }
    }

    $files = glob($saveDir . '/*.json') ?: [];
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
    $dirs = methods_lessons_dirs();
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return $dirs[0];
}

function methods_lessons_dirs(): array
{
    return array_values(array_unique([
        dirname(__DIR__, 2) . '/lessons-data',
        dirname(__DIR__) . '/lessons-data',
    ]));
}

function methods_load_lessons_for_method(string $methodId): array
{
    $methodId = methods_normalize_id($methodId);
    if ($methodId === '') {
        return [];
    }

    $items = [];
    $seen = [];
    foreach (methods_lessons_dirs() as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
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

            $lessonId = methods_normalize_string($decoded['id'] ?? pathinfo($file, PATHINFO_FILENAME));
            if ($lessonId === '' || isset($seen[$lessonId])) {
                continue;
            }
            $seen[$lessonId] = true;

            $items[] = [
                'id' => $lessonId,
                'title' => methods_normalize_string($decoded['title'] ?? ''),
                'description' => methods_normalize_string($decoded['description'] ?? ''),
                'basisIndex' => array_key_exists('basisIndex', $decoded) ? (int)$decoded['basisIndex'] : -1,
                'basisWord' => methods_normalize_string($decoded['basisWord'] ?? ''),
                'lessonNumber' => array_key_exists('lessonNumber', $decoded) ? (int)$decoded['lessonNumber'] : 1,
                'updatedAt' => methods_normalize_string($decoded['updatedAt'] ?? ''),
                'stepsCount' => is_array($decoded['steps'] ?? null) ? count($decoded['steps']) : 0,
                'filename' => basename($file),
            ];
        }
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

function methods_delete_lessons_for_method(string $methodId): array
{
    $methodId = methods_normalize_id($methodId);
    if ($methodId === '') {
        return [
            'deleted' => [],
            'errors' => [],
        ];
    }

    $deleted = [];
    $errors = [];
    $files = [];
    foreach (methods_lessons_dirs() as $dir) {
        if (is_dir($dir)) {
            $files = array_merge($files, glob($dir . '/*.json') ?: []);
        }
    }

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

        $lessonId = methods_normalize_string($decoded['id'] ?? pathinfo($file, PATHINFO_FILENAME));
        if (@unlink($file)) {
            $deleted[] = [
                'id' => $lessonId,
                'filename' => basename($file),
            ];
            continue;
        }

        $errors[] = [
            'id' => $lessonId,
            'filename' => basename($file),
        ];
    }

    return [
        'deleted' => $deleted,
        'errors' => $errors,
    ];
}
