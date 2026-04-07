<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function elevenlabs_slugify_filename(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
    $value = trim((string)$value, '-_.');
    return $value !== '' ? $value : 'audio';
}

function elevenlabs_public_root_dir(): string
{
    foreach (elevenlabs_base_dirs() as $baseDir) {
        $candidate = $baseDir . '/public_html';
        if (is_dir($candidate)) {
            return $candidate;
        }
    }

    return dirname(__DIR__, 2);
}

function elevenlabs_resolve_save_destination(string $relativeDir, string $fileName): array
{
    $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
    if ($relativeDir === '') {
        $relativeDir = 'braillestudio/sounds/nl/instructions';
    }

    if (!preg_match('#^braillestudio/sounds(?:/[a-z0-9_-]+)+$#i', $relativeDir)) {
        elevenlabs_json_response([
            'ok' => false,
            'error' => 'Invalid save_path. Use a path under braillestudio/sounds/.',
        ], 400);
    }

    $safeFileName = elevenlabs_slugify_filename($fileName);
    if (!str_ends_with($safeFileName, '.mp3')) {
        $safeFileName .= '.mp3';
    }

    $publicRoot = rtrim(elevenlabs_public_root_dir(), '/');
    $targetDir = $publicRoot . '/' . $relativeDir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        elevenlabs_json_response([
            'ok' => false,
            'error' => 'Could not create destination directory.',
            'target_dir' => $targetDir,
        ], 500);
    }

    $realPublicRoot = realpath($publicRoot) ?: $publicRoot;
    $realTargetDir = realpath($targetDir) ?: $targetDir;
    if (strpos($realTargetDir, $realPublicRoot) !== 0) {
        elevenlabs_json_response([
            'ok' => false,
            'error' => 'Resolved destination is outside public root.',
        ], 400);
    }

    return [
        'target_dir' => $targetDir,
        'target_file' => $targetDir . '/' . $safeFileName,
        'public_path' => '/' . $relativeDir . '/' . $safeFileName,
    ];
}

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
$savePath = elevenlabs_normalize_text($input['save_path'] ?? '');
$fileName = elevenlabs_normalize_text($input['file_name'] ?? '');
$saveToFile = !empty($input['save_to_file']);

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

if ($saveToFile) {
    $fallbackName = $fileName !== '' ? $fileName : ('instruction-' . $safeVoice);
    $destination = elevenlabs_resolve_save_destination($savePath, $fallbackName);
    if (file_put_contents($destination['target_file'], $result['body']) === false) {
        elevenlabs_json_response([
            'ok' => false,
            'error' => 'Could not save MP3 file.',
            'target_file' => $destination['target_file'],
        ], 500);
    }

    elevenlabs_json_response([
        'ok' => true,
        'saved' => true,
        'file_name' => basename($destination['target_file']),
        'public_path' => $destination['public_path'],
        'public_url' => ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . $destination['public_path'],
    ]);
}

header('Content-Type: audio/mpeg');
header('Content-Disposition: inline; filename="elevenlabs-' . $safeVoice . '.mp3"');
header('Cache-Control: no-store');
echo $result['body'];
exit;
