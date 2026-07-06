<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

function bs_auth_project_root(): string
{
    return dirname(__DIR__);
}

function bs_auth_config_candidates(): array
{
    $paths = [];
    $envPath = trim((string)(getenv('BRAILLESTUDIO_AUTH_CONFIG') ?: ($_SERVER['BRAILLESTUDIO_AUTH_CONFIG'] ?? $_ENV['BRAILLESTUDIO_AUTH_CONFIG'] ?? '')));
    if ($envPath !== '') {
        $paths[] = $envPath;
    }

    $paths[] = __DIR__ . '/config.php';
    $paths[] = '/home3/kydjgrmy/braillestudio-auth/config.php';

    $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot !== '') {
        $paths[] = rtrim(dirname($documentRoot), '/') . '/braillestudio-auth/config.php';
        $paths[] = rtrim(dirname($documentRoot), '/') . '/private/braillestudio-auth.php';
    }

    $projectRoot = bs_auth_project_root();
    $paths[] = dirname($projectRoot) . '/braillestudio-auth/config.php';
    $paths[] = dirname($projectRoot) . '/private/braillestudio-auth.php';

    return array_values(array_unique($paths));
}

function bs_auth_config_path(): ?string
{
    foreach (bs_auth_config_candidates() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function bs_auth_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = bs_auth_config_path();
    if ($path === null) {
        throw new RuntimeException('BrailleStudio auth config not found. Place auth/config.example.php outside public_html and point BRAILLESTUDIO_AUTH_CONFIG to it if needed.');
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('BrailleStudio auth config must return an array.');
    }

    $config = $loaded;
    return $config;
}

function bs_auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = [];
    try {
        $config = bs_auth_config()['session'] ?? [];
    } catch (Throwable $e) {
        $config = [];
    }

    $secure = (bool)($config['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
    $sameSite = trim((string)($config['same_site'] ?? 'Lax')) ?: 'Lax';
    $name = trim((string)($config['name'] ?? 'BRAILLESTUDIO_AUTH')) ?: 'BRAILLESTUDIO_AUTH';

    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
    session_start();
}

function bs_auth_session_cookie_name(): string
{
    try {
        $name = trim((string)(bs_auth_config()['session']['name'] ?? 'BRAILLESTUDIO_AUTH'));
    } catch (Throwable $e) {
        $name = 'BRAILLESTUDIO_AUTH';
    }
    return $name !== '' ? $name : 'BRAILLESTUDIO_AUTH';
}

function bs_auth_require_vendor(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $autoload = bs_auth_project_root() . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Composer dependencies missing. Run: composer require delight-im/auth');
    }

    require_once $autoload;
    if (!class_exists(\Delight\Auth\Auth::class)) {
        throw new RuntimeException('delight-im/auth is not available from vendor/autoload.php.');
    }

    $loaded = true;
}

function bs_auth_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdoConfig = bs_auth_config()['pdo'] ?? [];
    $dsn = trim((string)($pdoConfig['dsn'] ?? ''));
    if ($dsn === '') {
        throw new RuntimeException('Auth PDO DSN is missing.');
    }

    $options = $pdoConfig['options'] ?? [];
    if (!is_array($options)) {
        $options = [];
    }
    $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
    $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
    $options[PDO::ATTR_EMULATE_PREPARES] = false;

    $pdo = new PDO(
        $dsn,
        (string)($pdoConfig['username'] ?? ''),
        (string)($pdoConfig['password'] ?? ''),
        $options
    );
    return $pdo;
}

