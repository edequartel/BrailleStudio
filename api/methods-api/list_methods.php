<?php
declare(strict_types=1);

require_once __DIR__ . '/_methods_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    methods_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    methods_method_not_allowed(['GET']);
}

methods_require_authentication();

$items = methods_load_all();
$status = methods_normalize_string($_GET['status'] ?? '');
$q = methods_normalize_string($_GET['q'] ?? '');
$compact = filter_var($_GET['compact'] ?? false, FILTER_VALIDATE_BOOLEAN);

$filtered = array_values(array_filter($items, static function (array $item) use ($status, $q): bool {
    if ($status !== '' && ($item['status'] ?? '') !== $status) {
        return false;
    }

    if ($q !== '') {
        $haystack = mb_strtolower(
            ($item['id'] ?? '') . ' ' .
            ($item['title'] ?? '') . ' ' .
            ($item['description'] ?? '') . ' ' .
            ($item['dataSource'] ?? '')
        );
        if (!str_contains($haystack, mb_strtolower($q))) {
            return false;
        }
    }

    return true;
}));

if (!$compact) {
    $lessonsByMethod = methods_load_lessons_by_method();
    $filtered = array_map(static function (array $item) use ($lessonsByMethod): array {
        return methods_enrich_with_lessons($item, $lessonsByMethod);
    }, $filtered);
}

methods_json_response([
    'ok' => true,
    'count' => count($filtered),
    'items' => $filtered
]);
