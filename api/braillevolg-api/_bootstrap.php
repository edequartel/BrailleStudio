<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$dbDir = __DIR__ . '/data';
$dbFile = $dbDir . '/braille_leerlingen.sqlite';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

$db = new SQLite3($dbFile);
$db->exec('PRAGMA foreign_keys = ON');

$db->exec("
    CREATE TABLE IF NOT EXISTS leerlingen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        naam TEXT NOT NULL,
        groep_klas TEXT NOT NULL DEFAULT '',
        niveau TEXT NOT NULL DEFAULT '',
        doelstellingen TEXT NOT NULL DEFAULT '',
        letters_beheerst INTEGER NOT NULL DEFAULT 0,
        woorden_beheerst INTEGER NOT NULL DEFAULT 0,
        leessnelheid TEXT NOT NULL DEFAULT '',
        laatste_toetsdatum TEXT NOT NULL DEFAULT '',
        opmerkingen TEXT NOT NULL DEFAULT '',
        deleted_at TEXT NOT NULL DEFAULT '',
        deleted_by TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

$leerlingColumns = [];
$leerlingInfo = $db->query("PRAGMA table_info(leerlingen)");
if ($leerlingInfo) {
    while ($column = $leerlingInfo->fetchArray(SQLITE3_ASSOC)) {
        $leerlingColumns[] = (string)($column['name'] ?? '');
    }
}
if (!in_array('doelstellingen', $leerlingColumns, true)) {
    $db->exec("ALTER TABLE leerlingen ADD COLUMN doelstellingen TEXT NOT NULL DEFAULT ''");
}
if (!in_array('deleted_at', $leerlingColumns, true)) {
    $db->exec("ALTER TABLE leerlingen ADD COLUMN deleted_at TEXT NOT NULL DEFAULT ''");
}
if (!in_array('deleted_by', $leerlingColumns, true)) {
    $db->exec("ALTER TABLE leerlingen ADD COLUMN deleted_by TEXT NOT NULL DEFAULT ''");
}

$db->exec("
    CREATE TRIGGER IF NOT EXISTS leerlingen_updated_at
    AFTER UPDATE ON leerlingen
    FOR EACH ROW
    BEGIN
        UPDATE leerlingen
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.id;
    END;
");

$db->exec("
    CREATE TABLE IF NOT EXISTS voortgang (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        leerling_id INTEGER NOT NULL,
        datum TEXT NOT NULL DEFAULT '',
        onderdeel TEXT NOT NULL DEFAULT '',
        auteur TEXT NOT NULL DEFAULT '',
        letters_beheerst INTEGER NOT NULL DEFAULT 0,
        woorden_beheerst INTEGER NOT NULL DEFAULT 0,
        leessnelheid TEXT NOT NULL DEFAULT '',
        notitie TEXT NOT NULL DEFAULT '',
        deleted_at TEXT NOT NULL DEFAULT '',
        deleted_by TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (leerling_id) REFERENCES leerlingen(id) ON DELETE CASCADE
    )
");

$voortgangColumns = [];
$voortgangInfo = $db->query("PRAGMA table_info(voortgang)");
if ($voortgangInfo) {
    while ($column = $voortgangInfo->fetchArray(SQLITE3_ASSOC)) {
        $voortgangColumns[] = (string)($column['name'] ?? '');
    }
}
if (!in_array('auteur', $voortgangColumns, true)) {
    $db->exec("ALTER TABLE voortgang ADD COLUMN auteur TEXT NOT NULL DEFAULT ''");
}
if (!in_array('deleted_at', $voortgangColumns, true)) {
    $db->exec("ALTER TABLE voortgang ADD COLUMN deleted_at TEXT NOT NULL DEFAULT ''");
}
if (!in_array('deleted_by', $voortgangColumns, true)) {
    $db->exec("ALTER TABLE voortgang ADD COLUMN deleted_by TEXT NOT NULL DEFAULT ''");
}

$db->exec("
    CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor TEXT NOT NULL DEFAULT '',
        actor_role TEXT NOT NULL DEFAULT '',
        entity_type TEXT NOT NULL DEFAULT '',
        entity_id INTEGER NOT NULL DEFAULT 0,
        action TEXT NOT NULL DEFAULT '',
        details_json TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TRIGGER IF NOT EXISTS voortgang_updated_at
    AFTER UPDATE ON voortgang
    FOR EACH ROW
    BEGIN
        UPDATE voortgang
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.id;
    END;
");

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function redirect_with_query(string $page, array $params = []): never
{
    $query = http_build_query(array_filter($params, static fn($value) => $value !== '' && $value !== null));
    header('Location: ' . $page . ($query !== '' ? '?' . $query : ''));
    exit;
}

function fetch_leerling_by_id(SQLite3 $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM leerlingen WHERE id = :id AND deleted_at = '' LIMIT 1");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    return $row ?: null;
}

function fetch_voortgang_by_id(SQLite3 $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM voortgang WHERE id = :id AND deleted_at = '' LIMIT 1");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    return $row ?: null;
}

function sync_leerling_latest_voortgang(SQLite3 $db, int $leerlingId): void
{
    if ($leerlingId <= 0) {
        return;
    }

    $stmt = $db->prepare("
        SELECT datum, letters_beheerst, woorden_beheerst, leessnelheid
        FROM voortgang
        WHERE leerling_id = :leerling_id
          AND deleted_at = ''
        ORDER BY datum DESC, id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':leerling_id', $leerlingId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $latest = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;

    $stmt = $db->prepare("
        UPDATE leerlingen
        SET
            letters_beheerst = :letters_beheerst,
            woorden_beheerst = :woorden_beheerst,
            leessnelheid = :leessnelheid,
            laatste_toetsdatum = :laatste_toetsdatum
        WHERE id = :id
    ");
    $stmt->bindValue(':id', $leerlingId, SQLITE3_INTEGER);
    $stmt->bindValue(':letters_beheerst', (int)($latest['letters_beheerst'] ?? 0), SQLITE3_INTEGER);
    $stmt->bindValue(':woorden_beheerst', (int)($latest['woorden_beheerst'] ?? 0), SQLITE3_INTEGER);
    $stmt->bindValue(':leessnelheid', (string)($latest['leessnelheid'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':laatste_toetsdatum', (string)($latest['datum'] ?? ''), SQLITE3_TEXT);
    $stmt->execute();
}

function render_page_start(string $title, string $intro = ''): void
{
    $safeTitle = e($title);
    $safeIntro = e($intro);
    $currentUser = e((string)($_SESSION['braillevolg_auth_user'] ?? ''));
    $currentRole = e((string)($_SESSION['braillevolg_auth_role'] ?? ''));
    $authTools = '';
    if ($currentUser !== '') {
        $authTools = <<<HTML
        <div class="hero-tools">
          <span class="hero-user">Ingelogd als {$currentUser} ({$currentRole})</span>
          <a class="btn btn-secondary" href="logout.php">Uitloggen</a>
        </div>
HTML;
    }
    echo <<<HTML
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>{$safeTitle}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg:#eef4ff;
      --card:#ffffff;
      --text:#172033;
      --muted:#5f6c85;
      --line:#d8e1ee;
      --blue:#2563eb;
      --blue-dark:#1d4ed8;
      --slate:#64748b;
      --slate-dark:#475569;
      --amber:#f59e0b;
      --amber-dark:#d97706;
      --red:#dc2626;
      --red-dark:#b91c1c;
      --green:#16a34a;
      --shadow:0 14px 36px rgba(15,23,42,0.08);
    }
    * { box-sizing:border-box; }
    body {
      font-family: Arial, sans-serif;
      max-width: 1320px;
      margin: 0 auto;
      padding: 24px 16px 40px;
      background: linear-gradient(180deg, var(--bg) 0%, #f8fbff 220px);
      color: var(--text);
    }
    h1,h2,h3,p { margin-top: 0; }
    .hero {
      background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #60a5fa 100%);
      color:#fff;
      border-radius:24px;
      padding:28px;
      box-shadow: var(--shadow);
      margin-bottom:20px;
    }
    .hero-header {
      display:flex;
      justify-content:space-between;
      gap:16px;
      align-items:flex-start;
    }
    .hero h1 { margin-bottom:8px; }
    .hero p { color:rgba(255,255,255,.84); max-width:760px; margin-bottom:0; }
    .hero-tools {
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }
    .hero-user {
      display:inline-flex;
      align-items:center;
      min-height:48px;
      padding:0 14px;
      border-radius:999px;
      background:rgba(255,255,255,.14);
      color:#fff;
      font-size:14px;
      font-weight:700;
      white-space:nowrap;
    }
    .layout { display:grid; gap:20px; }
    .grid-2 { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr); gap:20px; align-items:start; }
    .card {
      background: var(--card);
      border:1px solid var(--line);
      border-radius:20px;
      padding:22px;
      box-shadow: var(--shadow);
    }
    .card.soft { background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); }
    .card-header {
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      margin-bottom:16px;
    }
    .card-subtitle { color:var(--muted); font-size:14px; margin:0; }
    .section-title {
      font-size:12px;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:#34518a;
      margin:8px 0 -6px;
    }
    label {
      display:block;
      font-weight:700;
      margin-top:12px;
      margin-bottom:6px;
      color:#24324a;
    }
    input[type="text"], input[type="password"], input[type="number"], input[type="date"], select, textarea {
      width:100%;
      padding:12px 14px;
      border:1px solid #cbd5e1;
      border-radius:12px;
      font:inherit;
      background:#fff;
    }
    input[type="text"]:focus, input[type="password"]:focus, input[type="number"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
      outline:none;
      border-color:#60a5fa;
      box-shadow:0 0 0 4px rgba(96,165,250,.18);
    }
    input[type="text"], input[type="password"], input[type="number"], input[type="date"], select {
      min-height:48px;
      height:48px;
      line-height:24px;
      -webkit-appearance:none;
      appearance:none;
    }
    textarea { min-height:120px; resize:vertical; }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    button, .btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border:none;
      padding:12px 16px;
      min-height:48px;
      height:48px;
      border-radius:12px;
      text-decoration:none;
      cursor:pointer;
      font-size:15px;
      line-height:1;
      font-family: Arial, sans-serif;
      font-weight:700;
      white-space:nowrap;
      transition:transform .15s ease, background .15s ease;
    }
    button { background:var(--blue); color:#fff; }
    button:hover { background:var(--blue-dark); transform:translateY(-1px); }
    .btn-secondary { background:var(--slate); color:#fff; }
    .btn-secondary:hover { background:var(--slate-dark); }
    .btn-edit { background:var(--amber); color:#fff; }
    .btn-edit:hover { background:var(--amber-dark); }
    .btn-delete { background:var(--red); color:#fff; }
    .btn-delete:hover { background:var(--red-dark); }
    .btn-success { background:var(--green); color:#fff; }
    .melding, .fout {
      padding:14px 16px;
      border-radius:12px;
      margin-bottom:16px;
    }
    .melding { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .fout { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    table { width:100%; border-collapse:collapse; }
    th, td { border-bottom:1px solid #e8eef6; padding:12px 10px; text-align:left; vertical-align:top; }
    th { background:#f8fbff; color:var(--muted); font-size:13px; text-transform:uppercase; letter-spacing:.04em; }
    .notes-col { width: 340px; min-width: 340px; }
    .notes-text {
      display:-webkit-box;
      -webkit-box-orient:vertical;
      -webkit-line-clamp:3;
      line-clamp:3;
      overflow:hidden;
      white-space:normal;
      word-break:break-word;
      overflow-wrap:anywhere;
      line-height:1.45;
      max-height:4.35em;
    }
    .summary-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-top:16px; }
    .summary-tile { border:1px solid var(--line); border-radius:14px; background:#f8fbff; padding:14px; }
    .summary-tile strong { display:block; font-size:24px; line-height:1; margin-bottom:6px; }
    .summary-tile span { color:var(--muted); font-size:13px; }
    .empty { color:var(--muted); padding:28px 0; text-align:center; }
    .inline-delete { display:inline; }
    .table-actions .btn, .table-actions button { margin-bottom:8px; }
    .table-actions { text-align:right; white-space:nowrap; }
    .auth-shell {
      min-height:calc(100vh - 180px);
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .auth-card {
      width:min(100%, 460px);
      padding:28px;
      background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .auth-card .card-header {
      margin-bottom:20px;
    }
    .is-hidden { display:none; }
    .site-footer {
      margin-top:28px;
      padding:18px 4px 0;
      border-top:1px solid var(--line);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      color:var(--muted);
      font-size:14px;
      font-weight:700;
    }
    .site-footer img {
      display:block;
      height:32px;
      width:auto;
    }
    .auth-note {
      margin-top:14px;
      padding:14px 16px;
      border-radius:14px;
      background:#eff6ff;
      border:1px solid #bfdbfe;
      color:#1e3a8a;
      font-size:14px;
    }
    @media (max-width: 860px) {
      .grid-2, .row, .summary-grid { grid-template-columns:1fr; }
      .hero-header { flex-direction:column; }
      .auth-shell { min-height:auto; }
      .site-footer { flex-direction:column; align-items:flex-start; }
    }
  </style>
</head>
<body>
  <section class="hero">
    <div class="hero-header">
      <div>
        <h1>{$safeTitle}</h1>
        <p>{$safeIntro}</p>
      </div>
      {$authTools}
    </div>
  </section>
HTML;
}

function render_page_end(): void
{
    echo <<<HTML
  <footer class="site-footer">
    <div>Powered by Bartiméus</div>
    <div><img src="https://www.tastenbraille.com/braillestudio/assets/bartimeus.png" alt="Bartiméus"></div>
  </footer>
</body></html>
HTML;
}

function get_auth_config(): array
{
    $configFile = __DIR__ . '/auth_config.php';
    if (is_file($configFile)) {
        $config = require $configFile;
        if (is_array($config)) {
            return $config;
        }
    }

    return [
        'retention_days' => 1825,
        'purge_after_soft_delete_days' => 90,
        'users' => [
            ['username' => 'Gerda', 'role' => 'editor', 'password_hash' => '$2y$10$zP0B1JwuikBSLJvj9/9icuW5HG.tKjiRZO8DlaKSytt7Zv2XIjEFS'],
            ['username' => 'Manon', 'role' => 'editor', 'password_hash' => '$2y$10$AndwvpYAq3zlE2S0GYpe/uopE0YvF6KJWL1fQhl03quWKeX9qHPyG'],
            ['username' => 'Eric', 'role' => 'admin', 'password_hash' => '$2y$10$UBx2jqC/x9BharozayKl5u0GJsgVptd41i9kAH2Uo/1id2g9JE4H.'],
            ['username' => 'bartimeus', 'role' => 'viewer', 'password_hash' => '$2y$10$U5YkVNKWbOFwynZfQAaSdeq9DL6QLiSoy0.uEdlquSxg1ndgarqX.'],
        ],
    ];
}

function is_authenticated(): bool
{
    return !empty($_SESSION['braillevolg_authenticated']);
}

function authenticate_user(string $username, string $password): bool
{
    $config = get_auth_config();
    $users = $config['users'] ?? [];

    if ($username === '' || $password === '' || !is_array($users)) {
        return false;
    }

    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $expectedUser = (string)($user['username'] ?? '');
        $expectedHash = (string)($user['password_hash'] ?? '');
        $expectedRole = (string)($user['role'] ?? 'editor');

        if ($expectedUser === '' || $expectedHash === '') {
            continue;
        }

        if (hash_equals($expectedUser, $username) && password_verify($password, $expectedHash)) {
            $_SESSION['braillevolg_authenticated'] = true;
            $_SESSION['braillevolg_auth_user'] = $expectedUser;
            $_SESSION['braillevolg_auth_role'] = $expectedRole;
            return true;
        }
    }

    return false;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function current_auth_user(): string
{
    return (string)($_SESSION['braillevolg_auth_user'] ?? '');
}

function current_auth_role(): string
{
    return (string)($_SESSION['braillevolg_auth_role'] ?? '');
}

function is_admin(): bool
{
    return current_auth_role() === 'admin';
}

function can_edit_voortgang(): bool
{
    return in_array(current_auth_role(), ['admin', 'editor'], true);
}

function require_admin(): void
{
    if (is_admin()) {
        return;
    }
    http_response_code(403);
    exit('403 - Alleen admins mogen deze actie uitvoeren.');
}

function require_voortgang_editor(): void
{
    if (can_edit_voortgang()) {
        return;
    }
    http_response_code(403);
    exit('403 - Alleen admins en editors mogen aantekeningen wijzigen.');
}

function write_audit_log(SQLite3 $db, string $entityType, int $entityId, string $action, array $details = []): void
{
    $stmt = $db->prepare("
        INSERT INTO audit_log (actor, actor_role, entity_type, entity_id, action, details_json)
        VALUES (:actor, :actor_role, :entity_type, :entity_id, :action, :details_json)
    ");
    $stmt->bindValue(':actor', current_auth_user(), SQLITE3_TEXT);
    $stmt->bindValue(':actor_role', current_auth_role(), SQLITE3_TEXT);
    $stmt->bindValue(':entity_type', $entityType, SQLITE3_TEXT);
    $stmt->bindValue(':entity_id', $entityId, SQLITE3_INTEGER);
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':details_json', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->execute();
}

function soft_delete_leerling(SQLite3 $db, int $leerlingId): void
{
    $deletedAt = gmdate('Y-m-d H:i:s');
    $deletedBy = current_auth_user();

    $stmt = $db->prepare("UPDATE leerlingen SET deleted_at = :deleted_at, deleted_by = :deleted_by WHERE id = :id AND deleted_at = ''");
    $stmt->bindValue(':deleted_at', $deletedAt, SQLITE3_TEXT);
    $stmt->bindValue(':deleted_by', $deletedBy, SQLITE3_TEXT);
    $stmt->bindValue(':id', $leerlingId, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $db->prepare("UPDATE voortgang SET deleted_at = :deleted_at, deleted_by = :deleted_by WHERE leerling_id = :leerling_id AND deleted_at = ''");
    $stmt->bindValue(':deleted_at', $deletedAt, SQLITE3_TEXT);
    $stmt->bindValue(':deleted_by', $deletedBy, SQLITE3_TEXT);
    $stmt->bindValue(':leerling_id', $leerlingId, SQLITE3_INTEGER);
    $stmt->execute();
}

function soft_delete_voortgang(SQLite3 $db, int $voortgangId, int $leerlingId): void
{
    $stmt = $db->prepare("UPDATE voortgang SET deleted_at = :deleted_at, deleted_by = :deleted_by WHERE id = :id AND leerling_id = :leerling_id AND deleted_at = ''");
    $stmt->bindValue(':deleted_at', gmdate('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->bindValue(':deleted_by', current_auth_user(), SQLITE3_TEXT);
    $stmt->bindValue(':id', $voortgangId, SQLITE3_INTEGER);
    $stmt->bindValue(':leerling_id', $leerlingId, SQLITE3_INTEGER);
    $stmt->execute();
}

function export_leerling_bundle(SQLite3 $db, int $leerlingId): ?array
{
    $leerling = fetch_leerling_by_id($db, $leerlingId);
    if (!$leerling) {
        return null;
    }

    $stmt = $db->prepare("SELECT * FROM voortgang WHERE leerling_id = :leerling_id AND deleted_at = '' ORDER BY datum DESC, id DESC");
    $stmt->bindValue(':leerling_id', $leerlingId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $items = [];
    while ($row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false) {
        $items[] = $row;
    }

    return [
        'exportedAt' => gmdate('c'),
        'exportedBy' => current_auth_user(),
        'leerling' => $leerling,
        'voortgang' => $items,
    ];
}

function apply_retention_policy(SQLite3 $db): void
{
    $config = get_auth_config();
    $purgeAfterDays = (int)($config['purge_after_soft_delete_days'] ?? 90);
    if ($purgeAfterDays <= 0) {
        return;
    }

    $threshold = gmdate('Y-m-d H:i:s', time() - ($purgeAfterDays * 86400));
    $stmt = $db->prepare("DELETE FROM voortgang WHERE deleted_at != '' AND deleted_at < :threshold");
    $stmt->bindValue(':threshold', $threshold, SQLITE3_TEXT);
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM leerlingen WHERE deleted_at != '' AND deleted_at < :threshold");
    $stmt->bindValue(':threshold', $threshold, SQLITE3_TEXT);
    $stmt->execute();
}

apply_retention_policy($db);

function require_authentication(): void
{
    if (is_authenticated()) {
        return;
    }
    redirect_with_query('login.php');
}
