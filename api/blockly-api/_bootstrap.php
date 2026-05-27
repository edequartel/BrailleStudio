<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/json-guard.php';
braillestudio_json_guard_start();

require_once dirname(__DIR__) . '/authentication-api/_bootstrap.php';

function blockly_api_require_authentication(): array
{
    return authenticate_require_bearer_token_for_audiences([
        'braillestudio-api',
        'braillestudio-elevenlabs-api',
    ]);
}

function blockly_api_data_dir(): string
{
    $dirs = blockly_api_data_dirs();
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return $dirs[0];
}

function blockly_api_data_dirs(): array
{
    return array_values(array_unique([
        dirname(__DIR__, 2) . '/blockly-data',
        dirname(__DIR__) . '/blockly-data',
    ]));
}

function blockly_api_find_script_path(string $safeId): ?string
{
    foreach (blockly_api_data_dirs() as $dir) {
        $filePath = $dir . '/' . $safeId . '.json';
        if (is_file($filePath)) {
            return $filePath;
        }
    }
    return null;
}

function blockly_api_writable_data_dir(): ?string
{
    foreach (blockly_api_data_dirs() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    foreach (blockly_api_data_dirs() as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (@mkdir($dir, 0775, true) && is_writable($dir)) {
            return $dir;
        }
    }

    return null;
}

function blockly_api_json_error(string $message, int $status = 500, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
