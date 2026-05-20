<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function authenticate_json_response(array $payload, int $status = 200): never
{
    bs_auth_json_response($payload, $status);
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

function authenticate_require_bearer_token(string $audience): array
{
    return authenticate_require_bearer_token_for_audiences([$audience]);
}

function authenticate_require_bearer_token_for_audiences(array $audiences): array
{
    $user = bs_auth_require_login(['admin', 'docent'], 'json');
    return [
        'sub' => $user['email'] !== '' ? $user['email'] : $user['username'],
        'username' => $user['display'],
        'role' => $user['role'],
        'aud' => implode(',', array_map('strval', $audiences)),
        'auth' => 'php-auth-session',
    ];
}

function authenticate_issue_token(string $username, string $role, string $audience): array
{
    $now = time();
    $exp = $now + 3600;
    $payload = [
        'sub' => $username,
        'username' => $username,
        'role' => $role,
        'aud' => $audience,
        'iat' => $now,
        'exp' => $exp,
        'iss' => 'braillestudio-php-auth-session',
    ];
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $base64 = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $encodedHeader = $base64(json_encode($header, JSON_UNESCAPED_SLASHES));
    $encodedPayload = $base64(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, session_id(), true);

    return [
        'token' => $encodedHeader . '.' . $encodedPayload . '.' . $base64($signature),
        'claims' => $payload,
    ];
}

function authenticate_user(string $username, string $password): ?array
{
    try {
        $user = bs_auth_login_identifier($username, $password);
    } catch (Throwable $e) {
        return null;
    }

    if ($user === []) {
        return null;
    }

    return [
        'username' => $user['display'],
        'role' => $user['role'],
    ];
}
