<?php
declare(strict_types=1);

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
    $publicDir = dirname(__DIR__, 2) . '/lessons-data';
    if (is_dir($publicDir)) {
        return $publicDir;
    }
    return dirname(__DIR__) . '/lessons-data';
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
