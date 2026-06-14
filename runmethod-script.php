<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['ok' => false, 'error' => 'Method not allowed. Use GET.'], 405);
}

$methodId = normalize_id((string)($_GET['method'] ?? $_GET['methodId'] ?? ''));
$scriptId = normalize_id((string)($_GET['id'] ?? ''));
if ($methodId === '') {
    respond(['ok' => false, 'error' => 'Missing method id.'], 400);
}
if (!is_file(data_dir('methods') . '/' . $methodId . '.json')) {
    respond(['ok' => false, 'error' => 'Method not found.'], 404);
}

$referencedIds = referenced_script_ids($methodId);
if ($scriptId === '') {
    $items = [];
    foreach ($referencedIds as $id) {
        $content = read_json(data_dir('blockly') . '/' . $id . '.json');
        if (!is_array($content)) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'title' => trim((string)($content['title'] ?? '')),
            'updatedAt' => trim((string)($content['updatedAt'] ?? '')),
            'meta' => normalize_meta($content),
        ];
    }
    respond(['ok' => true, 'items' => $items]);
}

if (!in_array($scriptId, $referencedIds, true)) {
    respond(['ok' => false, 'error' => 'Script is not part of this method.'], 403);
}
$content = read_json(data_dir('blockly') . '/' . $scriptId . '.json');
if (!is_array($content)) {
    respond(['ok' => false, 'error' => 'Script not found.'], 404);
}
$content['meta'] = normalize_meta($content);
respond($content);

function normalize_id(string $value): string
{
    return trim((string)preg_replace('/[^a-zA-Z0-9_-]/', '-', trim($value)), '-_');
}

function data_dir(string $section): string
{
    return dirname(__DIR__) . '/braillestudio-data/data/' . trim($section, '/');
}

function read_json(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function referenced_script_ids(string $methodId): array
{
    $ids = [];
    foreach (glob(data_dir('lessons') . '/*.json') ?: [] as $path) {
        $lesson = read_json($path);
        if (!is_array($lesson)) {
            continue;
        }
        $method = is_array($lesson['method'] ?? null) ? $lesson['method'] : [];
        $lessonMethodId = normalize_id((string)($lesson['methodId'] ?? ($method['id'] ?? '')));
        if ($lessonMethodId !== $methodId) {
            continue;
        }
        foreach (is_array($lesson['steps'] ?? null) ? $lesson['steps'] : [] as $step) {
            $id = normalize_id((string)($step['id'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
    }
    return array_keys($ids);
}

function normalize_meta(array $content): array
{
    $meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
    return [
        'title' => trim((string)($meta['title'] ?? ($content['title'] ?? ''))),
        'description' => trim((string)($meta['description'] ?? '')),
        'instruction' => trim((string)($meta['instruction'] ?? '')),
        'prompt' => trim((string)($meta['prompt'] ?? '')),
        'status' => trim((string)($meta['status'] ?? 'draft')),
    ];
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
