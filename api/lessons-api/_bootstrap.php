<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/json-guard.php';
braillestudio_json_guard_start();

require_once dirname(__DIR__) . '/authentication-api/_bootstrap.php';

function lessons_api_require_authentication(): array
{
    return authenticate_require_bearer_token_for_audiences([
        'braillestudio-api',
        'braillestudio-elevenlabs-api',
    ]);
}

function lessons_api_data_dir(): string
{
    foreach (lessons_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return lessons_api_data_dirs()[0];
}

function lessons_api_data_dirs(): array
{
    return array_values(array_unique([
        dirname(__DIR__, 2) . '/lessons-data',
        dirname(__DIR__) . '/lessons-data',
    ]));
}

function lessons_api_find_lesson_path(string $safeId): ?string
{
    foreach (lessons_api_data_dirs() as $dir) {
        $filePath = $dir . '/' . $safeId . '.json';
        if (is_file($filePath)) {
            return $filePath;
        }
    }
    return null;
}

function lessons_api_writable_data_dir(): ?string
{
    foreach (lessons_api_data_dirs() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    foreach (lessons_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (@mkdir($dir, 0775, true) && is_writable($dir)) {
            return $dir;
        }
    }

    return null;
}

function lessons_api_json_error(string $message, int $status = 500, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lessons_api_step_script_id(array $row): string
{
    foreach (['id', 'scriptId', 'blocklyScriptId', 'script_id', 'blockly_script_id'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $script = is_array($row['script'] ?? null) ? $row['script'] : [];
    return trim((string)($script['id'] ?? ''));
}

function lessons_api_clean_step_script_id(array $row): string
{
    $id = lessons_api_step_script_id($row);
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
    return trim((string)$id, '-_');
}
