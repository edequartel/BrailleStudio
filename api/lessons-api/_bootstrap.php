<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/json-guard.php';
braillestudio_json_guard_start();

require_once dirname(__DIR__) . '/authentication-api/_bootstrap.php';

function lessons_api_require_authentication(): array
{
    return authenticate_require_bearer_token_for_audiences([
        'braillestudio-api',
        'braillestudio-elevenlabs-api',
    ]);
}

function lessons_api_remote_data_base_url(): string
{
    return 'https://www.tastenbraille.com/braillestudio-data/data/lessons';
}

function lessons_api_manifest_file(): string
{
    return dirname(__DIR__, 2) . '/temp/manifests/lessons.json';
}

function lessons_api_remote_lesson_url(string $safeId): string
{
    return lessons_api_remote_data_base_url() . '/' . rawurlencode($safeId) . '.json';
}

function lessons_api_load_remote_lesson(string $safeId): ?array
{
    $path = lessons_api_find_lesson_path($safeId);
    if ($path === null) {
        return null;
    }
    $content = json_decode((string)@file_get_contents($path), true);
    return is_array($content) ? $content : null;
}

function lessons_api_load_remote_manifest(): ?array
{
    $dir = lessons_api_data_dir();
    return is_dir($dir) ? lessons_api_build_manifest_from_dir($dir) : null;
}

function lessons_api_data_dir(): string
{
    foreach (lessons_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return lessons_api_data_dirs()[0];
}

function lessons_api_data_dirs(): array
{
    return [
        dirname(__DIR__, 3) . '/braillestudio-data/data/lessons',
    ];
}

function lessons_api_find_lesson_path(string $safeId): ?string
{
    foreach (lessons_api_data_dirs() as $dir) {
        $filePath = $dir . '/' . $safeId . '.json';
        if (is_file($filePath)) {
            return $filePath;
        }
    }
    return null;
}

function lessons_api_writable_data_dir(): ?string
{
    foreach (lessons_api_data_dirs() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    foreach (lessons_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (@mkdir($dir, 0775, true) && is_writable($dir)) {
            return $dir;
        }
    }

    return null;
}

function lessons_api_normalize_manifest_item(array $content, string $fallbackId = ''): array
{
    $method = is_array($content['method'] ?? null) ? $content['method'] : [];
    $id = trim((string)($content['id'] ?? $fallbackId));

    return [
        'id' => $id,
        'title' => trim((string)($content['title'] ?? '')),
        'description' => trim((string)($content['description'] ?? '')),
        'methodId' => trim((string)($content['methodId'] ?? ($method['id'] ?? ''))),
        'method' => [
            'id' => trim((string)($content['methodId'] ?? ($method['id'] ?? ''))),
            'title' => trim((string)($method['title'] ?? '')),
            'description' => trim((string)($method['description'] ?? '')),
            'imageUrl' => trim((string)($method['imageUrl'] ?? '')),
            'basisFile' => trim((string)($method['basisFile'] ?? '')),
            'dataSource' => trim((string)($method['dataSource'] ?? '')),
        ],
        'basisIndex' => array_key_exists('basisIndex', $content) ? (int)$content['basisIndex'] : -1,
        'basisWord' => trim((string)($content['basisWord'] ?? '')),
        'lessonNumber' => array_key_exists('lessonNumber', $content) ? (int)$content['lessonNumber'] : 1,
        'basisRecord' => is_array($content['basisRecord'] ?? null) ? $content['basisRecord'] : [],
        'updatedAt' => trim((string)($content['updatedAt'] ?? '')),
        'filename' => $id !== '' ? $id . '.json' : '',
        'url' => $id !== '' ? lessons_api_remote_lesson_url($id) : '',
    ];
}

function lessons_api_build_manifest_from_dir(string $dir): array
{
    $items = [];
    $files = glob(rtrim($dir, '/') . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
        if (str_starts_with(basename($file), '._')) {
            continue;
        }
        $content = json_decode((string)@file_get_contents($file), true);
        if (!is_array($content)) {
            continue;
        }
        $id = trim((string)($content['id'] ?? pathinfo($file, PATHINFO_FILENAME)));
        $safeId = trim((string)preg_replace('/[^a-zA-Z0-9_-]/', '-', $id), '-_');
        if ($safeId !== '') {
            $items[] = lessons_api_normalize_manifest_item($content, $safeId);
        }
    }

    return [
        'ok' => true,
        'updatedAt' => gmdate('c'),
        'items' => $items,
    ];
}

function lessons_api_rebuild_manifest(string $dir): bool
{
    $dir = rtrim($dir, '/');
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $manifest = lessons_api_build_manifest_from_dir($dir);
    $items = $manifest['items'];

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

    $manifest['items'] = $items;
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    $manifestFile = lessons_api_manifest_file();
    $manifestDir = dirname($manifestFile);
    if (!is_dir($manifestDir) && !@mkdir($manifestDir, 0775, true) && !is_dir($manifestDir)) {
        return false;
    }

    return file_put_contents($manifestFile, $encoded, LOCK_EX) !== false;
}

function lessons_api_json_error(string $message, int $status = 500, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lessons_api_step_script_id(array $row): string
{
    foreach (['id', 'scriptId', 'blocklyScriptId', 'script_id', 'blockly_script_id'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $script = is_array($row['script'] ?? null) ? $row['script'] : [];
    return trim((string)($script['id'] ?? ''));
}

function lessons_api_clean_step_script_id(array $row): string
{
    $id = lessons_api_step_script_id($row);
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
    return trim((string)$id, '-_');
}
