<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function elevenlabs_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function elevenlabs_get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function elevenlabs_normalize_text(mixed $value): string
{
    return trim((string)$value);
}

function elevenlabs_load_config(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $config = require $path;
    return is_array($config) ? $config : null;
}

function elevenlabs_load_config_value(string $path): ?string
{
    $config = elevenlabs_load_config($path);
    if (!is_array($config)) {
        return null;
    }

    $value = trim((string)($config['api_key'] ?? ''));
    return $value !== '' ? $value : null;
}

function elevenlabs_base_dirs(): array
{
    $dir = __DIR__;
    $candidates = [
        dirname($dir, 2),
        dirname($dir, 3),
        dirname($dir, 4),
    ];

    $baseDirs = [];
    foreach ($candidates as $path) {
        $normalized = rtrim((string)$path, '/');
        if ($normalized === '' || in_array($normalized, $baseDirs, true)) {
            continue;
        }
        $baseDirs[] = $normalized;
    }

    return $baseDirs;
}

function elevenlabs_config_candidates(): array
{
    $paths = [];
    foreach (elevenlabs_base_dirs() as $baseDir) {
        $paths[] = $baseDir . '/private/elevenlabs_config.php';
        $paths[] = $baseDir . '/secrets/elevenlabs_config.php';
    }
    return $paths;
}

function elevenlabs_existing_config_path(): ?string
{
    foreach (elevenlabs_config_candidates() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function elevenlabs_server_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    foreach (elevenlabs_config_candidates() as $path) {
        $loaded = elevenlabs_load_config($path);
        if (is_array($loaded)) {
            $config = $loaded;
            return $config;
        }
    }

    $config = [];
    return $config;
}

function elevenlabs_configured_voices(): array
{
    $configured = elevenlabs_server_config()['voices'] ?? [];
    if (!is_array($configured)) {
        return [];
    }

    $voices = [];
    foreach ($configured as $key => $voice) {
        if (!is_array($voice)) {
            $voiceId = trim((string)$voice);
            if ($voiceId === '') {
                continue;
            }
            $voices[] = [
                'key' => trim((string)$key),
                'voice_id' => $voiceId,
                'name' => trim((string)$key),
                'language' => '',
            ];
            continue;
        }

        $voiceId = trim((string)($voice['voice_id'] ?? ''));
        if ($voiceId === '') {
            continue;
        }

        $voices[] = [
            'key' => trim((string)($voice['key'] ?? $key)),
            'voice_id' => $voiceId,
            'name' => trim((string)($voice['name'] ?? $key)),
            'language' => trim((string)($voice['language'] ?? '')),
        ];
    }

    return $voices;
}

function elevenlabs_default_voice_id(): string
{
    $config = elevenlabs_server_config();
    $defaultVoiceId = trim((string)($config['default_voice_id'] ?? ''));
    if ($defaultVoiceId !== '') {
        return $defaultVoiceId;
    }

    $voices = elevenlabs_configured_voices();
    return $voices[0]['voice_id'] ?? '';
}

function elevenlabs_secret_candidates(): array
{
    $paths = [
        getenv('ELEVENLABS_API_KEY') ?: null,
        $_SERVER['ELEVENLABS_API_KEY'] ?? null,
        $_ENV['ELEVENLABS_API_KEY'] ?? null,
    ];

    foreach (elevenlabs_base_dirs() as $baseDir) {
        $paths[] = elevenlabs_load_config_value($baseDir . '/private/elevenlabs_config.php');
        $paths[] = elevenlabs_load_config_value($baseDir . '/secrets/elevenlabs_config.php');
        $paths[] = @is_file($baseDir . '/elevenlabs_api_key.txt') ? file_get_contents($baseDir . '/elevenlabs_api_key.txt') : null;
        $paths[] = @is_file($baseDir . '/private/elevenlabs_api_key.txt') ? file_get_contents($baseDir . '/private/elevenlabs_api_key.txt') : null;
        $paths[] = @is_file($baseDir . '/secrets/elevenlabs_api_key.txt') ? file_get_contents($baseDir . '/secrets/elevenlabs_api_key.txt') : null;
    }

    return $paths;
}

function elevenlabs_api_key(): string
{
    foreach (elevenlabs_secret_candidates() as $candidate) {
        $value = trim((string)($candidate ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    elevenlabs_json_response([
        'ok' => false,
        'error' => 'ElevenLabs API key not configured. Set ELEVENLABS_API_KEY or place private/elevenlabs_config.php outside public webroot.'
    ], 500);
}

function elevenlabs_debug_info(): array
{
    $configCandidates = [];
    foreach (elevenlabs_config_candidates() as $path) {
        $configCandidates[] = [
            'path' => $path,
            'exists' => is_file($path),
        ];
    }

    $hasApiKey = false;
    foreach (elevenlabs_secret_candidates() as $candidate) {
        if (trim((string)($candidate ?? '')) !== '') {
            $hasApiKey = true;
            break;
        }
    }

    return [
        'base_dirs' => elevenlabs_base_dirs(),
        'config_candidates' => $configCandidates,
        'selected_config_path' => elevenlabs_existing_config_path(),
        'default_voice_id' => elevenlabs_default_voice_id(),
        'configured_voice_count' => count(elevenlabs_configured_voices()),
        'has_api_key' => $hasApiKey,
    ];
}

function elevenlabs_request(
    string $method,
    string $path,
    ?array $jsonBody = null,
    array $extraHeaders = []
): array {
    $apiKey = elevenlabs_api_key();
    $url = 'https://api.elevenlabs.io' . $path;
    $headers = array_merge([
        'xi-api-key: ' . $apiKey,
        'Accept: application/json',
    ], $extraHeaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 90,
    ]);

    if ($jsonBody !== null) {
      $payload = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }

    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        elevenlabs_json_response([
            'ok' => false,
            'error' => 'ElevenLabs request failed: ' . ($error !== '' ? $error : 'unknown cURL error'),
        ], 502);
    }

    return [
        'status' => $status,
        'body' => (string)$body,
    ];
}
