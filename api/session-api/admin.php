<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

$authUser = bs_auth_current_user() ?? ['display' => '', 'role' => ''];

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?session-api$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$sessionBase = $scriptDir;

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Step Link Admin</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'components/braille-monitor/braillemonitor.css?v=20260529-mode-label-1')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'components/braillebridge-status/braillebridge-status.css?v=20260526-popup-3')) ?>">
  <style>
    .script-selection-card .card-body {
      display: grid;
      gap: 1rem;
    }

    .script-selection-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(18rem, .9fr);
      gap: 1rem;
      align-items: start;
    }

    .script-meta-preview {
      display: grid;
      gap: .75rem;
      align-content: start;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      padding: .875rem;
      background: var(--tblr-bg-surface-secondary);
    }

    .script-meta-preview__grid {
      display: grid;
      gap: .75rem;
    }

    .script-meta-preview__label {
      display: flex;
      align-items: center;
      gap: .375rem;
      margin-bottom: .375rem;
      color: var(--tblr-body-color);
      font-size: .75rem;
      font-weight: 700;
    }

    .script-meta-preview__value {
      min-height: 3.25rem;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      padding: .625rem .75rem;
      background: var(--tblr-bg-surface);
      color: var(--tblr-body-color);
      font-size: .8125rem;
      line-height: 1.45;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }

    .step-link-settings {
      display: grid;
      gap: .75rem;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      padding: .75rem;
      background: var(--tblr-bg-surface);
    }

    .step-link-variable-row {
      display: grid;
      grid-template-columns: minmax(8rem, 14rem) minmax(0, 1fr);
      align-items: start;
      gap: .625rem;
    }

    .step-link-variable-row .form-label {
      margin-bottom: 0;
      padding-top: .25rem;
    }

    .braille-monitor-standard-card .card-body {
      display: grid;
      gap: .75rem;
    }

    .braille-monitor-standard-host {
      overflow: hidden;
      border-radius: 5px;
    }

    .braille-monitor-standard-host .braille-monitor-component,
    .braille-monitor-standard-host .braille-monitor-cells,
    .braille-monitor-standard-host .braille-monitor-cell-container {
      width: 100%;
      max-width: 100%;
      border-radius: 5px;
    }

    .braille-monitor-thumb-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
      align-items: center;
      gap: .5rem;
    }

    .braille-monitor-thumb-controls {
      display: inline-flex;
      grid-column: 2;
      gap: .5rem;
      justify-content: center;
    }

    .braille-monitor-thumb-controls .btn {
      min-width: 3rem;
      font-weight: 600;
    }

    .braille-monitor-run-controls {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      justify-content: flex-end;
    }

    .braille-monitor-bridge-status {
      grid-column: 3;
      justify-self: end;
      flex: 0 1 auto;
      min-width: 0;
    }

    .braille-monitor-bridge-status.is-collapsed {
      min-width: 0;
    }

    .braille-monitor-bridge-status .braillebridge-status__body {
      padding: .625rem .75rem;
    }

    .braille-monitor-bridge-status .braillebridge-status__icon,
    .braille-monitor-bridge-status .braillebridge-status__toggle {
      width: 2.25rem;
      height: 2.25rem;
      font-size: 1.15rem;
    }

    .braille-monitor-bridge-status .braillebridge-status__toggle-dot {
      margin-top: -1.1rem;
      margin-left: 1.1rem;
    }

    .runner-frame {
      position: absolute;
      left: -10000px;
      top: 0;
      width: 1px;
      height: 1px;
      border: 0;
      opacity: 0;
      pointer-events: none;
    }

    @media (max-width: 767.98px) {
      .script-selection-layout,
      .step-link-variable-row {
        grid-template-columns: 1fr;
      }

      .braille-monitor-thumb-row {
        grid-template-columns: 1fr;
      }

      .braille-monitor-thumb-controls,
      .braille-monitor-bridge-status {
        grid-column: 1;
        justify-self: center;
      }
    }
  </style>
