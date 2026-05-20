<?php
declare(strict_types=1);

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
    $publicDir = dirname(__DIR__, 2) . '/blockly-data';
    if (is_dir($publicDir)) {
        return $publicDir;
    }
    return dirname(__DIR__) . '/blockly-data';
}
