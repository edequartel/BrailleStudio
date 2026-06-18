<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
elevenlabs_require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    elevenlabs_json_response([
        'ok' => false,
        'error' => 'Method not allowed. Use GET.'
    ], 405);
}

$voiceId = trim((string)($_GET['voice_id'] ?? ''));
if ($voiceId === '' || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $voiceId)) {
    elevenlabs_json_response([
        'ok' => false,
        'error' => 'Invalid voice_id.'
    ], 400);
}

$result = elevenlabs_request(
    'GET',
    '/v1/voices/' . rawurlencode($voiceId)
);

$voice = json_decode($result['body'], true);
if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($voice)) {
    elevenlabs_json_response([
        'ok' => false,
        'error' => is_array($voice)
            ? (string)($voice['detail']['message'] ?? $voice['message'] ?? 'Could not retrieve voice information.')
            : 'Could not retrieve voice information.',
        'status' => $result['status'],
    ], $result['status'] >= 400 ? $result['status'] : 502);
}

$labels = is_array($voice['labels'] ?? null) ? $voice['labels'] : [];
$verifiedLanguages = [];
foreach (($voice['verified_languages'] ?? []) as $language) {
    if (!is_array($language)) {
        continue;
    }
    $verifiedLanguages[] = [
        'language' => trim((string)($language['language'] ?? '')),
        'locale' => trim((string)($language['locale'] ?? '')),
        'accent' => trim((string)($language['accent'] ?? '')),
        'model_id' => trim((string)($language['model_id'] ?? '')),
    ];
}

elevenlabs_json_response([
    'ok' => true,
    'voice' => [
        'voice_id' => trim((string)($voice['voice_id'] ?? $voiceId)),
        'name' => trim((string)($voice['name'] ?? '')),
        'category' => trim((string)($voice['category'] ?? '')),
        'description' => trim((string)($voice['description'] ?? '')),
        'labels' => $labels,
        'verified_languages' => $verifiedLanguages,
        'high_quality_base_model_ids' => array_values(array_filter(
            array_map('strval', is_array($voice['high_quality_base_model_ids'] ?? null)
                ? $voice['high_quality_base_model_ids']
                : [])
        )),
        'preview_url' => trim((string)($voice['preview_url'] ?? '')),
        'is_owner' => (bool)($voice['is_owner'] ?? false),
        'is_legacy' => (bool)($voice['is_legacy'] ?? false),
    ],
]);