function bs_auth(): \Delight\Auth\Auth
{
    static $auth = null;
    if ($auth instanceof \Delight\Auth\Auth) {
        return $auth;
    }

    bs_auth_start_session();
    bs_auth_require_vendor();

    $config = bs_auth_config()['auth'] ?? [];
    $ipAddress = trim((string)($config['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    $tablePrefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($config['table_prefix'] ?? ''));
    $throttling = (bool)($config['throttling'] ?? true);
    $sessionResyncInterval = (int)($config['session_resync_interval'] ?? 300);
    $schema = trim((string)($config['schema'] ?? ''));

    $auth = new \Delight\Auth\Auth(
        bs_auth_pdo(),
        $ipAddress !== '' ? $ipAddress : null,
        $tablePrefix,
        $throttling,
        $sessionResyncInterval > 0 ? $sessionResyncInterval : 300,
        $schema !== '' ? $schema : null
    );

    return $auth;
}

function bs_auth_role_map(): array
{
    bs_auth_require_vendor();
    return [
        'admin' => \Delight\Auth\Role::ADMIN,
        'developer' => \Delight\Auth\Role::DEVELOPER,
        'docent' => \Delight\Auth\Role::EDITOR,
        'leerling' => \Delight\Auth\Role::SUBSCRIBER,
    ];
}

function bs_auth_default_role(): string
{
    $role = trim((string)((bs_auth_config()['auth']['default_role'] ?? 'leerling')));
    return in_array($role, ['admin', 'developer', 'docent', 'leerling'], true) ? $role : 'leerling';
}

function bs_auth_admin_emails(): array
{
    $emails = bs_auth_config()['auth']['admin_emails'] ?? [];
    if (!is_array($emails)) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn ($email): string => strtolower(trim((string)$email)),
        $emails
    )));
}

function bs_auth_current_user(): ?array
{
    $auth = bs_auth();
    if (!$auth->isLoggedIn()) {
        return null;
    }

    $email = trim((string)($auth->getEmail() ?? ''));
    $username = trim((string)($auth->getUsername() ?? ''));
    $role = bs_auth_current_role();

    return [
        'id' => (int)$auth->getUserId(),
        'email' => $email,
        'username' => $username,
        'display' => $username !== '' ? $username : $email,
        'role' => $role,
    ];
}

function bs_auth_current_role(): string
{
    $auth = bs_auth();
    if (!$auth->isLoggedIn()) {
        return '';
    }

    $email = strtolower(trim((string)($auth->getEmail() ?? '')));
    if ($email !== '' && in_array($email, bs_auth_admin_emails(), true)) {
        return 'admin';
    }

    foreach (bs_auth_role_map() as $name => $mask) {
        if ($auth->hasRole($mask)) {
            return $name;
        }
    }

    return bs_auth_default_role();
}

function bs_auth_user_role_by_id(int $userId): string
{
    $admin = bs_auth()->admin();
    foreach (bs_auth_role_map() as $name => $mask) {
        if ($admin->doesUserHaveRole($userId, $mask)) {
            return $name;
        }
    }
    return bs_auth_default_role();
}

function bs_auth_set_user_role(int $userId, string $role): void
{
    $role = in_array($role, ['admin', 'developer', 'docent', 'leerling'], true) ? $role : bs_auth_default_role();
    $admin = bs_auth()->admin();
    foreach (bs_auth_role_map() as $mask) {
        if ($admin->doesUserHaveRole($userId, $mask)) {
            $admin->removeRoleForUserById($userId, $mask);
        }
    }
    $admin->addRoleForUserById($userId, bs_auth_role_map()[$role]);
}

function bs_auth_can_access(array $roles): bool
{
    $user = bs_auth_current_user();
    if ($user === null) {
        return false;
    }
    return $roles === [] || in_array($user['role'], $roles, true);
}

function bs_auth_base_url(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
    $base = preg_replace('~/(?:admin|api|lessonbuilder|authentication-api|session-api|blockly|tools|download|qr-api|phonemes-api|klanken)(?:/.*)?$~', '', $scriptDir) ?? '';
    return rtrim($base, '/');
}

function bs_auth_login_url(?string $returnTo = null): string
{
    $base = bs_auth_base_url();
    $url = ($base === '' ? '' : $base) . '/authentication.php';
    $returnTo = bs_auth_safe_return_to($returnTo ?? ($_SERVER['REQUEST_URI'] ?? ''));
    if ($returnTo !== '') {
        $url .= '?returnTo=' . rawurlencode($returnTo);
    }
    return $url;
}

