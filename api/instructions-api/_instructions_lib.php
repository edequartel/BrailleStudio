<?php
declare(strict_types=1);

/*
BrailleStudio Instructions Library

Shared helper functions for:
- loading instructions JSON
- saving instructions JSON
- finding records
- validating/normalizing records
- JSON responses

Expected file structure:
  /public_html/braillestudio/
    /api/_instructions_lib.php
    /data/instructions.json
*/

function instructions_data_file(): string {
    return dirname(__DIR__) . '/data/instructions.json';
}

function ensure_instructions_file_exists(): void {
    $file = instructions_data_file();
    $dir = dirname($file);

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!file_exists($file)) {
        file_put_contents($file, "[]", LOCK_EX);
    }
}

function load_instructions(): array {
    ensure_instructions_file_exists();

    $file = instructions_data_file();
    $json = @file_get_contents($file);

    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [];
    }

    $items = [];
    foreach ($data as $item) {
        if (is_array($item)) {
            $items[] = normalize_instruction($item);
        }
    }

    return $items;
}

function save_instructions(array $items): bool {
    ensure_instructions_file_exists();

    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $normalized[] = normalize_instruction($item);
        }
    }

    usort($normalized, function ($a, $b) {
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    $json = json_encode(
        array_values($normalized),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    return file_put_contents(instructions_data_file(), $json, LOCK_EX) !== false;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function instruction_exists(string $id, array $items): bool {
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            return true;
        }
    }
    return false;
}

function find_instruction_by_id(string $id, array $items): ?array {
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

function find_instruction_index_by_id(string $id, array $items): int {
    foreach ($items as $index => $item) {
        if (($item['id'] ?? '') === $id) {
            return (int)$index;
        }
    }
    return -1;
}

function normalize_string(mixed $value): string {
    if (is_string($value)) {
        return trim($value);
    }
    if (is_numeric($value)) {
        return trim((string)$value);
    }
    return '';
}

function normalize_string_array(mixed $value): array {
    if (!is_array($value)) {
        return [];
    }

    $out = [];
    foreach ($value as $v) {
        $s = normalize_string($v);
        if ($s !== '') {
            $out[] = $s;
        }
    }

    return array_values(array_unique($out));
}

function normalize_tags(mixed $value): array {
    if (is_string($value)) {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn($x) => $x !== '');
        return array_values(array_unique($parts));
    }

    return normalize_string_array($value);
}

function normalize_playlist(mixed $value): array {
    if (is_string($value)) {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn($x) => $x !== '');
        return array_values($lines);
    }

    return normalize_string_array($value);
}

function normalize_instruction(array $item): array {
    $audioMode = normalize_string($item['audioMode'] ?? 'single_mp3');
    if (!in_array($audioMode, ['single_mp3', 'playlist'], true)) {
        $audioMode = 'single_mp3';
    }

    $status = normalize_string($item['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'active', 'archived'], true)) {
        $status = 'draft';
    }

    $normalized = [
        'id' => normalize_string($item['id'] ?? ''),
        'title' => normalize_string($item['title'] ?? ''),
        'text' => normalize_string($item['text'] ?? ''),
        'audioMode' => $audioMode,
        'audioRef' => normalize_string($item['audioRef'] ?? ''),
        'audioPlaylist' => normalize_playlist($item['audioPlaylist'] ?? []),
        'tags' => normalize_tags($item['tags'] ?? []),
        'status' => $status,
        'notes' => normalize_string($item['notes'] ?? ''),
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if ($normalized['audioMode'] === 'single_mp3') {
        $normalized['audioPlaylist'] = [];
    }

    if ($normalized['audioMode'] === 'playlist') {
        $normalized['audioRef'] = '';
    }

    return $normalized;
}

function validate_instruction(array $item, array $existingItems = [], bool $isUpdate = false): array {
    $errors = [];

    $id = normalize_string($item['id'] ?? '');
    $title = normalize_string($item['title'] ?? '');
    $audioMode = normalize_string($item['audioMode'] ?? '');

    if ($id === '') {
        $errors[] = 'id is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
        $errors[] = 'id may only contain letters, numbers, underscore, and dash';
    }

    if ($title === '') {
        $errors[] = 'title is required';
    }

    if ($audioMode === '') {
        $errors[] = 'audioMode is required';
    } elseif (!in_array($audioMode, ['single_mp3', 'playlist'], true)) {
        $errors[] = 'audioMode must be single_mp3 or playlist';
    }

    $normalized = normalize_instruction($item);

    if ($normalized['audioMode'] === 'single_mp3' && $normalized['audioRef'] === '') {
        $errors[] = 'audioRef is required when audioMode is single_mp3';
    }

    if ($normalized['audioMode'] === 'playlist' && count($normalized['audioPlaylist']) === 0) {
        $errors[] = 'audioPlaylist must contain at least one item when audioMode is playlist';
    }

    if (!$isUpdate && $id !== '' && instruction_exists($id, $existingItems)) {
        $errors[] = 'id already exists';
    }

    return $errors;
}

function json_response(array $payload, int $statusCode = 200): never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function method_not_allowed(array $allowed): never {
    header('Allow: ' . implode(', ', $allowed));
    json_response([
        'ok' => false,
        'error' => 'Method not allowed'
    ], 405);
}