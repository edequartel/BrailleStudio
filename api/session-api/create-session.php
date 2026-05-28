<?php
declare(strict_types=1);

const SESSION_API_SUPABASE_CONFIG = '/home3/kydjgrmy/private/supabase_config.php';

session_api_send_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    session_api_json([
        'ok' => false,
        'error' => 'Method not allowed. Use POST, or GET for simple QR testing.',
    ], 405);
}

$config = session_api_load_supabase_config();
$input = session_api_read_input();

$sessionCode = session_api_normalize_session_code((string)($input['session_code'] ?? ''));
if ($sessionCode === '') {
    $sessionCode = session_api_generate_session_code();
}

$now = gmdate('c');
$record = [
    'session_code' => $sessionCode,
    'script_id' => null,
    'command' => null,
    'status' => 'waiting',
    'record_index' => null,
    'executed' => false,
    'updated_at' => $now,
];

$patchEndpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions?session_code=eq.' . rawurlencode($sessionCode);
$response = session_api_supabase_request('PATCH', $patchEndpoint, $config['SUPABASE_SERVICE_ROLE_KEY'], $record, [
    'Prefer: return=representation',
]);

if (
    $response['curl_error'] === ''
    && $response['http_code'] >= 200
    && $response['http_code'] < 300
    && session_api_response_has_no_rows($response)
) {
    $insertEndpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions';
    $response = session_api_supabase_request('POST', $insertEndpoint, $config['SUPABASE_SERVICE_ROLE_KEY'], $record, [
        'Prefer: return=representation',
    ]);
}

$status = $response['curl_error'] !== ''
    ? 502
    : ($response['http_code'] > 0 ? $response['http_code'] : 500);
$baseUrl = session_api_public_base_url();
$laptopUrl = $baseUrl . '/laptop.html?session=' . rawurlencode($sessionCode);
$phoneUrl = $baseUrl . '/phone.html?session=' . rawurlencode($sessionCode);
$sendUrl = $baseUrl . '/send-script.php?session_code=' . rawurlencode($sessionCode) . '&script_id=SCRIPT_ID';

session_api_json([
    'ok' => $response['curl_error'] === '' && $response['http_code'] >= 200 && $response['http_code'] < 300,
    'http_code' => $response['http_code'],
    'session_code' => $sessionCode,
    'laptop_url' => $laptopUrl,
    'phone_url' => $phoneUrl,
    'send_url' => $sendUrl,
    'supabase_url' => $config['SUPABASE_URL'],
    'supabase_anon_key' => $config['SUPABASE_ANON_KEY'],
    'supabase_response' => $response['body_json'] ?? $response['body'],
    'curl_error' => $response['curl_error'] ?: null,
], $status);

function session_api_send_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function session_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function session_api_read_input(): array
{
    $input = $_GET;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $raw = file_get_contents('php://input');
        $json = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                session_api_json(['ok' => false, 'error' => 'Ongeldige JSON body.'], 400);
            }
            $json = $decoded;
        }
        $input = array_merge($input, $_POST, $json);
    }
    return $input;
}

function session_api_load_supabase_config(): array
{
    if (!is_file(SESSION_API_SUPABASE_CONFIG)) {
        session_api_json([
            'ok' => false,
            'error' => 'Configbestand niet gevonden.',
            'path' => SESSION_API_SUPABASE_CONFIG,
        ], 500);
    }

    $config = require SESSION_API_SUPABASE_CONFIG;
    $config = is_array($config) ? $config : [];

    $url = defined('SUPABASE_URL') ? (string)SUPABASE_URL : (string)($config['SUPABASE_URL'] ?? ($SUPABASE_URL ?? ''));
    $serviceRoleKey = defined('SUPABASE_SERVICE_ROLE_KEY') ? (string)SUPABASE_SERVICE_ROLE_KEY : (string)($config['SUPABASE_SERVICE_ROLE_KEY'] ?? ($SUPABASE_SERVICE_ROLE_KEY ?? ''));
    $anonKey = defined('SUPABASE_ANON_KEY') ? (string)SUPABASE_ANON_KEY : (string)($config['SUPABASE_ANON_KEY'] ?? ($SUPABASE_ANON_KEY ?? ''));

    if (trim($url) === '') {
        session_api_json(['ok' => false, 'error' => 'SUPABASE_URL ontbreekt.'], 500);
    }
    if (trim($serviceRoleKey) === '') {
        session_api_json(['ok' => false, 'error' => 'SUPABASE_SERVICE_ROLE_KEY ontbreekt.'], 500);
    }
    if (trim($anonKey) === '') {
        session_api_json(['ok' => false, 'error' => 'SUPABASE_ANON_KEY ontbreekt. De browser heeft alleen deze publieke key nodig.'], 500);
    }

    return [
        'SUPABASE_URL' => trim($url),
        'SUPABASE_SERVICE_ROLE_KEY' => trim($serviceRoleKey),
        'SUPABASE_ANON_KEY' => trim($anonKey),
    ];
}

function session_api_normalize_session_code(string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '') {
        return '';
    }
    if (!preg_match('/\A[A-Z0-9_-]{3,32}\z/', $value)) {
        session_api_json([
            'ok' => false,
            'error' => 'Ongeldige session_code. Gebruik 3-32 tekens: letters, cijfers, underscore of koppelteken.',
        ], 400);
    }
    return $value;
}

function session_api_generate_session_code(): string
{
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    return $letters[random_int(0, strlen($letters) - 1)]
        . $letters[random_int(0, strlen($letters) - 1)]
        . $letters[random_int(0, strlen($letters) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)];
}

function session_api_response_has_no_rows(array $response): bool
{
    $body = $response['body_json'] ?? null;
    return is_array($body) && count($body) === 0;
}

function session_api_public_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/api/session-api/create-session.php'))), '/');
    return $scheme . '://' . $host . $dir;
}

function session_api_supabase_request(string $method, string $endpoint, string $serviceRoleKey, array $payload, array $extraHeaders = []): array
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

    $headers = array_merge([
        'apikey: ' . $serviceRoleKey,
        'Authorization: Bearer ' . $serviceRoleKey,
        'Content-Type: application/json',
    ], $extraHeaders);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
