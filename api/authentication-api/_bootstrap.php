<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function authenticate_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function authenticate_get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function authenticate_load_config(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $config = require $path;
    return is_array($config) ? $config : null;
}

function authenticate_base_dirs(): array
{
    $dir = __DIR__;
    $candidates = [
        dirname($dir, 2),
        dirname($dir, 3),
        dirname($dir, 4),
    ];

    $baseDirs = [];
    foreach ($candidates as $path) {
        $normalized = rtrim((string)$path, '/');
        if ($normalized === '' || in_array($normalized, $baseDirs, true)) {
            continue;
        }
        $baseDirs[] = $normalized;
    }

    return $baseDirs;
}

function authenticate_config_candidates(): array
{
    $paths = [];
    foreach (authenticate_base_dirs() as $baseDir) {
        $paths[] = $baseDir . '/private/authentication_auth.php';
        $paths[] = $baseDir . '/secrets/authentication_auth.php';
        $paths[] = $baseDir . '/private/authenticate_api_auth.php';
        $paths[] = $baseDir . '/secrets/authenticate_api_auth.php';
        $paths[] = $baseDir . '/private/elevenlabs_auth.php';
        $paths[] = $baseDir . '/secrets/elevenlabs_auth.php';
    }
    return $paths;
}

function authenticate_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    foreach (authenticate_config_candidates() as $path) {
        $loaded = authenticate_load_config($path);
        if (is_array($loaded)) {
            $config = $loaded;
            return $config;
        }
    }

    $usersJson = trim((string)(getenv('AUTHENTICATION_USERS_JSON') ?: ($_SERVER['AUTHENTICATION_USERS_JSON'] ?? $_ENV['AUTHENTICATION_USERS_JSON'] ?? '')));
    $secret = trim((string)(getenv('AUTHENTICATION_JWT_SECRET') ?: ($_SERVER['AUTHENTICATION_JWT_SECRET'] ?? $_ENV['AUTHENTICATION_JWT_SECRET'] ?? '')));
    $ttl = (int)(getenv('AUTHENTICATION_TOKEN_TTL') ?: ($_SERVER['AUTHENTICATION_TOKEN_TTL'] ?? $_ENV['AUTHENTICATION_TOKEN_TTL'] ?? 3600));
    $users = json_decode($usersJson, true);

    $config = [
        'jwt_secret' => $secret,
        'token_ttl' => $ttl > 0 ? $ttl : 3600,
        'users' => is_array($users) ? $users : [],
    ];
    return $config;
}

function authenticate_secret(): string
{
    $secret = trim((string)(authenticate_config()['jwt_secret'] ?? ''));
    if ($secret === '') {
        authenticate_json_response([
            'ok' => false,
            'error' => 'Authentication secret not configured. Create private/authentication_auth.php.',
        ], 500);
    }
    return $secret;
}

function authenticate_users(): array
{
    $users = authenticate_config()['users'] ?? [];
    return is_array($users) ? $users : [];
}

function authenticate_token_ttl(): int
{
    $ttl = (int)(authenticate_config()['token_ttl'] ?? 3600);
    return $ttl > 0 ? $ttl : 3600;
}

function authenticate_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function authenticate_base64url_decode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    return (string)base64_decode(strtr($value, '-_', '+/'), true);
}

function authenticate_issue_token(string $username, string $role, string $audience): array
{
    $now = time();
    $exp = $now + authenticate_token_ttl();
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'sub' => $username,
        'role' => $role,
        'iat' => $now,
        'exp' => $exp,
        'iss' => 'braillestudio-authentication',
        'aud' => $audience,
    ];
    $encodedHeader = authenticate_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $encodedPayload = authenticate_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, authenticate_secret(), true);
    return [
        'token' => $encodedHeader . '.' . $encodedPayload . '.' . authenticate_base64url_encode($signature),
        'claims' => $payload,
    ];
}

