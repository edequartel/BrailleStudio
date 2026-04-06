<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$result = elevenlabs_request('GET', '/v1/voices');
$decoded = json_decode($result['body'], true);

if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($decoded)) {
    elevenlabs_json_response([
        'ok' => false,
        'error' => 'Failed to load voices from ElevenLabs.',
        'status' => $result['status'],
        'raw' => $result['body'],
    ], $result['status'] >= 400 ? $result['status'] : 502);
}

$voices = [];
foreach (($decoded['voices'] ?? []) as $voice) {
    if (!is_array($voice)) {
        continue;
    }
    $voices[] = [
        'voice_id' => trim((string)($voice['voice_id'] ?? '')),
        'name' => trim((string)($voice['name'] ?? '')),
        'category' => trim((string)($voice['category'] ?? '')),
        'labels' => is_array($voice['labels'] ?? null) ? $voice['labels'] : [],
        'preview_url' => trim((string)($voice['preview_url'] ?? '')),
    ];
}

elevenlabs_json_response([
    'ok' => true,
    'voices' => $voices,
]);
