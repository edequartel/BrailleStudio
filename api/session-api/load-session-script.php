<?php
declare(strict_types=1);

require_once __DIR__ . '/supabase-config.php';

define('SESSION_API_SUPABASE_CONFIG', session_api_supabase_config_path());

session_script_send_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    session_script_json(['ok' => false, 'error' => 'Method not allowed. Use GET.'], 405);
}

$sessionCode = session_script_normalize_token((string)($_GET['session_code'] ?? $_GET['session'] ?? ''), 'session_code', 3, 32);
$scriptId = session_script_normalize_token((string)($_GET['id'] ?? ''), 'id', 3, 128);
$config = session_script_load_config();
$endpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions'
    . '?session_code=eq.' . rawurlencode($sessionCode)
    . '&select=session_code,script_id'
    . '&limit=1';
$response = session_script_supabase_get($endpoint, $config['SUPABASE_SERVICE_ROLE_KEY']);

if ($response['curl_error'] !== '') {
    session_script_json(['ok' => false, 'error' => 'Session lookup failed.'], 502);
}
if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
    session_script_json(['ok' => false, 'error' => 'Session lookup failed.'], $response['http_code'] ?: 500);
}

$rows = is_array($response['body_json'] ?? null) ? $response['body_json'] : [];
$session = is_array($rows[0] ?? null) ? $rows[0] : null;
if ($session === null) {
    session_script_json(['ok' => false, 'error' => 'Session not found.'], 404);
}
if (trim((string)($session['script_id'] ?? '')) !== $scriptId) {
    session_script_json(['ok' => false, 'error' => 'Script is not assigned to this session.'], 403);
}

$path = dirname(__DIR__, 3) . '/braillestudio-data/data/blockly/' . $scriptId . '.json';
if (!is_file($path)) {
    session_script_json(['ok' => false, 'error' => 'Script not found.'], 404);
}
$content = json_decode((string)file_get_contents($path), true);
if (!is_array($content)) {
    session_script_json(['ok' => false, 'error' => 'Invalid stored script JSON.'], 500);
}

$meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
$content['meta'] = [
    'title' => trim((string)($meta['title'] ?? ($content['title'] ?? ''))),
    'description' => trim((string)($meta['description'] ?? '')),
    'instruction' => trim((string)($meta['instruction'] ?? '')),
    'prompt' => trim((string)($meta['prompt'] ?? '')),
    'status' => trim((string)($meta['status'] ?? 'draft')),
];
session_script_json($content);

function session_script_send_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function session_script_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function session_script_normalize_token(string $value, string $field, int $min, int $max): string
{
    $value = trim($value);
    if (strlen($value) < $min || strlen($value) > $max || !preg_match('/\A[A-Za-z0-9_-]+\z/', $value)) {
        session_script_json(['ok' => false, 'error' => 'Invalid ' . $field . '.'], 400);
    }
    return $field === 'session_code' ? strtoupper($value) : $value;
}

function session_script_load_config(): array
{
    $config = require SESSION_API_SUPABASE_CONFIG;
    $config = is_array($config) ? $config : [];
    $url = trim((string)($config['SUPABASE_URL'] ?? ''));
    $serviceRoleKey = trim((string)($config['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    if ($url === '' || $serviceRoleKey === '') {
        session_script_json(['ok' => false, 'error' => 'Session service is not configured.'], 500);
    }
    return ['SUPABASE_URL' => $url, 'SUPABASE_SERVICE_ROLE_KEY' => $serviceRoleKey];
}

function session_script_supabase_get(string $endpoint, string $serviceRoleKey): array
{
    if (!function_exists('curl_init')) {
        return ['http_code' => 0, 'body_json' => null, 'curl_error' => 'PHP cURL extension is not available'];
    }
    $ch = curl_init($endpoint);
    if ($ch === false) {
        return ['http_code' => 0, 'body_json' => null, 'curl_error' => 'curl_init failed'];
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
    $decoded = json_decode(is_string($body) ? $body : '', true);
    return [
        'http_code' => $httpCode,
        'body_json' => is_array($decoded) ? $decoded : null,
        'curl_error' => $error,
    ];
}
