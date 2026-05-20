<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Method not allowed. Use POST.'
    ], 405);
}

$input = authenticate_get_json_input();
$identifier = trim((string)($input['username'] ?? $input['email'] ?? ''));
$password = (string)($input['password'] ?? '');
$audience = trim((string)($input['audience'] ?? 'braillestudio-api')) ?: 'braillestudio-api';
$remember = (bool)($input['remember'] ?? false);

if ($identifier === '' || $password === '') {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Email/username and password are required.'
    ], 400);
}

try {
    $user = bs_auth_login_identifier($identifier, $password, $remember);
} catch (\Delight\Auth\InvalidEmailException|\Delight\Auth\InvalidPasswordException|\Delight\Auth\UnknownUsernameException|\Delight\Auth\AmbiguousUsernameException $e) {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Invalid username or password.'
    ], 401);
} catch (\Delight\Auth\EmailNotVerifiedException $e) {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Email not verified.'
    ], 403);
} catch (\Delight\Auth\TooManyRequestsException $e) {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Too many login attempts. Try again later.'
    ], 429);
}

$issued = authenticate_issue_token($user['display'], $user['role'], $audience);

authenticate_json_response([
    'ok' => true,
    'token' => $issued['token'],
    'token_type' => 'Session',
    'audience' => $audience,
    'expires_in' => max(0, (int)$issued['claims']['exp'] - time()),
    'expires_at' => gmdate('c', (int)$issued['claims']['exp']),
    'user' => $user,
]);
