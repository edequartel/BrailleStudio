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
        dirname(__DIR__, 3) . '/braillestudio-data/data/methods',
        dirname(__DIR__, 2) . '/data/methods',
        dirname(__DIR__) . '/data/methods',
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

function methods_remote_data_base_url(string $section = ''): string
{
    $section = trim($section, '/');
    return 'https://www.tastenbraille.com/braillestudio-data/data'
        . ($section !== '' ? '/' . $section : '');
}

function methods_manifest_name(string $section): string
{
    return match ($section) {
        'blockly' => 'blockly',
        'lessons' => 'lessons',
        'klanken' => 'klanken',
        default => 'methods',
    };
}

function methods_remote_manifest_url(string $section): string
{
    return methods_remote_app_base_url() . '/temp/manifests/' . methods_manifest_name($section) . '.json';
}

function methods_manifest_file(string $section): string
{
    return dirname(__DIR__, 2) . '/temp/manifests/' . methods_manifest_name($section) . '.json';
}

function methods_is_canonical_host(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return $host === 'www.tastenbraille.com' || $host === 'tastenbraille.com';
}

function methods_remote_app_base_url(): string
{
    if (!methods_is_canonical_host()) {
        return 'https://www.tastenbraille.com/braillestudio';
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $appPath = preg_replace('~/api/methods-api(?:/.*)?$~', '', $scriptName) ?? '';
    $appPath = '/' . trim($appPath, '/');
    if ($appPath === '/') {
        $appPath = '/braillestudio';
    }

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'www.tastenbraille.com'));
    return 'https://' . $host . $appPath;
}

function methods_is_http_url(string $value): bool
{
    return preg_match('~^https?://~i', trim($value)) === 1;
}

function methods_fetch_url(string $url): ?string
{
    if (!methods_is_http_url($url)) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: BrailleStudioMethodsApi/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (is_string($raw) && trim($raw) !== '' && !preg_match('/^\s*</', $raw)) {
        return $raw;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'BrailleStudioMethodsApi/1.0',
    ]);
    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    if (!is_string($result) || trim($result) === '' || $status >= 400 || preg_match('/^\s*</', $result)) {
        return null;
    }
    return $result;
}

