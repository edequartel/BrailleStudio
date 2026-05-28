<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configPath = '/home3/kydjgrmy/private/supabase_config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'step' => 'config_file',
        'error' => 'Configbestand niet gevonden',
        'path' => $configPath
    ], JSON_PRETTY_PRINT);
    exit;
}

$config = require $configPath;

$projectUrl = trim((string)($config['SUPABASE_URL'] ?? ''));
$serviceKey = trim((string)($config['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));

if ($projectUrl === '' || $serviceKey === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'step' => 'config_values',
        'error' => 'SUPABASE_URL of SUPABASE_SERVICE_ROLE_KEY ontbreekt of is leeg',
        'has_url' => $projectUrl !== '',
        'has_service_key' => $serviceKey !== '',
        'config_keys' => array_keys($config)
    ], JSON_PRETTY_PRINT);
    exit;
}

$sessionCode = $_POST['session_code'] ?? $_GET['session_code'] ?? '';
$scriptId = $_POST['script_id'] ?? $_GET['script_id'] ?? '';

if ($sessionCode === '' || $scriptId === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'step' => 'input',
        'error' => 'session_code en script_id zijn verplicht',
        'example_get' => 'send-script.php?session_code=ABC123&script_id=/braillestudio/activities/test.json'
    ], JSON_PRETTY_PRINT);
    exit;
}

$projectUrl = rtrim($projectUrl, '/');

$url = $projectUrl
    . '/rest/v1/sessions?session_code=eq.'
    . rawurlencode($sessionCode);

$data = [
    'script_id' => $scriptId,
    'command' => 'load_script',
    'status' => 'pending',
    'executed' => false,
    'updated_at' => gmdate('c'),
];

$headers = [
    'apikey: ' . $serviceKey,
    'Authorization: Bearer ' . $serviceKey,
    'Content-Type: application/json',
    'Prefer: return=representation',
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HEADER => true,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'step' => 'curl',
        'error' => $error
    ], JSON_PRETTY_PRINT);
    exit;
}

$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
$decodedBody = json_decode($responseBody, true);

http_response_code($httpCode ?: 500);

echo json_encode([
    'ok' => $httpCode >= 200 && $httpCode < 300,
    'step' => 'supabase_patch',
    'http_code' => $httpCode,
    'session_code' => $sessionCode,
    'url' => $url,
    'service_key_present' => $serviceKey !== '',
    'service_key_length' => strlen($serviceKey),
    'sent' => $data,
    'response_body' => $decodedBody ?? $responseBody
], JSON_PRETTY_PRINT);