function authenticate_extract_bearer_token(): string
{
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = (string)$_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['Authorization'])) {
        $header = (string)$_SERVER['Authorization'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $matches)) {
        return trim((string)$matches[1]);
    }
    return '';
}

function authenticate_verify_token(string $token, string $audience): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $expectedSignature = authenticate_base64url_encode(
        hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, authenticate_secret(), true)
    );

    if (!hash_equals($expectedSignature, $encodedSignature)) {
        return null;
    }

    $payload = json_decode(authenticate_base64url_decode($encodedPayload), true);
    if (!is_array($payload)) {
        return null;
    }

    $exp = (int)($payload['exp'] ?? 0);
    if ($exp <= 0 || $exp < time()) {
        return null;
    }

    if (trim((string)($payload['aud'] ?? '')) !== $audience) {
        return null;
    }

    return $payload;
}

function authenticate_verify_token_for_audiences(string $token, array $audiences): ?array
{
    $normalizedAudiences = [];
    foreach ($audiences as $audience) {
        $value = trim((string)$audience);
        if ($value !== '' && !in_array($value, $normalizedAudiences, true)) {
            $normalizedAudiences[] = $value;
        }
    }

    foreach ($normalizedAudiences as $audience) {
        $claims = authenticate_verify_token($token, $audience);
        if (is_array($claims)) {
            return $claims;
        }
    }

    return null;
}

function authenticate_require_bearer_token(string $audience): array
{
    $token = authenticate_extract_bearer_token();
    if ($token === '') {
        header('WWW-Authenticate: Bearer realm="' . $audience . '"');
        authenticate_json_response([
            'ok' => false,
            'error' => 'Missing bearer token.',
        ], 401);
    }

    $claims = authenticate_verify_token($token, $audience);
    if (!is_array($claims)) {
        header('WWW-Authenticate: Bearer realm="' . $audience . '", error="invalid_token"');
        authenticate_json_response([
            'ok' => false,
            'error' => 'Invalid or expired bearer token.',
        ], 401);
    }

    return $claims;
}

function authenticate_require_bearer_token_for_audiences(array $audiences): array
{
    $normalizedAudiences = [];
    foreach ($audiences as $audience) {
        $value = trim((string)$audience);
        if ($value !== '' && !in_array($value, $normalizedAudiences, true)) {
            $normalizedAudiences[] = $value;
        }
    }

    if ($normalizedAudiences === []) {
        authenticate_json_response([
            'ok' => false,
            'error' => 'No authentication audience configured.',
        ], 500);
    }

    $token = authenticate_extract_bearer_token();
    if ($token === '') {
        header('WWW-Authenticate: Bearer realm="' . implode(',', $normalizedAudiences) . '"');
        authenticate_json_response([
            'ok' => false,
            'error' => 'Missing bearer token.',
        ], 401);
    }

    $claims = authenticate_verify_token_for_audiences($token, $normalizedAudiences);
    if (!is_array($claims)) {
        header('WWW-Authenticate: Bearer realm="' . implode(',', $normalizedAudiences) . '", error="invalid_token"');
        authenticate_json_response([
            'ok' => false,
            'error' => 'Invalid or expired bearer token.',
        ], 401);
    }

    return $claims;
}

function authenticate_user(string $username, string $password): ?array
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return null;
    }

    foreach (authenticate_users() as $user) {
        if (!is_array($user)) {
            continue;
        }
        $expectedUsername = trim((string)($user['username'] ?? ''));
        $passwordHash = (string)($user['password_hash'] ?? '');
        if ($expectedUsername === '' || $passwordHash === '') {
            continue;
        }
        if (!hash_equals($expectedUsername, $username)) {
            continue;
        }
        if (!password_verify($password, $passwordHash)) {
            return null;
        }
        return [
            'username' => $expectedUsername,
            'role' => trim((string)($user['role'] ?? 'user')) ?: 'user',
        ];
    }

    return null;
}