function methods_fetch_json_url(string $url): ?array
{
    $raw = methods_fetch_url($url);
    if ($raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function methods_remote_method_url(string $id): string
{
    return methods_remote_data_base_url('methods') . '/' . rawurlencode($id) . '.json';
}

function methods_remote_manifest_urls(string $section, array $extraNames = []): array
{
    return [
        methods_remote_manifest_url($section) . '?_=' . rawurlencode((string)time()),
    ];
}

function methods_load_remote_manifest(string $section, array $extraNames = []): ?array
{
    foreach (methods_remote_manifest_urls($section, $extraNames) as $url) {
        $manifest = methods_fetch_json_url($url);
        if (is_array($manifest)) {
            return $manifest;
        }
    }

    if (methods_is_canonical_host()) {
        if ($section === 'methods') {
            methods_rebuild_manifest(methods_writable_save_dir() ?? methods_save_dir());
        } elseif ($section === 'klanken') {
            methods_rebuild_basis_manifest(methods_basis_dir());
        } elseif ($section === 'lessons') {
            methods_rebuild_lessons_manifest(methods_lessons_dir());
        }

        $manifest = json_decode((string)@file_get_contents(methods_manifest_file($section)), true);
        if (is_array($manifest)) {
            return $manifest;
        }
    }

    return null;
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
    $dataSource = str_replace([
        'https://www.tastenbraille.com/braillestudio/klanken/',
        'https://www.tastenbraille.com/braillestudio/data/klanken/',
        'https://www.tastenbraille.com/braillestudio-data/klanken/',
        'https://www.tastenbraille.com/braillestudio-data/data/klanken/',
    ], methods_remote_data_base_url('klanken') . '/', $dataSource);
    $imageUrl = methods_normalize_string($item['imageUrl'] ?? '');
    if ($basisFile !== '' && $dataSource === '') {
        $dataSource = methods_remote_data_base_url('klanken') . '/' . rawurlencode($basisFile);
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
        'updatedAt' => methods_normalize_string($item['updatedAt'] ?? '') ?: gmdate('Y-m-d\TH:i:s\Z'),
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
        dirname(__DIR__, 3) . '/braillestudio-data/data/klanken',
        dirname(dirname(__DIR__)) . '/data/klanken',
        dirname(__DIR__) . '/data/klanken',
        dirname(__DIR__, 3) . '/data/klanken',
        rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/braillestudio/data/klanken',
        rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/data/klanken',
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

function methods_remote_basis_file_url(string $fileName): string
{
    return methods_remote_data_base_url('klanken') . '/' . rawurlencode($fileName);
}

function methods_rebuild_basis_manifest(string $dir): bool
{
    $dir = rtrim($dir, '/');
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $items = [];
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (str_starts_with($name, '._')) {
            continue;
        }
        $items[] = [
            'id' => $name,
            'name' => $name,
            'label' => $name,
            'url' => methods_remote_basis_file_url($name),
        ];
    }

    $manifest = [
        'ok' => true,
        'updatedAt' => gmdate('c'),
        'items' => $items,
    ];
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    $manifestFile = methods_manifest_file('klanken');
    $manifestDir = dirname($manifestFile);
    if (!is_dir($manifestDir) && !@mkdir($manifestDir, 0775, true) && !is_dir($manifestDir)) {
        return false;
    }

    return file_put_contents($manifestFile, $encoded, LOCK_EX) !== false;
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
    $manifest = methods_load_remote_manifest('methods', ['methods.json']);
    if (!is_array($manifest)) {
        return [];
    }

    $rawItems = is_array($manifest['items'] ?? null) ? $manifest['items'] : $manifest;

    $items = [];
    $seen = [];
    foreach ($rawItems as $item) {
        if (is_string($item) || is_numeric($item)) {
            $item = ['id' => (string)$item];
        }
        if (!is_array($item)) {
            continue;
        }
        $id = methods_normalize_id($item['id'] ?? pathinfo((string)($item['filename'] ?? ''), PATHINFO_FILENAME));
        if ($id === '' || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;

        $content = $item;
        if (!array_key_exists('basisFile', $content) && !array_key_exists('dataSource', $content)) {
            $remoteContent = methods_fetch_json_url(methods_remote_method_url($id));
            if (is_array($remoteContent)) {
                $content = array_replace_recursive($remoteContent, $item);
            }
        }

        $normalized = methods_normalize_method(['id' => $id] + $content);
        if ($normalized['id'] !== '') {
            $normalized['filename'] = basename((string)($content['filename'] ?? ($normalized['id'] . '.json')));
            $normalized['url'] = methods_remote_method_url($normalized['id']);
            $items[] = $normalized;
        }
    }

    usort($items, static function ($a, $b) {
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    return $items;
}

function methods_rebuild_manifest(string $dir): bool
{
    $dir = rtrim($dir, '/');
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $items = [];
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (str_starts_with($name, '._')) {
            continue;
        }
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (!is_array($decoded)) {
            continue;
        }
        $normalized = methods_normalize_method($decoded);
        if ($normalized['id'] === '') {
            continue;
        }
        $normalized['filename'] = $normalized['id'] . '.json';
        $normalized['url'] = methods_remote_method_url($normalized['id']);
        $items[] = $normalized;
    }

    usort($items, static function ($a, $b) {
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    $manifest = [
        'ok' => true,
        'updatedAt' => gmdate('c'),
        'items' => $items,
    ];
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    $manifestFile = methods_manifest_file('methods');
    $manifestDir = dirname($manifestFile);
    if (!is_dir($manifestDir) && !@mkdir($manifestDir, 0775, true) && !is_dir($manifestDir)) {
        return false;
    }

    return file_put_contents($manifestFile, $encoded, LOCK_EX) !== false;
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
        if (str_starts_with(basename($file), '._')) {
            continue;
        }
        if (!isset($expectedPaths[$file])) {
            @unlink($file);
        }
    }

    return methods_rebuild_manifest($saveDir);
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
        dirname(__DIR__, 3) . '/braillestudio-data/data/lessons',
        dirname(__DIR__, 2) . '/data/lessons',
        dirname(__DIR__) . '/data/lessons',
    ]));
}

function methods_rebuild_lessons_manifest(string $dir): bool
{
    $dir = rtrim($dir, '/');
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $items = [];
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (str_starts_with($name, '._')) {
            continue;
        }
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (!is_array($decoded)) {
            continue;
        }
        $id = methods_normalize_id($decoded['id'] ?? pathinfo($file, PATHINFO_FILENAME));
        if ($id === '') {
            continue;
        }
        $method = is_array($decoded['method'] ?? null) ? $decoded['method'] : [];
        $items[] = [
            'id' => $id,
            'title' => methods_normalize_string($decoded['title'] ?? ''),
            'description' => methods_normalize_string($decoded['description'] ?? ''),
            'methodId' => methods_normalize_id($decoded['methodId'] ?? ($method['id'] ?? '')),
            'basisIndex' => array_key_exists('basisIndex', $decoded) ? (int)$decoded['basisIndex'] : -1,
            'basisWord' => methods_normalize_string($decoded['basisWord'] ?? ''),
            'lessonNumber' => array_key_exists('lessonNumber', $decoded) ? (int)$decoded['lessonNumber'] : 1,
            'updatedAt' => methods_normalize_string($decoded['updatedAt'] ?? ''),
            'stepsCount' => is_array($decoded['steps'] ?? null) ? count($decoded['steps']) : 0,
            'filename' => $id . '.json',
            'url' => methods_remote_data_base_url('lessons') . '/' . rawurlencode($id) . '.json',
        ];
    }

    usort($items, static function (array $a, array $b): int {
        $aIndex = (int)($a['basisIndex'] ?? -1);
        $bIndex = (int)($b['basisIndex'] ?? -1);
        if ($aIndex !== $bIndex) {
            return $aIndex <=> $bIndex;
        }
        $lessonCompare = ((int)($a['lessonNumber'] ?? 1)) <=> ((int)($b['lessonNumber'] ?? 1));
        if ($lessonCompare !== 0) {
            return $lessonCompare;
        }
        return strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? ''));
    });

    $manifest = [
        'ok' => true,
        'updatedAt' => gmdate('c'),
        'items' => $items,
    ];
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    $manifestFile = methods_manifest_file('lessons');
    $manifestDir = dirname($manifestFile);
    if (!is_dir($manifestDir) && !@mkdir($manifestDir, 0775, true) && !is_dir($manifestDir)) {
        return false;
    }

    return file_put_contents($manifestFile, $encoded, LOCK_EX) !== false;
}

