<?php
declare(strict_types=1);

function session_api_supabase_config_path(): string
{
    $envPath = trim((string)(getenv('BRAILLESTUDIO_SUPABASE_CONFIG') ?: ($_SERVER['BRAILLESTUDIO_SUPABASE_CONFIG'] ?? $_ENV['BRAILLESTUDIO_SUPABASE_CONFIG'] ?? '')));
    $candidates = [];
    if ($envPath !== '') {
        $candidates[] = $envPath;
    }

    $candidates[] = __DIR__ . '/supabase_config.php';
    $candidates[] = dirname(__DIR__, 2) . '/broadcast/supabase_config.php';
    $candidates[] = '/home3/kydjgrmy/private/supabase_config.php';

    foreach (array_values(array_unique($candidates)) as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return $candidates[0];
}
