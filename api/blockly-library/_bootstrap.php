<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/authentication-api/_bootstrap.php';

function blockly_library_require_authentication(): array
{
    return authenticate_require_bearer_token_for_audiences([
        'braillestudio-api',
        'braillestudio-elevenlabs-api',
    ]);
}

function blockly_library_data_dir(): string
{
    return dirname(__DIR__) . '/blockly-library-data';
}

function blockly_library_normalize_id(string $id): string
{
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', trim($id));
    return trim((string)$safeId, '-_');
}
