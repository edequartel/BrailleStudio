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
