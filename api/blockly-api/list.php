<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

blockly_api_require_authentication();

function blockly_api_walk_blocks($block, &$externalNames): void
{
    if (!is_array($block)) {
        return;
    }

    $type = isset($block['type']) ? (string)$block['type'] : '';
    if (in_array($type, ['external_variable_get', 'external_variable_set', 'external_variable_exists', 'external_property_get'], true)) {
        $name = isset($block['fields']['VAR']) ? trim((string)$block['fields']['VAR']) : '';
        if ($name !== '') {
            $externalNames[$name] = true;
        }
    }

    $inputs = isset($block['inputs']) && is_array($block['inputs']) ? $block['inputs'] : [];
    foreach ($inputs as $input) {
        if (!is_array($input)) {
            continue;
        }
        if (isset($input['block'])) {
            blockly_api_walk_blocks($input['block'], $externalNames);
        }
        if (isset($input['shadow'])) {
            blockly_api_walk_blocks($input['shadow'], $externalNames);
        }
    }

    if (isset($block['next']['block'])) {
        blockly_api_walk_blocks($block['next']['block'], $externalNames);
    }
}

function blockly_api_external_variable_names(array $content): array
{
    $externalNames = [];
    $blocks = $content['blockly']['blocks']['blocks'] ?? [];
    if (is_array($blocks)) {
        foreach ($blocks as $block) {
            blockly_api_walk_blocks($block, $externalNames);
        }
    }
    return array_keys($externalNames);
}

function blockly_api_has_legacy_variable_format(array $content): bool
{
    $externalNames = blockly_api_external_variable_names($content);
    if (count($externalNames) === 0) {
        return false;
    }

    $scriptVariables = $content['blockly']['scriptVariables'] ?? null;
    if (!is_array($scriptVariables)) {
        return true;
    }

    $metadataNames = [];
    foreach ($scriptVariables as $variable) {
        if (!is_array($variable)) {
            continue;
        }
        $name = isset($variable['name']) ? trim((string)$variable['name']) : '';
        $scope = isset($variable['scope']) ? strtolower(trim((string)$variable['scope'])) : '';
        if ($name !== '' && $scope === 'external') {
            $metadataNames[$name] = true;
            $id = isset($variable['id']) ? trim((string)$variable['id']) : '';
            $nameSlug = strtolower(trim(preg_replace('/[^a-z0-9_]+/', '_', $name), '_'));
            $expectedPrefix = 'external_' . $nameSlug . '_';
            if ($nameSlug !== '' && $id !== '' && strpos($id, $expectedPrefix) !== 0) {
                return true;
            }
        }
    }

    foreach ($externalNames as $name) {
        if (!isset($metadataNames[$name])) {
            return true;
        }
    }

    return false;
}

$localDataDir = blockly_api_data_dir();
$manifest = is_dir($localDataDir)
    ? blockly_api_build_manifest_from_dir($localDataDir)
    : blockly_api_load_remote_manifest();
if (!is_array($manifest)) {
    blockly_api_json_error('Blockly data directory not found', 404, [
        'source' => blockly_api_data_dir(),
    ]);
}

$rawItems = is_array($manifest['items'] ?? null) ? $manifest['items'] : $manifest;
$items = [];
$seen = [];

foreach ($rawItems as $item) {
    if (is_string($item) || is_numeric($item)) {
        $item = ['id' => (string)$item];
    }
    if (!is_array($item)) {
        continue;
    }
    $id = trim((string)($item['id'] ?? pathinfo((string)($item['filename'] ?? ''), PATHINFO_FILENAME)));
    $safeId = trim((string)preg_replace('/[^a-zA-Z0-9_-]/', '-', $id), '-_');
    if ($safeId === '' || isset($seen[$safeId])) {
        continue;
    }
    $seen[$safeId] = true;

    $content = $item;
    if (!isset($content['blockly'])) {
        $scriptContent = blockly_api_load_local_script($safeId);
        if (!is_array($scriptContent)) {
            continue;
        }
        $content = array_replace_recursive($scriptContent, $item);
    }

    $meta = array_key_exists('meta', $content) && is_array($content['meta']) ? $content['meta'] : [];
    $normalizedMeta = [
        'title' => isset($meta['title']) ? trim((string)$meta['title']) : trim((string)($content['title'] ?? '')),
        'description' => isset($meta['description']) ? trim((string)$meta['description']) : trim((string)($content['description'] ?? '')),
        'instruction' => isset($meta['instruction']) ? trim((string)$meta['instruction']) : trim((string)($content['instruction'] ?? '')),
        'memo' => isset($meta['memo']) ? trim((string)$meta['memo']) : trim((string)($content['memo'] ?? '')),
        'prompt' => isset($meta['prompt']) ? trim((string)$meta['prompt']) : trim((string)($content['prompt'] ?? '')),
        'status' => isset($meta['status']) ? trim((string)$meta['status']) : 'draft',
    ];

    $items[] = [
        'id' => $safeId,
        'title' => $content['title'] ?? $normalizedMeta['title'],
        'updatedAt' => $content['updatedAt'] ?? '',
        'meta' => $normalizedMeta,
        'filename' => basename((string)($content['filename'] ?? ($safeId . '.json'))),
        'url' => blockly_api_remote_script_url($safeId),
        'legacyVariableFormat' => isset($content['blockly']) ? blockly_api_has_legacy_variable_format($content) : false,
    ];
}

usort($items, function ($a, $b) {
    return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
});

echo json_encode([
    'ok' => true,
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
