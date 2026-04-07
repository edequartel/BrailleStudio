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
$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');
$audience = trim((string)($input['audience'] ?? 'braillestudio-elevenlabs-api'));

if ($audience === '') {
    $audience = 'braillestudio-elevenlabs-api';
}

$user = authenticate_user($username, $password);
if (!is_array($user)) {
    authenticate_json_response([
        'ok' => false,
        'error' => 'Invalid username or password.'
    ], 401);
}

$issued = authenticate_issue_token($user['username'], $user['role'], $audience);

authenticate_json_response([
    'ok' => true,
    'token' => $issued['token'],
    'token_type' => 'Bearer',
    'audience' => $audience,
    'expires_in' => max(0, (int)$issued['claims']['exp'] - time()),
    'expires_at' => gmdate('c', (int)$issued['claims']['exp']),
    'user' => [
        'username' => $user['username'],
        'role' => $user['role'],
    ],
]);
