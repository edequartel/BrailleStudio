<?php
declare(strict_types=1);

function xapi_config_path(): string
{
    $envPath = trim((string)(getenv('BRAILLESTUDIO_XAPI_CONFIG') ?: ($_SERVER['BRAILLESTUDIO_XAPI_CONFIG'] ?? $_ENV['BRAILLESTUDIO_XAPI_CONFIG'] ?? '')));
    $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $candidates = [];

    if ($envPath !== '') {
        $candidates[] = $envPath;
    }

    if ($documentRoot !== '') {
        $candidates[] = rtrim(dirname($documentRoot), '/') . '/private/supabase_xapi.php';
    }

    $candidates[] = '/home3/kydjgrmy/private/supabase_xapi.php';
    $candidates[] = dirname(__DIR__, 2) . '/private/supabase_xapi.php';

    foreach (array_values(array_unique($candidates)) as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    throw new RuntimeException('Missing private/supabase_xapi.php. Set BRAILLESTUDIO_XAPI_CONFIG when using a custom location.');
}

function xapi_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $loaded = require xapi_config_path();
    if (!is_array($loaded)) {
        throw new RuntimeException('The xAPI Supabase config must return an array.');
    }

    $config = [
        'SUPABASE_URL' => trim((string)($loaded['SUPABASE_URL'] ?? '')),
        'SUPABASE_SERVICE_ROLE_KEY' => trim((string)($loaded['SUPABASE_SERVICE_ROLE_KEY'] ?? '')),
        'SUPABASE_ANON_KEY' => trim((string)($loaded['SUPABASE_ANON_KEY'] ?? '')),
        // Keep the original xAPI identifier namespace unless explicitly overridden.
        'APP_HOME' => rtrim(trim((string)($loaded['APP_HOME'] ?? 'https://www.tastenbraille.com/xapi')), '/'),
    ];

    if ($config['SUPABASE_URL'] === '' || $config['SUPABASE_SERVICE_ROLE_KEY'] === '') {
        throw new RuntimeException('SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are required in private/supabase_xapi.php.');
    }

    return $config;
}

function sb_request(string $method, string $path, ?array $body = null): array
{
    $config = xapi_config();
    $serviceKey = $config['SUPABASE_SERVICE_ROLE_KEY'];
    $ch = curl_init(rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/' . $path);

    $headers = [
        'apikey: ' . $serviceKey,
        'Authorization: Bearer ' . $serviceKey,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'data' => json_decode($response ?: '[]', true),
        'raw' => $response
    ];
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
