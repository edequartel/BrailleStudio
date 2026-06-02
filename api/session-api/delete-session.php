<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';
require_once __DIR__ . '/supabase-config.php';

define('SESSION_API_SUPABASE_CONFIG', session_api_supabase_config_path());

session_api_handle_options();
session_api_require_post();

$input = array_merge($_POST, session_api_read_json_input());
$sessionCode = session_api_normalize_public_session_code((string)($input['session_code'] ?? ($input['session'] ?? '')));
if ($sessionCode === '') {
    session_api_error('session_code ontbreekt.', 400);
}

$config = session_api_load_supabase_config();
$endpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions?session_code=eq.' . rawurlencode($sessionCode);
$response = session_api_supabase_request('DELETE', $endpoint, $config['SUPABASE_SERVICE_ROLE_KEY']);
$status = $response['curl_error'] !== ''
    ? 502
    : ($response['http_code'] > 0 ? $response['http_code'] : 500);

session_api_respond([
    'ok' => $response['curl_error'] === '' && $response['http_code'] >= 200 && $response['http_code'] < 300,
    'http_code' => $response['http_code'],
    'session_code' => $sessionCode,
    'deleted' => is_array($response['body_json'] ?? null) ? count($response['body_json']) : null,
    'supabase_response' => $response['body_json'] ?? $response['body'],
    'curl_error' => $response['curl_error'] ?: null,
], $status);

function session_api_normalize_public_session_code(string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '') {
        return '';
    }
    if (!preg_match('/\A[A-Z0-9_-]{3,32}\z/', $value)) {
        session_api_error('Ongeldige session_code. Gebruik 3-32 tekens: letters, cijfers, underscore of koppelteken.', 400);
    }
    return $value;
}

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

    $body = is_string($body) ? $body : '';
    $decoded = json_decode($body, true);

    return [
        'http_code' => $httpCode,
        'body' => $body,
        'body_json' => is_array($decoded) ? $decoded : null,
        'curl_error' => $error,
    ];
}
