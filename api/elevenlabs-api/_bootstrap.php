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

function elevenlabs_secret_candidates(): array
{
    $dir = __DIR__;
    return [
        getenv('ELEVENLABS_API_KEY') ?: null,
        $_SERVER['ELEVENLABS_API_KEY'] ?? null,
        $_ENV['ELEVENLABS_API_KEY'] ?? null,
        @is_file(dirname($dir, 4) . '/elevenlabs_api_key.txt') ? file_get_contents(dirname($dir, 4) . '/elevenlabs_api_key.txt') : null,
        @is_file(dirname($dir, 4) . '/private/elevenlabs_api_key.txt') ? file_get_contents(dirname($dir, 4) . '/private/elevenlabs_api_key.txt') : null,
        @is_file(dirname($dir, 4) . '/secrets/elevenlabs_api_key.txt') ? file_get_contents(dirname($dir, 4) . '/secrets/elevenlabs_api_key.txt') : null,
        @is_file(dirname($dir, 3) . '/elevenlabs_api_key.txt') ? file_get_contents(dirname($dir, 3) . '/elevenlabs_api_key.txt') : null,
        @is_file(dirname($dir, 3) . '/private/elevenlabs_api_key.txt') ? file_get_contents(dirname($dir, 3) . '/private/elevenlabs_api_key.txt') : null,
        @is_file(dirname($dir, 3) . '/secrets/elevenlabs_api_key.txt') ? file_get_contents(dirname($dir, 3) . '/secrets/elevenlabs_api_key.txt') : null,
    ];
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
        'error' => 'ElevenLabs API key not configured. Set ELEVENLABS_API_KEY or place elevenlabs_api_key.txt outside public webroot.'
    ], 500);
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
