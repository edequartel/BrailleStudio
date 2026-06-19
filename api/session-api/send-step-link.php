<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';
require_once __DIR__ . '/supabase-config.php';

define('SESSION_API_SUPABASE_CONFIG', session_api_supabase_config_path());

session_api_handle_options();

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    session_api_error('Method not allowed. Use POST, or GET temporarily for testing.', 405);
}

$input = session_api_read_request_input();
$sessionCode = session_api_normalize_public_session_code((string)($input['session_code'] ?? ($input['session'] ?? '')));
$code = session_api_normalize_token((string)($input['code'] ?? ($input['step_link'] ?? '')), 'code', 3, 64);
$methodIdRaw = trim((string)($input['methodId'] ?? ($input['method_id'] ?? '')));
$methodId = $methodIdRaw !== '' ? session_api_normalize_token($methodIdRaw, 'methodId', 3, 128) : '';

if ($sessionCode === '') {
    session_api_error('session_code ontbreekt.', 400);
}

$stepLink = session_api_load_step_link_or_fail($code, $methodId);
$resolvedMethodId = trim((string)($stepLink['methodId'] ?? $methodId));
$stepLinkCommand = 'load_step_link:' . $code;
if ($resolvedMethodId !== '') {
    $stepLinkCommand .= ':' . $resolvedMethodId;
}
$config = session_api_load_supabase_config();

if (($stepLink['active'] ?? true) === false) {
    $sent = [
        'script_id' => null,
        'command' => 'step_link_inactive:' . $code,
        'status' => 'step_link_inactive',
        'record_index' => null,
        'executed' => false,
        'updated_at' => gmdate('c'),
    ];
    if ($resolvedMethodId !== '') {
        $sent['command'] .= ':' . $resolvedMethodId;
    }
    $endpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions?session_code=eq.' . rawurlencode($sessionCode);
    $response = session_api_supabase_request('PATCH', $endpoint, $config['SUPABASE_SERVICE_ROLE_KEY'], $sent);
    session_api_respond([
        'ok' => false,
        'error' => 'Step-link is niet actief.',
        'step' => $response['curl_error'] === '' ? 'supabase_patch_inactive' : 'curl',
        'http_code' => $response['http_code'],
        'session_code' => $sessionCode,
        'code' => $code,
        'methodId' => $resolvedMethodId,
        'sent' => $sent,
        'supabase_response' => $response['body_json'] ?? $response['body'],
        'curl_error' => $response['curl_error'] ?: null,
    ], 409);
}

$scriptId = trim((string)($stepLink['scriptId'] ?? ''));
if ($scriptId === '') {
    session_api_error('Step-link heeft geen scriptId.', 500, ['code' => $code]);
}
$scriptMeta = session_api_load_blockly_script_meta($scriptId);

$recordIndex = null;
if (isset($stepLink['meta']['order']) && is_numeric($stepLink['meta']['order'])) {
    $recordIndex = (int)$stepLink['meta']['order'];
}

$sent = [
    'script_id' => $scriptId,
    'command' => $stepLinkCommand,
    'status' => 'pending',
    'record_index' => $recordIndex,
    'executed' => false,
    'updated_at' => gmdate('c'),
];

$endpoint = rtrim($config['SUPABASE_URL'], '/') . '/rest/v1/sessions?session_code=eq.' . rawurlencode($sessionCode);
$response = session_api_supabase_request('PATCH', $endpoint, $config['SUPABASE_SERVICE_ROLE_KEY'], $sent);
$status = $response['curl_error'] !== ''
    ? 502
    : ($response['http_code'] > 0 ? $response['http_code'] : 500);

session_api_respond([
    'ok' => $response['curl_error'] === '' && $response['http_code'] >= 200 && $response['http_code'] < 300,
    'step' => $response['curl_error'] === '' ? 'supabase_patch' : 'curl',
    'http_code' => $response['http_code'],
    'session_code' => $sessionCode,
    'code' => $code,
    'methodId' => $resolvedMethodId,
    'stepId' => (string)($stepLink['stepId'] ?? ''),
    'scriptId' => $scriptId,
    'meta' => $scriptMeta,
    'stepInputs' => is_array($stepLink['stepInputs'] ?? null) ? session_api_strip_deprecated_step_inputs($stepLink['stepInputs']) : new stdClass(),
    'sent' => $sent,
    'supabase_response' => $response['body_json'] ?? $response['body'],
    'curl_error' => $response['curl_error'] ?: null,
], $status);

function session_api_strip_deprecated_step_inputs(array $stepInputs): array
{
    unset($stepInputs['repeat']);
    unset($stepInputs['student_code']);
    return $stepInputs;
}

function session_api_load_blockly_script_meta(string $scriptId): array
{
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $scriptId);
    $safeId = trim((string)$safeId, '-_');
    if ($safeId === '') {
        return [];
    }

    foreach (session_api_blockly_script_dirs() as $dir) {
        $path = $dir . '/' . $safeId . '.json';
        if (!is_file($path)) {
            continue;
        }
        $content = json_decode((string)file_get_contents($path), true);
        if (!is_array($content)) {
            return [];
        }
        $meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
        return [
            'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
            'description' => isset($meta['description']) ? trim((string)$meta['description']) : trim((string)($content['description'] ?? '')),
            'instruction' => isset($meta['instruction']) ? trim((string)$meta['instruction']) : trim((string)($content['instruction'] ?? '')),
            'memo' => isset($meta['memo']) ? trim((string)$meta['memo']) : trim((string)($content['memo'] ?? '')),
            'prompt' => isset($meta['prompt']) ? trim((string)$meta['prompt']) : trim((string)($content['prompt'] ?? '')),
            'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
        ];
    }

    return [];
}

function session_api_blockly_script_dirs(): array
{
    return [
        dirname(__DIR__, 3) . '/braillestudio-data/data/blockly',
    ];
}

function session_api_read_request_input(): array
{
    $input = $_GET;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $raw = file_get_contents('php://input');
        $json = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                session_api_error('Ongeldige JSON body.', 400);
            }
            $json = $decoded;
        }
        $input = array_merge($input, $_POST, $json);
    }
    return $input;
}

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

function session_api_supabase_request(string $method, string $endpoint, string $serviceRoleKey, array $payload): array
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
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
