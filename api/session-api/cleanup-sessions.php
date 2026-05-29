<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

const SESSION_API_SUPABASE_CONFIG = '/home3/kydjgrmy/private/supabase_config.php';
const SESSION_API_SUPABASE_IDLE_TTL_SECONDS = 1800;

session_api_handle_options();

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    session_api_error('Method not allowed. Use POST, or GET for scheduled cleanup.', 405);
}

$config = session_api_load_supabase_config();
$cutoff = gmdate('c', time() - SESSION_API_SUPABASE_IDLE_TTL_SECONDS);
$endpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions?updated_at=lt.' . rawurlencode($cutoff);
$response = session_api_supabase_request('DELETE', $endpoint, $config['SUPABASE_SERVICE_ROLE_KEY']);
$status = $response['curl_error'] !== ''
    ? 502
    : ($response['http_code'] > 0 ? $response['http_code'] : 500);

session_api_respond([
    'ok' => $response['curl_error'] === '' && $response['http_code'] >= 200 && $response['http_code'] < 300,
    'http_code' => $response['http_code'],
    'idle_ttl_seconds' => SESSION_API_SUPABASE_IDLE_TTL_SECONDS,
    'cutoff' => $cutoff,
    'deleted' => is_array($response['body_json'] ?? null) ? count($response['body_json']) : null,
    'supabase_response' => $response['body_json'] ?? $response['body'],
    'curl_error' => $response['curl_error'] ?: null,
], $status);

function session_api_load_supabase_config(): array
{
    if (!is_file(SESSION_API_SUPABASE_CONFIG)) {
        session_api_error('Configbestand niet gevonden.', 500, ['path' => SESSION_API_SUPABASE_CONFIG]);
    }

    $config = require SESSION_API_SUPABASE_CONFIG;
    $config = is_array($config) ? $config : [];

    $url = defined('SUPABASE_URL') ? (string)SUPABASE_URL : (string)($config['SUPABASE_URL'] ?? ($SUPABASE_URL ?? ''));
    $serviceRoleKey = defined('SUPABASE_SERVICE_ROLE_KEY') ? (string)SUPABASE_SERVICE_ROLE_KEY : (string)($config['SUPABASE_SERVICE_ROLE_KEY'] ?? ($SUPABASE_SERVICE_ROLE_KEY ?? ''));

    if (trim($url) === '') {
        session_api_error('SUPABASE_URL ontbreekt.', 500);
    }
    if (trim($serviceRoleKey) === '') {
        session_api_error('SUPABASE_SERVICE_ROLE_KEY ontbreekt.', 500);
    }

    return [
        'SUPABASE_URL' => trim($url),
        'SUPABASE_SERVICE_ROLE_KEY' => trim($serviceRoleKey),
    ];
}

function session_api_supabase_request(string $method, string $endpoint, string $serviceRoleKey): array
{
    $ch = curl_init($endpoint);
    if ($ch === false) {
        return [
            'http_code' => 0,
            'body' => '',
            'body_json' => null,
            'curl_error' => 'curl_init failed',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $serviceRoleKey,
            'Authorization: Bearer ' . $serviceRoleKey,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $body = is_string($body) ? $body : '';
    $decoded = json_decode($body, true);

    return [
        'http_code' => $httpCode,
        'body' => $body,
        'body_json' => is_array($decoded) ? $decoded : null,
        'curl_error' => $error,
    ];
}