function methods_load_lessons_for_method(string $methodId): array
{
    $methodId = methods_normalize_id($methodId);
    if ($methodId === '') {
        return [];
    }

    $manifest = methods_load_remote_manifest('lessons', ['lessons.json']);
    if (!is_array($manifest)) {
        return [];
    }
    $rawItems = is_array($manifest['items'] ?? null) ? $manifest['items'] : $manifest;

    $items = [];
    $seen = [];
    foreach ($rawItems as $item) {
        if (is_string($item) || is_numeric($item)) {
            $item = ['id' => (string)$item];
        }
        if (!is_array($item)) {
            continue;
        }
        $lessonId = methods_normalize_id($item['id'] ?? pathinfo((string)($item['filename'] ?? ''), PATHINFO_FILENAME));
        if ($lessonId === '' || isset($seen[$lessonId])) {
            continue;
        }

        $decoded = $item;
        if (!array_key_exists('methodId', $decoded) && !array_key_exists('method', $decoded)) {
            $remoteLesson = methods_fetch_json_url(methods_remote_data_base_url('lessons') . '/' . rawurlencode($lessonId) . '.json');
            if (is_array($remoteLesson)) {
                $decoded = array_replace_recursive($remoteLesson, $item);
            }
        }

        $lessonMethodId = methods_normalize_id($decoded['methodId'] ?? ($decoded['method']['id'] ?? ''));
        if ($lessonMethodId !== $methodId) {
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
            'stepsCount' => is_array($decoded['steps'] ?? null) ? count($decoded['steps']) : (int)($decoded['stepsCount'] ?? 0),
            'filename' => basename((string)($decoded['filename'] ?? ($lessonId . '.json'))),
            'url' => methods_remote_data_base_url('lessons') . '/' . rawurlencode($lessonId) . '.json',
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

    if ($deleted !== []) {
        foreach (methods_lessons_dirs() as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                methods_rebuild_lessons_manifest($dir);
                break;
            }
        }
    }

    return [
        'deleted' => $deleted,
        'errors' => $errors,
    ];
}