</head>
<body class="bg-body">
  <div id="adminLoadingScreen" class="page page-center" aria-live="polite">
    <div class="container-tight py-4">
      <div class="card card-md">
        <div class="card-body text-center py-5">
          <div class="mb-3">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          </div>
          <h1 class="h3 mb-2">Session Admin laden</h1>
          <p id="adminLoadingMessage" class="text-secondary mb-0">Beheeromgeving voorbereiden.</p>
        </div>
      </div>
    </div>
  </div>

  <div id="adminAppPage" class="page d-none" hidden>
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row ms-auto align-items-center">
          <span class="navbar-text text-secondary me-3">
            Ingelogd als <?= $html($authUser['display']) ?> (<?= $html($authUser['role']) ?>)
          </span>
          <form method="post" action="<?= $htmlUrl($urlFor($appBase, 'authentication.php')) ?>" class="mb-0">
            <input type="hidden" name="csrf" value="<?= $html(bs_auth_csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="returnTo" value="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
            <button class="btn btn-outline-secondary" type="submit">
              <i class="ti ti-logout me-2" aria-hidden="true"></i>
              Uitloggen
            </button>
          </form>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <div class="page-pretitle">BrailleStudio</div>
              <h1 class="page-title">Step Link Admin</h1>
              <div class="text-secondary mt-2">Create and manage session step-links.</div>
              <div class="text-secondary small mt-2" id="pageVersion">Version pending...</div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="row row-cards">
            <div class="col-12">
              <div class="card braille-monitor-standard-card">
                <div class="card-body">
                  <div class="row g-3 align-items-center">
                    <div class="col">
                      <div id="sessionSendStatus" class="form-hint">Gebruik de speelknop bij een step-link om deze direct te starten.</div>
                    </div>
                  </div>
                  <div id="adminBrailleMonitorComponent" class="braille-monitor-standard-host"></div>
                  <div class="braille-monitor-thumb-row" aria-label="Thumb keys">
                    <div class="braille-monitor-thumb-controls">
                      <button id="adminThumbLeftBtn" class="btn btn-outline-primary" type="button" aria-label="Left thumb" title="Left thumb">&lt;&lt;</button>
                      <button id="adminCursor5Btn" class="btn btn-outline-primary" type="button" aria-label="Left middle thumb" title="Left middle thumb">&lt;</button>
                      <button id="adminChord1Btn" class="btn btn-outline-primary" type="button" aria-label="Right middle thumb" title="Right middle thumb">&gt;</button>
                      <button id="adminThumbRightBtn" class="btn btn-outline-primary" type="button" aria-label="Right thumb" title="Right thumb">&gt;&gt;</button>
                    </div>
                    <div class="braille-monitor-run-controls">
                      <button id="adminStopBtn" class="btn btn-danger" type="button">
                        <i class="ti ti-player-stop me-1" aria-hidden="true"></i>
                        Stop
                      </button>
                    </div>
                    <div
                      class="braille-monitor-bridge-status"
                      data-braillebridge-status
                      data-collapsible="true"
                      data-expanded="false"
                      data-popup="true"
                      data-show-launch="true"
                      data-launch-url="braillebridge://"
                      data-auto-launch="true"
                    ></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="card script-selection-card">
                <div class="card-body">
                  <div class="script-selection-layout">
                    <div class="row g-3">
                      <div class="col-12">
                        <label class="form-label" for="scriptSelect">Online script</label>
                        <select id="scriptSelect" class="form-select">
                          <option value="">Scripts laden...</option>
                        </select>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="codeInput">Short code</label>
                        <input id="codeInput" class="form-control" type="text" placeholder="leave empty to auto-generate">
                      </div>
                      <div class="col-12">
                        <label class="form-label" for="infoInput">Info</label>
                        <textarea id="infoInput" class="form-control" rows="2" placeholder="interne notitie bij deze step-link"></textarea>
                      </div>
                      <div class="col-12">
                        <button id="createLinkBtn" class="btn btn-success" type="button">Create step link</button>
                      </div>
                    </div>

                    <div class="script-meta-preview" aria-label="Scriptgegevens">
                      <div class="script-meta-preview__grid">
                        <div>
                          <div class="script-meta-preview__label">
                            <i class="ti ti-notes" aria-hidden="true"></i>
                            <span>Omschrijving</span>
                          </div>
                          <div id="descriptionText" class="script-meta-preview__value">-</div>
                        </div>
                        <div>
                          <div class="script-meta-preview__label">
                            <i class="ti ti-list-check" aria-hidden="true"></i>
                            <span>Instructie</span>
                          </div>
                          <div id="instructionText" class="script-meta-preview__value">-</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div>
                    <div id="scriptsStatus" class="form-hint">Scripts worden geladen.</div>
                    <div id="createStatus" class="form-hint mt-1">Ready to create a step link.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Existing Step Links</h2>
              <div class="card-actions">
                <div class="btn-list">
                  <button id="deleteOldLinksBtn" class="btn btn-outline-danger btn-sm" type="button" disabled>
                    <i class="ti ti-trash me-1" aria-hidden="true"></i>
                    Delete old step-links
                  </button>
                  <a class="btn btn-outline-secondary btn-sm" href="<?= $htmlUrl($urlFor($sessionBase, 'step-links-pdf.php?active=1')) ?>">
                    <i class="ti ti-file-type-pdf me-1" aria-hidden="true"></i>
                    QR PDF
                  </a>
                  <button id="refreshLinksBtn" class="btn btn-primary btn-sm" type="button">Refresh links</button>
                </div>
              </div>
            </div>
            <div class="card-body">
              <div id="linksStatus" class="form-hint mb-3">No links loaded yet.</div>
              <div id="linksList"></div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Logging</h2>
              <div class="card-actions">
                <div class="btn-list">
                  <button id="toggleLogBtn" class="btn btn-outline-secondary btn-sm" type="button">Unhide log</button>
                  <button id="copyLogBtn" class="btn btn-outline-secondary btn-sm" type="button">Copy log</button>
                  <button id="clearLogBtn" class="btn btn-outline-secondary btn-sm" type="button">Clear log</button>
                </div>
              </div>
            </div>
            <div id="logBody" class="card-body d-none" hidden>
              <div id="logBox" class="list-group list-group-flush border rounded font-monospace overflow-auto" style="max-height: 32rem"></div>
            </div>
          </div>

          <iframe id="adminBuilderFrame" class="runner-frame" title="BrailleStudio runner" allow="autoplay"></iframe>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="<?= $htmlUrl($urlFor($appBase, 'components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1')) ?>"></script>
  <script src="<?= $htmlUrl($urlFor($appBase, 'components/braillebridge-status/braillebridge-status.js?v=20260612-runtime-status-4')) ?>"></script>
  <script>
    const ADMIN_VERSION = '2026-05-29 rebuilt-step-link-admin-debug-4';
    const SCRIPT_LIST_URL = <?= $jsValue($urlFor($appBase, 'api/blockly-api/list.php')) ?>;
    const SCRIPT_LOAD_URL = <?= $jsValue($urlFor($appBase, 'api/blockly-api/load.php')) ?>;
    const CREATE_LINK_URL = <?= $jsValue($urlFor($sessionBase, 'create-link.php')) ?>;
    const UPDATE_LINK_URL = <?= $jsValue($urlFor($sessionBase, 'update-link.php')) ?>;
    const DELETE_LINK_URL = <?= $jsValue($urlFor($sessionBase, 'delete-link.php')) ?>;
    const LIST_LINKS_URL = <?= $jsValue($urlFor($sessionBase, 'list-links.php')) ?>;
    const BLOCKLY_URL = <?= $jsValue($urlFor($appBase, 'blockly/session-player.php?v=20260602-headless-session-player-1')) ?>;
    const $ = (id) => document.getElementById(id);
    let scriptsCache = [];
    let linksCache = [];
    let pendingCopiedPlacement = null;
    let adminBrailleMonitorUi = null;
    let adminBrailleMonitorSyncTimer = null;
    let lastBrailleSnapshot = '';
    let activeStepLinkCode = '';
    let pendingStepLinkCode = '';
    let stepLinkRunToken = 0;
    let isStoppingStepLink = false;
    const scriptDataCache = new Map();

    function setLoadingMessage(message) {
      $('adminLoadingMessage').textContent = message;
    }

    function hideLoadingScreen() {
      $('adminLoadingScreen').hidden = true;
      $('adminLoadingScreen').classList.add('d-none');
      $('adminAppPage').hidden = false;
      $('adminAppPage').classList.remove('d-none');
    }

    function showLoadingError(message) {
      const loadingMessage = $('adminLoadingMessage');
      loadingMessage.textContent = message;
      loadingMessage.classList.remove('text-secondary');
      loadingMessage.classList.add('text-danger');
    }

    function buildLogDetailLines(data, prefix = '') {
      if (typeof data === 'undefined') return [];
      if (data === null || typeof data !== 'object') {
        return [`${prefix ? `${prefix}: ` : ''}${String(data)}`];
      }
      if (Array.isArray(data)) {
        return data.length
          ? data.flatMap((value, index) => buildLogDetailLines(value, prefix ? `${prefix}.${index + 1}` : String(index + 1)))
          : [`${prefix ? `${prefix}: ` : ''}(leeg)`];
      }
      const entries = Object.entries(data);
      return entries.length
        ? entries.flatMap(([key, value]) => buildLogDetailLines(value, prefix ? `${prefix}.${key}` : key))
        : [`${prefix ? `${prefix}: ` : ''}(leeg)`];
    }

    function logLine(message, data) {
      const item = document.createElement('div');
      item.className = 'list-group-item py-2';
      const title = document.createElement('div');
      title.className = 'fw-medium';
      title.textContent = `[${new Date().toLocaleTimeString('nl-NL', { hour12: false })}] ${String(message || '').trim()}`;
      item.appendChild(title);
      buildLogDetailLines(data).forEach((line) => {
        const detail = document.createElement('div');
        detail.className = 'text-secondary small';
        detail.textContent = line;
        item.appendChild(detail);
      });
      $('logBox').prepend(item);
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function statusText(message, tone = 'secondary') {
      const classes = {
        danger: 'text-danger',
        success: 'text-success',
        warning: 'text-warning',
        secondary: 'text-secondary'
      };
      return `<span class="${classes[tone] || classes.secondary}">${escapeHtml(message)}</span>`;
    }

    function slugifyStepPart(value) {
      return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-');
    }

    function buildAutoStepId(scriptId) {
      const base = slugifyStepPart(scriptId);
      return base ? `${base}-step-1` : '';
    }

    function formatStepLinkDateTime(value) {
      const raw = String(value || '').trim();
      if (!raw) return '-';
      const date = new Date(raw);
      if (Number.isNaN(date.getTime())) return raw;
      const pad = (number) => String(number).padStart(2, '0');
      return [
        pad(date.getDate()),
        pad(date.getMonth() + 1),
        pad(date.getFullYear() % 100)
      ].join('-') + ' ' + [
        pad(date.getHours()),
        pad(date.getMinutes())
      ].join(':');
    }

    function getSelectedScriptId() {
      const select = $('scriptSelect');
      const option = select.options[select.selectedIndex];
      return String(option?.dataset?.scriptId || option?.value || '').trim();
    }

    function setScriptMetaPreview(meta = {}) {
      $('descriptionText').textContent = String(meta.description || meta.prompt || '').trim() || '-';
      $('instructionText').textContent = String(meta.instruction || '').trim() || '-';
    }

    function setSessionSendStatus(message, tone = 'secondary') {
      const target = $('sessionSendStatus');
      if (!target) return;
      target.innerHTML = statusText(message, tone);
    }

    function initAdminBrailleMonitor() {
      if (adminBrailleMonitorUi) return adminBrailleMonitorUi;
      if (window.BrailleMonitor && typeof window.BrailleMonitor.init === 'function') {
        adminBrailleMonitorUi = window.BrailleMonitor.init({
          containerId: 'adminBrailleMonitorComponent',
          showInfo: false
        });
        adminBrailleMonitorUi.clear?.();
      }
      if (window.BrailleBridgeStatus && typeof window.BrailleBridgeStatus.initAll === 'function') {
        window.BrailleBridgeStatus.initAll();
      }
      return adminBrailleMonitorUi;
    }

    function clearAdminBrailleMonitor() {
      const monitor = initAdminBrailleMonitor();
      if (monitor && typeof monitor.clear === 'function') {
        monitor.clear();
      } else if (monitor && typeof monitor.setText === 'function') {
        monitor.setText('');
      }
    }

    function getBuilderWindow() {
      const frame = $('adminBuilderFrame');
      return frame?.contentWindow || null;
    }

    async function waitForLoadWorkspaceOnline(timeoutMs = 15000) {
      const started = Date.now();
      while (Date.now() - started < timeoutMs) {
        const frameWindow = getBuilderWindow();
        if (frameWindow && typeof frameWindow.loadWorkspaceOnline === 'function') {
          return frameWindow;
        }
        if (frameWindow?.BrailleBlocklyApp && typeof frameWindow.BrailleBlocklyApp.applyResolvedSessionPayload === 'function') {
          return frameWindow;
        }
        await new Promise((resolve) => window.setTimeout(resolve, 150));
      }
      return null;
    }

    function syncBrailleMonitorFromRunner() {
      try {
        const monitor = initAdminBrailleMonitor();
        const app = getBuilderWindow()?.BrailleBlocklyApp || null;
        if (!monitor || !app || typeof app.getRuntimeSnapshot !== 'function') return;
        const runtime = app.getRuntimeSnapshot();
        const brailleUnicode = String(runtime?.brailleUnicode || '');
        const sourceText = String(runtime?.text || '');
        const signature = JSON.stringify({
          brailleUnicode,
          sourceText,
          cellCaret: runtime?.cellCaret ?? null,
          textCaret: runtime?.textCaret ?? null,
          caretVisible: runtime?.caretVisible ?? true
        });
        if (signature === lastBrailleSnapshot) return;
        lastBrailleSnapshot = signature;
        if (!brailleUnicode && !sourceText) {
          clearAdminBrailleMonitor();
          return;
        }
        monitor.setBrailleUnicode(brailleUnicode, sourceText, {
          caretPosition: Number.isInteger(runtime?.cellCaret) ? runtime.cellCaret : undefined,
          textCaretPosition: Number.isInteger(runtime?.textCaret) ? runtime.textCaret : undefined,
          caretVisible: typeof runtime?.caretVisible === 'boolean' ? runtime.caretVisible : true
        });
      } catch (err) {
        logLine('BrailleMonitor sync mislukt.', { message: err.message || String(err) });
      }
    }

    function rememberRunnerMonitorSnapshot(targetWindow) {
      try {
        const app = targetWindow?.BrailleBlocklyApp || null;
        if (!app || typeof app.getRuntimeSnapshot !== 'function') return;
        const runtime = app.getRuntimeSnapshot();
        lastBrailleSnapshot = JSON.stringify({
          brailleUnicode: String(runtime?.brailleUnicode || ''),
          sourceText: String(runtime?.text || ''),
          cellCaret: runtime?.cellCaret ?? null,
          textCaret: runtime?.textCaret ?? null,
          caretVisible: runtime?.caretVisible ?? true
        });
      } catch (err) {
        logLine('BrailleMonitor snapshot onthouden mislukt.', { message: err.message || String(err) });
      }
    }

    function startBrailleMonitorSync(options = {}) {
      if (adminBrailleMonitorSyncTimer) return;
      adminBrailleMonitorSyncTimer = window.setInterval(syncBrailleMonitorFromRunner, 250);
      if (options.immediate !== false) {
        syncBrailleMonitorFromRunner();
      }
    }

    function stopBrailleMonitorSync() {
      if (!adminBrailleMonitorSyncTimer) return;
      window.clearInterval(adminBrailleMonitorSyncTimer);
      adminBrailleMonitorSyncTimer = null;
    }

    function forceBrailleMonitorSyncFromRunner() {
      lastBrailleSnapshot = '';
      syncBrailleMonitorFromRunner();
    }

    function updateRunToggleButtons() {
      document.querySelectorAll('.js-run-toggle-link').forEach((button) => {
        const code = String(button.getAttribute('data-link-code') || '').trim();
        const isPlaying = code !== '' && (code === activeStepLinkCode || code === pendingStepLinkCode);
        button.disabled = isStoppingStepLink;
        button.classList.toggle('btn-outline-primary', !isPlaying);
        button.classList.toggle('btn-outline-danger', isPlaying);
        button.setAttribute('title', isPlaying ? 'Stop step-link' : 'Start step-link');
        button.setAttribute('aria-label', `${isPlaying ? 'Stop' : 'Start'} step link ${code}`);
        button.innerHTML = isPlaying
          ? '<i class="ti ti-player-stop" aria-hidden="true"></i>'
          : '<i class="ti ti-player-play" aria-hidden="true"></i>';
      });
      const stopButton = $('adminStopBtn');
      if (stopButton) {
        stopButton.disabled = isStoppingStepLink;
        stopButton.innerHTML = `<i class="ti ti-player-stop me-1" aria-hidden="true"></i>${isStoppingStepLink ? 'Stopping...' : 'Stop'}`;
      }
    }

    function isCurrentStepLinkRun(token, code) {
      return token === stepLinkRunToken
        && code !== ''
        && (code === activeStepLinkCode || code === pendingStepLinkCode);
    }

    async function waitForStepLinkRunnerIdle(app, token, code, timeoutMs = 30000) {
      const startedAt = Date.now();
      while (isCurrentStepLinkRun(token, code) && Date.now() - startedAt < timeoutMs) {
        const runtime = typeof app?.getRuntimeSnapshot === 'function'
          ? app.getRuntimeSnapshot()
          : null;
        if (!runtime?.isActive && !runtime?.hasActiveAudio && !runtime?.hasPendingStart) {
          return true;
        }
        await new Promise((resolve) => window.setTimeout(resolve, 100));
      }
      return false;
    }

    async function dispatchRunnerInput(event) {
      const targetWindow = await waitForLoadWorkspaceOnline();
      const app = targetWindow?.BrailleBlocklyApp || null;
      if (app && typeof app.dispatchRuntimeEvent === 'function') {
        await app.dispatchRuntimeEvent(event);
        return;
      }
      throw new Error('Runner input API is niet beschikbaar.');
    }

    function getRunnerDebugSnapshot(targetWindow, payload = null) {
      const app = targetWindow?.BrailleBlocklyApp || null;
      const api = targetWindow?.BrailleStudioAPI || null;
      const runtime = app && typeof app.getRuntimeSnapshot === 'function'
        ? app.getRuntimeSnapshot()
        : null;
      const stepInputs = payload?.stepInputs && typeof payload.stepInputs === 'object' ? payload.stepInputs : {};
      const external = runtime?.external && typeof runtime.external === 'object' ? runtime.external : {};
      const requestedExternal = {};
      Object.keys(stepInputs).forEach((name) => {
        requestedExternal[name] = {
          requested: stepInputs[name],
          runtimeValue: external[name],
          apiValue: api && typeof api.getExternalVariable === 'function' ? api.getExternalVariable(name) : undefined,
          exists: api && typeof api.externalVariableExists === 'function' ? api.externalVariableExists(name) : undefined
        };
      });
      return {
        hasFrame: Boolean(targetWindow),
        hasApp: Boolean(app),
        hasApi: Boolean(api),
        capabilities: {
          applyResolvedSessionPayload: typeof app?.applyResolvedSessionPayload === 'function',
          runCurrentWorkspace: typeof app?.runCurrentWorkspace === 'function',
          stopProgram: typeof app?.stopProgram === 'function',
          getRuntimeSnapshot: typeof app?.getRuntimeSnapshot === 'function',
          setExternalVariable: typeof api?.setExternalVariable === 'function',
          getExternalVariable: typeof api?.getExternalVariable === 'function',
          externalVariableExists: typeof api?.externalVariableExists === 'function'
        },
        runtime: runtime ? {
          stopped: runtime.stopped,
          isActive: runtime.isActive,
          text: runtime.text,
          brailleUnicode: runtime.brailleUnicode,
          external,
          stepCompletion: runtime.stepCompletion,
          lessonCompletion: runtime.lessonCompletion
        } : null,
        requestedExternal
      };
    }

    function readPlayableStepInputs(item, index = -1) {
      const container = Number.isInteger(index) && index >= 0
        ? document.querySelector(`[data-inline-link-index="${index}"]`)
        : null;
      if (container) {
        const inputs = readInlineStepInputs(container, item);
        logLine('Step-link inputs gelezen uit open editor.', {
          index,
          code: item?.code || '',
          inputKeys: Object.keys(inputs),
          inputs
        });
        return inputs;
      }
      const inputs = stripDeprecatedStepInputs(item?.stepInputs);
      logLine('Step-link inputs gelezen uit opgeslagen record.', {
        index,
        code: item?.code || '',
        inputKeys: Object.keys(inputs),
        inputs
      });
      return inputs;
    }

    function buildResolvedStepLinkPayload(item, index = -1) {
      const code = String(item?.code || '').trim();
      if (!code) throw new Error('Missing step link code');
      return {
        code,
        methodId: String(item?.methodId || '').trim(),
        stepId: String(item?.stepId || '').trim(),
        scriptId: String(item?.scriptId || '').trim(),
        meta: getScriptDisplayMeta(item),
        stepInputs: readPlayableStepInputs(item, index)
      };
    }

    async function playStepLink(item, index = -1, runToken = stepLinkRunToken) {
      const payload = buildResolvedStepLinkPayload(item, index);
      if (!payload.scriptId) throw new Error('Step-link heeft geen scriptId.');
      if (activeStepLinkCode && activeStepLinkCode !== payload.code) {
        logLine('Andere step-link wordt gestart; huidige runtime wordt eerst gestopt.', {
          activeStepLinkCode,
          nextCode: payload.code
        });
        await stopCurrentStepLink({ silent: true, reason: 'switch-step-link', preservePending: true, invalidateRun: false });
        if (runToken !== stepLinkRunToken) return;
        pendingStepLinkCode = payload.code;
        updateRunToggleButtons();
      }
      const inputNames = Object.keys(payload.stepInputs || {});
      logLine('Step-link play payload opgebouwd.', {
        code: payload.code,
        scriptId: payload.scriptId,
        stepId: payload.stepId,
        methodId: payload.methodId,
        inputNames,
        stepInputs: payload.stepInputs,
        meta: payload.meta
      });
      setSessionSendStatus(inputNames.length
        ? `Step-link ${payload.code} wordt direct gestart met ${inputNames.length} externe waarde(n)...`
        : `Step-link ${payload.code} wordt direct gestart...`);
      stopBrailleMonitorSync();
      const targetWindow = await waitForLoadWorkspaceOnline();
      if (runToken !== stepLinkRunToken) return;
      if (!targetWindow) {
        throw new Error('Blockly runner is nog niet beschikbaar.');
      }
      const app = targetWindow.BrailleBlocklyApp || {};
      if (typeof app.setLogHandler === 'function') {
        app.setLogHandler((line) => {
          logLine(`Runner: ${String(line || '').replace(/^\[[^\]]+\]\s*/, '')}`);
        });
      }
      logLine('Runner gevonden voor step-link play.', getRunnerDebugSnapshot(targetWindow, payload));
      if (typeof app.applyResolvedSessionPayload === 'function') {
        await app.applyResolvedSessionPayload(payload, { autoRun: false, force: true });
      } else if (typeof targetWindow.applyResolvedSessionPayload === 'function') {
        await targetWindow.applyResolvedSessionPayload(payload, { autoRun: false, force: true });
      } else {
        await targetWindow.loadWorkspaceOnline(payload.scriptId);
      }
      if (runToken !== stepLinkRunToken) return;
      logLine('Runner state na applyResolvedSessionPayload/loadWorkspaceOnline.', getRunnerDebugSnapshot(targetWindow, payload));
      const api = targetWindow.BrailleStudioAPI || {};
      Object.entries(payload.stepInputs || {}).forEach(([name, value]) => {
        if (String(name || '').trim() && typeof api.setExternalVariable === 'function') {
          api.setExternalVariable(name, value);
        }
      });
      logLine('Runner state na expliciete external variable injectie.', getRunnerDebugSnapshot(targetWindow, payload));
      activeStepLinkCode = payload.code;
      pendingStepLinkCode = '';
      updateRunToggleButtons();
      setSessionSendStatus(`Step-link ${payload.code} is gestart.`, 'success');
      $('linksStatus').innerHTML = statusText(`Step link ${payload.code} started.`, 'success');
      rememberRunnerMonitorSnapshot(targetWindow);
      let runPromise = null;
      if (typeof app.runCurrentWorkspace === 'function') {
        runPromise = app.runCurrentWorkspace();
      } else if (typeof targetWindow.onRunClicked === 'function') {
        runPromise = targetWindow.onRunClicked();
      } else {
        throw new Error('Start API is niet beschikbaar.');
      }
      startBrailleMonitorSync({ immediate: false });
      window.setTimeout(forceBrailleMonitorSyncFromRunner, 350);
      Promise.resolve(runPromise).then(() => {
        logLine('Runner state na run completion.', getRunnerDebugSnapshot(targetWindow, payload));
        return waitForStepLinkRunnerIdle(app, runToken, payload.code);
      }).then((runnerIdle) => {
        if (runnerIdle && isCurrentStepLinkRun(runToken, payload.code)) {
          activeStepLinkCode = '';
          pendingStepLinkCode = '';
          updateRunToggleButtons();
          setSessionSendStatus(`Step-link ${payload.code} is klaar.`, 'success');
          $('linksStatus').innerHTML = statusText(`Step link ${payload.code} finished.`, 'success');
        }
      }).catch((err) => {
        logLine('Step-link run mislukt.', {
          code: payload.code,
          message: err.message || String(err)
        });
        const isCurrentRun = isCurrentStepLinkRun(runToken, payload.code);
        if (isCurrentRun) {
          activeStepLinkCode = '';
          pendingStepLinkCode = '';
          updateRunToggleButtons();
          setSessionSendStatus(err.message || String(err), 'danger');
          $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });
      logLine('Runner state direct na start-aanroep.', getRunnerDebugSnapshot(targetWindow, payload));
      window.setTimeout(() => {
        logLine('Runner state 500ms na start.', getRunnerDebugSnapshot(targetWindow, payload));
      }, 500);
      logLine('Step-link direct gestart vanuit admin.', payload);
    }

    async function stopCurrentStepLink(options = {}) {
      if (isStoppingStepLink) return;
      isStoppingStepLink = true;
      const silent = Boolean(options.silent);
      if (options.invalidateRun !== false) {
        stepLinkRunToken += 1;
      }
      updateRunToggleButtons();
      if (!silent) setSessionSendStatus('Script wordt gestopt...');
      try {
        const targetWindow = await waitForLoadWorkspaceOnline();
        const app = targetWindow?.BrailleBlocklyApp || null;
        if (!app || (typeof app.stopProgram !== 'function' && typeof app.stopAudio !== 'function')) {
          throw new Error('Stop API is niet beschikbaar.');
        }
        const audioStopped = typeof app.stopAudio === 'function'
          ? await app.stopAudio()
          : false;
        if (typeof app.stopProgram === 'function') {
          await app.stopProgram();
        }
        stopBrailleMonitorSync();
        clearAdminBrailleMonitor();
        const stoppedCode = activeStepLinkCode;
        activeStepLinkCode = '';
        if (!options.preservePending) {
          pendingStepLinkCode = '';
        }
        if (!silent) {
          setSessionSendStatus('Script gestopt.', 'success');
          $('linksStatus').innerHTML = statusText('Script stopped.', 'success');
        }
        logLine('Step-link runtime gestopt vanuit admin.', {
          stoppedCode,
          audioStopped: Boolean(audioStopped),
          reason: options.reason || 'manual'
        });
      } finally {
        isStoppingStepLink = false;
        updateRunToggleButtons();
      }
    }

    function getScriptDisplayMeta(itemOrScriptId = {}) {
      const scriptId = typeof itemOrScriptId === 'string'
        ? String(itemOrScriptId || '').trim()
        : String(itemOrScriptId?.scriptId || '').trim();
      const scriptData = scriptDataCache.get(scriptId) || null;
      const scriptListItem = scriptsCache.find((script) => String(script?.id || '').trim() === scriptId) || null;
      const meta = scriptData?.meta && typeof scriptData.meta === 'object'
        ? scriptData.meta
        : (scriptListItem?.meta && typeof scriptListItem.meta === 'object' ? scriptListItem.meta : {});
      return {
        title: String(meta.title || scriptData?.title || scriptListItem?.title || scriptId || '').trim(),
        description: String(meta.description || meta.prompt || '').trim(),
        instruction: String(meta.instruction || '').trim(),
        prompt: String(meta.prompt || '').trim()
      };
    }

    function getStepLinkInfo(item = {}) {
      const meta = item?.meta && typeof item.meta === 'object' ? item.meta : {};
      return String(meta.info || '').trim();
    }

    function buildStepLinkMeta(info = '') {
      const value = String(info || '').trim();
      return value ? { info: value } : {};
    }

    function stripDeprecatedStepInputs(stepInputs = {}) {
      const normalized = stepInputs && typeof stepInputs === 'object' ? { ...stepInputs } : {};
      delete normalized.repeat;
      delete normalized.student_code;
      return normalized;
    }

    function getStoredStepLinkMetaKeys(item = {}) {
      const meta = item?.meta && typeof item.meta === 'object' ? item.meta : {};
      return Object.entries(meta)
        .filter(([key]) => key !== 'info')
        .filter(([, value]) => {
          if (value == null) return false;
          if (typeof value === 'string') return value.trim() !== '';
          if (Array.isArray(value)) return value.length > 0;
          if (typeof value === 'object') return Object.keys(value).length > 0;
          return true;
        })
        .map(([key]) => key);
    }

    function collectExternalVariableNamesFromBlocks(blocklyState = {}) {
      const names = new Set();
      const visitBlock = (block) => {
        if (!block || typeof block !== 'object') return;
        if (['external_variable_get', 'external_variable_set', 'external_variable_exists', 'external_property_get'].includes(String(block.type || ''))) {
          const name = String(block.fields?.VAR || '').trim();
          if (name && name !== 'repeat') names.add(name);
        }
        Object.values(block.inputs || {}).forEach((input) => {
          visitBlock(input?.block);
          visitBlock(input?.shadow);
        });
        visitBlock(block.next?.block);
      };
      const rootBlocks = Array.isArray(blocklyState?.blocks?.blocks)
        ? blocklyState.blocks.blocks
        : (Array.isArray(blocklyState?.blocks) ? blocklyState.blocks : []);
      rootBlocks.forEach(visitBlock);
      return Array.from(names);
    }

    function getExternalVariablesFromScriptData(data = null) {
      const blocklyState = data?.blockly && typeof data.blockly === 'object' ? data.blockly : {};
      const variables = [
        data?.scriptVariables,
        data?.variablesMetadata,
        data?.meta?.scriptVariables,
        data?.meta?.variables,
        blocklyState.scriptVariables,
        blocklyState.variablesMetadata
      ].find((items) => Array.isArray(items)) || [];
      const normalized = variables
        .map((item) => {
          const name = String(item?.name || '').trim();
          if (!name || name === 'repeat' || name === 'student_code' || String(item?.scope || '').trim().toLowerCase() !== 'external') return null;
          return {
            name,
            type: String(item?.type || 'string').trim() || 'string',
            defaultValue: item?.defaultValue ?? '',
            description: String(item?.description || '').trim()
          };
        })
        .filter(Boolean);
      const seen = new Set(normalized.map((variable) => variable.name));
      collectExternalVariableNamesFromBlocks(blocklyState).forEach((name) => {
        if (name === 'student_code' || seen.has(name)) return;
        seen.add(name);
        normalized.push({
          name,
          type: 'string',
          defaultValue: '',
          description: 'External variable detected from Blockly blocks. Save the script again to add full metadata.'
        });
      });
      return normalized;
    }

    function isOldStepLink(item = {}) {
      const stepInputs = item?.stepInputs && typeof item.stepInputs === 'object' ? item.stepInputs : {};
      return getStoredStepLinkMetaKeys(item).length > 0
        || Object.prototype.hasOwnProperty.call(stepInputs, 'repeat')
        || ['word', 'text', 'letters', 'sounds'].some((key) => Object.prototype.hasOwnProperty.call(stepInputs, key));
    }

    function formatExternalVariableDefault(value) {
      if (value == null) return '';
      if (Array.isArray(value) || (typeof value === 'object' && value !== null)) {
        return JSON.stringify(value, null, 2);
      }
      return String(value);
    }

    function parseExternalVariableDefault(variable) {
      const type = String(variable?.type || 'string').trim();
      const value = variable?.defaultValue;
      if (type === 'boolean') return value === true || value === 'true' || value === 1 || value === '1';
      if (type === 'number') {
        const numberValue = Number(value);
        return Number.isFinite(numberValue) ? numberValue : 0;
      }
      if (type === 'array') {
        if (Array.isArray(value)) return value;
        try {
          const parsed = JSON.parse(String(value || '[]'));
          return Array.isArray(parsed) ? parsed : [];
        } catch {
          return String(value || '').split(',').map((item) => item.trim()).filter(Boolean);
        }
      }
      if (type === 'object') {
        if (value && typeof value === 'object' && !Array.isArray(value)) return value;
        try {
          const parsed = JSON.parse(String(value || '{}'));
          return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch {
          return {};
        }
      }
      return String(value ?? '');
    }

    function coerceExternalVariableInput(value, type = 'string') {
      const normalizedType = String(type || 'string').trim();
      if (normalizedType === 'boolean') return value === true || value === 'true' || value === 1 || value === '1';
      if (normalizedType === 'number') {
        const numberValue = Number(value);
        return Number.isFinite(numberValue) ? numberValue : 0;
      }
      if (normalizedType === 'array') {
        try {
          const parsed = JSON.parse(String(value || '[]'));
          return Array.isArray(parsed) ? parsed : [];
        } catch {
          return String(value || '').split(',').map((item) => item.trim()).filter(Boolean);
        }
      }
      if (normalizedType === 'object') {
        try {
          const parsed = JSON.parse(String(value || '{}'));
          return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch {
          return {};
        }
      }
      return String(value ?? '');
    }

    async function loadScriptData(scriptId) {
      const id = String(scriptId || '').trim();
      if (!id) return null;
      if (scriptDataCache.has(id)) return scriptDataCache.get(id);
      const url = new URL(SCRIPT_LOAD_URL, window.location.origin);
      url.searchParams.set('id', id);
      const res = await fetch(url.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data?.ok === false) {
        throw new Error(data?.error || `Could not load script ${id} (HTTP ${res.status})`);
      }
      scriptDataCache.set(id, data);
      return data;
    }

    function buildPayload({ overwrite = false } = {}) {
      const scriptId = getSelectedScriptId();
      const payload = {
        code: String($('codeInput').value || '').trim() || undefined,
        scriptId,
        stepId: buildAutoStepId(scriptId),
        active: true,
        meta: buildStepLinkMeta($('infoInput').value),
        stepInputs: {}
      };
      if (!payload.code) delete payload.code;
      if (overwrite) payload.overwrite = true;
      return payload;
    }

    async function loadScripts() {
      $('scriptsStatus').textContent = 'Loading scripts...';
      const res = await fetch(SCRIPT_LIST_URL, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      scriptsCache = Array.isArray(data.items) ? data.items : (Array.isArray(data.scripts) ? data.scripts : []);
      const select = $('scriptSelect');
      select.innerHTML = '<option value="">-- choose a script --</option>';
      scriptsCache.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id || '');
        const metaTitle = String(item?.meta?.title || '').trim();
        const title = String(item.title || metaTitle || item.id || '').trim();
        option.textContent = `${title} (${item.id})`;
        option.dataset.scriptId = String(item.id || '');
        select.appendChild(option);
      });
      $('scriptsStatus').textContent = `${scriptsCache.length} script(s) loaded.`;
    }

    async function applySelectedScriptToForm() {
      const scriptId = getSelectedScriptId();
      if (!scriptId) {
        setScriptMetaPreview();
        return;
      }
      await loadScriptData(scriptId).catch(() => null);
      setScriptMetaPreview(getScriptDisplayMeta(scriptId));
    }

    async function sendStepLinkSaveRequest(payload) {
      const res = await fetch(CREATE_LINK_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      return { res, data };
    }

    async function saveLink({ overwrite = false } = {}) {
      const payload = buildPayload({ overwrite });
      if (!payload.scriptId) throw new Error('Choose an online script first.');
      if (!payload.stepId) throw new Error('stepId could not be derived.');
      $('createStatus').textContent = overwrite ? 'Overwriting step link...' : 'Creating step link...';
      const { res, data } = await sendStepLinkSaveRequest(payload);
      if (res.status === 409 && !overwrite) {
        const code = String(data?.code || payload.code || '').trim();
        if (window.confirm(code ? `Step-link ${code} bestaat al. Overschrijven?` : 'Deze step-link bestaat al. Overschrijven?')) {
          await saveLink({ overwrite: true });
        } else {
          $('createStatus').innerHTML = statusText('Overschrijven geannuleerd.', 'secondary');
        }
        return;
      }
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      $('createStatus').innerHTML = statusText(`Step link created: ${data.code}`, 'success');
      $('codeInput').value = String(data.code || '');
      await loadLinks();
    }

    function buildLinkMutationPayload(item, overrides = {}) {
      return {
        methodId: String(item?.methodId || '').trim(),
        scriptId: String(item?.scriptId || '').trim(),
        stepId: String(item?.stepId || '').trim(),
        active: Boolean(item?.active),
        meta: buildStepLinkMeta(getStepLinkInfo(item)),
        stepInputs: stripDeprecatedStepInputs(item?.stepInputs),
        ...overrides
      };
    }

    async function updateLink(payload) {
      const res = await fetch(UPDATE_LINK_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      return data;
    }

    async function updateStepLinkActive(item, active) {
      const code = String(item?.code || '').trim();
      if (!code) throw new Error('Missing step link code');
      await updateLink(buildLinkMutationPayload(item, { originalCode: code, code, active }));
      $('linksStatus').innerHTML = statusText(`Step link ${code} is ${active ? 'active' : 'inactive'}.`, 'success');
      await loadLinks();
    }

    async function updateStepLinkCode(item, nextCode) {
      const originalCode = String(item?.code || '').trim();
      const code = String(nextCode || '').trim();
      if (!originalCode) throw new Error('Missing original step link code');
      if (!code) throw new Error('Step-link code mag niet leeg zijn.');
      if (code === originalCode) {
        $('linksStatus').innerHTML = statusText(`Step link ${code} is niet gewijzigd.`, 'secondary');
        return;
      }
      const data = await updateLink(buildLinkMutationPayload(item, { originalCode, code }));
      $('linksStatus').innerHTML = statusText(`Step link ${originalCode} gewijzigd naar ${data.code}.`, 'success');
      await loadLinks();
    }

    async function copyStepLink(item) {
      const sourceCode = String(item?.code || '').trim();
      if (!sourceCode) throw new Error('Missing step link code');
      const res = await fetch(CREATE_LINK_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildLinkMutationPayload(item))
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      pendingCopiedPlacement = {
        sourceCode,
        copiedCode: String(data.code || data?.record?.code || '').trim()
      };
      $('linksStatus').innerHTML = statusText(`Step link ${sourceCode} copied to ${pendingCopiedPlacement.copiedCode}.`, 'success');
      await loadLinks();
    }

    async function deleteLink(item) {
      const code = String(item?.code || '').trim();
      const methodId = String(item?.methodId || '').trim();
      if (!code) throw new Error('Missing step link code');
      if (!window.confirm(`Delete step link ${code}? This cannot be undone.`)) return;
      const res = await fetch(DELETE_LINK_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code, methodId })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      $('linksStatus').innerHTML = statusText(`Step link ${code} deleted.`, 'success');
      await loadLinks();
    }

    async function deleteOldLinks() {
      const oldLinks = linksCache.filter(isOldStepLink);
      if (!oldLinks.length) {
        $('linksStatus').innerHTML = statusText('No old step-links found.', 'secondary');
        return;
      }
      const codes = oldLinks.map((item) => String(item?.code || '').trim()).filter(Boolean);
      if (!window.confirm(`Delete ${codes.length} old step-link(s)? This cannot be undone.\n\n${codes.join(', ')}`)) return;
      for (const item of oldLinks) {
        const code = String(item?.code || '').trim();
        const methodId = String(item?.methodId || '').trim();
        if (!code) continue;
        const res = await fetch(DELETE_LINK_URL, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code, methodId })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      }
      $('linksStatus').innerHTML = statusText(`${codes.length} old step-link(s) deleted.`, 'success');
      await loadLinks();
    }

    function renderInlineStepLinkEditor(item, index) {
      const scriptMeta = getScriptDisplayMeta(item);
      const stepInputs = item?.stepInputs && typeof item.stepInputs === 'object' ? item.stepInputs : {};
      const externalVariables = getExternalVariablesFromScriptData(scriptDataCache.get(String(item?.scriptId || '').trim()) || null);
      const variablesHtml = externalVariables.length
        ? externalVariables.map((variable) => {
            const hasValue = Object.prototype.hasOwnProperty.call(stepInputs, variable.name);
            const value = hasValue ? stepInputs[variable.name] : parseExternalVariableDefault(variable);
            const valueText = variable.type === 'boolean'
              ? (Boolean(value) ? 'checked' : '')
              : `value="${escapeHtml(formatExternalVariableDefault(value))}"`;
            const controlHtml = variable.type === 'boolean'
              ? `<input type="checkbox" class="form-check-input" data-external-variable-name="${escapeHtml(variable.name)}" data-external-variable-type="${escapeHtml(variable.type)}" ${valueText}>`
              : variable.type === 'number'
                ? `<input type="number" class="form-control form-control-sm" data-external-variable-name="${escapeHtml(variable.name)}" data-external-variable-type="${escapeHtml(variable.type)}" ${valueText}>`
                : `<textarea rows="${variable.type === 'array' || variable.type === 'object' ? 3 : 2}" class="form-control form-control-sm" data-external-variable-name="${escapeHtml(variable.name)}" data-external-variable-type="${escapeHtml(variable.type)}">${escapeHtml(formatExternalVariableDefault(value))}</textarea>`;
            return `
              <div class="step-link-variable-row">
                <label class="form-label small">${escapeHtml(variable.name)} (${escapeHtml(variable.type)})</label>
                <div>
                  ${variable.description ? `<div class="form-hint">${escapeHtml(variable.description)}</div>` : ''}
                  ${controlHtml}
                </div>
              </div>
            `;
          }).join('')
        : '<div class="text-secondary small">Geen variabelen voor dit script.</div>';

      return `
        <div class="card bg-body-tertiary">
          <div class="card-body" data-inline-link-index="${index}">
            <div class="step-link-settings">
              <div>
                <label class="form-label small" for="inlineInfo${index}">Info</label>
                <textarea id="inlineInfo${index}" class="form-control form-control-sm" rows="2" data-field="info">${escapeHtml(getStepLinkInfo(item))}</textarea>
              </div>
              <div class="script-meta-preview" aria-label="Scriptgegevens">
                <div class="script-meta-preview__grid">
                  <div>
                    <div class="script-meta-preview__label">
                      <i class="ti ti-notes" aria-hidden="true"></i>
                      <span>Omschrijving</span>
                    </div>
                    <div class="script-meta-preview__value">${escapeHtml(scriptMeta.description || scriptMeta.prompt || '-')}</div>
                  </div>
                  <div>
                    <div class="script-meta-preview__label">
                      <i class="ti ti-list-check" aria-hidden="true"></i>
                      <span>Instructie</span>
                    </div>
                    <div class="script-meta-preview__value">${escapeHtml(scriptMeta.instruction || '-')}</div>
                  </div>
                </div>
              </div>
              ${variablesHtml}
            </div>
            <div class="btn-list mt-3">
              <button type="button" class="btn btn-primary btn-sm js-save-inline-link" data-inline-action-index="${index}">
                <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>
                Save
              </button>
            </div>
          </div>
        </div>
      `;
    }

    function readInlineStepInputs(container, item) {
      const currentInputs = item?.stepInputs && typeof item.stepInputs === 'object' ? item.stepInputs : {};
      const nextInputs = stripDeprecatedStepInputs(currentInputs);
      container.querySelectorAll('[data-external-variable-name]').forEach((control) => {
        const name = String(control.getAttribute('data-external-variable-name') || '').trim();
        const type = String(control.getAttribute('data-external-variable-type') || 'string').trim();
        if (!name) return;
        nextInputs[name] = control.type === 'checkbox'
          ? Boolean(control.checked)
          : coerceExternalVariableInput(control.value, type);
      });
      return nextInputs;
    }

    async function saveInlineLinkVariables(item, row) {
      const index = Number(row?.dataset?.linkIndex ?? -1);
      const container = document.querySelector(`[data-inline-link-index="${index}"]`);
      if (!item || !container) throw new Error('Inline editor not found');
      const code = String(item?.code || '').trim();
      if (!code) throw new Error('Missing step link code');
      await updateLink({
        originalCode: code,
        code,
        methodId: String(item?.methodId || '').trim(),
        scriptId: String(item?.scriptId || '').trim(),
        stepId: String(item?.stepId || '').trim(),
        active: Boolean(item?.active),
        meta: buildStepLinkMeta(container.querySelector('[data-field="info"]')?.value || ''),
        stepInputs: readInlineStepInputs(container, item)
      });
      $('linksStatus').innerHTML = statusText(`Step link ${code} saved.`, 'success');
      await loadLinks();
    }

    async function loadLinks() {
      $('linksStatus').textContent = 'Loading links...';
      const res = await fetch(LIST_LINKS_URL, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);

      let items = Array.isArray(data.items) ? data.items : [];
      await Promise.all(items.map((item) => loadScriptData(item?.scriptId || '').catch(() => null)));
      if (pendingCopiedPlacement?.sourceCode && pendingCopiedPlacement?.copiedCode) {
        const sourceIndex = items.findIndex((item) => String(item?.code || '').trim() === pendingCopiedPlacement.sourceCode);
        const copiedIndex = items.findIndex((item) => String(item?.code || '').trim() === pendingCopiedPlacement.copiedCode);
        if (sourceIndex >= 0 && copiedIndex >= 0 && copiedIndex !== sourceIndex + 1) {
          const copied = items[copiedIndex];
          items = items.filter((_, index) => index !== copiedIndex);
          const adjustedSourceIndex = items.findIndex((item) => String(item?.code || '').trim() === pendingCopiedPlacement.sourceCode);
          items.splice(adjustedSourceIndex + 1, 0, copied);
        }
        pendingCopiedPlacement = null;
      }
      linksCache = items;
      const oldLinkCount = items.filter(isOldStepLink).length;
      $('deleteOldLinksBtn').disabled = oldLinkCount === 0;
      $('deleteOldLinksBtn').innerHTML = `
        <i class="ti ti-trash me-1" aria-hidden="true"></i>
        Delete old step-links${oldLinkCount ? ` (${oldLinkCount})` : ''}
      `;
      $('linksStatus').textContent = `${items.length} link(s) loaded.`;
      const list = $('linksList');
      if (!items.length) {
        list.innerHTML = '<div class="empty"><div class="empty-title">No step links yet.</div></div>';
        return;
      }
      list.innerHTML = `
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Script</th>
                <th>Title</th>
                <th>Status</th>
                <th>Info</th>
                <th>Updated</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              ${items.map((item, index) => {
                const scriptMeta = getScriptDisplayMeta(item);
                const formatWarning = isOldStepLink(item);
                return `
                <tr data-link-index="${index}" aria-controls="linkEditorRow${index}">
                  <td>
                    <div class="d-flex align-items-center gap-2" style="min-width: 14rem">
                      <div class="input-group input-group-sm flex-nowrap">
                        <input type="text" class="form-control font-monospace js-link-code-input" value="${escapeHtml(item.code || '')}" maxlength="64" aria-label="Step-link code">
                        <button type="button" class="btn btn-outline-primary js-save-link-code" title="Save step-link code" aria-label="Save step-link code">
                          <i class="ti ti-device-floppy" aria-hidden="true"></i>
                        </button>
                      </div>
                      ${formatWarning ? '<i class="ti ti-alert-triangle text-warning" aria-hidden="true" title="Old step-link"></i><span class="visually-hidden">Old step-link</span>' : ''}
                    </div>
                  </td>
                  <td>${escapeHtml(item.scriptId || '-')}</td>
                  <td><strong>${escapeHtml(scriptMeta.title || '-')}</strong></td>
                  <td><span class="badge ${item.active ? 'bg-success-lt' : 'bg-secondary-lt'}">${item.active ? 'active' : 'inactive'}</span></td>
                  <td>${escapeHtml(getStepLinkInfo(item) || '-')}</td>
                  <td>${escapeHtml(formatStepLinkDateTime(item.updatedAt))}</td>
                  <td class="text-end">
                    <div class="btn-list flex-nowrap justify-content-end">
                      <button type="button" class="btn btn-icon btn-outline-primary js-run-toggle-link" data-link-code="${escapeHtml(item.code || '')}" title="Start step-link" aria-label="Start step link ${escapeHtml(item.code || '')}">
                        <i class="ti ti-player-play" aria-hidden="true"></i>
                      </button>
                      <button type="button" class="btn btn-icon ${item.active ? 'btn-outline-warning' : 'btn-outline-success'} js-toggle-active-link" title="${item.active ? 'Make inactive' : 'Make active'}" aria-label="${item.active ? 'Make step link inactive' : 'Make step link active'}">
                        <i class="ti ${item.active ? 'ti-toggle-right' : 'ti-toggle-left'}" aria-hidden="true"></i>
                      </button>
                      <button type="button" class="btn btn-icon btn-outline-secondary js-copy-link" title="Copy step link" aria-label="Copy step link ${escapeHtml(item.code || '')}">
                        <i class="ti ti-copy" aria-hidden="true"></i>
                      </button>
                      <button type="button" class="btn btn-icon btn-outline-secondary js-toggle-inline-link" title="Expand details" aria-label="Expand details" aria-expanded="false" aria-controls="linkEditorRow${index}">
                        <i class="ti ti-chevron-right" aria-hidden="true"></i>
                      </button>
                      <button type="button" class="btn btn-icon btn-outline-danger js-delete-link" title="Delete step link" aria-label="Delete step link ${escapeHtml(item.code || '')}">
                        <i class="ti ti-trash" aria-hidden="true"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr id="linkEditorRow${index}" class="d-none" hidden>
                  <td colspan="7">${renderInlineStepLinkEditor(item, index)}</td>
                </tr>
              `;
              }).join('')}
            </tbody>
          </table>
        </div>
      `;
      bindLinkListEvents(list);
      updateRunToggleButtons();
    }

    function bindLinkListEvents(list) {
      list.querySelectorAll('.js-save-link-code').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          const input = row?.querySelector('.js-link-code-input');
          if (!Number.isInteger(index) || index < 0 || !linksCache[index] || !input) return;
          updateStepLinkCode(linksCache[index], input.value).catch((err) => {
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
          });
        });
      });
      list.querySelectorAll('.js-link-code-input').forEach((input) => {
        input.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter') return;
          event.preventDefault();
          input.closest('tr[data-link-index]')?.querySelector('.js-save-link-code')?.click();
        });
      });
      list.querySelectorAll('.js-toggle-inline-link').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          const editorRow = $(`linkEditorRow${index}`);
          if (!editorRow) return;
          const isExpanded = button.getAttribute('aria-expanded') === 'true';
          button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
          button.setAttribute('title', isExpanded ? 'Expand details' : 'Collapse details');
          button.innerHTML = isExpanded
            ? '<i class="ti ti-chevron-right" aria-hidden="true"></i>'
            : '<i class="ti ti-chevron-down" aria-hidden="true"></i>';
          editorRow.hidden = isExpanded;
          editorRow.classList.toggle('d-none', isExpanded);
        });
      });
      list.querySelectorAll('.js-save-inline-link').forEach((button) => {
        button.addEventListener('click', () => {
          const index = Number(button?.dataset?.inlineActionIndex ?? -1);
          const row = list.querySelector(`tr[data-link-index="${index}"]`);
          if (!Number.isInteger(index) || index < 0 || !linksCache[index]) return;
          saveInlineLinkVariables(linksCache[index], row).catch((err) => {
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
          });
        });
      });
      list.querySelectorAll('.js-toggle-active-link').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          if (!Number.isInteger(index) || index < 0 || !linksCache[index]) return;
          updateStepLinkActive(linksCache[index], !Boolean(linksCache[index].active)).catch((err) => {
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
          });
        });
      });
      list.querySelectorAll('.js-run-toggle-link').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          if (!Number.isInteger(index) || index < 0 || !linksCache[index]) return;
          const item = linksCache[index];
          const code = String(item?.code || '').trim();
          const isCurrent = code && (code === activeStepLinkCode || code === pendingStepLinkCode);
          const action = isCurrent ? 'stop' : 'play';
          const runToken = action === 'play'
            ? ++stepLinkRunToken
            : stepLinkRunToken;
          if (action === 'play') {
            pendingStepLinkCode = code;
            updateRunToggleButtons();
          }
          logLine('Run-toggle knop in step-link rij gebruikt.', {
            action,
            index,
            code,
            scriptId: item?.scriptId || '',
            activeStepLinkCode,
            pendingStepLinkCode
          });
          const task = action === 'stop'
            ? stopCurrentStepLink({ reason: 'row-toggle' })
            : playStepLink(item, index, runToken);
          task.catch((err) => {
            if (action === 'play' && runToken !== stepLinkRunToken) {
              return;
            }
            if (pendingStepLinkCode === code) {
              pendingStepLinkCode = '';
            }
            if (activeStepLinkCode === code) {
              activeStepLinkCode = '';
            }
            setSessionSendStatus(err.message || String(err), 'danger');
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
            updateRunToggleButtons();
          });
        });
      });
      list.querySelectorAll('.js-copy-link').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          if (!Number.isInteger(index) || index < 0 || !linksCache[index]) return;
          copyStepLink(linksCache[index]).catch((err) => {
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
          });
        });
      });
      list.querySelectorAll('.js-delete-link').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          if (!Number.isInteger(index) || index < 0 || !linksCache[index]) return;
          deleteLink(linksCache[index]).catch((err) => {
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
          });
        });
      });
    }

    function setLogVisibility(visible) {
      const body = $('logBody');
      body.hidden = !visible;
      body.classList.toggle('d-none', !visible);
      $('toggleLogBtn').textContent = visible ? 'Hide log' : 'Unhide log';
    }

    async function copyLogToClipboard() {
      const text = Array.from($('logBox')?.children || [])
        .map((item) => String(item.innerText || item.textContent || '').trim())
        .filter(Boolean)
        .join('\n\n');
      if (!text) throw new Error('Log is empty.');
      await navigator.clipboard.writeText(text);
    }

    function bootstrap() {
      $('pageVersion').textContent = `Admin version ${ADMIN_VERSION}`;
      $('adminBuilderFrame').src = BLOCKLY_URL;
      initAdminBrailleMonitor();
      $('adminThumbLeftBtn')?.addEventListener('click', () => {
        dispatchRunnerInput({ type: 'thumbKey', key: 'left' }).catch((err) => setSessionSendStatus(err.message || String(err), 'danger'));
      });
      $('adminThumbRightBtn')?.addEventListener('click', () => {
        dispatchRunnerInput({ type: 'thumbKey', key: 'right' }).catch((err) => setSessionSendStatus(err.message || String(err), 'danger'));
      });
      $('adminCursor5Btn')?.addEventListener('click', () => {
        dispatchRunnerInput({ type: 'thumbKey', key: 'left-middle' }).catch((err) => setSessionSendStatus(err.message || String(err), 'danger'));
      });
      $('adminChord1Btn')?.addEventListener('click', () => {
        dispatchRunnerInput({ type: 'thumbKey', key: 'right-middle' }).catch((err) => setSessionSendStatus(err.message || String(err), 'danger'));
      });
      $('adminStopBtn')?.addEventListener('click', () => {
        stopCurrentStepLink({ reason: 'admin-stop-button' }).catch((err) => {
          setSessionSendStatus(err.message || String(err), 'danger');
          $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
        });
      });
      $('createLinkBtn').addEventListener('click', async () => {
        try {
          await saveLink();
        } catch (err) {
          $('createStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });
      $('scriptSelect').addEventListener('change', async () => {
        try {
          await applySelectedScriptToForm();
        } catch (err) {
          $('scriptsStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });
      $('refreshLinksBtn').addEventListener('click', async () => {
        try {
          await loadLinks();
        } catch (err) {
          $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });
      $('deleteOldLinksBtn').addEventListener('click', async () => {
        try {
          await deleteOldLinks();
        } catch (err) {
          $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });
      $('toggleLogBtn').addEventListener('click', () => {
        setLogVisibility(Boolean($('logBody')?.hidden));
      });
      $('copyLogBtn').addEventListener('click', async () => {
        try {
          await copyLogToClipboard();
        } catch (err) {
          $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });
      $('clearLogBtn').addEventListener('click', () => {
        $('logBox').replaceChildren();
      });
      setLogVisibility(false);
      setLoadingMessage('Scripts laden.');
      loadScripts()
        .then(() => {
          setLoadingMessage('Step-links laden.');
          return loadLinks();
        })
        .then(() => {
          setLoadingMessage('Beheeromgeving klaarzetten.');
          hideLoadingScreen();
        })
        .catch((err) => {
          $('scriptsStatus').innerHTML = statusText(err.message || String(err), 'danger');
          showLoadingError(`Laden mislukt: ${err.message || String(err)}`);
        });
    }

    bootstrap();
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
