<?php
header('Content-Type: application/json; charset=utf-8');

// Load private config
$config = require '/home/youraccount/private/eleven_config.php';

$apiKey  = $config['api_key'];
$voiceId = $config['voice_id'];
$modelId = $config['model_id'];

// Read JSON input
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$text = trim($data['text'] ?? '');
$customFileName = trim($data['file_name'] ?? '');

// Validate
if ($text === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'No text provided.'
    ]);
    exit;
}

if (mb_strlen($text) > 3000) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Text is too long.'
    ]);
    exit;
}

// Generate a safe filename
if ($customFileName !== '') {
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($customFileName, PATHINFO_FILENAME));
    $fileName = $baseName . '.mp3';
} else {
    $fileName = sha1($text . '|' . $voiceId . '|' . $modelId) . '.mp3';
}

// File locations
$outputDir = __DIR__ . '/audio/generated/';
$outputPath = $outputDir . $fileName;
$publicUrl = 'https://yourdomain.com/audio/generated/' . rawurlencode($fileName);

// Create folder if missing
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Could not create output directory.'
        ]);
        exit;
    }
}

// Optional cache: if same file already exists, reuse it
if (file_exists($outputPath)) {
    echo json_encode([
        'ok' => true,
        'cached' => true,
        'url' => $publicUrl,
        'file_name' => $fileName
    ]);
    exit;
}

// ElevenLabs endpoint
$url = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voiceId) . '?output_format=mp3_44100_128';

// Request body
$payload = json_encode([
    'text' => $text,
    'model_id' => $modelId
], JSON_UNESCAPED_UNICODE);

// cURL request
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: audio/mpeg',
        'Content-Type: application/json',
        'xi-api-key: ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 120,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle errors
if ($response === false || $httpCode < 200 || $httpCode >= 300) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to generate speech.',
        'http_code' => $httpCode,
        'details' => $curlError
    ]);
    exit;
}

// Save MP3
if (file_put_contents($outputPath, $response) === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not save MP3 file.'
    ]);
    exit;
}

// Success
echo json_encode([
    'ok' => true,
    'cached' => false,
    'url' => $publicUrl,
    'file_name' => $fileName
]);