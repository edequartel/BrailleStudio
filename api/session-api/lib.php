<?php
declare(strict_types=1);

/*
 * Minimal file-based session + step resolver backend for BrailleStudio.
 * Designed for shared hosting: no database, no composer, JSON files only.
 */

const SESSION_API_SESSION_TTL_SECONDS = 28800; // 8 hours
const SESSION_API_SESSION_ID_BYTES = 16;
const SESSION_API_STEP_CODE_BYTES = 4;
const SESSION_API_CLEANUP_SCAN_LIMIT = 100;

function session_api_project_root(): string
{
    return dirname(__DIR__);
}

function session_api_data_root(): string
{
    return session_api_project_root() . '/data';
}

function session_api_sessions_dir(): string
{
    return session_api_data_root() . '/sessions';
}

function session_api_step_links_dir(): string
{
    return session_api_data_root() . '/step-links';
}

function session_api_send_common_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function session_api_handle_options(): void
{
    session_api_send_common_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function session_api_respond(array $payload, int $status = 200): never
{
    session_api_send_common_headers();
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function session_api_error(string $message, int $status = 400, array $extra = []): never
{
    session_api_respond(array_merge([
        'ok' => false,
        'error' => $message,
    ], $extra), $status);
}

function session_api_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        session_api_error('Method not allowed', 405);
    }
}

function session_api_read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        session_api_error('Invalid JSON body', 400);
    }

    return $decoded;
}

function session_api_ensure_dir(string $dir): void
{
    if (is_dir($dir)) {
        return;
    }
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        session_api_error('Could not create storage directory', 500, ['path' => $dir]);
    }
}

function session_api_ensure_storage_dirs(): void
{
    session_api_ensure_dir(session_api_data_root());
    session_api_ensure_dir(session_api_sessions_dir());
    session_api_ensure_dir(session_api_step_links_dir());
    session_api_cleanup_expired_sessions();
}

function session_api_normalize_token(string $value, string $fieldName, int $minLength = 3, int $maxLength = 64): string
{
    $value = trim($value);
    $pattern = '/\A[a-zA-Z0-9][a-zA-Z0-9_-]{' . max(0, $minLength - 1) . ',' . max(0, $maxLength - 1) . '}\z/';
    if ($value === '' || !preg_match($pattern, $value)) {
        session_api_error(
            sprintf(
                'Invalid %s. Use only letters, numbers, underscore, hyphen; length %d-%d.',
                $fieldName,
                $minLength,
                $maxLength
            ),
            400
        );
    }
    return $value;
}

function session_api_sessions_file(string $sessionId): string
{
    $safeId = session_api_normalize_token($sessionId, 'sessionId', 16, 64);
    return session_api_sessions_dir() . '/' . $safeId . '.json';
}

function session_api_step_link_file(string $code): string
{
    $safeCode = session_api_normalize_token($code, 'code', 3, 64);
    return session_api_step_links_dir() . '/' . $safeCode . '.json';
}

function session_api_read_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        session_api_error('Could not open JSON file', 500, ['path' => $path]);
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            session_api_error('Could not lock JSON file for reading', 500, ['path' => $path]);
        }

        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if ($contents === false || trim($contents) === '') {
        return null;
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        session_api_error('Invalid JSON file contents', 500, ['path' => $path]);
    }

    return $decoded;
}

function session_api_write_json_file(string $path, array $payload): void
{
    session_api_ensure_dir(dirname($path));

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        session_api_error('Could not encode JSON payload', 500);
    }

    $tmpPath = $path . '.tmp';
    $handle = fopen($tmpPath, 'cb');
    if ($handle === false) {
        session_api_error('Could not open temp JSON file for writing', 500, ['path' => $tmpPath]);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            session_api_error('Could not lock temp JSON file for writing', 500, ['path' => $tmpPath]);
        }

        ftruncate($handle, 0);
        rewind($handle);
        $written = fwrite($handle, $json);
        if ($written === false || $written < strlen($json)) {
            session_api_error('Could not write JSON payload', 500, ['path' => $tmpPath]);
        }

        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if (!rename($tmpPath, $path)) {
        @unlink($tmpPath);
        session_api_error('Could not finalize JSON file write', 500, ['path' => $path]);
    }
}

function session_api_update_session_file(string $sessionId, callable $mutator): array
{
    $path = session_api_sessions_file($sessionId);
    session_api_ensure_dir(dirname($path));

    $handle = fopen($path, 'c+b');
    if ($handle === false) {
        session_api_error('Could not open session file', 500, ['path' => $path]);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            session_api_error('Could not lock session file', 500, ['path' => $path]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false || trim($contents) === '') {
            flock($handle, LOCK_UN);
            session_api_error('Session not found', 404, ['sessionId' => $sessionId]);
        }

        $session = json_decode($contents, true);
        if (!is_array($session)) {
            flock($handle, LOCK_UN);
            session_api_error('Invalid session JSON', 500, ['path' => $path]);
        }

        if (session_api_is_expired((string)($session['expiresAt'] ?? ''))) {
            flock($handle, LOCK_UN);
            session_api_error('Session expired', 410, [
                'sessionId' => $sessionId,
                'expiresAt' => $session['expiresAt'] ?? null,
            ]);
        }

        $updated = $mutator($session);
        if (!is_array($updated)) {
            flock($handle, LOCK_UN);
            session_api_error('Session mutator must return an array', 500);
        }

        $json = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            flock($handle, LOCK_UN);
            session_api_error('Could not encode updated session JSON', 500);
        }

        ftruncate($handle, 0);
        rewind($handle);
        $written = fwrite($handle, $json);
        if ($written === false || $written < strlen($json)) {
            flock($handle, LOCK_UN);
            session_api_error('Could not write updated session JSON', 500, ['path' => $path]);
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        return $updated;
    } finally {
        fclose($handle);
    }
}

