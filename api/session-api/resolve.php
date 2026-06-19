<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_require_post();
session_api_ensure_storage_dirs();

$input = session_api_read_json_input();
$sessionId = session_api_normalize_token((string)($input['sessionId'] ?? ''), 'sessionId', 16, 64);
$code = session_api_normalize_token((string)($input['code'] ?? ''), 'code', 3, 64);
$methodIdRaw = trim((string)($input['methodId'] ?? ''));
$methodId = $methodIdRaw !== '' ? session_api_normalize_token($methodIdRaw, 'methodId', 3, 128) : '';

$stepLink = session_api_load_step_link_or_fail($code, $methodId);
$scriptId = (string)$stepLink['scriptId'];
$scriptMeta = session_api_load_blockly_script_meta($scriptId);

$resolvedAt = session_api_now_iso();
$resolvedPayload = [
    'code' => $code,
    'methodId' => (string)($stepLink['methodId'] ?? $methodId),
    'stepId' => (string)$stepLink['stepId'],
    'scriptId' => $scriptId,
    'meta' => $scriptMeta,
    'stepInputs' => is_array($stepLink['stepInputs'] ?? null) ? session_api_strip_deprecated_step_inputs($stepLink['stepInputs']) : new stdClass(),
    'resolvedAt' => $resolvedAt,
];

$accepted = true;
$ignoredResponse = null;
$session = session_api_update_session_file($sessionId, function (array $session) use ($sessionId, $code, $resolvedAt, $resolvedPayload, &$accepted, &$ignoredResponse): array {
    if (!($session['active'] ?? true)) {
        session_api_error('Session is inactive', 409, ['sessionId' => $sessionId]);
    }

    $runtime = session_api_build_runtime_state($session);
    if (($runtime['state'] ?? 'idle') === 'active') {
        $accepted = false;
        $ignoredResponse = [
            'ok' => true,
            'accepted' => false,
            'ignored' => true,
            'reason' => 'session_busy',
            'message' => 'Session has an active step.',
            'sessionId' => $sessionId,
            'runtime' => $runtime,
        ];
        return $session;
    }

    $session['lastResolvedAt'] = $resolvedAt;
    $session['lastResolvedCode'] = $code;
    $session['lastResolved'] = $resolvedPayload;
    session_api_set_runtime_state($session, 'active', $resolvedPayload);
    return $session;
});

if (!$accepted) {
    session_api_respond($ignoredResponse ?? [
        'ok' => true,
        'accepted' => false,
        'ignored' => true,
        'reason' => 'session_busy',
        'message' => 'Session has an active step.',
        'sessionId' => $sessionId,
        'runtime' => session_api_build_runtime_state($session),
    ]);
}

session_api_respond([
    'ok' => true,
    'accepted' => true,
    'sessionId' => $sessionId,
    ...$resolvedPayload,
]);

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
