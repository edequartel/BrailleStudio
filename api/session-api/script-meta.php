<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    session_api_error('Method not allowed', 405);
}

$input = $_GET;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $input = array_merge($input, session_api_read_json_input());
}

$scriptId = session_api_normalize_token((string)($input['id'] ?? ($input['scriptId'] ?? '')), 'scriptId', 3, 128);
$path = session_api_find_blockly_script_path($scriptId);
if ($path === null) {
    session_api_error('Script not found', 404, ['scriptId' => $scriptId]);
}

$content = json_decode((string)file_get_contents($path), true);
if (!is_array($content)) {
    session_api_error('Invalid stored script JSON', 500, ['scriptId' => $scriptId]);
}

$meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
$normalizedMeta = [
    'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
    'description' => isset($meta['description']) ? trim((string)$meta['description']) : trim((string)($content['description'] ?? '')),
    'instruction' => isset($meta['instruction']) ? trim((string)$meta['instruction']) : trim((string)($content['instruction'] ?? '')),
    'memo' => isset($meta['memo']) ? trim((string)$meta['memo']) : trim((string)($content['memo'] ?? '')),
    'prompt' => isset($meta['prompt']) ? trim((string)$meta['prompt']) : trim((string)($content['prompt'] ?? '')),
    'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
];

session_api_respond([
    'ok' => true,
    'id' => $scriptId,
    'title' => trim((string)($content['title'] ?? $normalizedMeta['title'])),
    'updatedAt' => trim((string)($content['updatedAt'] ?? '')),
    'meta' => $normalizedMeta,
]);

function session_api_find_blockly_script_path(string $safeId): ?string
{
    foreach (session_api_blockly_script_dirs() as $dir) {
        $path = $dir . '/' . $safeId . '.json';
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function session_api_blockly_script_dirs(): array
{
    return [
        dirname(__DIR__, 3) . '/braillestudio-data/data/blockly',
    ];
}
