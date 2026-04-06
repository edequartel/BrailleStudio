<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    elevenlabs_json_response([
        'ok' => false,
        'error' => 'Method not allowed. Use POST.'
    ], 405);
}

$input = elevenlabs_get_json_input();
$text = elevenlabs_normalize_text($input['text'] ?? '');
$voiceId = elevenlabs_normalize_text($input['voice_id'] ?? '');
$modelId = elevenlabs_normalize_text($input['model_id'] ?? 'eleven_multilingual_v2');

if ($text === '') {
    elevenlabs_json_response([
        'ok' => false,
        'error' => 'Missing text.'
    ], 400);
}

if ($voiceId === '') {
    elevenlabs_json_response([
        'ok' => false,
        'error' => 'Missing voice_id.'
    ], 400);
}

$payload = [
    'text' => $text,
    'model_id' => $modelId,
];

$result = elevenlabs_request(
    'POST',
    '/v1/text-to-speech/' . rawurlencode($voiceId),
    $payload,
    ['Accept: audio/mpeg']
);

if ($result['status'] < 200 || $result['status'] >= 300) {
    $decoded = json_decode($result['body'], true);
    elevenlabs_json_response([
        'ok' => false,
        'error' => is_array($decoded) ? ($decoded['detail']['message'] ?? $decoded['message'] ?? 'ElevenLabs request failed.') : 'ElevenLabs request failed.',
        'status' => $result['status'],
        'raw' => $result['body'],
    ], $result['status']);
}

$safeVoice = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $voiceId);
$safeVoice = trim((string)$safeVoice, '-_') ?: 'voice';

header('Content-Type: audio/mpeg');
header('Content-Disposition: inline; filename="elevenlabs-' . $safeVoice . '.mp3"');
header('Cache-Control: no-store');
echo $result['body'];
exit;
