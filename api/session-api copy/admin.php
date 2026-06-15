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
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Step Link Admin</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row ms-auto">
          <div class="nav-item">
            <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($sessionBase, 'laptop.php')) ?>">
              <i class="ti ti-device-laptop me-2" aria-hidden="true"></i>
              Open session resolver
            </a>
          </div>
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
              <div class="text-secondary mt-2">Create short QR step links by choosing an existing Blockly online script and adding lesson-step input data.</div>
              <div class="text-secondary small mt-2" id="pageVersion">Version pending...</div>
            </div>
            <div class="col-auto">
              <div class="btn-list">
                <span class="navbar-text text-secondary">
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
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="row row-cards">
            <div class="col-12 col-xl-6">
              <div class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">1. Script Selection</h2>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label" for="scriptSelect">Online script</label>
                    <select id="scriptSelect" class="form-select">
                      <option value="">-- load scripts first --</option>
                    </select>
                  </div>
                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="scriptIdInput">Script id</label>
                      <input id="scriptIdInput" class="form-control" type="text" placeholder="listen-and-type-001">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="stepIdInput">Step id</label>
                      <input id="stepIdInput" class="form-control" type="text" placeholder="lesson-1-step-3">
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="codeInput">Short code</label>
                      <input id="codeInput" class="form-control" type="text" placeholder="leave empty to auto-generate">
                    </div>
                  </div>
                  <div class="btn-list mt-3">
                    <button id="loadScriptsBtn" class="btn btn-primary" type="button">Load scripts</button>
                    <button id="fillSelectedBtn" class="btn btn-outline-secondary" type="button">Copy selected script id</button>
                  </div>
                  <div id="scriptsStatus" class="form-hint mt-2">Scripts not loaded yet.</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-6">
              <div class="card h-100">
                <div class="card-header">
                  <h2 class="card-title">2. Meta</h2>
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="titleInput">Title</label>
                      <input id="titleInput" class="form-control" type="text" placeholder="luister naar het woord">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="orderInput">Order</label>
                      <input id="orderInput" class="form-control" type="number" min="1" step="1" placeholder="3">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="bookInput">Book</label>
                      <input id="bookInput" class="form-control" type="text" placeholder="method-1">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="pageInput">Page</label>
                      <input id="pageInput" class="form-control" type="text" placeholder="12">
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="descriptionInput">Description</label>
                      <textarea id="descriptionInput" class="form-control" rows="3" placeholder="omschrijving van het script of de step"></textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="instructionInput">Instruction</label>
                      <textarea id="instructionInput" class="form-control" rows="3" placeholder="luister naar het woord en typ het"></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">3. Step Inputs</h2>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="textInput">Text</label>
                  <textarea id="textInput" class="form-control" rows="4" placeholder="bal"></textarea>
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="wordInput">Word</label>
                  <input id="wordInput" class="form-control" type="text" placeholder="bal">
                  <label class="form-label mt-3" for="repeatInput">Repeat</label>
                  <input id="repeatInput" class="form-control" type="number" min="1" step="1" value="1">
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="lettersInput">Letters</label>
                  <textarea id="lettersInput" class="form-control" rows="3" placeholder="b, a, l"></textarea>
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="soundsInput">Sounds</label>
                  <textarea id="soundsInput" class="form-control" rows="3" placeholder="b, a, l"></textarea>
                </div>
                <div class="col-12">
                  <div class="border-top pt-3 mt-1">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                      <div>
                        <div class="form-label mb-0">External variables</div>
                        <div class="form-hint">Waarden die dit Blockly-script buiten het script beschikbaar maakt of overschrijft.</div>
                      </div>
                      <span id="externalVariablesStatus" class="badge bg-secondary-lt">No script selected</span>
                    </div>
                    <div id="externalVariablesEditor" class="row g-3">
                      <div class="col-12 text-secondary">Kies eerst een Blockly-script.</div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-3">
                <label class="form-check form-check-inline">
                  <input id="activeInput" class="form-check-input" type="checkbox" checked>
                  <span class="form-check-label">Active</span>
                </label>
                <label class="form-check form-check-inline">
                  <input id="overwriteInput" class="form-check-input" type="checkbox">
                  <span class="form-check-label">Overwrite if code exists</span>
                </label>
              </div>
              <div class="btn-list mt-3">
                <button id="createLinkBtn" class="btn btn-success" type="button">Create step link</button>
                <button id="resetFormBtn" class="btn btn-outline-danger" type="button">Reset form</button>
              </div>
              <div id="createStatus" class="form-hint mt-2">Ready to create a step link.</div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Preview Payload</h2>
            </div>
            <div class="card-body">
              <div id="previewBox" class="row row-cards"></div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Existing Step Links</h2>
              <div class="card-actions">
                <button id="refreshLinksBtn" class="btn btn-primary btn-sm" type="button">Refresh links</button>
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
              <pre id="logBox" class="form-control font-monospace mb-0">Ready.</pre>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
    const ADMIN_VERSION = '2026-05-26 external-vars-1';
    const SCRIPT_LIST_URL = <?= $jsValue($urlFor($appBase, 'api/blockly-api/list.php')) ?>;
    const SCRIPT_LOAD_URL = <?= $jsValue($urlFor($appBase, 'api/blockly-api/load.php')) ?>;
    const CREATE_LINK_URL = <?= $jsValue($urlFor($sessionBase, 'create-link.php')) ?>;
    const UPDATE_LINK_URL = <?= $jsValue($urlFor($sessionBase, 'update-link.php')) ?>;
    const LIST_LINKS_URL = <?= $jsValue($urlFor($sessionBase, 'list-links.php')) ?>;
    const $ = (id) => document.getElementById(id);
    let scriptsCache = [];
    const scriptDataCache = new Map();
    let linksCache = [];
    let editingOriginalCode = '';

    function logLine(message, data) {
      const box = $('logBox');
      if (!box) return;
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      let line = `[${timestamp}] ${String(message || '').trim()}`;
      if (typeof data !== 'undefined') {
        try {
          line += `\n${JSON.stringify(data, null, 2)}`;
        } catch {
          line += `\n${String(data)}`;
        }
      }
      box.textContent = box.textContent.trim()
        ? `${box.textContent}\n${line}`
        : line;
      box.scrollTop = box.scrollHeight;
    }

    async function copyLogToClipboard() {
      const text = String($('logBox')?.textContent || '').trim();
      if (!text) {
        throw new Error('Log is empty');
      }
      await navigator.clipboard.writeText(text);
      logLine('Log copied to clipboard.');
    }

    function setLogVisibility(visible) {
      const body = $('logBody');
      const button = $('toggleLogBtn');
      if (!body || !button) return;
      body.hidden = !visible;
      body.classList.toggle('d-none', !visible);
      button.textContent = visible ? 'Hide log' : 'Unhide log';
    }

    function getAuthToken() {
      return '';
    }

    function setAuthToken(token) {
      const normalized = String(token || '').trim();
      if (normalized) {
        sessionStorage.setItem('braillestudioAuthToken', normalized);
        localStorage.setItem('braillestudioAuthToken', normalized);
        sessionStorage.setItem('elevenlabsAuthToken', normalized);
        localStorage.setItem('elevenlabsAuthToken', normalized);
      } else {
        sessionStorage.removeItem('braillestudioAuthToken');
        localStorage.removeItem('braillestudioAuthToken');
        sessionStorage.removeItem('elevenlabsAuthToken');
        localStorage.removeItem('elevenlabsAuthToken');
      }
      logLine(normalized ? 'Authentication token stored.' : 'Authentication token cleared.');
    }

    function buildHomepageAuthUrl(returnTo = window.location.href) {
      const url = new URL(AUTH_LOGIN_URL, window.location.origin);
      const target = String(returnTo || '').trim();
      if (target) {
        url.searchParams.set('returnTo', target);
      }
      return url.toString();
    }

    function openAuthenticationPopup() {
      return new Promise((resolve, reject) => {
        const currentOrigin = String(window.location.origin || '').trim();
        const useSameOriginStorageFlow = currentOrigin === 'https://www.tastenbraille.com';
        const authUrl = new URL(
          useSameOriginStorageFlow
            ? AUTH_LOGIN_URL
            : AUTH_BRIDGE_URL,
          window.location.origin
        );
        if (!useSameOriginStorageFlow) {
          authUrl.searchParams.set('origin', currentOrigin);
        }
        const initialToken = getAuthToken();
        const popup = window.open(
          authUrl.toString(),
          'braillestudioAuthBridge',
          'width=560,height=720,resizable=yes,scrollbars=yes'
        );
        if (!popup) {
          logLine('Authentication popup blocked.');
          reject(new Error('Popup blocked'));
          return;
        }
        try {
          popup.focus();
        } catch {}

        let settled = false;
        const cleanup = () => {
          window.removeEventListener('message', onMessage);
          if (pollTimer) window.clearInterval(pollTimer);
        };

        const onMessage = (event) => {
          if (event.origin !== 'https://www.tastenbraille.com') return;
          if (event.data?.type !== 'braillestudio-auth-token') return;
          const token = String(event.data?.token || '').trim();
          if (!token) return;
          setAuthToken(token);
          logLine('Authentication token received from popup message.');
          settled = true;
          cleanup();
          resolve(token);
        };

        window.addEventListener('message', onMessage);

        const pollTimer = window.setInterval(() => {
          const currentToken = getAuthToken();
          if (!settled && currentToken && currentToken !== initialToken) {
            logLine('Authentication token detected through storage polling.');
            settled = true;
            cleanup();
            try {
              popup.close();
            } catch {}
            resolve(currentToken);
            return;
          }
          if (popup.closed && !settled) {
            cleanup();
            logLine('Authentication popup closed before token was received.');
            reject(new Error('Authentication popup closed'));
          }
        }, 250);
      });
    }

    function isAuthenticated() {
      return true;
    }

    function requireAuthentication() {
      return true;
    }

    function renderAuthenticationState() {
      const pill = $('authStatePill');
      if (pill) {
        pill.textContent = 'Authenticated';
        pill.className = 'badge bg-success-lt';
      }
      $('loadScriptsBtn').disabled = false;
      $('createLinkBtn').disabled = false;
      $('refreshLinksBtn').disabled = false;
      $('scriptsStatus').textContent = 'Authenticated. You can load scripts and create links.';
      logLine('Authentication state: active.');
    }

    function renderEditingState() {
      $('createLinkBtn').textContent = editingOriginalCode ? 'Update step link' : 'Create step link';
      $('resetFormBtn').textContent = editingOriginalCode ? 'Cancel edit' : 'Reset form';
      $('createStatus').textContent = editingOriginalCode
        ? `Editing existing step link: ${editingOriginalCode}`
        : 'Ready to create a step link.';
    }

    function parseCommaList(value) {
      return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
    }

    function safeJson(value) {
      return JSON.stringify(value, null, 2);
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

    function formatListChips(items) {
      const list = Array.isArray(items) ? items.filter(Boolean) : [];
      if (!list.length) {
        return '<span class="text-secondary">-</span>';
      }
      return list.map((item) => `<span class="badge bg-secondary-lt me-1 mb-1">${escapeHtml(item)}</span>`).join('');
    }

    function collectExternalVariableNamesFromBlocks(blocklyState = {}) {
      const names = new Set();
      const visitBlock = (block) => {
        if (!block || typeof block !== 'object') return;
        if (
          ['external_variable_get', 'external_variable_set', 'external_variable_exists', 'external_property_get'].includes(String(block.type || ''))
        ) {
          const name = String(block.fields?.VAR || '').trim();
          if (name) names.add(name);
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
          if (!name || String(item?.scope || '').trim().toLowerCase() !== 'external') return null;
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
        if (seen.has(name)) return;
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
      if (type === 'boolean') {
        return value === true || value === 'true' || value === 1 || value === '1';
      }
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
      if (normalizedType === 'boolean') {
        return value === true || value === 'true' || value === 1 || value === '1';
      }
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
      if (scriptDataCache.has(id)) {
        return scriptDataCache.get(id);
      }
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

    function renderExternalVariablesEditor(variables = [], values = {}) {
      const editor = $('externalVariablesEditor');
      const status = $('externalVariablesStatus');
      if (!editor) return;
      const safeValues = values && typeof values === 'object' ? values : {};
      editor.innerHTML = '';
      if (status) {
        status.className = variables.length ? 'badge bg-blue-lt' : 'badge bg-secondary-lt';
        status.textContent = variables.length ? `${variables.length} external variable(s)` : 'No external variables';
      }
      if (!variables.length) {
        editor.innerHTML = '<div class="col-12 text-secondary">Dit Blockly-script heeft geen external variables.</div>';
        return;
      }
      variables.forEach((variable) => {
        const col = document.createElement('div');
        col.className = 'col-12 col-lg-6';
        const label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = `${variable.name} (${variable.type})`;
        col.appendChild(label);

        const hasValue = Object.prototype.hasOwnProperty.call(safeValues, variable.name);
        const value = hasValue ? safeValues[variable.name] : parseExternalVariableDefault(variable);
        let control;
        if (variable.type === 'boolean') {
          const checkLabel = document.createElement('label');
          checkLabel.className = 'form-check';
          control = document.createElement('input');
          control.type = 'checkbox';
          control.className = 'form-check-input';
          control.checked = Boolean(value);
          const checkText = document.createElement('span');
          checkText.className = 'form-check-label';
          checkText.textContent = variable.description || variable.name;
          checkLabel.appendChild(control);
          checkLabel.appendChild(checkText);
          col.appendChild(checkLabel);
        } else if (variable.type === 'number') {
          control = document.createElement('input');
          control.type = 'number';
          control.className = 'form-control';
          control.value = formatExternalVariableDefault(value);
          col.appendChild(control);
        } else {
          control = document.createElement('textarea');
          control.rows = variable.type === 'array' || variable.type === 'object' ? 3 : 2;
          control.className = 'form-control';
          control.value = formatExternalVariableDefault(value);
          col.appendChild(control);
        }
        control.dataset.externalVariableName = variable.name;
        control.dataset.externalVariableType = variable.type;
        control.title = variable.description || variable.name;
        control.addEventListener('input', renderPreview);
        control.addEventListener('change', renderPreview);

        if (variable.description && variable.type !== 'boolean') {
          const description = document.createElement('div');
          description.className = 'form-hint';
          description.textContent = variable.description;
          col.appendChild(description);
        }
        editor.appendChild(col);
      });
    }

    async function renderExternalVariablesForScript(scriptId, values = {}) {
      const id = String(scriptId || '').trim();
      const editor = $('externalVariablesEditor');
      const status = $('externalVariablesStatus');
      if (!id) {
        if (status) {
          status.className = 'badge bg-secondary-lt';
          status.textContent = 'No script selected';
        }
        if (editor) editor.innerHTML = '<div class="col-12 text-secondary">Kies eerst een Blockly-script.</div>';
        return [];
      }
      if (status) {
        status.className = 'badge bg-secondary-lt';
        status.textContent = 'Loading...';
      }
      if (editor) editor.innerHTML = '<div class="col-12 text-secondary">External variables laden...</div>';
      const scriptData = await loadScriptData(id);
      const variables = getExternalVariablesFromScriptData(scriptData);
      renderExternalVariablesEditor(variables, values);
      return variables;
    }

    function readExternalVariableInputs() {
      const values = {};
      document.querySelectorAll('[data-external-variable-name]').forEach((control) => {
        const name = String(control.getAttribute('data-external-variable-name') || '').trim();
        const type = String(control.getAttribute('data-external-variable-type') || 'string').trim();
        if (!name) return;
        values[name] = control.type === 'checkbox'
          ? Boolean(control.checked)
          : coerceExternalVariableInput(control.value, type);
      });
      return values;
    }

    function renderExternalInputChips(stepInputs = {}) {
      const normalKeys = new Set(['text', 'word', 'letters', 'repeat', 'sounds']);
      const entries = Object.entries(stepInputs || {})
        .filter(([key]) => !normalKeys.has(key))
        .map(([key, value]) => {
          const display = value && typeof value === 'object' ? JSON.stringify(value) : String(value ?? '');
          return `<span class="badge bg-blue-lt me-1 mb-1">${escapeHtml(key)}: ${escapeHtml(display || '(empty)')}</span>`;
        });
      return entries.length ? entries.join('') : '<span class="text-secondary">-</span>';
    }

    function renderPayloadKeyValue(rows) {
      return `
        <div class="list-group list-group-flush">
          ${rows.map(([label, value]) => `
            <div class="list-group-item">
              <div class="row g-2">
                <div class="col-12 col-md-4 fw-semibold">${escapeHtml(label)}</div>
                <div class="col text-break">${value}</div>
              </div>
            </div>
          `).join('')}
        </div>
      `;
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

    function buildPayload() {
      const orderRaw = String($('orderInput').value || '').trim();
      const orderValue = orderRaw === '' ? null : Number(orderRaw);
      const payload = {
        code: String($('codeInput').value || '').trim() || undefined,
        scriptId: String($('scriptIdInput').value || '').trim(),
        stepId: String($('stepIdInput').value || '').trim(),
        active: Boolean($('activeInput').checked),
        overwrite: Boolean($('overwriteInput').checked),
        meta: {
          title: String($('titleInput').value || '').trim(),
          description: String($('descriptionInput').value || '').trim(),
          instruction: String($('instructionInput').value || '').trim(),
          book: String($('bookInput').value || '').trim(),
          page: String($('pageInput').value || '').trim(),
          order: Number.isFinite(orderValue) ? orderValue : null
        },
        stepInputs: {
          text: String($('textInput').value || '').trim(),
          word: String($('wordInput').value || '').trim(),
          letters: parseCommaList($('lettersInput').value),
          repeat: Math.max(1, Math.floor(Number($('repeatInput').value || 1) || 1)),
          sounds: parseCommaList($('soundsInput').value),
          ...readExternalVariableInputs()
        }
      };

      if (!payload.code) {
        delete payload.code;
      }
      if (payload.meta.order == null) {
        delete payload.meta.order;
      }
      return payload;
    }

    async function beginEditLink(item) {
      if (!item || typeof item !== 'object') {
        return;
      }
      editingOriginalCode = String(item.code || '').trim();
      $('codeInput').value = String(item.code || '');
      $('scriptIdInput').value = String(item.scriptId || '');
      $('stepIdInput').value = String(item.stepId || '');
      $('titleInput').value = String(item?.meta?.title || '');
      $('descriptionInput').value = String(item?.meta?.description || '');
      $('instructionInput').value = String(item?.meta?.instruction || '');
      $('bookInput').value = String(item?.meta?.book || '');
      $('pageInput').value = String(item?.meta?.page || '');
      $('orderInput').value = item?.meta?.order ?? '';
      $('textInput').value = String(item?.stepInputs?.text || '');
      $('wordInput').value = String(item?.stepInputs?.word || '');
      $('lettersInput').value = Array.isArray(item?.stepInputs?.letters) ? item.stepInputs.letters.join(', ') : '';
      $('soundsInput').value = Array.isArray(item?.stepInputs?.sounds) ? item.stepInputs.sounds.join(', ') : '';
      $('repeatInput').value = String(item?.stepInputs?.repeat ?? 1);
      $('activeInput').checked = Boolean(item.active);
      $('overwriteInput').checked = false;
      try {
        await renderExternalVariablesForScript(item.scriptId || '', item?.stepInputs || {});
      } catch (err) {
        $('externalVariablesStatus').className = 'badge bg-danger-lt';
        $('externalVariablesStatus').textContent = 'Load failed';
        $('externalVariablesEditor').innerHTML = `<div class="col-12 text-danger">${escapeHtml(err.message || String(err))}</div>`;
      }
      renderEditingState();
      renderPreview();
      $('createStatus').innerHTML = statusText(`Editing step link ${editingOriginalCode}`, 'success');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      logLine('Editing existing step link.', { code: editingOriginalCode, item });
    }

    function renderPreview() {
      const payload = buildPayload();
      $('previewBox').innerHTML = `
        <div class="col-12 col-lg-4">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Basis</h3></div>
            <div class="card-body p-0">
              ${renderPayloadKeyValue([
                ['Script id', escapeHtml(payload.scriptId || '-')],
                ['Step id', escapeHtml(payload.stepId || '-')],
                ['Short code', escapeHtml(payload.code || 'auto-generate')],
                ['Active', payload.active ? '<span class="badge bg-success-lt">Yes</span>' : '<span class="badge bg-secondary-lt">No</span>'],
                ['Overwrite', payload.overwrite ? '<span class="badge bg-warning-lt">Yes</span>' : '<span class="badge bg-secondary-lt">No</span>']
              ])}
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Meta</h3></div>
            <div class="card-body p-0">
              ${renderPayloadKeyValue([
                ['Title', escapeHtml(payload.meta?.title || '-')],
                ['Description', payload.meta?.description ? `<pre class="form-control font-monospace mb-0">${escapeHtml(payload.meta.description)}</pre>` : '<span class="text-secondary">-</span>'],
                ['Instruction', payload.meta?.instruction ? `<pre class="form-control font-monospace mb-0">${escapeHtml(payload.meta.instruction)}</pre>` : '<span class="text-secondary">-</span>'],
                ['Book', escapeHtml(payload.meta?.book || '-')],
                ['Page', escapeHtml(payload.meta?.page || '-')],
                ['Order', escapeHtml(payload.meta?.order ?? '-')]
              ])}
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Step inputs</h3></div>
            <div class="card-body p-0">
              ${renderPayloadKeyValue([
                ['Text', payload.stepInputs?.text ? `<pre class="form-control font-monospace mb-0">${escapeHtml(payload.stepInputs.text)}</pre>` : '<span class="text-secondary">-</span>'],
                ['Word', escapeHtml(payload.stepInputs?.word || '-')],
                ['Letters', formatListChips(payload.stepInputs?.letters)],
                ['Repeat', escapeHtml(payload.stepInputs?.repeat ?? 1)],
                ['Sounds', formatListChips(payload.stepInputs?.sounds)],
                ['External variables', renderExternalInputChips(payload.stepInputs)]
              ])}
            </div>
          </div>
        </div>
      `;
    }

    function renderScriptsSelect(items) {
      const select = $('scriptSelect');
      select.innerHTML = '<option value="">-- choose a script --</option>';
      items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id || '');
        const metaTitle = String(item?.meta?.title || '').trim();
        const metaDescription = String(item?.meta?.description || '').trim();
        const title = String(item.title || metaTitle || item.id || '').trim();
        option.textContent = `${title} (${item.id})`;
        option.dataset.scriptId = String(item.id || '');
        option.dataset.scriptTitle = title;
        option.dataset.scriptDescription = metaDescription;
        option.dataset.scriptInstruction = String(item?.meta?.instruction || '').trim();
        select.appendChild(option);
      });
    }

    async function applySelectedScriptToForm({ overwrite = false } = {}) {
      const select = $('scriptSelect');
      const option = select.options[select.selectedIndex];
      const scriptId = String(option?.dataset?.scriptId || '').trim();
      const scriptTitle = String(option?.dataset?.scriptTitle || '').trim();
      const scriptDescription = String(option?.dataset?.scriptDescription || '').trim();
      const scriptInstruction = String(option?.dataset?.scriptInstruction || '').trim();
      if (!scriptId) {
        return false;
      }

      $('scriptIdInput').value = scriptId;
      const autoStepId = buildAutoStepId(scriptId);
      if (autoStepId && (overwrite || !$('stepIdInput').value.trim())) {
        $('stepIdInput').value = autoStepId;
      }
      if (overwrite || !$('titleInput').value.trim()) {
        $('titleInput').value = scriptTitle;
      }
      if (overwrite || !$('descriptionInput').value.trim()) {
        $('descriptionInput').value = scriptDescription;
      }
      if (overwrite || !$('instructionInput').value.trim()) {
        $('instructionInput').value = scriptInstruction || scriptDescription;
      }
      try {
        await renderExternalVariablesForScript(scriptId);
      } catch (err) {
        $('externalVariablesStatus').className = 'badge bg-danger-lt';
        $('externalVariablesStatus').textContent = 'Load failed';
        $('externalVariablesEditor').innerHTML = `<div class="col-12 text-danger">${escapeHtml(err.message || String(err))}</div>`;
      }
      renderPreview();
      logLine('Applied selected script to form.', {
        scriptId,
        stepId: $('stepIdInput').value.trim(),
        title: scriptTitle,
        description: scriptDescription,
        instruction: scriptInstruction
      });
      return true;
    }

    async function loadScripts() {
      requireAuthentication();
      $('scriptsStatus').textContent = 'Loading scripts...';
      logLine('Loading script list.', { url: SCRIPT_LIST_URL });
      const res = await fetch(SCRIPT_LIST_URL, {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json'
        }
      });
      const data = await res.json().catch(() => ({}));
      logLine('Script list response received.', {
        status: res.status,
        ok: Boolean(res.ok),
        itemCount: Array.isArray(data.items) ? data.items.length : null,
        keys: Object.keys(data || {})
      });
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }

      scriptsCache = Array.isArray(data.items)
        ? data.items
        : Array.isArray(data.scripts)
          ? data.scripts
          : [];
      renderScriptsSelect(scriptsCache);
      $('scriptsStatus').textContent = `${scriptsCache.length} script(s) loaded.`;
      logLine(`Loaded ${scriptsCache.length} script(s).`);
    }

    async function fillFromSelectedScript() {
      const applied = await applySelectedScriptToForm({ overwrite: true });
      if (!applied) {
        throw new Error('Choose a script first');
      }
    }

    async function saveLink() {
      requireAuthentication();
      const payload = buildPayload();
      if (!payload.scriptId) {
        throw new Error('scriptId is required');
      }
      if (!payload.stepId) {
        throw new Error('stepId is required');
      }

      const isEditing = Boolean(editingOriginalCode);
      $('createStatus').textContent = isEditing ? 'Updating step link...' : 'Creating step link...';
      logLine(isEditing ? 'Updating step link.' : 'Creating step link.', {
        originalCode: editingOriginalCode || null,
        payload
      });
      const requestUrl = isEditing ? UPDATE_LINK_URL : CREATE_LINK_URL;
      const requestPayload = isEditing
        ? { originalCode: editingOriginalCode, ...payload }
        : payload;
      const res = await fetch(requestUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestPayload)
      });
      const data = await res.json().catch(() => ({}));
      logLine(`${isEditing ? 'Update' : 'Create'} step link response received.`, {
        status: res.status,
        ok: Boolean(res.ok),
        response: data
      });
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }

      $('createStatus').innerHTML = statusText(`Step link ${isEditing ? 'updated' : 'created'}: ${data.code}`, 'success');
      $('codeInput').value = String(data.code || '');
      editingOriginalCode = '';
      renderEditingState();
      renderPreview();
      await loadLinks();
    }

    async function loadLinks() {
      requireAuthentication();
      $('linksStatus').textContent = 'Loading links...';
      logLine('Loading existing step links.', { url: LIST_LINKS_URL });
      const res = await fetch(LIST_LINKS_URL, {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json'
        }
      });
      const data = await res.json().catch(() => ({}));
      logLine('Existing links response received.', {
        status: res.status,
        ok: Boolean(res.ok),
        itemCount: Array.isArray(data.items) ? data.items.length : null
      });
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }

      const items = Array.isArray(data.items) ? data.items : [];
      $('linksStatus').textContent = `${items.length} link(s) loaded.`;
      const list = $('linksList');
      list.innerHTML = '';

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
                <th>Step</th>
                <th>Title</th>
                <th>Word</th>
                <th>Repeat</th>
                <th>Letters</th>
                <th>Sounds / External</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              ${items.map((item, index) => `
                <tr data-link-index="${index}">
                  <td><span class="badge bg-primary-lt">${escapeHtml(item.code || '-')}</span></td>
                  <td>${escapeHtml(item.scriptId || '-')}</td>
                  <td>${escapeHtml(item.stepId || '-')}</td>
                  <td>
                    <div><strong>${escapeHtml(item?.meta?.title || '-')}</strong></div>
                    ${item?.meta?.instruction ? `<div class="text-secondary small mt-1">${escapeHtml(item.meta.instruction)}</div>` : ''}
                  </td>
                  <td>${escapeHtml(item?.stepInputs?.word || '-')}</td>
                  <td>${escapeHtml(item?.stepInputs?.repeat ?? '-')}</td>
                  <td>${formatListChips(item?.stepInputs?.letters)}</td>
                  <td>
                    ${formatListChips(item?.stepInputs?.sounds)}
                    <div class="mt-1">${renderExternalInputChips(item?.stepInputs || {})}</div>
                  </td>
                  <td><span class="badge ${item.active ? 'bg-success-lt' : 'bg-secondary-lt'}">${item.active ? 'active' : 'inactive'}</span></td>
                  <td>${escapeHtml(item.updatedAt || '-')}</td>
                  <td><button type="button" class="btn btn-outline-secondary btn-sm js-edit-link">Edit</button></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
      linksCache = items;
      list.querySelectorAll('.js-edit-link').forEach((button) => {
        button.addEventListener('click', () => {
          const row = button.closest('tr[data-link-index]');
          const index = Number(row?.dataset?.linkIndex ?? -1);
          if (!Number.isInteger(index) || index < 0 || !linksCache[index]) {
            return;
          }
          beginEditLink(linksCache[index]).catch((err) => {
            $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
          });
        });
      });
    }

    function resetForm() {
      editingOriginalCode = '';
      $('scriptSelect').value = '';
      $('scriptIdInput').value = '';
      $('stepIdInput').value = '';
      $('codeInput').value = '';
      $('titleInput').value = '';
      $('instructionInput').value = '';
      $('descriptionInput').value = '';
      $('bookInput').value = '';
      $('pageInput').value = '';
      $('orderInput').value = '';
      $('textInput').value = '';
      $('wordInput').value = '';
      $('lettersInput').value = '';
      $('soundsInput').value = '';
      $('repeatInput').value = '1';
      $('activeInput').checked = true;
      $('overwriteInput').checked = false;
      renderExternalVariablesForScript('').catch(() => {});
      renderEditingState();
      renderPreview();
    }

    function bindPreviewFields() {
      [
        'scriptSelect',
        'scriptIdInput',
        'stepIdInput',
        'codeInput',
        'titleInput',
        'descriptionInput',
        'instructionInput',
        'bookInput',
        'pageInput',
        'orderInput',
        'textInput',
        'wordInput',
        'lettersInput',
        'soundsInput',
        'repeatInput',
        'activeInput',
        'overwriteInput'
      ].forEach((id) => {
        const node = $(id);
        if (!node) return;
        node.addEventListener('input', renderPreview);
        node.addEventListener('change', renderPreview);
      });
    }

    function bootstrap() {
      bindPreviewFields();
      renderPreview();
      renderAuthenticationState();
      $('pageVersion').textContent = `Admin version ${ADMIN_VERSION}`;
      logLine('Admin page bootstrapped.', {
        version: ADMIN_VERSION,
        scriptListUrl: SCRIPT_LIST_URL,
        linksUrl: LIST_LINKS_URL
      });

      $('loadScriptsBtn').addEventListener('click', async () => {
        try {
          await loadScripts();
        } catch (err) {
          $('scriptsStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });

      $('fillSelectedBtn').addEventListener('click', async () => {
        try {
          await fillFromSelectedScript();
        } catch (err) {
          $('scriptsStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });

      $('scriptSelect').addEventListener('change', async () => {
        try {
          await applySelectedScriptToForm();
        } catch (err) {
          $('scriptsStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });

      $('scriptIdInput').addEventListener('change', async () => {
        try {
          await renderExternalVariablesForScript($('scriptIdInput').value || '');
          renderPreview();
        } catch (err) {
          $('externalVariablesStatus').className = 'badge bg-danger-lt';
          $('externalVariablesStatus').textContent = 'Load failed';
          $('externalVariablesEditor').innerHTML = `<div class="col-12 text-danger">${escapeHtml(err.message || String(err))}</div>`;
        }
      });

      $('createLinkBtn').addEventListener('click', async () => {
        try {
          await saveLink();
        } catch (err) {
          $('createStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });

      $('refreshLinksBtn').addEventListener('click', async () => {
        try {
          await loadLinks();
        } catch (err) {
          $('linksStatus').innerHTML = statusText(err.message || String(err), 'danger');
        }
      });

      $('resetFormBtn').addEventListener('click', resetForm);
      setLogVisibility(false);
      renderEditingState();
      $('toggleLogBtn').addEventListener('click', () => {
        const visible = Boolean($('logBody')?.hidden);
        setLogVisibility(visible);
      });
      $('copyLogBtn').addEventListener('click', async () => {
        try {
          await copyLogToClipboard();
        } catch (err) {
          const message = err.message || String(err);
          $('linksStatus').innerHTML = statusText(message, 'danger');
        }
      });
      $('clearLogBtn').addEventListener('click', () => {
        $('logBox').textContent = 'Log cleared.';
      });

      $('authBtn')?.addEventListener('click', async () => {
        try {
          $('authStatus').textContent = 'Opening authentication popup...';
          logLine('Authentication popup requested.');
          await openAuthenticationPopup();
          renderAuthenticationState();
          $('authStatus').className = 'alert alert-success mt-3 mb-0';
          $('authStatus').textContent = 'Authentication completed.';
          $('linksStatus').textContent = 'Authentication completed.';
          await loadScripts();
          await loadLinks();
        } catch (err) {
          const message = err.message || String(err);
          logLine('Authentication failed.', { message });
          const isPopupIssue = /popup blocked|popup closed/i.test(message);
          if (isPopupIssue) {
            const authUrl = buildHomepageAuthUrl(window.location.href);
            $('authStatus').className = 'alert alert-danger mt-3 mb-0';
            $('authStatus').innerHTML = `${escapeHtml(message)}. <a class="alert-link" href="${escapeHtml(authUrl)}" target="_blank" rel="noopener noreferrer">Open login page</a>`;
          } else {
            $('authStatus').className = 'alert alert-danger mt-3 mb-0';
            $('authStatus').textContent = message;
          }
          $('linksStatus').innerHTML = statusText(message, 'danger');
        }
      });

      window.addEventListener('storage', (event) => {
        if (event.key === 'braillestudioAuthToken' || event.key === 'elevenlabsAuthToken') {
          logLine('Authentication storage updated from another window.', {
            key: event.key,
            hasValue: Boolean(String(event.newValue || '').trim())
          });
          renderAuthenticationState();
        }
      });

      if (isAuthenticated()) {
        loadScripts().catch((err) => {
          const message = err.message || String(err);
          $('scriptsStatus').innerHTML = statusText(message, 'danger');
          logLine('Automatic script load failed.', { message });
        });
        loadLinks().catch((err) => {
          const message = err.message || String(err);
          $('linksStatus').innerHTML = statusText(message, 'danger');
          logLine('Automatic link load failed.', { message });
        });
      }
    }

    bootstrap();
  </script>
</body>
</html>
