<?php
declare(strict_types=1);

require_once __DIR__ . '/_instructions_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    method_not_allowed(['GET']);
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

json_response([
    'ok' => true,
    'count' => count($summary),
    'items' => $summary
]);
