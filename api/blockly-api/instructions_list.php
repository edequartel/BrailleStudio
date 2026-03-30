<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once dirname(__DIR__) . '/instructions-api/_instructions_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$items = load_instructions();

$q = normalize_string($_GET['q'] ?? '');
$status = normalize_string($_GET['status'] ?? '');
$tag = normalize_string($_GET['tag'] ?? '');

$filtered = array_filter($items, function (array $item) use ($q, $status, $tag) {
    if ($q !== '') {
        $haystack = mb_strtolower(
            ($item['id'] ?? '') . ' ' .
            ($item['title'] ?? '') . ' ' .
            ($item['text'] ?? '') . ' ' .
            implode(' ', $item['tags'] ?? [])
        );
        if (!str_contains($haystack, mb_strtolower($q))) {
            return false;
        }
    }

    if ($status !== '' && ($item['status'] ?? '') !== $status) {
        return false;
    }

    if ($tag !== '' && !in_array($tag, $item['tags'] ?? [], true)) {
        return false;
    }

    return true;
});

$summary = array_map(function (array $item) {
    return [
        'id' => $item['id'],
        'title' => $item['title'],
        'audioMode' => $item['audioMode'],
        'audioRef' => $item['audioRef'],
        'audioPlaylistCount' => count($item['audioPlaylist'] ?? []),
        'tags' => $item['tags'],
        'status' => $item['status'],
        'updatedAt' => $item['updatedAt'],
    ];
}, array_values($filtered));

echo json_encode([
    'ok' => true,
    'count' => count($summary),
    'items' => $summary
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
