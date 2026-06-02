<?php
declare(strict_types=1);

require_once __DIR__ . '/supabase-config.php';

define('SESSION_API_SUPABASE_CONFIG', session_api_supabase_config_path());

session_api_state_send_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    session_api_state_json([
        'ok' => false,
        'error' => 'Method not allowed. Use GET or POST.',
    ], 405);
}

$input = $_GET;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw = file_get_contents('php://input');
    $json = [];
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            session_api_state_json(['ok' => false, 'error' => 'Ongeldige JSON body.'], 400);
        }
        $json = $decoded;
    }
    $input = array_merge($input, $_POST, $json);
}

$sessionCode = session_api_state_normalize_session_code((string)($input['session_code'] ?? ($input['session'] ?? '')));
if ($sessionCode === '') {
    session_api_state_json(['ok' => false, 'error' => 'session_code ontbreekt.'], 400);
}

$config = session_api_state_load_supabase_config();
$endpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions'
    . '?session_code=eq.' . rawurlencode($sessionCode)
    . '&select=*'
    . '&limit=1';
$response = session_api_state_supabase_get($endpoint, $config['SUPABASE_SERVICE_ROLE_KEY']);

if ($response['curl_error'] !== '') {
    session_api_state_json([
        'ok' => false,
        'error' => 'Supabase request mislukt.',
        'http_code' => $response['http_code'],
        'curl_error' => $response['curl_error'],
    ], 502);
}

if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
    session_api_state_json([
        'ok' => false,
        'error' => 'Supabase gaf geen geldige sessiestatus terug.',
        'http_code' => $response['http_code'],
        'supabase_response' => $response['body_json'] ?? $response['body'],
    ], $response['http_code'] > 0 ? $response['http_code'] : 500);
}

$rows = is_array($response['body_json'] ?? null) ? $response['body_json'] : [];
$row = is_array($rows[0] ?? null) ? $rows[0] : null;

session_api_state_json([
    'ok' => true,
    'session_code' => $sessionCode,
    'session' => $row,
    'found' => $row !== null,
]);

function session_api_state_send_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function session_api_state_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function session_api_state_normalize_session_code(string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '') {
        return '';
    }
    if (!preg_match('/\A[A-Z0-9_-]{3,32}\z/', $value)) {
        session_api_state_json([
            'ok' => false,
            'error' => 'Ongeldige session_code. Gebruik 3-32 tekens: letters, cijfers, underscore of koppelteken.',
        ], 400);
    }
    return $value;
}

function session_api_state_load_supabase_config(): array
{
    if (!is_file(SESSION_API_SUPABASE_CONFIG)) {
        session_api_state_json([
            'ok' => false,
            'error' => 'Configbestand niet gevonden.',
            'path' => SESSION_API_SUPABASE_CONFIG,
        ], 500);
    }

    $config = require SESSION_API_SUPABASE_CONFIG;
    $config = is_array($config) ? $config : [];

    $url = defined('SUPABASE_URL') ? (string)SUPABASE_URL : (string)($config['SUPABASE_URL'] ?? ($SUPABASE_URL ?? ''));
    $serviceRoleKey = defined('SUPABASE_SERVICE_ROLE_KEY') ? (string)SUPABASE_SERVICE_ROLE_KEY : (string)($config['SUPABASE_SERVICE_ROLE_KEY'] ?? ($SUPABASE_SERVICE_ROLE_KEY ?? ''));

    if (trim($url) === '') {
        session_api_state_json(['ok' => false, 'error' => 'SUPABASE_URL ontbreekt.'], 500);
    }
    if (trim($serviceRoleKey) === '') {
        session_api_state_json(['ok' => false, 'error' => 'SUPABASE_SERVICE_ROLE_KEY ontbreekt.'], 500);
    }

    return [
        'SUPABASE_URL' => trim($url),
        'SUPABASE_SERVICE_ROLE_KEY' => trim($serviceRoleKey),
    ];
}

function session_api_state_supabase_get(string $endpoint, string $serviceRoleKey): array
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
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $serviceRoleKey,
            'Authorization: Bearer ' . $serviceRoleKey,
            'Content-Type: application/json',
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