function bs_auth_safe_return_to(?string $raw): string
{
    $value = trim((string)$raw);
    if ($value === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $value)) {
        $host = parse_url($value, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        return strtolower((string)$host) === strtolower((string)$currentHost) ? $value : '';
    }
    if ($value[0] !== '/') {
        $value = '/' . ltrim($value, '/');
    }
    $path = parse_url($value, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        $normalizedPath = preg_replace('~/+~', '/', $path) ?? $path;
        $segments = [];
        foreach (explode('/', $normalizedPath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $value = '/' . implode('/', $segments);
        $query = parse_url($raw, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $value .= '?' . $query;
        }
    }
    return $value;
}

function bs_auth_csrf_token(): string
{
    bs_auth_start_session();
    if (empty($_SESSION['braillestudio_csrf'])) {
        $_SESSION['braillestudio_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['braillestudio_csrf'];
}

function bs_auth_verify_csrf(?string $token): bool
{
    bs_auth_start_session();
    return is_string($token)
        && isset($_SESSION['braillestudio_csrf'])
        && hash_equals((string)$_SESSION['braillestudio_csrf'], $token);
}

function bs_auth_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bs_auth_public_session_api_files(): array
{
    return [
        'cleanup-sessions.php',
        'create-session.php',
        'delete-session.php',
        'join.php',
        'laptop.html',
        'laptop.php',
        'list-links.php',
        'load-session-script.php',
        'mark-session-open.php',
        'phone.html',
        'qr-image.php',
        'resolve.php',
        'runtime-state.php',
        'script-meta.php',
        'send-script.php',
        'send-step-link.php',
        'session-state.php',
        'start-session.php',
        'start.php',
        'status.php',
        'stop-step.php',
        'wait.php',
    ];
}

function bs_auth_is_public_session_api_script(string $script): bool
{
    $script = str_replace('\\', '/', $script);
    return strpos($script, '/api/session-api/') !== false
        && in_array(basename($script), bs_auth_public_session_api_files(), true);
}

function bs_auth_require_login(array $roles = [], string $mode = 'page'): array
{
    if (empty($_COOKIE[bs_auth_session_cookie_name()])) {
        if ($mode === 'json') {
            bs_auth_json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        header('Location: ' . bs_auth_login_url($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }

    try {
        $user = bs_auth_current_user();
    } catch (Throwable $e) {
        if ($mode === 'json') {
            bs_auth_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
        http_response_code(500);
        echo 'Authentication is not configured: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit;
    }

    if ($user === null) {
        if ($mode === 'json') {
            bs_auth_json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        header('Location: ' . bs_auth_login_url($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }

    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        if ($mode === 'json') {
            bs_auth_json_response(['ok' => false, 'error' => 'Insufficient role.'], 403);
        }
        http_response_code(403);
        echo 'Geen toegang voor deze rol.';
        exit;
    }

    return $user;
}

function bs_auth_require_when_direct_script(string $file, array $roles = ['admin', 'docent', 'leerling'], string $mode = 'json'): void
{
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $current = realpath($file);
    if ($script !== false && $current !== false && $script === $current) {
        bs_auth_require_login($roles, $mode);
    }
}

function bs_auth_login_identifier(string $identifier, string $password, bool $remember = false): array
{
    $auth = bs_auth();
    $duration = $remember ? (int)(60 * 60 * 24 * 30) : null;

    try {
        $auth->login($identifier, $password, $duration);
    } catch (\Delight\Auth\InvalidEmailException $e) {
        $auth->loginWithUsername($identifier, $password, $duration);
    }

    return bs_auth_current_user() ?? [];
}

function bs_auth_logout(): void
{
    $auth = bs_auth();
    if ($auth->isLoggedIn()) {
        $auth->logOut();
    }
    $auth->destroySession();
}

require_once bs_auth_project_root() . '/includes/language.php';
