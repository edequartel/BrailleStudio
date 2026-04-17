<?php
declare(strict_types=1);

$routesFile = __DIR__ . '/routes.json';
$password = 'zeemeeuw2015';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalize_routes_for_admin(array $routes): array
{
    $normalized = [];
    foreach ($routes as $code => $record) {
        if (!is_string($code) || trim($code) === '') {
            continue;
        }

        if (is_string($record)) {
            $normalized[$code] = [
                'url' => $record,
                'remarks' => '',
            ];
            continue;
        }

        if (is_array($record)) {
            $normalized[$code] = [
                'url' => trim((string)($record['url'] ?? '')),
                'remarks' => trim((string)($record['remarks'] ?? '')),
            ];
        }
    }

    ksort($normalized);
    return $normalized;
}

$enteredPassword = $_POST['password'] ?? $_GET['password'] ?? '';
$isAllowed = hash_equals($password, (string)$enteredPassword);

$routes = [];
if (is_file($routesFile)) {
    $json = file_get_contents($routesFile);
    $decoded = json_decode((string)$json, true);
    if (is_array($decoded)) {
        $routes = normalize_routes_for_admin($decoded);
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Go Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg: #eef3f9;
      --panel: #ffffff;
      --panel-soft: #f7fbff;
      --text: #142033;
      --muted: #64748b;
      --border: #d8e1ee;
      --blue: #1d4ed8;
      --blue-dark: #173fb0;
      --green: #15803d;
      --red: #b91c1c;
      --shadow: 0 18px 42px rgba(15, 23, 42, 0.10);
      --radius: 22px;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(29, 78, 216, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
    }

    .page {
      max-width: 1260px;
      margin: 0 auto;
      padding: 28px 20px 40px;
      display: grid;
      gap: 18px;
    }

    .card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 22px;
    }

    .hero {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: flex-start;
    }

    .hero h1 {
      margin: 0;
      font-size: 34px;
      letter-spacing: -0.03em;
    }

    .hero p {
      margin: 8px 0 0;
      color: var(--muted);
      line-height: 1.5;
      max-width: 760px;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 40px;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid #cde6d2;
      background: #effaf1;
      color: var(--green);
      font-weight: 700;
      white-space: nowrap;
    }

    .login-card {
      max-width: 460px;
    }

    label {
      display: block;
      margin: 0 0 8px;
      font-size: 14px;
      font-weight: 700;
    }

    input[type="password"],
    input[type="text"],
    input[type="url"] {
      width: 100%;
      min-height: 46px;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 14px;
      font: inherit;
      color: var(--text);
      background: white;
    }

    input:focus {
      outline: none;
      border-color: rgba(29, 78, 216, 0.55);
      box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
    }

    .btn-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 14px;
    }

    button,
    .button-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 46px;
      padding: 11px 16px;
      border: 1px solid var(--border);
      border-radius: 14px;
      background: white;
      color: var(--text);
      font: inherit;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
    }

    button:hover,
    .button-link:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .btn-primary {
      background: var(--blue);
      border-color: var(--blue);
      color: white;
    }

    .btn-primary:hover {
      background: var(--blue-dark);
      border-color: var(--blue-dark);
    }

    .btn-danger {
      color: var(--red);
      border-color: #f1c5c5;
      background: #fff7f7;
    }

    .btn-danger:hover {
      box-shadow: 0 10px 24px rgba(185, 28, 28, 0.08);
    }

    .helper-box {
      border: 1px solid var(--border);
      border-radius: 16px;
      background: var(--panel-soft);
      padding: 16px;
    }

    .helper-box h2,
    .table-card h2 {
      margin: 0 0 10px;
      font-size: 18px;
    }

    .helper-box p {
      margin: 0;
      color: var(--muted);
      line-height: 1.5;
    }

    .helper-box code {
      display: inline-block;
      margin-top: 10px;
      padding: 8px 10px;
      border-radius: 10px;
      background: white;
      border: 1px solid var(--border);
      color: var(--blue-dark);
      font-size: 13px;
    }

    .table-shell {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin-top: 14px;
      min-width: 860px;
    }

    th, td {
      padding: 12px;
      text-align: left;
      vertical-align: top;
      border-bottom: 1px solid var(--border);
      background: white;
    }

    thead th {
      background: #eff5fb;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #4a607b;
    }

    thead th:first-child {
      border-top-left-radius: 14px;
    }

    thead th:last-child {
      border-top-right-radius: 14px;
    }

    tbody tr:last-child td:first-child {
      border-bottom-left-radius: 14px;
    }

    tbody tr:last-child td:last-child {
      border-bottom-right-radius: 14px;
    }

    td.col-actions {
      width: 140px;
    }

    .icon-actions {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .icon-btn {
      width: 42px;
      min-width: 42px;
      min-height: 42px;
      padding: 0;
      border-radius: 12px;
    }

    .icon-btn svg {
      width: 18px;
      height: 18px;
      display: block;
    }

    .small {
      color: var(--muted);
      font-size: 14px;
      line-height: 1.5;
    }

    .muted {
      color: var(--muted);
    }

    @media (max-width: 900px) {
      .page {
        padding: 18px 14px 28px;
      }

      .hero {
        display: grid;
        grid-template-columns: 1fr;
      }

      .card {
        padding: 18px;
        border-radius: 18px;
      }

      th, td {
        padding: 10px;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <section class="card">
      <div class="hero">
        <div>
          <h1>Go Admin</h1>
          <p>Beheer korte links voor de <code>/go/...</code> routes. Voeg routes toe, verwijder ze uit de lijst en noteer optioneel remarks per short code.</p>
        </div>
        <?php if ($isAllowed): ?>
          <div class="status-pill">Ingelogd</div>
        <?php endif; ?>
      </div>
    </section>

    <?php if (!$isAllowed): ?>
      <section class="card login-card">
        <form method="post">
          <label for="password">Wachtwoord</label>
          <input type="password" name="password" id="password" required autofocus>
          <div class="btn-row">
            <button class="btn-primary" type="submit">Open admin</button>
          </div>
        </form>
        <p class="small">Het wachtwoord staat nu nog hardcoded in <code>admin.php</code>.</p>
      </section>
    <?php else: ?>
      <section class="card">
        <div class="helper-box">
          <h2>Gebruik</h2>
          <p>Maak een korte code aan en koppel die aan een volledige URL. Gebruik <em>remarks</em> voor een notitie, boekreferentie of interne context.</p>
          <code>https://www.tastenbraille.com/go/page12</code>
        </div>
      </section>

      <section class="card table-card">
        <h2>Redirect routes</h2>
        <form method="post" action="save.php" id="routesForm">
          <input type="hidden" name="password" value="<?= h((string)$enteredPassword) ?>">

          <div class="btn-row">
            <button class="btn-primary" type="button" id="addRowBtn">Add route</button>
            <button type="submit">Save routes</button>
          </div>

          <div class="table-shell">
            <table>
              <thead>
                <tr>
                  <th style="width: 22%;">Short code</th>
                  <th style="width: 42%;">Target URL</th>
                  <th style="width: 24%;">Remarks</th>
                  <th style="width: 12%;">Actie</th>
                </tr>
              </thead>
              <tbody id="routesTableBody">
                <?php foreach ($routes as $code => $record): ?>
                  <tr>
                    <td><input type="text" name="codes[]" value="<?= h((string)$code) ?>"></td>
                    <td><input type="url" name="urls[]" value="<?= h((string)($record['url'] ?? '')) ?>"></td>
                    <td><input type="text" name="remarks[]" value="<?= h((string)($record['remarks'] ?? '')) ?>" placeholder="Boek, pagina of notitie"></td>
                    <td class="col-actions">
                      <div class="icon-actions">
                        <button type="button" class="icon-btn js-open-go-link" aria-label="Open shortened go link" title="Open shortened go link">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M14 5h5v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10 14 19 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                          </svg>
                        </button>
                        <button type="button" class="icon-btn js-copy-go-link" aria-label="Copy shortened go link" title="Copy shortened go link">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <rect x="9" y="9" width="10" height="10" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                          </svg>
                        </button>
                        <button class="icon-btn btn-danger js-remove-row" type="button" aria-label="Remove row" title="Remove row">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                            <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M6 6l1 14a1 1 0 0 0 1 .92h8a1 1 0 0 0 1-.92L18 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < 3; $i++): ?>
                  <tr>
                    <td><input type="text" name="codes[]" value=""></td>
                    <td><input type="url" name="urls[]" value=""></td>
                    <td><input type="text" name="remarks[]" value="" placeholder="Boek, pagina of notitie"></td>
                    <td class="col-actions">
                      <div class="icon-actions">
                        <button type="button" class="icon-btn js-open-go-link" aria-label="Open shortened go link" title="Open shortened go link">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M14 5h5v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10 14 19 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                          </svg>
                        </button>
                        <button type="button" class="icon-btn js-copy-go-link" aria-label="Copy shortened go link" title="Copy shortened go link">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <rect x="9" y="9" width="10" height="10" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                          </svg>
                        </button>
                        <button class="icon-btn btn-danger js-remove-row" type="button" aria-label="Remove row" title="Remove row">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                            <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M6 6l1 14a1 1 0 0 0 1 .92h8a1 1 0 0 0 1-.92L18 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </form>
        <p class="small">Lege regels worden genegeerd bij opslaan. Alleen geldige short codes en URLs worden opgeslagen.</p>
      </section>
    <?php endif; ?>
  </div>

  <?php if ($isAllowed): ?>
    <template id="routeRowTemplate">
      <tr>
        <td><input type="text" name="codes[]" value=""></td>
        <td><input type="url" name="urls[]" value=""></td>
        <td><input type="text" name="remarks[]" value="" placeholder="Boek, pagina of notitie"></td>
        <td class="col-actions">
          <div class="icon-actions">
            <button type="button" class="icon-btn js-open-go-link" aria-label="Open shortened go link" title="Open shortened go link">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M14 5h5v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M10 14 19 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
            </button>
            <button type="button" class="icon-btn js-copy-go-link" aria-label="Copy shortened go link" title="Copy shortened go link">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <rect x="9" y="9" width="10" height="10" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
            </button>
            <button class="icon-btn btn-danger js-remove-row" type="button" aria-label="Remove row" title="Remove row">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M3 6h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M6 6l1 14a1 1 0 0 0 1 .92h8a1 1 0 0 0 1-.92L18 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
              </svg>
            </button>
          </div>
        </td>
      </tr>
    </template>
    <script>
      (function () {
        const tableBody = document.getElementById('routesTableBody');
        const template = document.getElementById('routeRowTemplate');
        const addRowBtn = document.getElementById('addRowBtn');
        const goBase = 'https://www.tastenbraille.com/go/';

        if (!tableBody || !template || !addRowBtn) {
          return;
        }

        function copyText(text) {
          if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
          }

          const temp = document.createElement('textarea');
          temp.value = text;
          temp.setAttribute('readonly', 'readonly');
          temp.style.position = 'absolute';
          temp.style.left = '-9999px';
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          document.body.removeChild(temp);
          return Promise.resolve();
        }

        function isRowEmpty(row) {
          if (!row) return true;
          const codeInput = row.querySelector('input[name="codes[]"]');
          const urlInput = row.querySelector('input[name="urls[]"]');
          const remarksInput = row.querySelector('input[name="remarks[]"]');
          return !String(codeInput?.value || '').trim()
            && !String(urlInput?.value || '').trim()
            && !String(remarksInput?.value || '').trim();
        }

        function ensureTrailingEmptyRow() {
          const rows = Array.from(tableBody.querySelectorAll('tr'));
          if (!rows.length || !isRowEmpty(rows[rows.length - 1])) {
            const fragment = template.content.cloneNode(true);
            bindRowButtons(fragment);
            tableBody.appendChild(fragment);
          }
        }

        function bindRowButtons(root) {
          root.querySelectorAll('.js-open-go-link').forEach((button) => {
            button.addEventListener('click', () => {
              const row = button.closest('tr');
              const codeInput = row ? row.querySelector('input[name="codes[]"]') : null;
              const code = String(codeInput?.value || '').trim();
              if (!code) {
                alert('Vul eerst een short code in.');
                return;
              }

              const shortUrl = goBase + encodeURIComponent(code);
              window.open(shortUrl, '_blank', 'noopener,noreferrer');
            });
          });

          root.querySelectorAll('.js-remove-row').forEach((button) => {
            button.addEventListener('click', () => {
              const row = button.closest('tr');
              if (row) {
                row.remove();
                ensureTrailingEmptyRow();
              }
            });
          });

          root.querySelectorAll('.js-copy-go-link').forEach((button) => {
            button.addEventListener('click', async () => {
              const row = button.closest('tr');
              const codeInput = row ? row.querySelector('input[name="codes[]"]') : null;
              const code = String(codeInput?.value || '').trim();
              if (!code) {
                alert('Vul eerst een short code in.');
                return;
              }

              const shortUrl = goBase + encodeURIComponent(code);
              try {
                await copyText(shortUrl);
                button.style.borderColor = '#93c5fd';
                button.style.color = '#1d4ed8';
                window.setTimeout(() => {
                  button.style.borderColor = '';
                  button.style.color = '';
                }, 1200);
              } catch (err) {
                alert('Kopiëren mislukt.');
              }
            });
          });
        }

        addRowBtn.addEventListener('click', () => {
          const fragment = template.content.cloneNode(true);
          bindRowButtons(fragment);
          tableBody.appendChild(fragment);
        });

        bindRowButtons(document);
        ensureTrailingEmptyRow();
      })();
    </script>
  <?php endif; ?>
</body>
</html>
