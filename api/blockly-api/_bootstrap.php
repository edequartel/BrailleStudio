<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/json-guard.php';
braillestudio_json_guard_start();

require_once dirname(__DIR__) . '/authentication-api/_bootstrap.php';

function blockly_api_require_authentication(): array
{
    return authenticate_require_bearer_token_for_audiences([
        'braillestudio-api',
        'braillestudio-elevenlabs-api',
    ]);
}

function blockly_api_remote_data_base_url(): string
{
    return blockly_api_public_app_base_url() . '/data/blockly';
}

function blockly_api_remote_manifest_url(): string
{
    return blockly_api_public_app_base_url() . '/temp/manifests/blockly.json';
}

function blockly_api_public_app_base_url(): string
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $appPath = preg_replace('~/api/blockly-api$~', '', rtrim($scriptDir, '/')) ?? '';

    return $scheme . '://' . $host . ($appPath === '' ? '' : $appPath);
}

function blockly_api_manifest_file(): string
{
    return dirname(__DIR__, 2) . '/temp/manifests/blockly.json';
}

function blockly_api_is_canonical_host(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return $host === 'www.tastenbraille.com' || $host === 'tastenbraille.com';
}

function blockly_api_should_prefer_local_data(): bool
{
    return true;
}

function blockly_api_is_http_url(string $value): bool
{
    return preg_match('~^https?://~i', trim($value)) === 1;
}

function blockly_api_fetch_url(string $url): ?string
{
    if (!blockly_api_is_http_url($url)) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: BrailleStudioBlocklyApi/1.0\r\n",
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
        CURLOPT_USERAGENT => 'BrailleStudioBlocklyApi/1.0',
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

function blockly_api_fetch_json_url(string $url): ?array
{
    $raw = blockly_api_fetch_url($url);
    if ($raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function blockly_api_remote_script_url(string $safeId): string
{
    return blockly_api_remote_data_base_url() . '/' . rawurlencode($safeId) . '.json';
}

function blockly_api_load_remote_script(string $safeId): ?array
{
    if (blockly_api_should_prefer_local_data()) {
        $local = blockly_api_load_local_script($safeId);
        if (is_array($local)) {
            return $local;
        }
    }

    $remote = blockly_api_fetch_json_url(blockly_api_remote_script_url($safeId));
    if (is_array($remote)) {
        return $remote;
    }

    return blockly_api_load_local_script($safeId);
}

function blockly_api_remote_manifest_urls(): array
{
    return [
        blockly_api_remote_manifest_url() . '?_=' . rawurlencode((string)time()),
    ];
}

function blockly_api_load_remote_manifest(): ?array
{
    if (blockly_api_should_prefer_local_data()) {
        $local = blockly_api_load_local_manifest();
        if (is_array($local)) {
            return $local;
        }
    }

    foreach (blockly_api_remote_manifest_urls() as $url) {
        $manifest = blockly_api_fetch_json_url($url);
        if (is_array($manifest)) {
            return $manifest;
        }
    }

    if (blockly_api_is_canonical_host()) {
        $dir = blockly_api_writable_data_dir() ?? blockly_api_data_dir();
        blockly_api_rebuild_manifest($dir);
        $manifest = json_decode((string)@file_get_contents(blockly_api_manifest_file()), true);
        if (is_array($manifest)) {
            return $manifest;
        }
    }

    return null;
}

function blockly_api_data_dir(): string
{
    $dirs = blockly_api_data_dirs();
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return $dirs[0];
}

function blockly_api_data_dirs(): array
{
    $envDir = trim((string)getenv('BRAILLESTUDIO_BLOCKLY_DATA_DIR'));
    $dirs = [];
    if ($envDir !== '') {
        $dirs[] = $envDir;
    }
    $dirs[] = dirname(__DIR__, 2) . '/data/blockly';
    $dirs[] = dirname(__DIR__) . '/data/blockly';
    $dirs[] = dirname(__DIR__, 2) . '/XXX data/blockly';

    return array_values(array_unique($dirs));
}

function blockly_api_load_local_script(string $safeId): ?array
{
    $filePath = blockly_api_find_script_path($safeId);
    if ($filePath === null) {
        return null;
    }
    $content = json_decode((string)@file_get_contents($filePath), true);
    return is_array($content) ? $content : null;
}

function blockly_api_load_local_manifest(): ?array
{
    $manifest = json_decode((string)@file_get_contents(blockly_api_manifest_file()), true);
    if (is_array($manifest)) {
        return $manifest;
    }

    foreach (blockly_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            return blockly_api_build_manifest_from_dir($dir);
        }
    }

    return null;
}

function blockly_api_build_manifest_from_dir(string $dir): array
{
    $dir = rtrim($dir, '/');
    $items = [];
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (str_starts_with($name, '._')) {
            continue;
        }
        $content = json_decode((string)@file_get_contents($file), true);
        if (!is_array($content)) {
            continue;
        }
        $id = trim((string)($content['id'] ?? pathinfo($file, PATHINFO_FILENAME)));
        $safeId = trim((string)preg_replace('/[^a-zA-Z0-9_-]/', '-', $id), '-_');
        if ($safeId === '') {
            continue;
        }
        $items[] = [
            'id' => $safeId,
            'title' => trim((string)($content['title'] ?? '')),
            'updatedAt' => trim((string)($content['updatedAt'] ?? '')),
            'filename' => $safeId . '.json',
            'meta' => blockly_api_normalize_meta($content),
        ];
    }

    usort($items, static fn(array $a, array $b): int => strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? '')));
    return [
        'ok' => true,
        'updatedAt' => gmdate('c'),
        'items' => $items,
    ];
}

function blockly_api_find_script_path(string $safeId): ?string
{
    foreach (blockly_api_data_dirs() as $dir) {
        $filePath = $dir . '/' . $safeId . '.json';
        if (is_file($filePath)) {
            return $filePath;
        }
    }
    return null;
}

function blockly_api_writable_data_dir(): ?string
{
    foreach (blockly_api_data_dirs() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    foreach (blockly_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (@mkdir($dir, 0775, true) && is_writable($dir)) {
            return $dir;
        }
    }

    return null;
}

function blockly_api_normalize_meta(array $content): array
{
    $meta = isset($content['meta']) && is_array($content['meta']) ? $content['meta'] : [];
    return [
        'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
        'description' => isset($meta['description']) ? trim((string)$meta['description']) : trim((string)($content['description'] ?? '')),
        'instruction' => isset($meta['instruction']) ? trim((string)$meta['instruction']) : trim((string)($content['instruction'] ?? '')),
        'prompt' => isset($meta['prompt']) ? trim((string)$meta['prompt']) : trim((string)($content['prompt'] ?? '')),
        'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
    ];
}

function blockly_api_rebuild_manifest(string $dir): bool
{
    $dir = rtrim($dir, '/');
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $manifest = blockly_api_build_manifest_from_dir($dir);
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    $manifestFile = blockly_api_manifest_file();
    $manifestDir = dirname($manifestFile);
    if (!is_dir($manifestDir) && !@mkdir($manifestDir, 0775, true) && !is_dir($manifestDir)) {
        return false;
    }

    return file_put_contents($manifestFile, $encoded, LOCK_EX) !== false;
}

function blockly_api_json_error(string $message, int $status = 500, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