function session_api_generate_session_id(): string
{
    return 'sess_' . bin2hex(random_bytes(SESSION_API_SESSION_ID_BYTES));
}

function session_api_generate_step_code(): string
{
    return strtolower(bin2hex(random_bytes(SESSION_API_STEP_CODE_BYTES)));
}

function session_api_now_iso(): string
{
    return gmdate('c');
}

function session_api_expiry_iso(int $ttlSeconds = SESSION_API_SESSION_TTL_SECONDS): string
{
    return gmdate('c', time() + max(60, $ttlSeconds));
}

function session_api_is_expired(?string $expiresAt): bool
{
    if (!is_string($expiresAt) || trim($expiresAt) === '') {
        return false;
    }

    $timestamp = strtotime($expiresAt);
    if ($timestamp === false) {
        return false;
    }

    return $timestamp < time();
}

function session_api_load_session_or_fail(string $sessionId): array
{
    $path = session_api_sessions_file($sessionId);
    $session = session_api_read_json_file($path);
    if (!is_array($session)) {
        session_api_error('Session not found', 404);
    }

    if (session_api_is_expired((string)($session['expiresAt'] ?? ''))) {
        session_api_error('Session expired', 410, [
            'sessionId' => $sessionId,
            'expiresAt' => $session['expiresAt'] ?? null,
        ]);
    }

    return $session;
}

function session_api_cleanup_expired_sessions(int $scanLimit = SESSION_API_CLEANUP_SCAN_LIMIT): void
{
    $dir = session_api_sessions_dir();
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/sess_*.json');
    if (!is_array($files) || !$files) {
        return;
    }

    $scanned = 0;
    foreach ($files as $path) {
        if ($scanned >= $scanLimit) {
            break;
        }
        $scanned++;

        if (!is_string($path) || !is_file($path)) {
            continue;
        }

        $session = session_api_read_json_file($path);
        if (!is_array($session)) {
            continue;
        }

        if (!session_api_is_expired((string)($session['expiresAt'] ?? ''))) {
            continue;
        }

        @unlink($path);
    }
}

function session_api_normalize_runtime_state(?string $value): string
{
    $state = trim((string)($value ?? ''));
    if ($state === '') {
        session_api_error('Missing runtime state', 400);
    }
    if (!in_array($state, ['idle', 'active'], true)) {
        session_api_error('Invalid runtime state. Use idle or active.', 400);
    }
    return $state;
}

function session_api_build_runtime_state(array $session): array
{
    $runtime = is_array($session['runtime'] ?? null) ? $session['runtime'] : [];
    return [
        'state' => in_array(($runtime['state'] ?? 'idle'), ['idle', 'active'], true) ? (string)$runtime['state'] : 'idle',
        'updatedAt' => (string)($runtime['updatedAt'] ?? ''),
        'code' => (string)($runtime['code'] ?? ''),
        'scriptId' => (string)($runtime['scriptId'] ?? ''),
        'stepId' => (string)($runtime['stepId'] ?? ''),
    ];
}

function session_api_set_runtime_state(array &$session, string $state, array $payload = []): array
{
    $runtime = [
        'state' => $state,
        'updatedAt' => session_api_now_iso(),
        'code' => '',
        'scriptId' => '',
        'stepId' => '',
    ];

    if ($state === 'active') {
        $runtime['code'] = trim((string)($payload['code'] ?? ''));
        $runtime['scriptId'] = trim((string)($payload['scriptId'] ?? ''));
        $runtime['stepId'] = trim((string)($payload['stepId'] ?? ''));
    }

    $session['runtime'] = $runtime;
    return $runtime;
}

function session_api_load_step_link_or_fail(string $code): array
{
    $path = session_api_step_link_file($code);
    $record = session_api_read_json_file($path);
    if (!is_array($record)) {
        session_api_error('Step code not found', 404, ['code' => $code]);
    }

    if (!($record['active'] ?? false)) {
        session_api_error('Step code is inactive', 409, ['code' => $code]);
    }

    $scriptId = trim((string)($record['scriptId'] ?? ''));
    $stepId = trim((string)($record['stepId'] ?? ''));
    if ($scriptId === '' || $stepId === '') {
        session_api_error('Step code record is incomplete', 500, ['code' => $code]);
    }

    return $record;
}

function session_api_list_json_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $items = [];
    $entries = scandir($dir);
    if ($entries === false) {
        session_api_error('Could not read storage directory', 500, ['path' => $dir]);
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!str_ends_with($entry, '.json')) {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_file($path)) {
            $items[] = $path;
        }
    }

    sort($items, SORT_STRING);
    return $items;
}
