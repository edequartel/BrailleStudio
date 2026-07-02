<?php
declare(strict_types=1);

function braillestudio_cache_dir(): string
{
    return dirname(__DIR__) . '/temp/cache';
}

function braillestudio_cache_files_fingerprint(string $dir, string $pattern = '*.json'): ?string
{
    $dir = rtrim($dir, '/');
    if ($dir === '' || !is_dir($dir)) {
        return null;
    }

    $files = glob($dir . '/' . $pattern) ?: [];
    sort($files);

    $parts = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (str_starts_with($name, '._') || !is_file($file)) {
            continue;
        }
        $parts[] = implode(':', [
            $name,
            (string)(filesize($file) ?: 0),
            (string)(filemtime($file) ?: 0),
        ]);
    }

    return hash('sha256', implode('|', $parts));
}

function braillestudio_cache_get(string $key, ?string $fingerprint): ?array
{
    if ($fingerprint === null) {
        return null;
    }

    $file = braillestudio_cache_dir() . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '-', $key) . '.json';
    if (!is_file($file)) {
        return null;
    }

    $decoded = json_decode((string)@file_get_contents($file), true);
    if (!is_array($decoded) || ($decoded['fingerprint'] ?? '') !== $fingerprint) {
        return null;
    }

    return is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
}

function braillestudio_cache_set(string $key, ?string $fingerprint, array $data): void
{
    if ($fingerprint === null) {
        return;
    }

    $dir = braillestudio_cache_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '-', $key) . '.json';
    $payload = [
        'fingerprint' => $fingerprint,
        'cachedAt' => gmdate('c'),
        'data' => $data,
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($encoded)) {
        @file_put_contents($file, $encoded, LOCK_EX);
    }
}

function braillestudio_cache_remember(string $key, ?string $fingerprint, callable $builder): array
{
    $cached = braillestudio_cache_get($key, $fingerprint);
    if (is_array($cached)) {
        return $cached;
    }

    $data = $builder();
    if (!is_array($data)) {
        $data = [];
    }
    braillestudio_cache_set($key, $fingerprint, $data);
    return $data;
}
