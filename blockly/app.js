/* ---------------- UI helpers ---------------- */
function setBootStage(stage, extra = {}) {
  if (typeof window.__setBrailleBlocklyBootStage === 'function') {
    window.__setBrailleBlocklyBootStage(stage, extra);
  }
}

setBootStage('app-script-start');

let externalLogHandler = null;
let brailleMonitorUi = null;
let scriptBrailleMonitorUi = null;
const BLOCKLY_GRID_SNAP_KEY = 'blockly_grid_snap';
const BLOCKLY_MONITOR_VISIBLE_KEY = 'blockly_monitor_visible';
const BLOCKLY_SIDEBAR_WIDTH_KEY = 'blockly_sidebar_width';
const DEFAULT_LESSON_DATA_URL = '../klanken/aanvankelijklijst.json';
const FONEMEN_NL_JSON_URLS = [
  '../klanken/fonemen_nl_standaard.json',
  'https://www.tastenbraille.com/braillestudio/data/klanken/fonemen_nl_standaard.json'
];
const COMPOUND_LIBRARY_API_BASES = [
  'https://www.tastenbraille.com/braillestudio/blockly-library'
];
const ELEVENLABS_TTS_API_URL = 'https://www.tastenbraille.com/braillestudio/api/elevenlabs-api/tts.php';
const BRAILLE_BLOCKLY_AUTH_CONFIG = window.BrailleBlocklyAuth || {};
const ELEVENLABS_AUTH_API_BASE = BRAILLE_BLOCKLY_AUTH_CONFIG.authApiBasePath || '/braillestudio/authentication-api/';
const AUTH_BRIDGE_PAGE_URL = BRAILLE_BLOCKLY_AUTH_CONFIG.authBridgePageUrl || '/braillestudio/authentication.php?mode=bridge';
const AUTH_LOGIN_PAGE_URL = BRAILLE_BLOCKLY_AUTH_CONFIG.authLoginPageUrl || '/braillestudio/authentication.php';
const AUTH_HOMEPAGE_ORIGIN = BRAILLE_BLOCKLY_AUTH_CONFIG.homepageOrigin || 'https://www.tastenbraille.com';
const BRAILLEBRIDGE_PROTOCOL_URL = 'braillebridge://';
const BRAILLESTUDIO_AUTH_TOKEN_KEY = 'braillestudioAuthToken';
const ELEVENLABS_AUTH_TOKEN_KEY = 'elevenlabsAuthToken';
const BLOCK_CLIPBOARD_STORAGE_KEY = 'brailleBlocklyBlockClipboard';
const WS_URL = 'ws://localhost:5000/ws';
const AUTO_RECONNECT_MS = 2000;
const BRAILLEBRIDGE_STARTUP_TIMEOUT_MS = 4000;
let currentFileHandle = null;
let ws = null;
let wsConnected = false;
let reconnectTimer = null;
let autoConnectEnabled = true;
let bridgeLaunchTimer = null;
let bridgeLaunchState = 'idle';
let gridSnapEnabled = true;
let brailleMonitorVisible = true;
let sidebarWidth = 780;
var pendingStart = false;
var pendingStartGeneration = 0;
var runGeneration = 0;
var workspace = null;
let uiExecutionState = {
  phase: 'idle',
  detail: 'Script is not running.'
};
function createInitialRuntimeState() {
  return {
    stopped: true,
    text: '',
    brailleUnicode: '',
    textCaret: 0,
    cellCaret: 0,
    editorMode: 'off',
    insertMode: 'off',
    caretVisible: true,
    lastThumbKey: '',
    lastCursorCell: -1,
    lastChord: '',
    lastEditorKey: '',
    lastVirtualKeyCode: 0,
    lastSound: '',
    lastTimerName: '',
    lastTimerTick: 0,
    lastWsNotice: '',
    activeTable: '',
    lineId: 0,
    lockInjectedLessonRecord: false,
    stepCompletion: null,
    lessonCompletion: null,
    programEndedGeneration: -1,
    programEndedCompletedGeneration: -1,
    external: {},
    procedures: new Map()
  };
}
var runtime = createInitialRuntimeState();
function getRuntime() {
  if (!runtime || typeof runtime !== 'object') {
    runtime = createInitialRuntimeState();
  }
  return runtime;
}

function log(message) {
  const text = String(message ?? '');
  if (
    text.startsWith('WS connecting to ') ||
    text.startsWith('WS connected to ') ||
    text === 'WS already connected/connecting' ||
    text === 'WS error' ||
    text.startsWith('WS disconnected code=')
  ) {
    return;
  }
  const box = document.getElementById('logBox');
  const now = new Date().toLocaleTimeString();
  const line = `[${now}] ${text}`;
  if (box) {
    box.value = `${line}\n${box.value}`;
    box.scrollTop = 0;
  }
  if (typeof externalLogHandler === 'function') {
    try {
      externalLogHandler(line);
    } catch {}
  }
}

window.BrailleBlocklyLog = function (message) {
  log(message);
};

function getInstructionCatalogItems() {
  return Array.isArray(window.BrailleStudioInstructionCatalog)
    ? window.BrailleStudioInstructionCatalog
    : [];
}

log(`Page href: ${window.location.href}`);
log(`Page origin: ${window.location.origin || '(none)'}`);
log(`Page protocol: ${window.location.protocol || '(none)'}`);

function clearLogBox() {
  const box = document.getElementById('logBox');
  if (box) box.value = '';
}

async function copyLogBox() {
  const box = document.getElementById('logBox');
  const text = String(box?.value || '');
  if (text === '') {
    log('Log copy skipped: log is empty');
    return;
  }

  const copyFromTextarea = () => {
    if (!box) return false;
    const previousSelectionStart = box.selectionStart;
    const previousSelectionEnd = box.selectionEnd;
    const previousActive = document.activeElement;
    box.focus();
    box.select();
    const ok = document.execCommand('copy');
    box.setSelectionRange(previousSelectionStart || 0, previousSelectionEnd || 0);
    if (previousActive instanceof HTMLElement && previousActive !== box) {
      previousActive.focus();
    }
    return ok;
  };

  try {
    if (navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(text);
        log('Log copied to clipboard');
        return;
      } catch (err) {
        log('Clipboard API copy failed, trying textarea fallback: ' + (err?.message || String(err)));
      }
    }
    if (copyFromTextarea()) {
      log('Log copied to clipboard');
      return;
    }
    throw new Error('textarea copy fallback failed');
  } catch (err) {
    log('Log copy failed: ' + (err?.message || String(err)));
  }
}

function formatLogValue(value) {
  if (Array.isArray(value)) return value.map((item) => String(item ?? '')).join(',');
  if (typeof value === 'string') return value === '' ? '""' : value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  if (value == null) return '';
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
}

function formatDurationMs(startTime) {
  const elapsed = performance.now() - startTime;
  return `${elapsed.toFixed(elapsed >= 100 ? 0 : 1)}ms`;
}

function formatTextJoinValue(value) {
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  if (value == null) return '';
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
}

function renderWsControl() {
}

function renderBrailleBridgeIndicator() {
  const indicator = document.getElementById('bridgeLaunchIndicator');
  if (!indicator) return;
  const state = wsConnected ? 'connected' : bridgeLaunchState;
  indicator.classList.remove('is-connected', 'is-starting', 'is-disconnected');
  if (state === 'connected') {
    indicator.classList.add('is-connected');
    indicator.setAttribute('aria-label', 'BrailleBridge connected');
    indicator.setAttribute('title', 'BrailleBridge connected');
    return;
  }
  if (state === 'starting') {
    indicator.classList.add('is-starting');
    indicator.setAttribute('aria-label', 'BrailleBridge starting');
    indicator.setAttribute('title', 'BrailleBridge starting');
    return;
  }
  indicator.classList.add('is-disconnected');
  indicator.setAttribute('aria-label', 'BrailleBridge unavailable');
  indicator.setAttribute('title', 'BrailleBridge unavailable');
}

function scheduleBrailleBridgeFailureCheck() {
  if (bridgeLaunchTimer) {
    clearTimeout(bridgeLaunchTimer);
  }
  bridgeLaunchTimer = setTimeout(() => {
    bridgeLaunchTimer = null;
    if (!wsConnected) {
      bridgeLaunchState = 'failed';
      renderBrailleBridgeIndicator();
    }
  }, BRAILLEBRIDGE_STARTUP_TIMEOUT_MS);
}

function requestBrailleBridgeLaunch(reason = 'startup') {
  bridgeLaunchState = 'starting';
  renderBrailleBridgeIndicator();
  scheduleBrailleBridgeFailureCheck();
  try {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.src = BRAILLEBRIDGE_PROTOCOL_URL;
    document.body.appendChild(iframe);
    setTimeout(() => {
      iframe.remove();
    }, 1500);
    if (reason !== 'auto-reconnect') {
      log(`BrailleBridge launch requested (${reason})`);
    }
  } catch (err) {
    bridgeLaunchState = 'failed';
    renderBrailleBridgeIndicator();
    log('BrailleBridge launch failed: ' + (err?.message || String(err)));
  }
}

function setWsBadge(isConnected) {
  wsConnected = !!isConnected;
  bridgeLaunchState = wsConnected ? 'connected' : (bridgeLaunchState === 'starting' ? 'starting' : 'failed');
  renderWsControl();
  renderBrailleBridgeIndicator();
  renderBrailleMonitorToggleControl();
  renderScriptBrailleLine();
}

function renderBrailleLine(msg) {
  const box = document.getElementById('brailleLineBox');
  if (box) {
    box.textContent = msg
      ? JSON.stringify(msg, null, 2)
      : '';
  }

  if (!brailleMonitorUi && window.BrailleMonitor && typeof window.BrailleMonitor.init === 'function') {
    const host = document.getElementById('brailleMonitorComponent');
    if (host) {
      brailleMonitorUi = window.BrailleMonitor.init({
        containerId: 'brailleMonitorComponent',
        showInfo: false
      });
    }
  }

  if (!brailleMonitorUi) return;
  if (!msg) {
    brailleMonitorUi.clear();
    return;
  }

  const brailleUnicode = String(msg?.braille?.unicodeText ?? '');
  const sourceText = String(msg?.sourceText ?? '');
  const caretPosition = Number.isInteger(msg?.meta?.caretCellPosition)
    ? msg.meta.caretCellPosition
    : msg?.caret?.cellIndex;
  const textCaretPosition = Number.isInteger(msg?.meta?.caretTextPosition)
    ? msg.meta.caretTextPosition
    : msg?.caret?.textIndex;
  const caretVisible = typeof msg?.caretVisible === 'boolean' ? msg.caretVisible : true;

  brailleMonitorUi.setBrailleUnicode(brailleUnicode, sourceText, {
    caretPosition,
    textCaretPosition,
    caretVisible
  });
}

function renderScriptBrailleLine() {
  const rt = getRuntime();
  const scriptRow = document.getElementById('scriptBrailleMonitorRow');
  const thumbRow = document.getElementById('thumbControlsRow');
  if (scriptRow) {
    scriptRow.classList.toggle('is-hidden', !brailleMonitorVisible || wsConnected);
  }
  if (thumbRow) {
    thumbRow.classList.toggle('is-hidden', !brailleMonitorVisible);
  }

  if (!scriptBrailleMonitorUi && window.BrailleMonitor && typeof window.BrailleMonitor.init === 'function') {
    const host = document.getElementById('scriptBrailleMonitorComponent');
    if (host) {
      scriptBrailleMonitorUi = window.BrailleMonitor.init({
        containerId: 'scriptBrailleMonitorComponent',
        showInfo: false
      });
    }
  }

  if (!scriptBrailleMonitorUi) return;

  const sourceText = String(rt?.text ?? '');
  if (!sourceText) {
    scriptBrailleMonitorUi.clear();
    return;
  }

  scriptBrailleMonitorUi.setText(sourceText);
  if (typeof scriptBrailleMonitorUi.setCaretPosition === 'function') {
    scriptBrailleMonitorUi.setCaretPosition(Number.isInteger(rt?.textCaret) ? rt.textCaret : null);
  }
}

function renderScriptMetadata(meta = null) {
  const titleInput = document.getElementById('scriptMetaTitle');
  const descriptionInput = document.getElementById('scriptMetaDescription');
  const instructionInput = document.getElementById('scriptMetaInstruction');
  const promptInput = document.getElementById('scriptMetaPrompt');
  if (titleInput) titleInput.value = String(meta?.title || '');
  if (descriptionInput) descriptionInput.value = String(meta?.description || '');
  if (instructionInput) instructionInput.value = String(meta?.instruction || '');
  if (promptInput) promptInput.value = String(meta?.prompt || '');
  renderInstructionTtsControl();
  renderStatusScriptName();
}

function renderStatusScriptName() {
  const target = document.getElementById('statusScriptName');
  if (!target) return;
  const scriptId = String(document.getElementById('onlineScriptIdInput')?.value || currentOnlineScriptId || '').trim();
  const fileName = String(document.getElementById('fileNameInput')?.value || '').trim();
  const name = scriptId || fileName || 'Geen script geopend';
  target.textContent = name;
  target.parentElement?.setAttribute('title', `Script-id: ${name}`);
}

function readScriptMetadataFromInputs() {
  const titleInput = document.getElementById('scriptMetaTitle');
  const descriptionInput = document.getElementById('scriptMetaDescription');
  const instructionInput = document.getElementById('scriptMetaInstruction');
  const promptInput = document.getElementById('scriptMetaPrompt');
  return {
    title: String(titleInput?.value || '').trim(),
    description: String(descriptionInput?.value || '').trim(),
    instruction: String(instructionInput?.value || '').trim(),
    prompt: String(promptInput?.value || '').trim()
  };
}

function makeVariableId(name, scope) {
  const base = String(name || 'variable').trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'variable';
  return `${scope}_${base}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 7)}`;
}

function variableIdMatchesName(id, name, scope) {
  const base = String(name || 'variable').trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'variable';
  return String(id || '').startsWith(`${scope}_${base}_`);
}

function normalizeVariableName(name) {
  return String(name || '').trim();
}

function normalizeScriptVariable(raw = {}, fallbackScope = 'internal') {
  const scope = 'external';
  const type = SCRIPT_VARIABLE_TYPES.includes(String(raw.type || '').toLowerCase())
    ? String(raw.type).toLowerCase()
    : 'string';
  const name = normalizeVariableName(raw.name);
  if (!name) return null;
  const rawId = String(raw.id || '').trim();
  return {
    id: rawId && variableIdMatchesName(rawId, name, scope) ? rawId : makeVariableId(name, scope),
    name,
    scope,
    type,
    defaultValue: raw.defaultValue ?? '',
    description: String(raw.description || '')
  };
}

function normalizeScriptVariables(items) {
  if (!Array.isArray(items)) return [];
  const seen = new Set();
  return items
    .map((item) => normalizeScriptVariable(item))
    .filter(Boolean)
    .filter((item) => {
      const key = `${item.scope}:${item.name}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
}

function getScriptVariables(scope = '') {
  const normalizedScope = String(scope || '').trim().toLowerCase();
  return normalizedScope ? scriptVariables.filter((item) => item.scope === normalizedScope) : [...scriptVariables];
}

function findScriptVariable(scope, nameOrId) {
  const key = String(nameOrId || '').trim();
  if (!key) return null;
  return getScriptVariables(scope).find((item) => item.name === key || item.id === key) || null;
}

function parseVariableDefaultValue(variable) {
  const raw = variable?.defaultValue;
  const type = String(variable?.type || 'string');
  if (raw == null) {
    if (type === 'number') return 0;
    if (type === 'boolean') return false;
    if (type === 'array') return [];
    if (type === 'object') return {};
    return '';
  }
  if (type === 'number') return Number(raw) || 0;
  if (type === 'boolean') {
    if (typeof raw === 'boolean') return raw;
    return ['true', '1', 'yes', 'ja'].includes(String(raw).trim().toLowerCase());
  }
  if (type === 'array' || type === 'object') {
    if (typeof raw === 'object') return structuredClone(raw);
    try {
      const parsed = JSON.parse(String(raw || '').trim() || (type === 'array' ? '[]' : '{}'));
      return type === 'array' ? (Array.isArray(parsed) ? parsed : []) : (parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {});
    } catch {
      return type === 'array' ? [] : {};
    }
  }
  return String(raw);
}

function serializeScriptVariables() {
  return getScriptVariables().map((item) => ({ ...item }));
}

function setScriptVariables(items, { markDirty = false } = {}) {
  scriptVariables = normalizeScriptVariables(items);
  renderVariableList();
  refreshVariableToolbox();
  initializeRuntimeVariableScopes();
  if (markDirty) setWorkspaceDirty(true);
}

function getVariableOptionLabel(variable) {
  if (variable.scope === 'external') {
    return variable.name;
  }
  const label = variable.scope === 'external' ? 'EXT' : 'INT';
  return `${label} ${variable.name}`;
}

window.BrailleBlocklyVariableOptions = function (scope) {
  const vars = getScriptVariables(scope);
  if (!vars.length) {
    return [[scope === 'external' ? 'no ext vars' : 'no internal variables', '']];
  }
  return vars.map((item) => [getVariableOptionLabel(item), item.name]);
};

window.BrailleBlocklyVariableTooltip = function (scope, name) {
  const variable = findScriptVariable(scope, name);
  if (!variable) return scope === 'external' ? 'External runtime input.' : 'Internal script state.';
  return [
    `${variable.scope} ${variable.type}: ${variable.name}`,
    variable.description
  ].filter(Boolean).join('\n');
};

function refreshVariableToolbox() {
  const toolbox = typeof workspace?.getToolbox === 'function' ? workspace.getToolbox() : null;
  if (toolbox && typeof toolbox.refreshSelection === 'function') {
    toolbox.refreshSelection();
  }
}

function formatVariableValue(value) {
  if (value == null) return '';
  if (typeof value === 'object') {
    try { return JSON.stringify(value); } catch {}
  }
  return String(value);
}

function renderVariableList() {
  const list = document.getElementById('variableList');
  const summary = document.getElementById('scriptVariableSummary');
  if (!list) return;
  list.innerHTML = '';
  const variables = serializeScriptVariables();
  const externalCount = variables.filter((item) => item.scope === 'external').length;
  if (summary) {
    summary.textContent = variables.length
      ? `${externalCount} external variable${externalCount === 1 ? '' : 's'}. Descriptions are saved with this script.`
      : 'No external variables defined.';
  }
  variables.forEach((variable) => {
    const item = document.createElement('div');
    item.className = `variable-item variable-item--${variable.scope}`;
    item.dataset.variableId = variable.id;
    item.title = variable.description || `${variable.scope} ${variable.type}`;
    item.tabIndex = 0;

    const top = document.createElement('div');
    top.className = 'variable-item__top';

    const badge = document.createElement('span');
    badge.className = `variable-badge variable-badge--${variable.scope}`;
    badge.textContent = variable.scope;
    top.appendChild(badge);

    const name = document.createElement('span');
    name.className = 'variable-item__name';
    name.textContent = variable.name;
    top.appendChild(name);

    const editButton = document.createElement('button');
    editButton.className = 'btn btn-outline-secondary btn-icon btn-sm ms-auto';
    editButton.type = 'button';
    editButton.setAttribute('aria-label', `Edit ${variable.name}`);
    editButton.title = 'Edit variable';
    editButton.innerHTML = '<i class="ti ti-dots-vertical" aria-hidden="true"></i>';
    editButton.addEventListener('click', (event) => {
      event.stopPropagation();
      openVariableContextMenu(variable.id, editButton.getBoundingClientRect().left, editButton.getBoundingClientRect().bottom);
    });
    top.appendChild(editButton);
    item.appendChild(top);

    const meta = document.createElement('div');
    meta.className = 'variable-item__meta';
    meta.textContent = `${variable.type} - default: ${formatVariableValue(variable.defaultValue) || '(empty)'}`;
    item.appendChild(meta);

    if (variable.description) {
      const description = document.createElement('div');
      description.className = 'variable-item__description';
      description.textContent = variable.description;
      item.appendChild(description);
    }

    item.addEventListener('contextmenu', (event) => {
      event.preventDefault();
      openVariableContextMenu(variable.id, event.clientX, event.clientY);
    });
    item.addEventListener('dblclick', () => openVariableModal(variable.id));
    item.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openVariableModal(variable.id);
      }
      if (event.key === 'ContextMenu') {
        event.preventDefault();
        const rect = item.getBoundingClientRect();
        openVariableContextMenu(variable.id, rect.left + 12, rect.bottom);
      }
    });
    list.appendChild(item);
  });
}

function getVariableModalElements() {
  return {
    modal: document.getElementById('variableModal'),
    title: document.getElementById('variableModalTitle'),
    id: document.getElementById('variableEditId'),
    external: document.getElementById('variableScopeExternal'),
    name: document.getElementById('variableNameInput'),
    type: document.getElementById('variableTypeInput'),
    defaultValue: document.getElementById('variableDefaultInput'),
    description: document.getElementById('variableDescriptionInput'),
    deleteButton: document.getElementById('variableModalDelete')
  };
}

function openVariableModal(variableId = '', initialScope = 'external') {
  const elements = getVariableModalElements();
  if (!elements.modal) return;
  const variable = scriptVariables.find((item) => item.id === variableId) || null;
  const scope = 'external';
  elements.title.textContent = variable ? 'Edit external variable' : 'Add external variable';
  elements.id.value = variable?.id || '';
  elements.name.value = variable?.name || '';
  elements.type.value = variable?.type || 'string';
  elements.defaultValue.value = formatVariableValue(variable?.defaultValue ?? '');
  elements.description.value = variable?.description || '';
  if (elements.deleteButton) elements.deleteButton.style.display = variable ? '' : 'none';
  elements.modal.classList.add('is-open');
  elements.modal.setAttribute('aria-hidden', 'false');
  setTimeout(() => elements.name?.focus(), 0);
}

function closeVariableModal() {
  const modal = document.getElementById('variableModal');
  if (!modal) return;
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
}

function saveVariableFromModal() {
  const elements = getVariableModalElements();
  const existingId = String(elements.id?.value || '').trim();
  const scope = 'external';
  const name = normalizeVariableName(elements.name?.value);
  if (!name) {
    elements.name?.focus();
    elements.name?.reportValidity?.();
    alert('Variable name is required.');
    return;
  }
  const duplicate = scriptVariables.find((item) => item.id !== existingId && item.scope === scope && item.name === name);
  if (duplicate) {
    alert(`A ${scope} variable named "${name}" already exists.`);
    return;
  }
  const variable = normalizeScriptVariable({
    id: existingId || makeVariableId(name, scope),
    scope: 'external',
    name,
    type: elements.type?.value || 'string',
    defaultValue: elements.defaultValue?.value || '',
    description: elements.description?.value || ''
  }, scope);
  if (!variable) return;
  const next = existingId
    ? scriptVariables.map((item) => item.id === existingId ? variable : item)
    : [...scriptVariables, variable];
  setScriptVariables(next, { markDirty: true });
  closeVariableModal();
}

function deleteScriptVariable(variableId) {
  const variable = scriptVariables.find((item) => item.id === variableId);
  if (!variable) return;
  if (!confirm(`Delete variable "${variable.name}"? Existing blocks that use it will return an empty value.`)) return;
  setScriptVariables(scriptVariables.filter((item) => item.id !== variableId), { markDirty: true });
  closeVariableContextMenu();
  closeVariableModal();
}

function openVariableContextMenu(variableId, x, y) {
  const menu = document.getElementById('variableContextMenu');
  if (!menu) return;
  activeVariableContextId = variableId;
  menu.style.left = `${Math.max(8, Number(x) || 8)}px`;
  menu.style.top = `${Math.max(8, Number(y) || 8)}px`;
  menu.classList.add('is-open');
  menu.setAttribute('aria-hidden', 'false');
}

function closeVariableContextMenu() {
  const menu = document.getElementById('variableContextMenu');
  if (!menu) return;
  menu.classList.remove('is-open');
  menu.setAttribute('aria-hidden', 'true');
  activeVariableContextId = '';
}

function bindVariableEditorControls() {
  const addExternalButton = document.getElementById('addExternalVariableBtn');
  if (addExternalButton && !addExternalButton.dataset.initialized) {
    addExternalButton.addEventListener('click', () => openVariableModal('', 'external'));
    addExternalButton.dataset.initialized = '1';
  }
  const saveButton = document.getElementById('variableModalSave');
  if (saveButton && !saveButton.dataset.initialized) {
    saveButton.addEventListener('click', saveVariableFromModal);
    saveButton.dataset.initialized = '1';
  }
  const cancelButton = document.getElementById('variableModalCancel');
  if (cancelButton && !cancelButton.dataset.initialized) {
    cancelButton.addEventListener('click', closeVariableModal);
    cancelButton.dataset.initialized = '1';
  }
  const deleteButton = document.getElementById('variableModalDelete');
  if (deleteButton && !deleteButton.dataset.initialized) {
    deleteButton.addEventListener('click', () => deleteScriptVariable(String(document.getElementById('variableEditId')?.value || '').trim()));
    deleteButton.dataset.initialized = '1';
  }
  const editContext = document.getElementById('variableContextEdit');
  if (editContext && !editContext.dataset.initialized) {
    editContext.addEventListener('click', () => {
      const id = activeVariableContextId;
      closeVariableContextMenu();
      if (id) openVariableModal(id);
    });
    editContext.dataset.initialized = '1';
  }
  const deleteContext = document.getElementById('variableContextDelete');
  if (deleteContext && !deleteContext.dataset.initialized) {
    deleteContext.addEventListener('click', () => deleteScriptVariable(activeVariableContextId));
    deleteContext.dataset.initialized = '1';
  }
  document.addEventListener('click', (event) => {
    const menu = document.getElementById('variableContextMenu');
    if (menu && menu.classList.contains('is-open') && !menu.contains(event.target)) {
      closeVariableContextMenu();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeVariableContextMenu();
      closeVariableModal();
    }
  });
}

function getElevenLabsAuthToken() {
  const fromSession = String(sessionStorage.getItem(BRAILLESTUDIO_AUTH_TOKEN_KEY) || '').trim()
    || String(sessionStorage.getItem(ELEVENLABS_AUTH_TOKEN_KEY) || '').trim();
  if (fromSession) return fromSession;
  return String(localStorage.getItem(BRAILLESTUDIO_AUTH_TOKEN_KEY) || '').trim()
    || String(localStorage.getItem(ELEVENLABS_AUTH_TOKEN_KEY) || '').trim();
}

function parseJwtPayload(token) {
  const value = String(token || '').trim();
  if (!value) return null;
  const parts = value.split('.');
  if (parts.length !== 3) return null;
  try {
    const normalized = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
    return JSON.parse(atob(padded));
  } catch {
    return null;
  }
}

function getValidElevenLabsAuthToken() {
  const token = getElevenLabsAuthToken();
  if (!token) return '';
  const payload = parseJwtPayload(token);
  const exp = Number(payload?.exp || 0);
  if (!exp || exp > Math.floor(Date.now() / 1000)) {
    return token;
  }
  setElevenLabsAuthToken('');
  return '';
}

function setElevenLabsAuthToken(token) {
  const normalized = String(token || '').trim();
  if (normalized) {
    sessionStorage.setItem(BRAILLESTUDIO_AUTH_TOKEN_KEY, normalized);
    localStorage.setItem(BRAILLESTUDIO_AUTH_TOKEN_KEY, normalized);
    sessionStorage.setItem(ELEVENLABS_AUTH_TOKEN_KEY, normalized);
    localStorage.setItem(ELEVENLABS_AUTH_TOKEN_KEY, normalized);
  } else {
    sessionStorage.removeItem(BRAILLESTUDIO_AUTH_TOKEN_KEY);
    localStorage.removeItem(BRAILLESTUDIO_AUTH_TOKEN_KEY);
    sessionStorage.removeItem(ELEVENLABS_AUTH_TOKEN_KEY);
    localStorage.removeItem(ELEVENLABS_AUTH_TOKEN_KEY);
  }
  renderElevenLabsAuthStatus();
  renderInstructionTtsControl();
}

function getElevenLabsAuthHeaders(extra = {}) {
  const headers = { ...extra };
  const token = getValidElevenLabsAuthToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

function getElevenLabsAuthBaseUrl() {
  return new URL(ELEVENLABS_AUTH_API_BASE, window.location.origin).toString();
}

function getElevenLabsAuthEndpointUrl(fileName) {
  return new URL(fileName, getElevenLabsAuthBaseUrl()).toString();
}

function renderElevenLabsAuthStatus(message = '') {
  const loginBtn = document.getElementById('elevenlabsLoginBtn');
  if (!loginBtn) return;
  const token = getValidElevenLabsAuthToken();

  loginBtn.className = token ? 'btn btn-outline-secondary' : 'btn btn-primary';
  loginBtn.innerHTML = token
    ? '<i class="ti ti-logout me-2" aria-hidden="true"></i>Logout'
    : '<i class="ti ti-login me-2" aria-hidden="true"></i>Authentication';
  loginBtn.disabled = false;
  loginBtn.title = message || (token ? 'Authenticated.' : 'Not authenticated.');
}

function applyEmbeddedSessionPlayerControls() {
  if (!IS_EMBEDDED_SESSION_PLAYER) return;
  const controls = [
    'onlineScriptsSelect',
    'onlineScriptStatusInput',
    'onlineRefreshBtn',
    'onlineSaveBtn',
    'onlineSaveAsBtn',
    'onlineDeleteBtn'
  ];
  for (const id of controls) {
    const el = document.getElementById(id);
    if (!el) continue;
    el.disabled = true;
    el.setAttribute('aria-disabled', 'true');
    el.title = 'Online scripts are disabled in session player mode.';
  }
}

async function refreshOnlineScriptsIfAuthenticated(reason = 'state-change') {
  const state = refreshState.onlineScripts;
  const now = Date.now();
  if (state.promise) {
    log(`Online scripts refresh skipped (${reason}): already running`);
    await state.promise;
    return;
  }
  if (now - state.completedAt < REFRESH_DEDUPE_MS) {
    log(`Online scripts refresh skipped (${reason}): recently refreshed`);
    return;
  }
  try {
    log(`Refreshing online scripts (${reason})`);
    state.promise = refreshOnlineScripts();
    await state.promise;
    state.completedAt = Date.now();
  } catch (err) {
    log(`Online scripts refresh failed (${reason}): ${err.message}`);
  } finally {
    state.promise = null;
  }
}

async function refreshCompoundLibraryIfAuthenticated(reason = 'state-change') {
  const state = refreshState.compoundLibrary;
  const now = Date.now();
  if (state.promise) {
    log(`Compound library refresh skipped (${reason}): already running`);
    await state.promise;
    return;
  }
  if (now - state.completedAt < REFRESH_DEDUPE_MS) {
    log(`Compound library refresh skipped (${reason}): recently refreshed`);
    return;
  }
  try {
    log(`Refreshing compound library (${reason})`);
    state.promise = refreshCompoundLibrary();
    await state.promise;
    state.completedAt = Date.now();
  } catch (err) {
    log(`Compound library refresh failed (${reason}): ${err.message}`);
  } finally {
    state.promise = null;
  }
}

function buildHomepageAuthUrl(returnTo = window.location.href) {
  const url = new URL(AUTH_LOGIN_PAGE_URL, window.location.origin);
  const target = String(returnTo || '').trim();
  if (target) {
    url.searchParams.set('returnTo', target);
  }
  return url.toString();
}

function requireHomepageAuthOnProduction() {
  return false;
}

async function loginElevenLabsAuth() {
  renderElevenLabsAuthStatus('Open authentication popup...');
  try {
    const token = await openBrailleStudioAuthPopup();
    setElevenLabsAuthToken(token);
    log(`Token stored after authentication: ${getElevenLabsAuthToken() ? 'present' : 'missing'}`);
    renderElevenLabsAuthStatus('Authenticated.');
    log('BrailleStudio auth popup completed');
    try {
      log('Refreshing online scripts after authentication');
      await refreshOnlineScripts();
    } catch (refreshErr) {
      log(`Online scripts refresh after authentication failed: ${refreshErr?.message || refreshErr}`);
    }
    try {
      log('Refreshing compound library after authentication');
      await refreshCompoundLibrary();
    } catch (refreshErr) {
      log(`Compound library refresh after authentication failed: ${refreshErr?.message || refreshErr}`);
    }
  } catch (err) {
    renderElevenLabsAuthStatus(`Authentication failed: ${err.message}`);
    log(`BrailleStudio auth popup failed: ${err.message}`);
  }
}

function logoutElevenLabsAuth() {
  setElevenLabsAuthToken('');
  renderElevenLabsAuthStatus('Logged out.');
  log('ElevenLabs auth logged out');
}

function openBrailleStudioAuthPopup() {
  return new Promise((resolve, reject) => {
    const currentOrigin = String(window.location.origin || '').trim();
    const useSameOriginStorageFlow = currentOrigin === AUTH_HOMEPAGE_ORIGIN;
    const authUrl = new URL(
      useSameOriginStorageFlow
        ? AUTH_LOGIN_PAGE_URL
        : AUTH_BRIDGE_PAGE_URL,
      window.location.origin
    );
    if (!useSameOriginStorageFlow) {
      authUrl.searchParams.set('origin', currentOrigin);
    }
    const initialToken = getElevenLabsAuthToken();
    log(`Opening auth popup for origin: ${currentOrigin}`);

    const popup = window.open(
      authUrl.toString(),
      'braillestudioAuthBridge',
      'width=560,height=720,resizable=yes,scrollbars=yes'
    );

    if (!popup) {
      reject(new Error('Popup blocked'));
      return;
    }

    let settled = false;
    const cleanup = () => {
      window.removeEventListener('message', onMessage);
      if (pollTimer) window.clearInterval(pollTimer);
    };

    const onMessage = (event) => {
      if (event.origin !== AUTH_HOMEPAGE_ORIGIN) return;
      if (event.data?.type !== 'braillestudio-auth-token') return;
      const token = String(event.data?.token || '').trim();
      log(`Auth popup message received from ${event.origin}; token ${token ? 'present' : 'missing'}`);
      if (!token) return;
      settled = true;
      cleanup();
      resolve(token);
    };

    window.addEventListener('message', onMessage);

    const pollTimer = window.setInterval(() => {
      const currentToken = getElevenLabsAuthToken();
      if (!settled && currentToken && currentToken !== initialToken) {
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
        reject(new Error('Authentication popup closed'));
      }
    }, 250);
  });
}

function getInstructionTtsState() {
  const instruction = String(document.getElementById('scriptMetaInstruction')?.value || '').trim();
  const scriptId = String(currentOnlineScriptId || '').trim();
  const voiceId = String(document.getElementById('instructionTtsVoiceSelect')?.value || 'yO6w2xlECAQRFP6pX7Hw').trim();
  return {
    scriptId,
    instruction,
    voiceId,
    canPlay: instruction !== '',
    canSave: scriptId !== '' && instruction !== ''
  };
}

function getCurrentInstructionOption() {
  const state = getInstructionTtsState();
  if (!state.scriptId) {
    return null;
  }
  return [`generated - ${state.scriptId}`, state.scriptId];
}

window.BrailleBlocklyGetCurrentInstructionOption = getCurrentInstructionOption;

function buildGeneratedInstructionFileName(scriptId, index) {
  const normalizedId = String(scriptId || '').trim();
  const normalizedIndex = Math.max(1, Number(index) || 1);
  return `${normalizedId}-${String(normalizedIndex).padStart(3, '0')}.mp3`;
}

function parseInstructionSegments(scriptId, instructionText) {
  const normalizedScriptId = String(scriptId || '').trim();
  const source = String(instructionText || '');
  const segments = [];
  let generatedIndex = 0;

  const pushTextSegment = (value) => {
    const text = String(value || '').replace(/\s+/g, ' ').trim();
    if (!text) return;
    generatedIndex += 1;
    segments.push({
      kind: 'generated',
      text,
      audioRef: `instructions/${buildGeneratedInstructionFileName(normalizedScriptId, generatedIndex)}`,
      fileName: buildGeneratedInstructionFileName(normalizedScriptId, generatedIndex)
    });
  };

  const pushPlaceholderSegment = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return;
    const items = raw.includes(',')
      ? raw.split(',').map((item) => item.trim()).filter(Boolean)
      : [raw];
    items.forEach((item) => {
      const file = String(item).trim();
      if (!file) return;
      segments.push({
        kind: 'speech',
        text: file,
        audioRef: `speech/${file}.mp3`,
        fileName: `${file}.mp3`
      });
    });
  };

  const lines = source.split(/\r?\n/);
  for (const line of lines) {
    const rawLine = String(line || '');
    if (!rawLine.trim()) continue;
    const regex = /<([^>]+)>/g;
    let lastIndex = 0;
    let match;
    while ((match = regex.exec(rawLine)) !== null) {
      pushTextSegment(rawLine.slice(lastIndex, match.index));
      pushPlaceholderSegment(match[1]);
      lastIndex = regex.lastIndex;
    }
    pushTextSegment(rawLine.slice(lastIndex));
  }

  return {
    scriptId: normalizedScriptId,
    segments,
    generatedSegments: segments.filter((segment) => segment.kind === 'generated')
  };
}

function buildGeneratedInstructionItem(scriptId, instructionText) {
  const parsed = parseInstructionSegments(scriptId, instructionText);
  const playlist = parsed.segments.map((segment) => segment.audioRef);
  if (playlist.length === 0) {
    return null;
  }
  return {
    id: parsed.scriptId,
    title: parsed.scriptId,
    text: String(instructionText || ''),
    audioMode: playlist.length > 1 ? 'playlist' : 'single_mp3',
    audioRef: playlist[0] || '',
    audioPlaylist: playlist,
    generatedFromBlocklyScript: true
  };
}

function toRelativeSoundsPath(audioRef) {
  const raw = String(audioRef || '').trim().replace(/^\/+/, '');
  if (!raw) return '';
  if (raw.startsWith('instructions/')) {
    return `../nl/instructions/${raw.slice('instructions/'.length)}`;
  }
  if (raw.startsWith('speech/')) {
    return `../nl/speech/${raw.slice('speech/'.length)}`;
  }
  if (raw.startsWith('letters/')) {
    return `../nl/letters/${raw.slice('letters/'.length)}`;
  }
  if (raw.startsWith('feedback/')) {
    return `../nl/feedback/${raw.slice('feedback/'.length)}`;
  }
  if (raw.startsWith('story/')) {
    return `../nl/stories/${raw.slice('story/'.length)}`;
  }
  if (raw.startsWith('general/')) {
    return `../general/${raw.slice('general/'.length)}`;
  }
  return `../${raw}`;
}

async function fetchGeneratedInstructionItemByScriptId(scriptId) {
  const normalizedId = String(scriptId || '').trim();
  if (!normalizedId) {
    return null;
  }

  let instructionText = '';
  if (normalizedId === String(currentOnlineScriptId || '').trim()) {
    instructionText = String(document.getElementById('scriptMetaInstruction')?.value || '').trim();
  }

  if (!instructionText) {
    try {
      const data = await onlineApiFetchJson(`/load.php?id=${encodeURIComponent(normalizedId)}&_=${Date.now()}`);
      instructionText = String(data?.meta?.instruction || '').trim();
    } catch {
      instructionText = '';
    }
  }

  if (!instructionText) {
    return null;
  }

  return buildGeneratedInstructionItem(normalizedId, instructionText);
}

function renderInstructionTtsControl(message = '') {
  const button = document.getElementById('saveInstructionTtsBtn');
  const status = document.getElementById('instructionTtsStatus');
  if (!button || !status) return;

  const state = getInstructionTtsState();
  button.disabled = !state.canSave;
  button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');

  if (typeof window.BrailleBlocklyRefreshInstructionDropdowns === 'function') {
    window.BrailleBlocklyRefreshInstructionDropdowns();
  }

  status.classList.remove('is-error');
  if (message) {
    status.textContent = message;
    return;
  }

  if (!state.scriptId) {
    status.textContent = 'Load an online Blockly script to save its instruction playlist.';
    return;
  }

  if (!state.instruction) {
    status.textContent = 'Add instruction text before saving the instruction playlist.';
    return;
  }

  status.textContent = `Generated instruction audio will be saved under ${state.scriptId}-NNN.mp3 in /braillestudio/sounds/nl/instructions/.`;
}

function createInstructionGeneratedItem(instructionId) {
  const normalizedId = String(instructionId || '').trim();
  if (!normalizedId) {
    return null;
  }
  const instructionText = normalizedId === String(currentOnlineScriptId || '').trim()
    ? String(document.getElementById('scriptMetaInstruction')?.value || '').trim()
    : '';
  return buildGeneratedInstructionItem(normalizedId, instructionText);
}

function insertInstructionPlayBlock() {
  const state = getInstructionTtsState();
  if (!workspace) {
    throw new Error('Blockly workspace is not ready');
  }
  if (!state.scriptId) {
    throw new Error('Load an online script first.');
  }

  const generatedItem = buildGeneratedInstructionItem(state.scriptId, state.instruction);
  if (!generatedItem || !Array.isArray(generatedItem.audioPlaylist) || generatedItem.audioPlaylist.length === 0) {
    throw new Error('Instruction has no playable items.');
  }

  const listBlock = workspace.newBlock('lists_create_with');
  if (typeof listBlock.itemCount_ === 'number' && typeof listBlock.updateShape_ === 'function') {
    listBlock.itemCount_ = Math.max(1, generatedItem.audioPlaylist.length);
    listBlock.updateShape_();
  }
  listBlock.initSvg();
  listBlock.render();

  generatedItem.audioPlaylist.forEach((audioRef, index) => {
    const textBlock = workspace.newBlock('text');
    textBlock.setFieldValue(toRelativeSoundsPath(audioRef), 'TEXT');
    textBlock.initSvg();
    textBlock.render();
    const input = listBlock.getInput(`ADD${index}`);
    if (input?.connection && textBlock.outputConnection) {
      input.connection.connect(textBlock.outputConnection);
    }
  });

  const loopBlock = workspace.newBlock('list_for_each_item');
  loopBlock.initSvg();
  loopBlock.render();

  const variableField = loopBlock.getField('VAR');
  const variableModel = variableField?.getVariable?.();
  const variableId = variableModel?.getId?.() || '';

  if (loopBlock.getInput('LIST')?.connection && listBlock.outputConnection) {
    loopBlock.getInput('LIST').connection.connect(listBlock.outputConnection);
  }

  const playBlock = workspace.newBlock('sound_play_sounds_relative');
  playBlock.initSvg();
  playBlock.render();

  const variableGetBlock = workspace.newBlock('variables_get');
  if (variableId) {
    variableGetBlock.setFieldValue(variableId, 'VAR');
  }
  variableGetBlock.initSvg();
  variableGetBlock.render();

  if (playBlock.getInput('PATH')?.connection && variableGetBlock.outputConnection) {
    playBlock.getInput('PATH').connection.connect(variableGetBlock.outputConnection);
  }

  if (loopBlock.getInput('DO')?.connection && playBlock.previousConnection) {
    loopBlock.getInput('DO').connection.connect(playBlock.previousConnection);
  }

  const metrics = workspace.getMetrics?.();
  const viewLeft = metrics?.viewLeft ?? 0;
  const viewTop = metrics?.viewTop ?? 0;
  const viewWidth = metrics?.viewWidth ?? 320;
  const viewHeight = metrics?.viewHeight ?? 240;
  loopBlock.moveBy(viewLeft + (viewWidth / 2) - 120, viewTop + (viewHeight / 2) - 40);
  loopBlock.select();
  refreshWorkspaceDirtyState();
  log(`Instruction playlist block inserted: ${state.scriptId} (${generatedItem.audioPlaylist.length} items)`);
}

async function saveInstructionAsMp3() {
  const status = document.getElementById('instructionTtsStatus');
  const button = document.getElementById('saveInstructionTtsBtn');
  const state = getInstructionTtsState();

  if (!state.scriptId) {
    renderInstructionTtsControl('Load an online Blockly script before saving the instruction playlist.');
    if (status) status.classList.add('is-error');
    return;
  }
  if (!state.instruction) {
    renderInstructionTtsControl('Instruction text is empty.');
    if (status) status.classList.add('is-error');
    return;
  }
  if (button) button.disabled = true;
  if (status) {
    status.classList.remove('is-error');
    status.textContent = 'Saving instruction playlist MP3 files...';
  }

  try {
    const parsed = parseInstructionSegments(state.scriptId, state.instruction);
    if (parsed.segments.length === 0) {
      throw new Error('Instruction has no playable items.');
    }

    for (const segment of parsed.generatedSegments) {
      const res = await fetch(ELEVENLABS_TTS_API_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: getElevenLabsAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
        voice_id: state.voiceId,
        model_id: 'eleven_v3',
        text: segment.text,
        save_to_file: true,
        save_path: 'braillestudio/sounds/nl/instructions',
          file_name: segment.fileName
        })
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
    }

    const summary = `${parsed.generatedSegments.length} generated, ${parsed.segments.length} playlist items`;
    log(`Instruction playlist saved: ${state.scriptId} (${summary})`);
    renderInstructionTtsControl(`Instruction playlist saved: ${summary}.`);
  } catch (err) {
    log(`Instruction playlist save failed: ${err.message}`);
    renderInstructionTtsControl(`Instruction playlist save failed: ${err.message}`);
    if (status) status.classList.add('is-error');
  } finally {
    renderInstructionTtsControl(status?.textContent || '');
    if (status && status.textContent.startsWith('Instruction playlist save failed:')) {
      status.classList.add('is-error');
    } else if (status) {
      status.classList.remove('is-error');
    }
    if (button) button.disabled = !getInstructionTtsState().canSave;
  }
}

async function saveInstructionPlaylistAndInsertBlock() {
  await saveInstructionAsMp3();
  const status = document.getElementById('instructionTtsStatus');
  if (status && status.classList.contains('is-error')) {
    return;
  }
  insertInstructionPlayBlock();
  renderInstructionTtsControl('Instruction playlist saved and playlist block inserted.');
}

function applyMetadataToXmlDom(xmlDom) {
  if (!xmlDom) return xmlDom;
  const meta = readScriptMetadataFromInputs();
  const variables = serializeScriptVariables();
  if (meta.title) xmlDom.setAttribute('data-title', meta.title);
  else xmlDom.removeAttribute('data-title');
  if (meta.description) xmlDom.setAttribute('data-description', meta.description);
  else xmlDom.removeAttribute('data-description');
  if (meta.instruction) xmlDom.setAttribute('data-instruction', meta.instruction);
  else xmlDom.removeAttribute('data-instruction');
  if (meta.prompt) xmlDom.setAttribute('data-prompt', meta.prompt);
  else xmlDom.removeAttribute('data-prompt');
  if (variables.length) xmlDom.setAttribute('data-variables', JSON.stringify(variables));
  else xmlDom.removeAttribute('data-variables');
  return xmlDom;
}

function renderGridSnapControl() {
  const btn = document.getElementById('gridSnapBtn');
  if (!btn) return;
  btn.classList.toggle('active', !!gridSnapEnabled);
  btn.setAttribute('aria-pressed', gridSnapEnabled ? 'true' : 'false');
  btn.setAttribute('aria-label', gridSnapEnabled ? 'Disable grid snap' : 'Enable grid snap');
  btn.title = gridSnapEnabled ? 'Disable grid snap' : 'Enable grid snap';
  setIconButtonContent(btn, 'ti ti-grid-dots');
}

function setIconButtonContent(btn, iconClass) {
  const icon = document.createElement('i');
  icon.className = iconClass;
  icon.setAttribute('aria-hidden', 'true');
  btn.replaceChildren(icon);
}

function renderSidebarToggleControl() {
  const btn = document.getElementById('sidebarToggleBtn');
  const main = document.getElementById('main');
  if (!btn || !main) return;
  const isHidden = main.classList.contains('is-sidebar-hidden');
  const isVisible = !isHidden;
  btn.classList.toggle('active', isVisible);
  btn.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  btn.setAttribute('aria-label', isVisible ? 'Hide status panel' : 'Show status panel');
  btn.title = isVisible ? 'Hide status panel' : 'Show status panel';
  setIconButtonContent(btn, 'ti ti-layout-sidebar-right');
}

function applySidebarWidth(nextWidth) {
  const normalized = Math.max(360, Math.min(1200, Math.round(Number(nextWidth) || 780)));
  sidebarWidth = normalized;
  document.documentElement.style.setProperty('--sidebar-width', `${normalized}px`);
  localStorage.setItem(BLOCKLY_SIDEBAR_WIDTH_KEY, String(normalized));
  if (workspace && typeof Blockly !== 'undefined' && typeof Blockly.svgResize === 'function') {
    setTimeout(() => Blockly.svgResize(workspace), 0);
  }
}

function renderBrailleMonitorToggleControl() {
  const btn = document.getElementById('monitorToggleBtn');
  const row = document.getElementById('brailleMonitorRow');
  const scriptRow = document.getElementById('scriptBrailleMonitorRow');
  const thumbRow = document.getElementById('thumbControlsRow');
  if (!btn || !row) return;
  const isVisible = !!brailleMonitorVisible;
  row.classList.toggle('is-hidden', !isVisible || !wsConnected);
  if (scriptRow) {
    scriptRow.classList.toggle('is-hidden', !isVisible || wsConnected);
  }
  if (thumbRow) {
    thumbRow.classList.toggle('is-hidden', !isVisible);
  }
  btn.classList.toggle('active', isVisible);
  btn.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  btn.setAttribute('aria-label', isVisible ? 'Hide monitor' : 'Show monitor');
  btn.title = isVisible ? 'Hide monitor' : 'Show monitor';
  setIconButtonContent(btn, 'ti ti-device-desktop');
}

function applyBrailleMonitorVisibility(visible) {
  brailleMonitorVisible = !!visible;
  localStorage.setItem(BLOCKLY_MONITOR_VISIBLE_KEY, brailleMonitorVisible ? 'true' : 'false');
  renderBrailleMonitorToggleControl();
}

function arrangeWorkspaceBlocks() {
  if (!workspace) return;
  const topBlocks = Array.isArray(workspace.getTopBlocks?.(true)) ? workspace.getTopBlocks(true) : [];
  if (!topBlocks.length) return;

  const marginX = 48;
  const marginY = 40;
  const columnGap = 72;
  const rowGap = 36;
  const metrics = typeof workspace.getMetrics === 'function' ? workspace.getMetrics() : null;
  const viewWidth = Number(metrics?.viewWidth || metrics?.contentWidth || 1400);
  const availableWidth = Math.max(720, viewWidth - marginX * 2);
  const columnWidth = Math.max(280, Math.floor((availableWidth - columnGap) / 2));
  const leftX = marginX;
  const rightX = marginX + columnWidth + columnGap;
  let leftY = marginY;
  let rightY = marginY;

  for (const [index, block] of topBlocks.entries()) {
    const placeRight = index % 2 === 1;
    const columnX = placeRight ? rightX : leftX;
    const targetY = placeRight ? rightY : leftY;
    const xy = typeof block.getRelativeToSurfaceXY === 'function' ? block.getRelativeToSurfaceXY() : { x: columnX, y: targetY };
    if (typeof block.moveBy === 'function') {
      block.moveBy(columnX - xy.x, targetY - xy.y);
    }
    const blockMetrics = block.getHeightWidth?.() || { height: 0 };
    if (placeRight) rightY += Number(blockMetrics.height || 0) + rowGap;
    else leftY += Number(blockMetrics.height || 0) + rowGap;
  }

  if (typeof Blockly !== 'undefined' && typeof Blockly.svgResize === 'function') {
    Blockly.svgResize(workspace);
  }
}

function toggleSidebarPanel() {
  const main = document.getElementById('main');
  if (!main) return;
  main.classList.toggle('is-sidebar-hidden');
  renderSidebarToggleControl();
  if (workspace && typeof Blockly !== 'undefined' && typeof Blockly.svgResize === 'function') {
    setTimeout(() => Blockly.svgResize(workspace), 0);
  }
}

function bindMainDividerResize() {
  const divider = document.getElementById('mainDivider');
  const main = document.getElementById('main');
  const sidebar = document.getElementById('sidebar');
  if (!divider || !main || !sidebar || divider.dataset.initialized) return;

  const onPointerMove = (event) => {
    if (main.classList.contains('is-sidebar-hidden')) return;
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    const nextWidth = viewportWidth - event.clientX;
    applySidebarWidth(nextWidth);
  };

  const stopDragging = () => {
    divider.classList.remove('is-dragging');
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', stopDragging);
    window.removeEventListener('pointercancel', stopDragging);
  };

  divider.addEventListener('pointerdown', (event) => {
    if (main.classList.contains('is-sidebar-hidden')) return;
    event.preventDefault();
    divider.classList.add('is-dragging');
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'col-resize';
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', stopDragging);
    window.addEventListener('pointercancel', stopDragging);
  });

  divider.dataset.initialized = '1';
}

function renderFileState() {
  const badge = document.getElementById('fileStateBadge');
  const text = document.getElementById('fileStateText');
  const saveBtn = document.getElementById('onlineSaveBtn');
  if (badge) {
    const setText = (value) => {
      if (text) text.textContent = value;
      else badge.textContent = value;
    };
    setText('');
    badge.classList.toggle('is-dirty', workspaceDirty);
  }
  if (saveBtn) {
    saveBtn.classList.toggle('is-dirty', workspaceDirty);
    saveBtn.classList.toggle('btn-outline-secondary', !workspaceDirty);
    saveBtn.classList.toggle('btn-primary', workspaceDirty);
    if (!IS_EMBEDDED_SESSION_PLAYER) {
      saveBtn.setAttribute('aria-label', workspaceDirty ? 'Save script with unsaved changes' : 'Save script');
      saveBtn.title = workspaceDirty ? 'Save script with unsaved changes' : 'Save script';
    }
  }
}

function getWorkspaceSignature() {
  if (!workspace) return '';
  const xmlDom = applyMetadataToXmlDom(Blockly.Xml.workspaceToDom(workspace));
  return Blockly.Xml.domToPrettyText(xmlDom);
}

function isWorkspaceEmptyForNewScript() {
  if (!workspace) return true;
  const blocks = typeof workspace.getAllBlocks === 'function'
    ? workspace.getAllBlocks(false).filter((block) => !block.isShadow?.())
    : [];
  return blocks.length === 0 && getScriptVariables().length === 0;
}

function isUntitledDraftNewScript() {
  return !String(currentOnlineScriptId || '').trim()
    && !String(document.getElementById('onlineScriptIdInput')?.value || '').trim()
    && !getCurrentOnlineScriptTitle()
    && getCurrentOnlineScriptStatus() === 'draft';
}

function isPristineNewScript() {
  return isUntitledDraftNewScript() && isWorkspaceEmptyForNewScript();
}

function setWorkspaceDirty(isDirty) {
  workspaceDirty = !!isDirty && !isPristineNewScript();
  renderFileState();
}

function getCurrentOnlineScriptTitle() {
  const titleInput = document.getElementById('onlineScriptTitleInput');
  return String(titleInput?.value || '').trim();
}

function getCurrentOnlineScriptStatus() {
  const statusInput = document.getElementById('onlineScriptStatusInput');
  return String(statusInput?.value || 'draft').trim() || 'draft';
}

function markWorkspaceSaved(signature = null) {
  lastSavedWorkspaceSignature = signature == null ? getWorkspaceSignature() : String(signature);
  lastSavedOnlineScriptTitle = getCurrentOnlineScriptTitle();
  lastSavedOnlineScriptStatus = getCurrentOnlineScriptStatus();
  setWorkspaceDirty(false);
}

function refreshWorkspaceDirtyState() {
  if (suppressDirtyTracking > 0) return;
  const signatureChanged = getWorkspaceSignature() !== lastSavedWorkspaceSignature;
  const titleChanged = getCurrentOnlineScriptTitle() !== lastSavedOnlineScriptTitle;
  const statusChanged = getCurrentOnlineScriptStatus() !== lastSavedOnlineScriptStatus;
  setWorkspaceDirty(signatureChanged || titleChanged || statusChanged);
}

async function showUnsavedChangesDialog(actionLabel) {
  const modal = document.getElementById('confirmModal');
  const title = document.getElementById('confirmModalTitle');
  const message = document.getElementById('confirmModalMessage');
  const saveBtn = document.getElementById('confirmModalSave');
  const discardBtn = document.getElementById('confirmModalDiscard');
  const cancelBtn = document.getElementById('confirmModalCancel');

  if (!modal || !title || !message || !saveBtn || !discardBtn || !cancelBtn) {
    return confirm(`You have unsaved changes. Continue and discard them before ${actionLabel}?`) ? 'discard' : 'cancel';
  }

  title.textContent = 'Unsaved changes';
  message.textContent = `You have unsaved changes. Save them before ${actionLabel}?`;

  return await new Promise((resolve) => {
    const finish = (result) => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      saveBtn.removeEventListener('click', onSave);
      discardBtn.removeEventListener('click', onDiscard);
      cancelBtn.removeEventListener('click', onCancel);
      modal.removeEventListener('click', onBackdrop);
      window.removeEventListener('keydown', onKeyDown);
      resolve(result);
    };
    const onSave = () => finish('save');
    const onDiscard = () => finish('discard');
    const onCancel = () => finish('cancel');
    const onBackdrop = (event) => {
      if (event.target === modal) finish('cancel');
    };
    const onKeyDown = (event) => {
      if (event.key === 'Escape') finish('cancel');
    };

    saveBtn.addEventListener('click', onSave);
    discardBtn.addEventListener('click', onDiscard);
    cancelBtn.addEventListener('click', onCancel);
    modal.addEventListener('click', onBackdrop);
    window.addEventListener('keydown', onKeyDown);
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    saveBtn.focus();
  });
}

async function confirmActionWithUnsavedChanges(actionLabel) {
  refreshWorkspaceDirtyState();
  if (isPristineNewScript()) {
    markWorkspaceSaved(getWorkspaceSignature());
    return true;
  }
  if (!workspaceDirty) return true;
  const choice = await showUnsavedChangesDialog(actionLabel);
  if (choice === 'cancel') {
    log(`${actionLabel} cancelled`);
    return false;
  }
  if (choice === 'save') {
    const onlineTargetId = resolveOnlineScriptId();
    const saved = onlineTargetId
      ? await saveWorkspaceOnline({ overwrite: true })
      : await saveWorkspace({ skipNoChangesLog: true });
    if (!saved) {
      log(`${actionLabel} cancelled`);
      return false;
    }
  }
  return true;
}

/* ---------------- Runtime state ---------------- */
const variableValues = {};
const SCRIPT_VARIABLE_TYPES = Object.freeze(['string', 'number', 'boolean', 'array', 'object']);
const SCRIPT_VARIABLE_COLOURS = Object.freeze({
  external: '#2563EB'
});
let scriptVariables = [];
let activeVariableContextId = '';
const timerHandles = new Map();
const listNextIndex = new WeakMap();
let soundVolume = 1;
let activeAudio = null;
let activeAudioCleanup = null;
let activeAudioStop = null;
let audioStoppedWaiters = [];
let audioQueue = Promise.resolve();
let audioQueuePending = 0;
let audioQueueGeneration = 0;
let instructionPreviewAudio = null;
let externalAudioPlayHandler = null;
const REFRESH_DEDUPE_MS = 1500;
const SOUND_BASE_URL = 'https://www.tastenbraille.com/braillestudio/sounds/nl/speech/';
const SOUND_FOLDER_URLS = {
  speech: 'https://www.tastenbraille.com/braillestudio/sounds/nl/speech/',
  letters: 'https://www.tastenbraille.com/braillestudio/sounds/nl/letters/',
  instructions: 'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions/',
  feedback: 'https://www.tastenbraille.com/braillestudio/sounds/nl/feedback/',
  story: 'https://www.tastenbraille.com/braillestudio/sounds/nl/stories/',
  general: 'https://www.tastenbraille.com/braillestudio/sounds/general/',
  ux: 'https://www.tastenbraille.com/braillestudio/sounds/ux/'
};
const lessonDataCache = new Map();
const SESSION_RESOLVED_PAYLOAD_STORAGE_KEY = 'braillestudio_session_api_last_resolved';
const BRAILLE_BLOCKLY_MODE_CONFIG = window.BrailleBlocklyMode || {};
const IS_HEADLESS_SESSION_PLAYER = window.BrailleBlocklyHeadless === true;
function resolveEmbedMode() {
  const queryMode = new URLSearchParams(window.location.search).get('embed') || '';
  if (queryMode) return queryMode;
  const configuredMode = String(BRAILLE_BLOCKLY_MODE_CONFIG.embedMode || '').trim();
  if (configuredMode) return configuredMode;
  if (!IS_HEADLESS_SESSION_PLAYER) {
    try {
      window.sessionStorage.removeItem('brailleBlocklyEmbedMode');
    } catch {}
    return '';
  }
  try {
    return String(window.sessionStorage.getItem('brailleBlocklyEmbedMode') || '').trim();
  } catch {
    return '';
  }
}
const EMBED_MODE = resolveEmbedMode();
const IS_EMBEDDED_SESSION_PLAYER = EMBED_MODE === 'session-player';
const refreshState = {
  onlineScripts: { promise: null, completedAt: 0 },
  compoundLibrary: { promise: null, completedAt: 0 }
};
let lastAppliedResolvedSessionSignature = '';
window.BrailleBlocklyDefaultLessonPlaceholders = window.BrailleBlocklyDefaultLessonPlaceholders || {
  word: 'bal',
  soundsCsv: 'b,a,l'
};
const DEFAULT_LESSON_STEP_INPUTS = Object.freeze({
  text: 'bal,kam,aap',
  word: 'bal',
  letters: Object.freeze(['b', 'a', 'l']),
  repeat: 1
});
let lessonStepExternalOverrides = null;
let fonemenNlJsonCache = null;
let workspaceDirty = false;
let lastSavedWorkspaceSignature = '';
let lastSavedOnlineScriptTitle = '';
let lastSavedOnlineScriptStatus = 'draft';
let suppressDirtyTracking = 0;
let currentOnlineScriptId = '';
let currentCompoundLibraryItems = [];
let lastSelectedBlocklyBlockId = '';
let runtimeKeyboardListenerAttached = false;

function shouldIgnoreRuntimeKeyboardEvent(event) {
  const target = event?.target;
  if (!target || typeof target !== 'object') return false;
  const tagName = String(target.tagName || '').toUpperCase();
  if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT' || target.isContentEditable) {
    return true;
  }
  return false;
}

function getKeyboardVirtualKeyCode(event) {
  const raw = event?.keyCode ?? event?.which ?? event?.charCode ?? 0;
  return Math.floor(Number(raw) || 0);
}

function acceptsRuntimeInput(generation = runGeneration, runtimeState = getRuntime()) {
  const rt = runtimeState && typeof runtimeState === 'object' ? runtimeState : getRuntime();
  return generation === runGeneration && !rt.stopped;
}

function attachRuntimeKeyboardListener() {
  if (runtimeKeyboardListenerAttached) return;
  runtimeKeyboardListenerAttached = true;
  window.addEventListener('keydown', (event) => {
    if (shouldIgnoreRuntimeKeyboardEvent(event)) return;
    const rt = getRuntime();
    if (!acceptsRuntimeInput(runGeneration, rt)) return;
    const key = String(event?.key || '');
    const keyCode = getKeyboardVirtualKeyCode(event);
    rt.lastEditorKey = key;
    rt.lastVirtualKeyCode = keyCode;
    renderStatus();
    void dispatchEvent({ type: 'editorKey', key }, runGeneration);
    void dispatchEvent({ type: 'virtualKeyCode', key, keyCode }, runGeneration);
  });
}

attachRuntimeKeyboardListener();

function isRuntimeActive(runtimeState = getRuntime()) {
  const rt = runtimeState && typeof runtimeState === 'object' ? runtimeState : getRuntime();
  if (rt.stopped) {
    return Boolean(pendingStart || activeAudio || audioQueuePending > 0);
  }
  const hasPendingProgramEnd =
    rt.programEndedGeneration < 0 ||
    rt.programEndedCompletedGeneration !== rt.programEndedGeneration;
  return Boolean(
    pendingStart ||
    (!rt.stopped && hasPendingProgramEnd) ||
    timerHandles.size > 0 ||
    activeAudio
  );
}

function setExecutionUiState(phase = 'idle', detail = '') {
  const normalized = String(phase || 'idle').trim().toLowerCase() || 'idle';
  const fallbackDetails = {
    idle: 'Script is not running.',
    running: 'Script is running.',
    stopping: 'Stop requested. Waiting for the current cycle to finish.',
    completed: 'Script finished.',
    stopped: 'Script stopped.',
    failed: 'Script failed.'
  };
  uiExecutionState = {
    phase: normalized,
    detail: String(detail || fallbackDetails[normalized] || fallbackDetails.idle)
  };
}

function executionPhaseFromCompletionStatus(status, fallback = 'completed') {
  const normalized = String(status || '').trim().toLowerCase();
  if (normalized === 'failed' || normalized === 'error') return 'failed';
  if (normalized === 'stopped' || normalized === 'cancelled' || normalized === 'canceled') return 'stopped';
  if (normalized === 'completed' || normalized === 'success' || normalized === 'passed') return 'completed';
  return fallback;
}

function renderStatus() {
  const rt = getRuntime();
  const legacyVarLines = workspace
    ? workspace.getAllVariables().map(v => `${v.name} = ${variableValues[v.getId()] ?? 0}`)
    : [];
  const externalLines = getScriptVariables('external').map((variable) => {
    const value = rt.external && Object.prototype.hasOwnProperty.call(rt.external, variable.name)
      ? rt.external[variable.name]
      : undefined;
    return `[external] ${variable.name}: ${variable.type} = ${formatLogValue(value)}${variable.description ? `\n  ${variable.description}` : ''}`;
  });
  const varLines = [
    ...externalLines,
    ...legacyVarLines.map((line) => `[legacy] ${line}`)
  ];
  const isRunning = isRuntimeActive(rt);
  const runBtn = document.getElementById('runBtn');
  const stopBtn = document.getElementById('stopBtn');

  const phase = isRunning
    ? (uiExecutionState.phase === 'stopping' ? 'stopping' : 'running')
    : (uiExecutionState.phase || 'idle');

  if (runBtn) {
    runBtn.classList.toggle('active', phase === 'running');
    runBtn.disabled = isRunning || pendingStart;
    runBtn.setAttribute('aria-pressed', isRunning || pendingStart ? 'true' : 'false');
    const label = phase === 'completed' ? 'Again' : (phase === 'running' ? 'Running' : (phase === 'stopping' ? 'Stopping' : 'Start'));
    runBtn.setAttribute('aria-label', label);
    runBtn.title = label;
  }

  if (stopBtn) {
    stopBtn.classList.toggle('active', phase === 'running');
    stopBtn.disabled = phase === 'stopping' || (!isRunning && !pendingStart);
    stopBtn.setAttribute('aria-pressed', isRunning || pendingStart ? 'true' : 'false');
    const label = phase === 'completed' ? 'Finished' : (phase === 'stopped' ? 'Stopped' : (phase === 'stopping' ? 'Stopping' : 'Stop'));
    stopBtn.setAttribute('aria-label', label);
    stopBtn.title = label;
  }

  const statusBox = document.getElementById('statusBox');
  if (statusBox) {
    statusBox.textContent =
`ws connected     : ${wsConnected}
text             : ${rt.text}
braille unicode  : ${rt.brailleUnicode}
text caret       : ${rt.textCaret}
cell caret       : ${rt.cellCaret}
editor mode      : ${rt.editorMode}
insert mode      : ${rt.insertMode}
caret visible    : ${rt.caretVisible}
last thumb key   : ${rt.lastThumbKey}
last cursor cell : ${rt.lastCursorCell}
last chord       : ${rt.lastChord}
last editor key  : ${rt.lastEditorKey}
last vk code    : ${rt.lastVirtualKeyCode}
last sound       : ${rt.lastSound}
last timer name  : ${rt.lastTimerName}
last timer tick  : ${rt.lastTimerTick}
last ws notice   : ${rt.lastWsNotice}
active table     : ${rt.activeTable}
line id          : ${rt.lineId}
is active        : ${isRunning}
active timers    : ${timerHandles.size}
active audio     : ${Boolean(activeAudio)}
pending start    : ${pendingStart}
stopped          : ${rt.stopped}

variables:
${varLines.length ? varLines.join('\n') : '(none)'}`;
  }

  renderScriptBrailleLine();
}

function applyGridSnap(enabled) {
  gridSnapEnabled = !!enabled;
  if (workspace) {
    if (workspace.options?.gridOptions) {
      workspace.options.gridOptions.snap = gridSnapEnabled;
    }
    const grid = typeof workspace.getGrid === 'function' ? workspace.getGrid() : workspace.grid;
    if (grid) {
      if (typeof grid.setSnapToGrid === 'function') {
        grid.setSnapToGrid(gridSnapEnabled);
      }
      if ('snapToGrid_' in grid) {
        grid.snapToGrid_ = gridSnapEnabled;
      }
      if ('snapToGrid' in grid && typeof grid.snapToGrid !== 'function') {
        grid.snapToGrid = gridSnapEnabled;
      }
    }
  }
  localStorage.setItem(BLOCKLY_GRID_SNAP_KEY, gridSnapEnabled ? 'true' : 'false');
  renderGridSnapControl();
}

function getOnlineApiBases() {
  return [
    new URL('../api/blockly-api', window.location.href).toString().replace(/\/$/, '')
  ];
}

function getCompoundLibraryApiBases() {
  const dynamicCandidates = [
    new URL('../blockly-library', window.location.href).toString().replace(/\/$/, '')
  ];
  return [...new Set([...dynamicCandidates, ...COMPOUND_LIBRARY_API_BASES])];
}

async function apiFetchJsonFromBases(bases, path, options = {}, logLabel = 'API') {
  const method = String(options.method || 'GET').toUpperCase();
  const requestOptions = {
    ...(options || {}),
    credentials: options.credentials || 'same-origin',
    headers: getElevenLabsAuthHeaders(options.headers || {})
  };
  let lastError = null;

  for (const base of bases) {
    const url = `${base}${path}`;
    try {
      const requestStartedAt = performance.now();
      log(`${logLabel} -> ${method} ${url}`);
      const res = await fetch(url, requestOptions);
      const responseReceivedAt = performance.now();
      const raw = await res.text();
      const bodyReadAt = performance.now();
      let data = null;
      try {
        data = JSON.parse(raw);
      } catch {
        throw new Error(`Non-JSON response for ${method} ${url} (HTTP ${res.status})`);
      }
      const parsedAt = performance.now();
      if (!res.ok || (data && data.ok === false)) {
        const msg = data && data.error ? data.error : `HTTP ${res.status}`;
        throw new Error(`${msg} (${method} ${url})`);
      }
      log(`${logLabel} <- ${res.status} ${method} ${url} total=${formatDurationMs(requestStartedAt)} response=${(responseReceivedAt - requestStartedAt).toFixed(1)}ms body=${(bodyReadAt - responseReceivedAt).toFixed(1)}ms parse=${(parsedAt - bodyReadAt).toFixed(1)}ms bytes=${raw.length}`);
      return data;
    } catch (err) {
      log(`${logLabel} failed: ${method} ${url} :: ${err.message}`);
      lastError = err;
    }
  }

  throw lastError || new Error(`${logLabel} failed for ${method} ${path}`);
}

async function onlineApiFetchJson(path, options = {}) {
  return apiFetchJsonFromBases(getOnlineApiBases(), path, options, 'Online API');
}

async function compoundLibraryApiFetchJson(path, options = {}) {
  return apiFetchJsonFromBases(getCompoundLibraryApiBases(), path, options, 'Compound Library API');
}

function readOnlineScriptInputs() {
  const idInput = document.getElementById('onlineScriptIdInput');
  const titleInput = document.getElementById('onlineScriptTitleInput');
  const statusInput = document.getElementById('onlineScriptStatusInput');
  return {
    id: String(idInput?.value || '').trim(),
    title: String(titleInput?.value || '').trim(),
    status: String(statusInput?.value || 'draft').trim() || 'draft'
  };
}

function slugifyOnlineScriptTitle(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function requireOnlineScriptTitle() {
  const titleInput = document.getElementById('onlineScriptTitleInput');
  const title = String(titleInput?.value || '').trim();
  if (title) return title;
  if (titleInput) {
    titleInput.focus();
    titleInput.reportValidity?.();
  }
  throw new Error('Script title is required before saving');
}

async function buildUniqueOnlineScriptId(title, options = {}) {
  const {
    excludeId = '',
    forceUnique = false
  } = options || {};
  const normalizedTitle = String(title || '').trim();
  const baseSlug = slugifyOnlineScriptTitle(normalizedTitle);
  if (!baseSlug) {
    throw new Error('Script title could not be converted into an id');
  }
  if (!forceUnique) {
    return baseSlug;
  }

  const excluded = String(excludeId || '').trim();
  let existingItems = [];
  try {
    existingItems = await listOnlineScripts();
  } catch {}
  const existingIds = new Set(
    existingItems
      .map((item) => String(item?.id || '').trim())
      .filter((id) => id && id !== excluded)
  );
  if (!existingIds.has(baseSlug)) {
    return baseSlug;
  }
  let counter = 2;
  while (existingIds.has(`${baseSlug}-${counter}`)) {
    counter += 1;
  }
  return `${baseSlug}-${counter}`;
}

function resolveOnlineScriptId() {
  const select = document.getElementById('onlineScriptsSelect');
  const inputId = String(document.getElementById('onlineScriptIdInput')?.value || '').trim();
  const currentId = String(currentOnlineScriptId || '').trim();
  const selectedId = String(select?.value || '').trim();
  return inputId || currentId || selectedId || '';
}

function setOnlineScriptInputs({ id = '', title = '', status = 'draft' } = {}) {
  const idInput = document.getElementById('onlineScriptIdInput');
  const titleInput = document.getElementById('onlineScriptTitleInput');
  const statusInput = document.getElementById('onlineScriptStatusInput');
  if (idInput) idInput.value = String(id || '');
  if (titleInput) titleInput.value = String(title || '');
  if (statusInput) statusInput.value = String(status || 'draft');
  renderStatusScriptName();
}

function setOnlineCurrentScript({ id = '', title = '', status = 'draft' } = {}) {
  currentOnlineScriptId = String(id || '').trim();
  setOnlineScriptInputs({
    id: currentOnlineScriptId,
    title: String(title || '').trim(),
    status: String(status || 'draft')
  });
  renderInstructionTtsControl();
  renderFileState();
}

function renderOnlineScriptsSelect(items) {
  const renderStartedAt = performance.now();
  const select = document.getElementById('onlineScriptsSelect');
  if (!select) return;

  const normalized = Array.isArray(items) ? items : [];
  select.innerHTML = '';

  const placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.disabled = false;
  placeholder.textContent = normalized.length > 0
    ? `Select online script (${normalized.length})`
    : 'No online scripts found';
  select.appendChild(placeholder);

  const normalizeStatus = (value) => {
    const raw = String(value || '').trim().toLowerCase();
    if (raw === 'started') return 'started';
    if (raw === 'in review') return 'in review';
    if (raw === 'approved') return 'approved';
    return 'draft';
  };
  const applySelectedStatusColor = () => {
    const selected = select.selectedOptions && select.selectedOptions[0]
      ? select.selectedOptions[0]
      : null;
    select.style.removeProperty('color');
  };

  let renderedCount = 0;
  for (const item of normalized) {
    const fallbackId = String(item?.filename || item?.name || item?.title || '').replace(/\.json$/i, '');
    const id = String(item?.id || fallbackId).trim();
    if (!id) continue;
    const title = String(item?.title || '').trim();
    const filename = String(item?.filename || '').trim();
    const status = normalizeStatus(item?.meta?.status);
    const hasLegacyVariableFormat = Boolean(item?.legacyVariableFormat);
    const displayTitle = title || filename || '(untitled)';
    const migrationSuffix = hasLegacyVariableFormat ? '\u00A0\u00A0⚠' : '';
    const option = document.createElement('option');
    option.value = id;
    option.dataset.title = title;
    option.dataset.status = status;
    option.dataset.legacyVariableFormat = hasLegacyVariableFormat ? '1' : '0';
    option.title = hasLegacyVariableFormat
      ? `${filename || id}: oude external-variable indeling. Wordt bij opslaan omgezet naar de nieuwe indeling.`
      : (filename || '');
    option.textContent = `${displayTitle}${migrationSuffix}`;
    select.appendChild(option);
    renderedCount += 1;
  }

  if (currentOnlineScriptId) {
    select.value = currentOnlineScriptId;
  }
  select.dataset.loadedCount = String(renderedCount);
  select.title = renderedCount > 0
    ? `${renderedCount} online scripts loaded`
    : 'No online scripts loaded';
  applySelectedStatusColor();
  log(`Online scripts select rendered (${renderedCount}) in ${formatDurationMs(renderStartedAt)}`);
}

function renderOnlineScriptsSelectError(message) {
  const select = document.getElementById('onlineScriptsSelect');
  if (!select) return;
  select.innerHTML = '';
  const option = document.createElement('option');
  option.value = '';
  option.textContent = `Could not load scripts: ${String(message || 'unknown error')}`;
  select.appendChild(option);
}

async function listOnlineScripts() {
  const startedAt = performance.now();
  log('Online scripts: listing started');
  const data = await onlineApiFetchJson(`/list.php?_=${Date.now()}`);
  const receivedAt = performance.now();
  const count = Array.isArray(data?.items) ? data.items.length : 0;
  const legacyCount = Array.isArray(data?.items)
    ? data.items.filter((item) => Boolean(item?.legacyVariableFormat)).length
    : 0;
  log(`Online scripts: list received count=${count} legacy=${legacyCount} in ${formatDurationMs(startedAt)} api=${(receivedAt - startedAt).toFixed(1)}ms`);
  return Array.isArray(data?.items) ? data.items : [];
}

async function refreshOnlineScripts() {
  const startedAt = performance.now();
  log('Online scripts refresh: started');
  try {
    const items = await listOnlineScripts();
    const listedAt = performance.now();
    renderOnlineScriptsSelect(items);
    const renderedAt = performance.now();
    log(`Online scripts refresh: done count=${items.length} total=${formatDurationMs(startedAt)} list=${(listedAt - startedAt).toFixed(1)}ms render=${(renderedAt - listedAt).toFixed(1)}ms`);
    return items;
  } catch (err) {
    renderOnlineScriptsSelectError(err?.message || err);
    log(`Online scripts refresh: failed after ${formatDurationMs(startedAt)} (${err?.message || err})`);
    throw err;
  }
}

function renderCompoundLibrarySelect(items) {
  const normalized = Array.isArray(items) ? items : [];
  currentCompoundLibraryItems = normalized;
  refreshCompoundLibraryToolboxCategory();
}

async function listCompoundLibraryItems() {
  log('Compound library: listing');
  const data = await compoundLibraryApiFetchJson(`/list.php?_=${Date.now()}`);
  const count = Array.isArray(data?.items) ? data.items.length : 0;
  log(`Compound library: list received (${count})`);
  return Array.isArray(data?.items) ? data.items : [];
}

async function refreshCompoundLibrary() {
  const items = await listCompoundLibraryItems();
  renderCompoundLibrarySelect(items);
  log(`Compound library refreshed (${items.length})`);
  return items;
}

function getSelectedBlocklyBlock() {
  const selected = typeof Blockly.getSelected === 'function' ? Blockly.getSelected() : Blockly.selected;
  if (selected && typeof selected.getRootBlock === 'function') {
    return selected;
  }
  if (lastSelectedBlocklyBlockId && workspace) {
    const byId = workspace.getBlockById(lastSelectedBlocklyBlockId);
    if (byId) return byId;
  }
  const topBlocks = Array.isArray(workspace?.getTopBlocks?.(false)) ? workspace.getTopBlocks(false) : [];
  if (topBlocks.length === 1) {
    return topBlocks[0];
  }
  return null;
}

function serializeSelectedCompoundBlock() {
  if (!workspace) throw new Error('Blockly workspace is not ready');
  const selectedBlock = getSelectedBlocklyBlock();
  if (!selectedBlock) throw new Error('Click a block first so it can be saved to My Blocks');
  const blockDom = Blockly.Xml.blockToDom(selectedBlock, true);
  if (!blockDom) throw new Error('Could not serialize selected block');
  blockDom.removeAttribute('x');
  blockDom.removeAttribute('y');
  return {
    xml: Blockly.Xml.domToText(blockDom),
    blockType: String(selectedBlock.type || '')
  };
}

function serializeBlockForClipboard(block) {
  if (!block) throw new Error('No block selected');
  const blockDom = Blockly.Xml.blockToDom(block, true);
  if (!blockDom) throw new Error('Could not serialize block');
  blockDom.removeAttribute('x');
  blockDom.removeAttribute('y');
  return {
    kind: 'braille-blockly-block',
    xml: Blockly.Xml.domToText(blockDom),
    blockType: String(block.type || '')
  };
}

async function writeBlockClipboardPayload(payload) {
  const text = JSON.stringify(payload);
  localStorage.setItem(BLOCK_CLIPBOARD_STORAGE_KEY, text);
  if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
    await navigator.clipboard.writeText(text);
  }
}

function parseBlockClipboardPayload(text) {
  const raw = String(text || '').trim();
  if (!raw) return null;
  try {
    const parsed = JSON.parse(raw);
    if (parsed && parsed.kind === 'braille-blockly-block' && typeof parsed.xml === 'string') {
      return parsed;
    }
  } catch {}
  return null;
}

async function readBlockClipboardPayload() {
  let payload = null;
  if (navigator.clipboard && typeof navigator.clipboard.readText === 'function') {
    try {
      payload = parseBlockClipboardPayload(await navigator.clipboard.readText());
    } catch {}
  }
  if (payload) return payload;
  return parseBlockClipboardPayload(localStorage.getItem(BLOCK_CLIPBOARD_STORAGE_KEY) || '');
}

async function copyBlocklyBlockToClipboard(block) {
  const payload = serializeBlockForClipboard(block);
  await writeBlockClipboardPayload(payload);
  log(`Block copied to clipboard: ${payload.blockType || 'block'}`);
}

async function pasteBlocklyBlockFromClipboard() {
  const payload = await readBlockClipboardPayload();
  if (!payload?.xml) {
    throw new Error('Clipboard does not contain a Blockly block');
  }
  insertCompoundBlockXml(payload.xml);
  log(`Block pasted from clipboard: ${String(payload.blockType || 'block')}`);
}

function getCompoundInsertionPosition() {
  const metrics = typeof workspace?.getMetrics === 'function' ? workspace.getMetrics() : null;
  return {
    x: Math.round(Number(metrics?.viewLeft ?? 0) + 40),
    y: Math.round(Number(metrics?.viewTop ?? 0) + 40)
  };
}

function insertCompoundBlockXml(xmlText) {
  if (!workspace) throw new Error('Blockly workspace is not ready');
  const source = String(xmlText || '').trim();
  if (!source) throw new Error('Compound block XML is empty');
  const xmlDom = Blockly.utils.xml.textToDom(source);
  const blockDom = xmlDom && String(xmlDom.nodeName || '').toLowerCase() === 'block'
    ? xmlDom
    : xmlDom?.querySelector?.('block');
  if (!blockDom) throw new Error('Invalid compound block XML');
  const { x, y } = getCompoundInsertionPosition();
  blockDom.setAttribute('x', String(x));
  blockDom.setAttribute('y', String(y));
  patchVariableFieldIds(blockDom);
  let inserted = null;
  if (typeof Blockly.Xml.domToBlock === 'function') {
    inserted = Blockly.Xml.domToBlock(blockDom, workspace);
  } else {
    const wrapper = Blockly.utils.xml.createElement('xml');
    wrapper.appendChild(blockDom);
    const beforeIds = new Set(workspace.getAllBlocks(false).map((block) => block.id));
    Blockly.Xml.domToWorkspace(wrapper, workspace);
    inserted = workspace.getAllBlocks(false).find((block) => !beforeIds.has(block.id)) || null;
  }
  if (!inserted) throw new Error('Could not insert compound block');
  if (typeof inserted?.select === 'function') inserted.select();
  return inserted;
}

function makeCompoundLibraryId(title, blockType) {
  const base = String(title || blockType || 'my-block')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    || 'my-block';
  const suffix = Date.now().toString(36);
  return `${base}-${suffix}`;
}

function defaultCompoundLibraryTitle(block) {
  const raw = String(block?.type || 'My Block');
  return raw
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (match) => match.toUpperCase());
}

async function saveSelectedCompoundLibraryItem({ overwrite = true } = {}) {
  const selectedBlock = getSelectedBlocklyBlock();
  if (!selectedBlock) {
    throw new Error('Click a block first so it can be saved to My Blocks');
  }
  const suggestedTitle = defaultCompoundLibraryTitle(selectedBlock);
  const enteredTitle = prompt('Name for this My Block:', suggestedTitle);
  if (enteredTitle == null) return false;
  const title = String(enteredTitle).trim() || suggestedTitle;
  const id = makeCompoundLibraryId(title, selectedBlock.type);
  const snippet = serializeSelectedCompoundBlock();
  const payload = {
    id,
    title,
    overwrite: !!overwrite,
    snippet,
    meta: {
      title,
      rootType: snippet.blockType || '',
      kind: 'compound-block'
    }
  };
  const data = await compoundLibraryApiFetchJson('/save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  await refreshCompoundLibrary();
  log(`Compound block saved: ${data?.id || id}`);
  return data;
}

async function loadCompoundLibraryItem(id) {
  const normalizedId = String(id || '').trim();
  if (!normalizedId) throw new Error('Compound block id is required');
  return compoundLibraryApiFetchJson(`/load.php?id=${encodeURIComponent(normalizedId)}`);
}

async function insertCompoundLibraryItem(id) {
  const data = await loadCompoundLibraryItem(id);
  const snippetXml = String(data?.snippet?.xml || '').trim();
  if (!snippetXml) throw new Error('Stored compound block is empty');
  insertCompoundBlockXml(snippetXml);
  log(`Compound block inserted: ${data?.id || id}`);
  return data;
}

async function deleteCompoundLibraryItem(id) {
  const normalizedId = String(id || '').trim();
  if (!normalizedId) throw new Error('Compound block id is required');
  const data = await compoundLibraryApiFetchJson('/delete.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: normalizedId })
  });
  await refreshCompoundLibrary();
  log(`Compound block deleted: ${normalizedId}`);
  return data;
}

async function saveWorkspaceOnline({ overwrite = true } = {}) {
  if (!workspace) throw new Error('Blockly workspace is not ready');

  const inputs = readOnlineScriptInputs();
  const title = requireOnlineScriptTitle();
  const status = String(inputs.status || 'draft').trim() || 'draft';
  const currentId = String(currentOnlineScriptId || '').trim();
  let id = String(inputs.id || '').trim();

  if (!id) {
    id = resolveOnlineScriptId();
  }
  if (!id) {
    id = await buildUniqueOnlineScriptId(title, {
      excludeId: currentId,
      forceUnique: !overwrite || !currentId
    });
  }

  const buildPayload = (includeVariableMetadata = true) => {
    const metadata = readScriptMetadataFromInputs();
    const blocklyState = Blockly.serialization.workspaces.save(workspace);
    return {
      id,
      title,
      overwrite: !!overwrite,
      blockly: includeVariableMetadata
        ? { ...blocklyState, scriptVariables: serializeScriptVariables() }
        : blocklyState,
      meta: {
        title: metadata.title || title,
        description: metadata.description || '',
        instruction: metadata.instruction || '',
        prompt: metadata.prompt || '',
        status
      }
    };
  };
  const payload = buildPayload(true);
  const blockCount = Array.isArray(payload?.blockly?.blocks?.blocks)
    ? payload.blockly.blocks.blocks.length
    : 0;
  log(`Online save requested: id=${payload.id}, overwrite=${payload.overwrite}, blocks=${blockCount}`);

  let data;
  try {
    data = await onlineApiFetchJson('/save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
  } catch (err) {
    const message = String(err?.message || err);
    const shouldRetryLegacy = message.includes('Non-JSON response') || message.includes('HTTP 500');
    if (!shouldRetryLegacy) throw err;
    log(`Online save retrying with legacy Blockly payload after: ${message}`);
    const legacyPayload = buildPayload(false);
    data = await onlineApiFetchJson('/save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(legacyPayload)
    });
  }

  setOnlineCurrentScript({ id: data?.id || id, title: payload.title, status });
  markWorkspaceSaved(getWorkspaceSignature());
  await refreshOnlineScripts();
  log(`Online script saved: ${data?.id || id}`);
  return data;
}

async function saveWorkspaceOnlineAs() {
  const current = readOnlineScriptInputs();
  const suggestedTitle = current.title || readScriptMetadataFromInputs().title || '';
  const enteredTitle = prompt('Online script title:', suggestedTitle);
  if (enteredTitle == null) return false;
  const normalizedTitle = String(enteredTitle).trim();
  if (!normalizedTitle) {
    const titleInput = document.getElementById('onlineScriptTitleInput');
    if (titleInput) {
      titleInput.value = '';
      titleInput.focus();
      titleInput.reportValidity?.();
    }
    throw new Error('Script title is required before saving');
  }
  const generatedId = await buildUniqueOnlineScriptId(normalizedTitle, { forceUnique: true });
  log(`Online Save As requested: id=${generatedId}`);
  setOnlineScriptInputs({ id: generatedId, title: normalizedTitle, status: current.status });
  await saveWorkspaceOnline({ overwrite: false });
  return true;
}

async function copyWorkspaceJsonToClipboard() {
  if (!workspace) {
    throw new Error('Blockly workspace is not ready');
  }

  const data = {
    ...Blockly.serialization.workspaces.save(workspace),
    scriptVariables: serializeScriptVariables()
  };
  const jsonText = JSON.stringify(data, null, 2);

  if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
    await navigator.clipboard.writeText(jsonText);
    log('Blockly JSON copied to clipboard');
    return;
  }

  const temp = document.createElement('textarea');
  temp.value = jsonText;
  temp.setAttribute('readonly', '');
  temp.style.position = 'fixed';
  temp.style.top = '-9999px';
  temp.style.left = '-9999px';
  document.body.appendChild(temp);
  temp.select();
  const ok = document.execCommand('copy');
  document.body.removeChild(temp);
  if (!ok) {
    throw new Error('Clipboard not available');
  }
  log('Blockly JSON copied to clipboard');
}

async function importWorkspaceJsonFromClipboard() {
  if (!workspace) {
    throw new Error('Blockly workspace is not ready');
  }

  if (!(await confirmActionWithUnsavedChanges('importing JSON from clipboard'))) return false;

  let jsonText = '';
  if (navigator.clipboard && typeof navigator.clipboard.readText === 'function') {
    jsonText = await navigator.clipboard.readText();
  } else {
    const fallback = prompt('Paste Blockly JSON:');
    jsonText = fallback == null ? '' : String(fallback);
  }

  if (!jsonText.trim()) {
    throw new Error('Clipboard is empty');
  }

  let state;
  try {
    state = JSON.parse(jsonText);
  } catch {
    throw new Error('Clipboard does not contain valid JSON');
  }

  if (!state || typeof state !== 'object') {
    throw new Error('Invalid Blockly JSON structure');
  }

  registerProcedures(state, runtime);
  suppressDirtyTracking++;
  workspace.clear();
  setScriptVariables(extractWorkspaceVariablesFromState(state), { markDirty: false });
  Blockly.serialization.workspaces.load(getBlocklySerializableState(state), workspace);
  refreshProcedureRegistry(workspace);
  ensureDefaultVariable();
  initVariableValues();
  suppressDirtyTracking--;
  setWorkspaceDirty(true);
  log('Blockly JSON imported from clipboard');
  return true;
}

async function loadWorkspaceOnline(id) {
  if (!workspace) throw new Error('Blockly workspace is not ready');
  const startedAt = performance.now();
  const targetId = String(id || '').trim();
  if (!targetId) throw new Error('Online script id is required');
  log(`Online load requested: id=${targetId}`);

  const data = await onlineApiFetchJson(`/load.php?id=${encodeURIComponent(targetId)}&_=${Date.now()}`);
  const apiLoadedAt = performance.now();
  const state = data?.blockly;
  if (!state || typeof state !== 'object') {
    throw new Error('Loaded script does not contain valid Blockly JSON');
  }
  const blockCount = Array.isArray(state?.blocks?.blocks) ? state.blocks.blocks.length : 0;
  const variableCount = Array.isArray(state?.variables) ? state.variables.length : 0;
  const scriptVariableCount = Array.isArray(state?.scriptVariables) ? state.scriptVariables.length : 0;
  log(`Online script load: payload received id=${targetId} blocks=${blockCount} variables=${variableCount} scriptVariables=${scriptVariableCount} api=${(apiLoadedAt - startedAt).toFixed(1)}ms`);

  registerProcedures(state, runtime);
  const proceduresRegisteredAt = performance.now();
  suppressDirtyTracking++;
  workspace.clear();
  const workspaceClearedAt = performance.now();
  setScriptVariables(extractWorkspaceVariablesFromState(state, data?.meta), { markDirty: false });
  const variablesLoadedAt = performance.now();
  Blockly.serialization.workspaces.load(getBlocklySerializableState(state), workspace);
  const workspaceLoadedAt = performance.now();
  refreshProcedureRegistry(workspace);
  ensureDefaultVariable();
  initVariableValues();
  suppressDirtyTracking--;
  const runtimeInitializedAt = performance.now();

  const meta = data?.meta && typeof data.meta === 'object' ? data.meta : {};
  renderScriptMetadata({
    title: String(meta.title || data.title || targetId),
    description: String(meta.description || ''),
    instruction: String(meta.instruction || ''),
    prompt: String(meta.prompt || '')
  });
  const metadataRenderedAt = performance.now();
  setOnlineCurrentScript({
    id: String(data?.id || targetId),
    title: String(data?.title || ''),
    status: String(meta.status || 'draft')
  });
  markWorkspaceSaved(getWorkspaceSignature());
  const savedAt = performance.now();
  log(`Online script loaded: ${targetId} total=${formatDurationMs(startedAt)} procedures=${(proceduresRegisteredAt - apiLoadedAt).toFixed(1)}ms clear=${(workspaceClearedAt - proceduresRegisteredAt).toFixed(1)}ms variables=${(variablesLoadedAt - workspaceClearedAt).toFixed(1)}ms workspace=${(workspaceLoadedAt - variablesLoadedAt).toFixed(1)}ms runtime=${(runtimeInitializedAt - workspaceLoadedAt).toFixed(1)}ms metadata=${(metadataRenderedAt - runtimeInitializedAt).toFixed(1)}ms saveState=${(savedAt - metadataRenderedAt).toFixed(1)}ms`);
  return data;
}

async function deleteWorkspaceOnline(id) {
  const targetId = String(id || '').trim();
  if (!targetId) throw new Error('Online script id is required');
  log(`Online delete requested: id=${targetId}`);

  const data = await onlineApiFetchJson('/delete.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: targetId })
  });

  if (currentOnlineScriptId === targetId) {
    if (workspace) {
      getRuntime().stopped = true;
      runGeneration++;
      stopAllTimers();
      stopSound();
      currentFileHandle = null;
      suppressDirtyTracking++;
      workspace.clear();
      refreshProcedureRegistry(workspace);
      setScriptVariables([], { markDirty: false });
      ensureDefaultVariable();
      initVariableValues();
      renderScriptMetadata(null);
      renderBrailleLine(null);
      suppressDirtyTracking--;
      setSelectedFileName('braille-activity.blockly');
      log('Workspace cleared after online delete');
    }
    setOnlineCurrentScript({ id: '', title: '', status: 'draft' });
    if (workspace) {
      markWorkspaceSaved(getWorkspaceSignature());
    }
  }
  await refreshOnlineScripts();
  log(`Online script deleted: ${targetId}`);
  return data;
}

/* ---------------- WebSocket ---------------- */
function wsEventType(msg) {
  return msg?.Type ?? msg?.type ?? '';
}

function wsField(obj, upperName, lowerName) {
  if (!obj || typeof obj !== 'object') return null;
  if (obj[upperName] != null) return obj[upperName];
  if (obj[lowerName] != null) return obj[lowerName];
  return null;
}

function wsPayload(msg) {
  return wsField(msg, 'Payload', 'payload') || {};
}

function wsCursor(msg) {
  return wsField(msg, 'Cursor', 'cursor') || {};
}

function stopTimerByName(name) {
  const key = String(name || '').trim();
  if (!key) return;
  const handle = timerHandles.get(key);
  if (!handle) return;
  clearInterval(handle.id);
  timerHandles.delete(key);
  log('Timer stopped: ' + key);
}

function stopAllTimers() {
  for (const [, handle] of timerHandles) {
    clearInterval(handle.id);
  }
  if (timerHandles.size > 0) {
    log('All timers stopped (' + timerHandles.size + ')');
  }
  timerHandles.clear();
}

function startTimer(name, seconds, generation) {
  const key = String(name || '').trim();
  if (!key) {
    log('Timer start skipped: empty name');
    return;
  }
  const periodMs = Math.max(50, Math.floor(toNumber(seconds) * 1000));
  stopTimerByName(key);
  const state = { id: 0, tick: 0, generation };
  state.id = setInterval(() => {
    if (state.generation !== runGeneration || getRuntime().stopped) return;
    state.tick += 1;
    getRuntime().lastTimerName = key;
    getRuntime().lastTimerTick = state.tick;
    renderStatus();
    dispatchEvent({ type: 'timer', name: key, tick: state.tick }, state.generation)
      .catch(err => log('Timer dispatch failed (' + key + '): ' + err.message));
  }, periodMs);
  timerHandles.set(key, state);
  log('Timer started: ' + key + ' every ' + (periodMs / 1000) + 's');
}

function resolveSoundUrl(input) {
  const raw = String(input ?? '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) return raw;
  const filename = raw.toLowerCase().endsWith('.mp3') ? raw : (raw + '.mp3');
  return SOUND_BASE_URL + encodeURIComponent(filename);
}

function resolveFolderSoundUrl(folder, input) {
  const raw = String(input ?? '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) return raw;
  const baseUrl = SOUND_FOLDER_URLS[String(folder || 'speech')] || SOUND_BASE_URL;
  const filename = raw.toLowerCase().endsWith('.mp3') ? raw : (raw + '.mp3');
  return baseUrl + encodeURIComponent(filename);
}

function resolveSoundsRelativeUrl(input) {
  const raw = String(input ?? '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) return raw;
  const normalized = raw.replace(/^(?:\.\.\/|\.\/|\/)+/, '');
  if (!normalized) return '';
  return 'https://www.tastenbraille.com/braillestudio/sounds/' + normalized.split('/').map(encodeURIComponent).join('/');
}

function resolveInstructionAudioUrl(input) {
  const raw = String(input ?? '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) return raw;
  const normalized = raw.replace(/^\/+/, '');
  const match = normalized.match(/^([^/]+)\/(.+)$/);
  if (!match) {
    return SOUND_FOLDER_URLS.instructions + normalized.split('/').map(encodeURIComponent).join('/');
  }
  const [, folder, rest] = match;
  const folderKey = String(folder).toLowerCase();
  const baseUrl = folderKey === 'phonemes'
    ? SOUND_FOLDER_URLS.letters
    : (SOUND_FOLDER_URLS[folderKey] || SOUND_FOLDER_URLS.instructions);
  return baseUrl + String(rest).split('/').map(encodeURIComponent).join('/');
}

function applyInstructionAudioOverrides(input, options = {}) {
  const raw = String(input ?? '').trim();
  if (!raw) return '';
  const phoneme = String(options.phoneme ?? '').trim();
  if (!phoneme) return raw;
  const normalized = raw.replace(/^\/+/, '');
  const match = normalized.match(/^([^/]+)\/(.+)$/);
  if (!match) return raw;
  const [, folder, rest] = match;
  const folderKey = String(folder).toLowerCase();
  if (folderKey !== 'phonemes' && folderKey !== 'phonems') return raw;
  const suffix = String(rest).toLowerCase().endsWith('.mp3') ? '.mp3' : '';
  const nextFile = String(phoneme).toLowerCase().endsWith('.mp3') ? String(phoneme) : `${phoneme}${suffix || '.mp3'}`;
  return `phonemes/${nextFile}`;
}

async function fetchJsonFromCandidates(urls, errorPrefix) {
  let lastError = null;
  for (const url of urls) {
    try {
      const response = await fetch(url, { cache: 'no-store' });
      const bodyText = await response.text();
      if (!response.ok) {
        throw new Error(`HTTP ${response.status} ${response.statusText}: ${bodyText.slice(0, 200)}`);
      }
      try {
        return JSON.parse(bodyText);
      } catch {
        throw new Error(`Invalid JSON from ${url}`);
      }
    } catch (err) {
      lastError = err;
    }
  }
  throw new Error(`${errorPrefix}: ${lastError?.message || 'unknown error'}`);
}

async function fetchInstructionById(id) {
  const instructionId = String(id ?? '').trim();
  if (!instructionId) {
    throw new Error('Instruction id is required');
  }
  const remoteUrl = `https://www.tastenbraille.com/braillestudio/instructions-api/instructions_get.php?id=${encodeURIComponent(instructionId)}`;
  let parsed;
  try {
    parsed = await fetchJsonFromCandidates([
      remoteUrl
    ], `Failed to load instruction "${instructionId}"`);
  } catch (err) {
    const fallbackItem = getInstructionCatalogItems().find((item) => String(item?.id ?? '').trim() === instructionId);
    if (fallbackItem) {
      log(`Instruction fallback used for ${instructionId}`);
      return fallbackItem;
    }
    const generatedItem = await fetchGeneratedInstructionItemByScriptId(instructionId);
    if (generatedItem) {
      log(`Generated instruction fallback used for ${instructionId}`);
      return generatedItem;
    }
    throw err;
  }
  if (!parsed?.item || typeof parsed.item !== 'object') {
    const generatedItem = await fetchGeneratedInstructionItemByScriptId(instructionId);
    if (generatedItem) {
      log(`Generated instruction API fallback used for ${instructionId}`);
      return generatedItem;
    }
    throw new Error(`Instruction not found: ${instructionId}`);
  }
  return parsed.item;
}

async function playInstructionById(id, options = {}) {
  const item = await fetchInstructionById(id);
  const instructionId = String(id ?? '').trim();
  if (String(item.audioMode || 'single_mp3') === 'playlist') {
    const playlist = Array.isArray(item.audioPlaylist) ? item.audioPlaylist : [];
    for (let index = 0; index < playlist.length; index++) {
      const entry = playlist[index];
      const resolvedEntry = applyInstructionAudioOverrides(entry, options);
      const url = resolveInstructionAudioUrl(resolvedEntry);
      if (!url) continue;
      log(`Instruction play [${instructionId}] step ${index + 1}/${playlist.length}: ${String(entry)} -> ${url}`);
      await playSound(url);
    }
    return item;
  }
  const resolvedEntry = applyInstructionAudioOverrides(item.audioRef, options);
  const url = resolveInstructionAudioUrl(resolvedEntry);
  if (!url) {
    throw new Error(`Instruction "${instructionId}" has no playable audio`);
  }
  log(`Instruction play [${instructionId}] single: ${String(item.audioRef ?? '')} -> ${url}`);
  await playSound(url);
  return item;
}

function getSoundFolderFromBlockType(type) {
  switch (type) {
    case 'sound_play_speech_file': return 'speech';
    case 'sound_play_letters_file': return 'letters';
    case 'sound_play_instructions_file': return 'instructions';
    case 'sound_play_feedback_file': return 'feedback';
    case 'sound_play_story_file': return 'story';
    case 'sound_play_general_file': return 'general';
    case 'sound_play_ux_file': return 'ux';
    default: return '';
  }
}

function resolveAudioStoppedWaiters() {
  if (audioStoppedWaiters.length === 0) return;
  const waiters = audioStoppedWaiters;
  audioStoppedWaiters = [];
  waiters.forEach(resolve => {
    try {
      resolve();
    } catch {
      // ignore resolver errors
    }
  });
}

function clearActiveAudio() {
  if (activeAudioCleanup) {
    try {
      activeAudioCleanup();
    } catch {
      // ignore listener cleanup errors
    }
    activeAudioCleanup = null;
  }
  activeAudioStop = null;
  activeAudio = null;
}

function waitForAudioStopped() {
  if (!activeAudio && audioQueuePending === 0) return Promise.resolve();
  return new Promise(resolve => {
    audioStoppedWaiters.push(resolve);
  });
}

function isInputBlockedByAudio() {
  return Boolean(activeAudio) || audioQueuePending > 0;
}

function isAudioBypassEvent(event) {
  const key = String(event?.key ?? '').trim().toUpperCase();
  const keyCode = Math.floor(Number(event?.keyCode) || 0);
  return key === 'F3' || keyCode === 114 || key === 'ESCAPE' || keyCode === 27;
}

function shouldBlockEventDuringAudio(event) {
  if (!isInputBlockedByAudio()) return false;
  if (isAudioBypassEvent(event)) return false;
  return (
    event?.type === 'thumbKey' ||
    event?.type === 'cursorRouting' ||
    event?.type === 'chord' ||
    event?.type === 'editorKey' ||
    event?.type === 'virtualKeyCode'
  );
}

function stopSound(reason = 'stopped') {
  audioQueueGeneration += 1;
  audioQueue = Promise.resolve();
  audioQueuePending = 0;
  if (!activeAudio) {
    resolveAudioStoppedWaiters();
    return;
  }
  try {
    if (typeof activeAudioStop === 'function') {
      activeAudioStop();
    } else {
      activeAudio.pause();
      activeAudio.currentTime = 0;
    }
  } catch {
    // ignore pause/currentTime errors
  }
  clearActiveAudio();
  resolveAudioStoppedWaiters();
}

function pauseSound() {
  if (!activeAudio) {
    log('Sound pause skipped: no active sound');
    return;
  }
  try {
    activeAudio.pause();
    log('Sound paused');
  } catch (err) {
    log('Sound pause failed: ' + err.message);
  }
}

async function resumeSound() {
  if (!activeAudio) {
    log('Sound resume skipped: no active sound');
    return;
  }
  try {
    await activeAudio.play();
    log('Sound resumed');
  } catch (err) {
    log('Sound resume failed: ' + err.message);
  }
}

async function playSoundNow(url, generation) {
  if (generation !== audioQueueGeneration) return;
  if (typeof externalAudioPlayHandler === 'function') {
    const audioToken = { external: true, url };
    activeAudio = audioToken;
    activeAudioCleanup = null;
    activeAudioStop = null;
    runtime.lastSound = url;
    renderStatus();
    log('Audio URL: ' + url);
    try {
      await externalAudioPlayHandler(url);
    } catch (err) {
      log('Sound failed: ' + (err?.message || String(err)));
    } finally {
      if (activeAudio === audioToken) {
        clearActiveAudio();
        resolveAudioStoppedWaiters();
      }
    }
    return;
  }

  const audio = new Audio(url);
  audio.volume = soundVolume;
  activeAudio = audio;
  let settlePlayback = null;
  const playbackDone = new Promise((resolve, reject) => {
    settlePlayback = { resolve, reject };
  });
  const onEnded = () => {
    if (activeAudio === audio) {
      clearActiveAudio();
      resolveAudioStoppedWaiters();
    }
    log('Sound ended');
    settlePlayback?.resolve();
  };
  const onError = () => {
    if (activeAudio === audio) {
      clearActiveAudio();
      resolveAudioStoppedWaiters();
    }
    log('Sound failed: playback error for ' + url);
    settlePlayback?.reject(new Error('Audio playback failed'));
  };
  const onAbort = () => {
    if (activeAudio === audio) {
      clearActiveAudio();
      resolveAudioStoppedWaiters();
    }
    settlePlayback?.resolve();
  };
  audio.addEventListener('ended', onEnded);
  audio.addEventListener('error', onError);
  audio.addEventListener('abort', onAbort);
  activeAudioCleanup = () => {
    audio.removeEventListener('ended', onEnded);
    audio.removeEventListener('error', onError);
    audio.removeEventListener('abort', onAbort);
  };
  activeAudioStop = () => {
    audio.pause();
    audio.currentTime = 0;
    settlePlayback?.resolve();
  };
  runtime.lastSound = url;
  renderStatus();
  log('Audio URL: ' + url);
  try {
    await audio.play();
    await playbackDone;
  } catch (err) {
    if (activeAudio === audio) {
      clearActiveAudio();
      resolveAudioStoppedWaiters();
    }
    log('Sound failed: ' + err.message);
  }
}

function queueSound(url) {
  const generation = audioQueueGeneration;
  audioQueuePending += 1;
  audioQueue = audioQueue
    .catch(() => {})
    .then(async () => {
      if (generation !== audioQueueGeneration) return;
      await playSoundNow(url, generation);
    })
    .finally(() => {
      if (generation !== audioQueueGeneration) return;
      audioQueuePending = Math.max(0, audioQueuePending - 1);
      if (!activeAudio && audioQueuePending === 0) {
        resolveAudioStoppedWaiters();
      }
    });
}

async function playSound(input) {
  const url = resolveSoundUrl(input);
  if (!url) {
    log('Sound skipped: empty filename/url');
    return;
  }
  queueSound(url);
}

if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.setPlayHandler === 'function') {
  window.BrailleStudioAPI.setPlayHandler(playSound);
}

function isWsOpen() {
  return !!ws && ws.readyState === WebSocket.OPEN;
}

function clearReconnectTimer() {
  if (!reconnectTimer) return;
  clearTimeout(reconnectTimer);
  reconnectTimer = null;
}

function scheduleReconnect() {
  if (!autoConnectEnabled || reconnectTimer || isWsOpen()) return;
  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    if (!autoConnectEnabled || isWsOpen()) return;
    connectWs('auto-reconnect');
  }, AUTO_RECONNECT_MS);
}

function disconnectWs() {
  autoConnectEnabled = false;
  clearReconnectTimer();
  pendingStart = false;
  stopAllTimers();
  stopSound();
  if (ws) {
    try {
      ws.close(1000, 'client close');
    } catch (err) {
      log('WS close failed: ' + err.message);
    }
  }
  ws = null;
  setWsBadge(false);
  runtime.lastWsNotice = 'Disconnected';
  renderStatus();
  log('WS disconnected (manual)');
}

function connectWs(reason = 'manual') {
  if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) {
    log('WS already connected/connecting');
    return;
  }

  if (reason !== 'auto-reconnect') {
    autoConnectEnabled = true;
  }
  clearReconnectTimer();
  if (!isWsOpen()) {
    requestBrailleBridgeLaunch(reason);
  }

  let socket = null;
  try {
    socket = new WebSocket(WS_URL);
  } catch (err) {
    log('WS connect failed: ' + String(err));
    scheduleReconnect();
    return;
  }
  ws = socket;
  renderWsControl();
  log('WS connecting to ' + WS_URL);

  socket.onopen = () => {
    if (ws !== socket) return;
    setWsBadge(true);
    getRuntime().lastWsNotice = '';
    clearReconnectTimer();
    renderStatus();
    log('WS connected to ' + WS_URL);
    sendWs({ type: 'getBrailleLine' });
    if (pendingStart && pendingStartGeneration === runGeneration && !getRuntime().stopped) {
      pendingStart = false;
      runStartedProgram(runGeneration).catch(err => log('Start failed: ' + err.message));
    }
  };

  socket.onclose = (ev) => {
    if (ws === socket) ws = null;
    setWsBadge(false);
    runtime.lastWsNotice = 'Disconnected';
    renderStatus();
    log('WS disconnected code=' + ev.code + ' reason=' + (ev.reason || '-'));
    scheduleReconnect();
  };

  socket.onerror = () => {
    log('WS error');
    scheduleReconnect();
  };

  socket.onmessage = async (ev) => {
    try {
      let raw = ev.data;
      if (typeof raw !== 'string') {
        if (raw && typeof raw.text === 'function') {
          raw = await raw.text();
        } else if (raw instanceof ArrayBuffer) {
          raw = new TextDecoder('utf-8').decode(raw);
        } else {
          log('WS ignored non-text message');
          return;
        }
      }
      const msg = JSON.parse(raw);
      await handleIncomingWs(msg);
    } catch (err) {
      log('WS parse error: ' + err.message);
    }
  };
}

async function ensureBrailleBridgeConnection(timeoutMs = 5000) {
  if (isWsOpen()) {
    setWsBadge(true);
    return true;
  }
  requestBrailleBridgeLaunch('ensure-connection');
  connectWs('manual');
  const startedAt = Date.now();
  while (Date.now() - startedAt < timeoutMs) {
    if (isWsOpen()) {
      setWsBadge(true);
      return true;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  setWsBadge(false);
  return false;
}

function sendWs(obj) {
  const json = JSON.stringify(obj);
  if (!ws || ws.readyState !== WebSocket.OPEN) {
    runtime.lastWsNotice = 'Not connected, skipped send: ' + json;
    renderStatus();
    return false;
  }
  runtime.lastWsNotice = '';
  ws.send(json);
  log('WS IN  ' + json);
  renderStatus();
  return true;
}

function sendEnvelope(command, extra = {}) {
  return sendWs({ type: 'command', command, ...extra });
}

async function handleIncomingWs(msg) {
  log('WS OUT ' + JSON.stringify(msg));
  const type = wsEventType(msg);

  if (type === 'brailleLine') {
    const prevTextCaret = runtime.textCaret;
    const prevCellCaret = runtime.cellCaret;
    runtime.text = msg.sourceText ?? runtime.text;
    runtime.brailleUnicode = msg.braille?.unicodeText ?? '';
    runtime.textCaret = msg.meta?.caretTextPosition ?? msg.caret?.textIndex ?? runtime.textCaret;
    runtime.cellCaret = msg.meta?.caretCellPosition ?? msg.caret?.cellIndex ?? runtime.cellCaret;
    runtime.editorMode = msg.status?.editorMode ?? runtime.editorMode;
    runtime.insertMode = msg.status?.insertMode ?? runtime.insertMode;
    runtime.caretVisible = msg.caretVisible ?? runtime.caretVisible;
    runtime.activeTable = msg.meta?.activeTable ?? runtime.activeTable;
    runtime.lineId = msg.meta?.lineId ?? runtime.lineId;
    renderStatus();
    renderBrailleLine(msg);
    if (runtime.textCaret !== prevTextCaret || runtime.cellCaret !== prevCellCaret) {
      await dispatchEvent({
        type: 'cursorPositionChanged',
        position: runtime.textCaret,
        cell: runtime.cellCaret
      }, runGeneration);
    }
    return;
  }

  if (type === 'thumbKey') {
    if (!acceptsRuntimeInput(runGeneration)) {
      return;
    }
    const payload = wsPayload(msg);
    const name = payload.Name ?? payload.name ?? '';
    const press = !!(payload.Press ?? payload.press);
    runtime.lastThumbKey = normalizeThumb(name);
    renderStatus();
    if (press) {
      if (isInputBlockedByAudio()) {
        return;
      }
      await dispatchEvent({ type: 'thumbKey', key: runtime.lastThumbKey }, runGeneration);
    }
    return;
  }

  if (type === 'editorKey') {
    if (!acceptsRuntimeInput(runGeneration)) {
      return;
    }
    const payload = wsPayload(msg);
    const key = payload.Key ?? payload.key ?? '';
    const press = !!(payload.Press ?? payload.press);
    runtime.lastEditorKey = key;
    renderStatus();
    if (press) {
      await dispatchEvent({ type: 'editorKey', key }, runGeneration);
    }
    return;
  }

  if (type === 'cursor') {
    if (!acceptsRuntimeInput(runGeneration)) {
      return;
    }
    const cursor = wsCursor(msg);
    runtime.lastCursorCell = cursor.CellIndex ?? cursor.cellIndex ?? runtime.lastCursorCell;
    runtime.textCaret = cursor.TextIndex ?? cursor.textIndex ?? runtime.textCaret;
    renderStatus();
    await dispatchEvent({
      type: 'cursorRouting',
      cell: runtime.lastCursorCell,
      textIndex: runtime.textCaret
    }, runGeneration);
    await dispatchEvent({
      type: 'cursorPositionChanged',
      position: runtime.textCaret,
      cell: runtime.lastCursorCell
    }, runGeneration);
    return;
  }

  if (type === 'chord') {
    if (!acceptsRuntimeInput(runGeneration)) {
      return;
    }
    runtime.lastChord = extractChord(msg);
    renderStatus();
    await dispatchEvent({ type: 'chord', dots: runtime.lastChord }, runGeneration);
    return;
  }
}

function normalizeThumb(name) {
  const s = String(name || '').toLowerCase();
  if (s.includes('left-middle') || s.includes('left middle') || s.includes('cursor5') || s.includes('cursor 5')) return 'left-middle';
  if (s.includes('right-middle') || s.includes('right middle') || s.includes('chord1') || s.includes('chord 1')) return 'right-middle';
  if (s.includes('left')) return 'left';
  if (s.includes('right')) return 'right';
  if (s.includes('up')) return 'up';
  if (s.includes('down')) return 'down';
  return s;
}

function extractChord(msg) {
  const payload = wsPayload(msg);
  const cursor = wsCursor(msg);
  const braille = wsField(msg, 'Braille', 'braille') || {};
  if (payload.Name != null) return String(payload.Name);
  if (payload.name != null) return String(payload.name);
  if (payload.Dots != null) return String(payload.Dots);
  if (payload.dots != null) return String(payload.dots);
  if (cursor.Character != null) return String(cursor.Character);
  if (cursor.character != null) return String(cursor.character);
  if (braille.CellChar != null) return String(braille.CellChar);
  if (braille.cellChar != null) return String(braille.cellChar);
  return '1';
}

function normalizeChordValue(value) {
  const raw = String(value ?? '').trim();
  if (!raw) return '';
  return raw
    .split(/[^0-9]+/)
    .map(part => part.trim())
    .filter(Boolean)
    .join('');
}

function hasPersistentEventBlocks() {
  if (!workspace) return false;
  const allTypes = workspace.getAllBlocks(false).map((block) => String(block?.type || '').trim());
  const hasPersistent = workspace.getAllBlocks(false).some((block) => {
    const type = String(block?.type || '').trim();
    return type.startsWith('event_when_') &&
      type !== 'event_when_started' &&
      type !== 'event_when_program_ended';
  });
  log(`Persistent event blocks check: ${JSON.stringify({ allTypes, hasPersistent })}`);
  return hasPersistent;
}

async function runStartedProgram(generation) {
  const rt = getRuntime();
  if (!workspace) {
    log('Start blocked: Blockly workspace is not ready');
    return;
  }
  refreshProcedureRegistry(workspace);
  if (generation !== runGeneration || rt.stopped) return;
  const startedBlocks = workspace.getTopBlocks(true).filter(b => b.type === 'event_when_started');
  if (startedBlocks.length === 0) {
    log('Start warning: no "when started" block found');
  }
  resetRunScopedVariables();
  await dispatchEvent({ type: 'started' }, generation);
  if (generation === runGeneration) {
    if (rt.stopped) {
      await dispatchProgramEnded(generation, 'stopped');
    } else if (!hasPersistentEventBlocks()) {
      await dispatchProgramEnded(generation, 'completed');
    } else {
      renderStatus();
      log('Program remains active: waiting for events');
    }
  }
  sendWs({ type: 'getBrailleLine' });
}

function bindWsControls() {
  renderWsControl();
}

async function onRunClicked() {
  stopAllTimers();
  getRuntime().stopped = false;
  getRuntime().programEndedGeneration = -1;
  getRuntime().programEndedCompletedGeneration = -1;
  runGeneration++;
  const generation = runGeneration;
  setExecutionUiState('running', 'Script is running.');
  renderStatus();

  if (!workspace) {
    setExecutionUiState('failed', 'Start blocked: Blockly workspace is not ready.');
    renderStatus();
    log('Start blocked: Blockly workspace is not ready');
    return;
  }

  pendingStart = false;
  if (!isWsOpen()) {
    log('Start without braille display connection: running local Blockly flow');
    connectWs('manual');
  }
  await runStartedProgram(generation);
}

async function onStopClicked() {
  const generation = runGeneration;
  const rt = getRuntime();
  setExecutionUiState('stopping', 'Stopping script and audio.');
  renderStatus();
  rt.stopped = true;
  rt.programEndedGeneration = generation;
  rt.programEndedCompletedGeneration = generation;
  pendingStart = false;
  stopAllTimers();
  stopSound();
  runGeneration++;
  setExecutionUiState('stopped', 'Script stopped by user.');
  renderStatus();
  log('Runtime stopped');
}

function bindAppControls() {
  const bind = (id, eventName, handler) => {
    const el = document.getElementById(id);
    if (!el) return;
    const key = `bound_${eventName}`;
    if (el.dataset[key]) return;
    el.addEventListener(eventName, handler);
    el.dataset[key] = '1';
  };

  bind('runBtn', 'click', onRunClicked);
  bind('stopBtn', 'click', onStopClicked);
  bind('clearBtn', 'click', newWorkspace);
  bind('saveBtn', 'click', saveWorkspace);
  bind('loadBtn', 'click', openWorkspaceFile);
  bind('gridSnapBtn', 'click', () => {
    applyGridSnap(!gridSnapEnabled);
  });
  bind('monitorToggleBtn', 'click', () => {
    applyBrailleMonitorVisibility(!brailleMonitorVisible);
  });
  bind('sidebarToggleBtn', 'click', () => {
    toggleSidebarPanel();
  });
  bind('newBtn', 'click', newWorkspace);
  bind('onlineRefreshBtn', 'click', async () => {
    if (IS_EMBEDDED_SESSION_PLAYER) {
      log('Online refresh skipped: session player mode');
      return;
    }
    try {
      await refreshOnlineScripts();
    } catch (err) {
      log('Online refresh failed: ' + err.message);
      alert('Could not refresh online scripts: ' + err.message);
    }
  });
  bind('onlineSaveBtn', 'click', async () => {
    if (IS_EMBEDDED_SESSION_PLAYER) {
      log('Online save skipped: session player mode');
      return;
    }
    try {
      await saveWorkspaceOnline({ overwrite: true });
    } catch (err) {
      log('Online save failed: ' + err.message);
      alert('Could not save online script: ' + err.message);
    }
  });
  bind('copyJsonBtn', 'click', async () => {
    try {
      await copyWorkspaceJsonToClipboard();
    } catch (err) {
      log('Copy JSON failed: ' + err.message);
      alert('Could not copy Blockly JSON: ' + err.message);
    }
  });
  bind('importJsonBtn', 'click', async () => {
    try {
      await importWorkspaceJsonFromClipboard();
    } catch (err) {
      log('Import JSON failed: ' + err.message);
      alert('Could not import Blockly JSON: ' + err.message);
    }
  });
  bind('onlineSaveAsBtn', 'click', async () => {
    if (IS_EMBEDDED_SESSION_PLAYER) {
      log('Online Save As skipped: session player mode');
      return;
    }
    try {
      await saveWorkspaceOnlineAs();
    } catch (err) {
      log('Online Save As failed: ' + err.message);
      alert('Could not save online script: ' + err.message);
    }
  });
  bind('onlineLoadBtn', 'click', async () => {
    if (IS_EMBEDDED_SESSION_PLAYER) {
      log('Online load skipped: session player mode');
      return;
    }
    const select = document.getElementById('onlineScriptsSelect');
    const id = String(select?.value || '').trim() || currentOnlineScriptId;
    if (!id) {
      alert('Select an online script first.');
      return;
    }
    if (!(await confirmActionWithUnsavedChanges('loading an online script'))) return;
    try {
      await loadWorkspaceOnline(id);
    } catch (err) {
      log('Online load failed: ' + err.message);
      alert('Could not load online script: ' + err.message);
    }
  });
  bind('onlineDeleteBtn', 'click', async () => {
    if (IS_EMBEDDED_SESSION_PLAYER) {
      log('Online delete skipped: session player mode');
      return;
    }
    const select = document.getElementById('onlineScriptsSelect');
    const id = String(select?.value || '').trim() || currentOnlineScriptId;
    if (!id) {
      alert('Select an online script first.');
      return;
    }
    const selectedOption = select?.selectedOptions && select.selectedOptions[0]
      ? select.selectedOptions[0]
      : null;
    const selectedTitle = String(selectedOption?.dataset?.title || document.getElementById('onlineScriptTitleInput')?.value || '').trim();
    if (!confirm(`Delete online script "${selectedTitle || 'Untitled'}"?`)) return;
    try {
      await deleteWorkspaceOnline(id);
    } catch (err) {
      log('Online delete failed: ' + err.message);
      alert('Could not delete online script: ' + err.message);
    }
  });
  bind('arrangeBtn', 'click', () => {
    arrangeWorkspaceBlocks();
  });
  bind('fileInput', 'change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      currentFileHandle = null;
      loadWorkspaceFromText(reader.result, file.name);
    };
    reader.readAsText(file);
    e.target.value = '';
  });
  bind('simThumbLeftBtn', 'click', async () => {
    await dispatchEvent({ type: 'thumbKey', key: 'left' }, runGeneration);
  });
  bind('simThumbRightBtn', 'click', async () => {
    await dispatchEvent({ type: 'thumbKey', key: 'right' }, runGeneration);
  });
  bind('simCursor5Btn', 'click', async () => {
    await dispatchEvent({ type: 'thumbKey', key: 'left-middle' }, runGeneration);
  });
  bind('simChord1Btn', 'click', async () => {
    await dispatchEvent({ type: 'thumbKey', key: 'right-middle' }, runGeneration);
  });
  bind('playSoundBtn', 'click', async () => {
    const input = document.getElementById('soundFileInput');
    await playSound(input ? input.value : '');
  });
  bind('pauseSoundBtn', 'click', pauseSound);
  bind('resumeSoundBtn', 'click', async () => {
    await resumeSound();
  });
  bind('stopSoundBtn', 'click', stopSound);
  bind('copyLogBtn', 'click', copyLogBox);
  bind('clearLogBtn', 'click', clearLogBox);
  bind('saveInstructionTtsBtn', 'click', async () => {
    try {
      await saveInstructionPlaylistAndInsertBlock();
    } catch (err) {
      log(`Instruction playlist save/insert failed: ${err.message}`);
      alert('Could not save instruction playlist and insert block: ' + err.message);
    }
  });
  bind('elevenlabsLoginBtn', 'click', async () => {
    if (getElevenLabsAuthToken()) {
      logoutElevenLabsAuth();
      return;
    }
    await loginElevenLabsAuth();
  });
  const baseUrlBox = document.getElementById('soundBaseUrlBox');
  if (baseUrlBox && !baseUrlBox.dataset.initialized) {
    baseUrlBox.textContent = SOUND_BASE_URL;
    baseUrlBox.dataset.initialized = '1';
  }

  const fileNameInput = document.getElementById('fileNameInput');
  if (fileNameInput && !fileNameInput.dataset.initialized) {
    fileNameInput.value = normalizeXmlFileName(fileNameInput.value || 'braille-activity.blockly');
    fileNameInput.addEventListener('change', () => {
      const normalized = normalizeXmlFileName(fileNameInput.value);
      fileNameInput.value = normalized;
      currentFileHandle = null;
    });
    fileNameInput.dataset.initialized = '1';
  }

  const gridSnapBtn = document.getElementById('gridSnapBtn');
  if (gridSnapBtn && !gridSnapBtn.dataset.initialized) {
    const savedSnap = localStorage.getItem(BLOCKLY_GRID_SNAP_KEY);
    gridSnapEnabled = savedSnap !== 'false';
    renderGridSnapControl();
    gridSnapBtn.dataset.initialized = '1';
  }

  const monitorToggleBtn = document.getElementById('monitorToggleBtn');
  if (monitorToggleBtn && !monitorToggleBtn.dataset.initialized) {
    brailleMonitorVisible = localStorage.getItem(BLOCKLY_MONITOR_VISIBLE_KEY) !== 'false';
    renderBrailleMonitorToggleControl();
    monitorToggleBtn.dataset.initialized = '1';
  }

  const savedSidebarWidth = localStorage.getItem(BLOCKLY_SIDEBAR_WIDTH_KEY);
  if (savedSidebarWidth) {
    applySidebarWidth(savedSidebarWidth);
  } else {
    applySidebarWidth(sidebarWidth);
  }
  bindMainDividerResize();

  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  if (sidebarToggleBtn && !sidebarToggleBtn.dataset.initialized) {
    const main = document.getElementById('main');
    if (main) {
      main.classList.add('is-sidebar-hidden');
    }
    renderSidebarToggleControl();
    sidebarToggleBtn.dataset.initialized = '1';
  }

  const metaTitle = document.getElementById('scriptMetaTitle');
  const metaDescription = document.getElementById('scriptMetaDescription');
  const metaInstruction = document.getElementById('scriptMetaInstruction');
  const metaPrompt = document.getElementById('scriptMetaPrompt');
  const onlineTitle = document.getElementById('onlineScriptTitleInput');
  const onlineStatus = document.getElementById('onlineScriptStatusInput');
  if (metaTitle && !metaTitle.dataset.initialized) {
    metaTitle.addEventListener('input', () => {
      refreshWorkspaceDirtyState();
      renderStatusScriptName();
    });
    metaTitle.dataset.initialized = '1';
  }
  if (metaDescription && !metaDescription.dataset.initialized) {
    metaDescription.addEventListener('input', () => refreshWorkspaceDirtyState());
    metaDescription.dataset.initialized = '1';
  }
  if (metaInstruction && !metaInstruction.dataset.initialized) {
    metaInstruction.addEventListener('input', () => {
      refreshWorkspaceDirtyState();
      renderInstructionTtsControl();
    });
    metaInstruction.dataset.initialized = '1';
  }
  const instructionTtsVoiceSelect = document.getElementById('instructionTtsVoiceSelect');
  if (instructionTtsVoiceSelect && !instructionTtsVoiceSelect.dataset.initialized) {
    instructionTtsVoiceSelect.addEventListener('change', () => renderInstructionTtsControl());
    instructionTtsVoiceSelect.dataset.initialized = '1';
  }
  renderElevenLabsAuthStatus();
  if (metaPrompt && !metaPrompt.dataset.initialized) {
    metaPrompt.addEventListener('input', () => refreshWorkspaceDirtyState());
    metaPrompt.dataset.initialized = '1';
  }
  if (onlineTitle && !onlineTitle.dataset.initialized) {
    onlineTitle.addEventListener('input', () => {
      refreshWorkspaceDirtyState();
      renderStatusScriptName();
    });
    onlineTitle.dataset.initialized = '1';
  }
  if (onlineStatus && !onlineStatus.dataset.initialized) {
    onlineStatus.addEventListener('change', () => refreshWorkspaceDirtyState());
    onlineStatus.dataset.initialized = '1';
  }

  const onlineSelect = document.getElementById('onlineScriptsSelect');
  if (onlineSelect && !onlineSelect.dataset.initialized) {
    onlineSelect.addEventListener('change', async () => {
      const previousOnlineId = currentOnlineScriptId;
      const previousOnlineTitle = String(document.getElementById('onlineScriptTitleInput')?.value || '').trim();
      const previousOnlineStatus = String(document.getElementById('onlineScriptStatusInput')?.value || 'draft').trim() || 'draft';
      const selectedId = String(onlineSelect.value || '').trim();
      if (!selectedId) return;
      const selectedOption = onlineSelect.selectedOptions && onlineSelect.selectedOptions[0]
        ? onlineSelect.selectedOptions[0]
        : null;
      const selectedTitle = String(selectedOption?.dataset?.title || '').trim();
      const selectedStatus = String(selectedOption?.dataset?.status || 'draft').trim() || 'draft';
      onlineSelect.style.removeProperty('color');
      if (!(await confirmActionWithUnsavedChanges('loading an online script'))) {
        onlineSelect.value = previousOnlineId || '';
        setOnlineCurrentScript({ id: previousOnlineId, title: previousOnlineTitle, status: previousOnlineStatus });
        return;
      }
      try {
        setOnlineScriptInputs({ id: selectedId, title: selectedTitle, status: selectedStatus });
        await loadWorkspaceOnline(selectedId);
        log(`Online script selected: ${selectedId}`);
      } catch (err) {
        log('Online auto-load failed: ' + err.message);
        alert('Could not load online script: ' + err.message);
        onlineSelect.value = previousOnlineId || '';
        setOnlineCurrentScript({ id: previousOnlineId, title: previousOnlineTitle, status: previousOnlineStatus });
      }
    });
    onlineSelect.dataset.initialized = '1';
  }

  bindVariableEditorControls();
}

bindWsControls();
bindAppControls();
renderBrailleBridgeIndicator();
connectWs('manual');
window.addEventListener('beforeunload', (event) => {
  if (!workspaceDirty) return;
  event.preventDefault();
  event.returnValue = '';
});

/* ---------------- Workspace ---------------- */
function ensureProcedureToolboxCategory() {
  const toolbox = document.getElementById('toolbox');
  if (!toolbox || toolbox.querySelector('category[custom="PROCEDURE"]')) return;
  const category = document.createElement('category');
  category.setAttribute('name', 'Procedures');
  category.setAttribute('custom', 'PROCEDURE');
  category.setAttribute('colour', '#BE185D');
  toolbox.appendChild(category);
}

function verifyProcedureBlocksAvailable() {
  const hasDefNoReturn = !!Blockly?.Blocks?.procedures_defnoreturn;
  const hasCallNoReturn = !!Blockly?.Blocks?.procedures_callnoreturn;
  if (!hasDefNoReturn || !hasCallNoReturn) {
    log('Warning: standard Blockly procedure blocks are missing from this bundle');
  }
}

function getProcedureName(block) {
  if (!block) return '';

  if (typeof block.getFieldValue === 'function') {
    const fromField = String(block.getFieldValue('NAME') ?? '').trim();
    if (fromField) return fromField;

    const extraState = typeof block.getExtraState === 'function'
      ? block.getExtraState()
      : (block.extraState_ ?? block.extraState ?? null);
    const fromExtra = String(extraState?.name ?? '').trim();
    if (fromExtra) return fromExtra;

    return '';
  }

  const fromJsonField = String(block?.fields?.NAME ?? '').trim();
  if (fromJsonField) return fromJsonField;

  const fromJsonExtra = String(block?.extraState?.name ?? '').trim();
  if (fromJsonExtra) return fromJsonExtra;

  return '';
}

function walkSerializedBlocks(block, visit) {
  if (!block || typeof block !== 'object') return;
  visit(block);

  const inputs = block.inputs;
  if (inputs && typeof inputs === 'object') {
    Object.values(inputs).forEach(input => {
      if (!input || typeof input !== 'object') return;
      if (input.block) walkSerializedBlocks(input.block, visit);
      if (input.shadow) walkSerializedBlocks(input.shadow, visit);
    });
  }

  const nextBlock = block.next?.block;
  if (nextBlock) walkSerializedBlocks(nextBlock, visit);
}

function registerProcedures(workspaceJson, runtimeState = null) {
  runtimeState = runtimeState || getRuntime();
  const procedureMap = new Map();
  const setProcedure = (name, entry) => {
    const key = String(name ?? '').trim();
    if (!key) return;
    procedureMap.set(key, entry);
  };

  if (workspaceJson && typeof workspaceJson.getAllBlocks === 'function') {
    workspaceJson.getAllBlocks(false).forEach(block => {
      if (block.type !== 'procedures_defnoreturn') return;
      const name = getProcedureName(block);
      setProcedure(name, {
        name,
        blockId: String(block.id || ''),
        block
      });
    });
  } else {
    const topBlocks = Array.isArray(workspaceJson?.blocks?.blocks)
      ? workspaceJson.blocks.blocks
      : [];
    topBlocks.forEach(topBlock => {
      walkSerializedBlocks(topBlock, block => {
        if (block?.type !== 'procedures_defnoreturn') return;
        const name = getProcedureName(block);
        setProcedure(name, {
          name,
          blockId: String(block.id || ''),
          block: null
        });
      });
    });
  }

  runtimeState.procedures = procedureMap;
  return procedureMap;
}

function refreshProcedureRegistry(source = workspace) {
  registerProcedures(source, runtime);
}

function refreshCompoundLibraryToolboxCategory() {
  const toolbox = typeof workspace?.getToolbox === 'function' ? workspace.getToolbox() : null;
  if (toolbox && typeof toolbox.refreshSelection === 'function') {
    toolbox.refreshSelection();
  }
}

function findCompoundLibraryItemByDisplayText(text) {
  const value = String(text || '').trim();
  if (!value) return null;
  return currentCompoundLibraryItems.find((item) => {
    const title = String(item?.title || '').trim();
    const id = String(item?.id || '').trim();
    return value === title || value === id;
  }) || null;
}

function createToolboxElement(tagName, attributes = {}) {
  const element = Blockly.utils.xml.createElement(tagName);
  Object.entries(attributes).forEach(([key, value]) => {
    if (value == null) return;
    element.setAttribute(key, String(value));
  });
  return element;
}

function registerCompoundLibraryToolboxCategory() {
  if (!workspace || typeof workspace.registerToolboxCategoryCallback !== 'function') return;
  workspace.registerToolboxCategoryCallback('MY_BLOCKS_LIBRARY', () => {
    const xmlItems = [];
    if (!Array.isArray(currentCompoundLibraryItems) || currentCompoundLibraryItems.length === 0) {
      xmlItems.push(createToolboxElement('label', { text: 'No saved compound blocks yet' }));
      xmlItems.push(createToolboxElement('label', { text: 'Select a block and use Save Selection' }));
      return xmlItems;
    }

    currentCompoundLibraryItems.forEach((item) => {
      const id = String(item?.id || '').trim();
      if (!id) return;
      const title = String(item?.title || '').trim();
      const callbackKey = `compound_library_insert_${id}`;
      if (typeof workspace.registerButtonCallback === 'function') {
        workspace.registerButtonCallback(callbackKey, () => {
          void insertCompoundLibraryItem(id).catch((err) => {
            log(`Compound toolbox insert failed: ${err.message}`);
            alert('Could not insert compound block: ' + err.message);
          });
        });
      }
      xmlItems.push(createToolboxElement('button', {
        text: title ? `${title}` : id,
        callbackKey
      }));
    });
    return xmlItems;
  });
}

function registerVariableToolboxButtons() {
  if (!workspace || typeof workspace.registerButtonCallback !== 'function') return;
  workspace.registerButtonCallback('ADD_EXTERNAL_VARIABLE', () => openVariableModal('', 'external'));
  workspace.registerButtonCallback('ADD_INTERNAL_VARIABLE', () => openVariableModal('', 'internal'));
}

function unregisterContextMenuItem(id) {
  const registry = Blockly?.ContextMenuRegistry?.registry;
  if (!registry || typeof registry.getItem !== 'function' || typeof registry.unregister !== 'function') {
    return;
  }
  if (registry.getItem(id)) {
    registry.unregister(id);
  }
}

function registerCustomContextMenuItems() {
  const registry = Blockly?.ContextMenuRegistry?.registry;
  const ScopeType = Blockly?.ContextMenuRegistry?.ScopeType;
  if (!registry || !ScopeType || typeof registry.register !== 'function') return;

  unregisterContextMenuItem('braille_copy_block_to_clipboard');
  unregisterContextMenuItem('braille_save_to_my_blocks');
  unregisterContextMenuItem('braille_paste_block_from_clipboard');
  unregisterContextMenuItem('braille_edit_script_variable');

  registry.register({
    id: 'braille_copy_block_to_clipboard',
    scopeType: ScopeType.BLOCK,
    displayText: () => 'Copy Block to Clipboard',
    preconditionFn: (scope) => {
      const block = scope?.block;
      return block && !block.isInFlyout ? 'enabled' : 'hidden';
    },
    callback: (scope) => {
      void copyBlocklyBlockToClipboard(scope.block).catch((err) => {
        log('Copy block failed: ' + err.message);
        alert('Could not copy block: ' + err.message);
      });
    },
    weight: 205,
  });

  registry.register({
    id: 'braille_save_to_my_blocks',
    scopeType: ScopeType.BLOCK,
    displayText: () => 'Save to My Blocks',
    preconditionFn: (scope) => {
      const block = scope?.block;
      return block && !block.isInFlyout ? 'enabled' : 'hidden';
    },
    callback: (scope) => {
      lastSelectedBlocklyBlockId = String(scope?.block?.id || '');
      void saveSelectedCompoundLibraryItem({ overwrite: true }).catch((err) => {
        log('Save to My Blocks failed: ' + err.message);
        alert('Could not save compound block: ' + err.message);
      });
    },
    weight: 206,
  });

  registry.register({
    id: 'braille_paste_block_from_clipboard',
    scopeType: ScopeType.WORKSPACE,
    displayText: () => 'Paste Block from Clipboard',
    preconditionFn: () => 'enabled',
    callback: () => {
      void pasteBlocklyBlockFromClipboard().catch((err) => {
        log('Paste block failed: ' + err.message);
        alert('Could not paste block: ' + err.message);
      });
    },
    weight: 205,
  });

  registry.register({
    id: 'braille_edit_script_variable',
    scopeType: ScopeType.BLOCK,
    displayText: () => 'Edit Script Variable',
    preconditionFn: (scope) => {
      const block = scope?.block;
      if (!block || block.isInFlyout) return 'hidden';
      const variableBlockTypes = new Set([
        'external_variable_get',
        'external_variable_set',
        'external_variable_exists',
        'external_property_get'
      ]);
      return variableBlockTypes.has(block.type) ? 'enabled' : 'hidden';
    },
    callback: (scope) => {
      const block = scope?.block;
      const name = String(block?.getFieldValue?.('VAR') || '').trim();
      const scopeName = 'external';
      const variable = findScriptVariable(scopeName, name);
      if (variable) openVariableModal(variable.id, scopeName);
    },
    weight: 204,
  });
}

function registerMyBlocksFlyoutContextMenu() {
  const workspaceWrap = document.getElementById('workspaceWrap');
  if (!workspaceWrap || workspaceWrap.dataset.myBlocksContextMenuInitialized === '1') return;
  workspaceWrap.addEventListener('contextmenu', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const flyoutButton = target.closest('.blocklyFlyoutButton');
    if (!flyoutButton) return;
    const flyout = target.closest('.blocklyFlyout');
    if (!flyout) return;
    const textNode = flyoutButton.querySelector('text');
    const label = String(textNode?.textContent || '').trim();
    const item = findCompoundLibraryItemByDisplayText(label);
    if (!item) return;
    event.preventDefault();
    if (!confirm(`Delete My Block "${String(item.title || item.id)}"?`)) return;
    void deleteCompoundLibraryItem(String(item.id || '')).catch((err) => {
      log('Delete My Block failed: ' + err.message);
      alert('Could not delete compound block: ' + err.message);
    });
  });
  workspaceWrap.dataset.myBlocksContextMenuInitialized = '1';
}

async function preloadLessonToolboxPlaceholders() {
  try {
    const res = await fetch(DEFAULT_LESSON_DATA_URL, { cache: 'no-store' });
    if (!res.ok) {
      throw new Error(`HTTP ${res.status} ${res.statusText}`);
    }
    const data = await res.json();
    const list = Array.isArray(data)
      ? data
      : (Array.isArray(data?.items) ? data.items : []);
    const first = list.find(item => item && typeof item === 'object') || null;
    if (!first) return;
    const word = String(first.word || '').trim() || 'bal';
    const sounds = Array.isArray(first.sounds) ? first.sounds.map(item => String(item ?? '').trim()).filter(Boolean) : [];
    window.BrailleBlocklyDefaultLessonPlaceholders = {
      word,
      soundsCsv: sounds.length ? sounds.join(',') : 'b,a,l'
    };
  } catch {}
}

if (!IS_HEADLESS_SESSION_PLAYER) {
  preloadLessonToolboxPlaceholders();
  ensureProcedureToolboxCategory();
}
verifyProcedureBlocksAvailable();
if (!IS_HEADLESS_SESSION_PLAYER) {
  registerCustomContextMenuItems();
}

setBootStage('before-workspace-inject');
workspace = IS_HEADLESS_SESSION_PLAYER
  ? new Blockly.Workspace()
  : Blockly.inject('blocklyDiv', {
      toolbox: document.getElementById('toolbox'),
      trashcan: true,
      renderer: 'geras',
      move: { drag: true, wheel: true, scrollbars: true },
      zoom: {
        controls: true,
        wheel: true,
        startScale: 1,
        maxScale: 2,
        minScale: 0.5,
        scaleSpeed: 1.1
      },
      grid: {
        spacing: 20,
        length: 3,
        colour: '#d1d5db',
        snap: true
      }
    });
if (!IS_HEADLESS_SESSION_PLAYER) {
  registerCompoundLibraryToolboxCategory();
  registerVariableToolboxButtons();
  registerMyBlocksFlyoutContextMenu();
}
setBootStage('workspace-injected');

if (!IS_HEADLESS_SESSION_PLAYER) {
  applyGridSnap(localStorage.getItem(BLOCKLY_GRID_SNAP_KEY) !== 'false');
}

function resizeBlockly() {
  if (!workspace || IS_HEADLESS_SESSION_PLAYER) return;
  Blockly.svgResize(workspace);
}

function blurToolboxFocusAfterPointerSelection() {
  const toolboxDiv = document.querySelector('.blocklyToolboxDiv');
  if (!toolboxDiv || toolboxDiv.dataset.focusPatchInitialized === '1') return;

  const blurToolboxFocus = () => {
    requestAnimationFrame(() => {
      const active = document.activeElement;
      if (active instanceof HTMLElement && toolboxDiv.contains(active)) {
        active.blur();
      }
    });
  };

  toolboxDiv.addEventListener('pointerup', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.closest('.blocklyTreeRow, .blocklyToolboxCategory, [role="treeitem"]')) {
      blurToolboxFocus();
    }
  });

  toolboxDiv.dataset.focusPatchInitialized = '1';
}

window.addEventListener('resize', resizeBlockly);
if (window.ResizeObserver) {
  const ro = new ResizeObserver(() => resizeBlockly());
  const workspaceWrap = document.getElementById('workspaceWrap');
  if (workspaceWrap) ro.observe(workspaceWrap);
}
if (!IS_HEADLESS_SESSION_PLAYER) {
  setTimeout(resizeBlockly, 0);
  setTimeout(blurToolboxFocusAfterPointerSelection, 0);
}

function ensureDefaultVariable() {
  const names = workspace.getAllVariables().map(v => v.name);
  if (!names.includes('score')) workspace.createVariable('score');
  if (!names.includes('count')) workspace.createVariable('count');
}

function initVariableValues() {
  for (const v of workspace.getAllVariables()) {
    if (!(v.getId() in variableValues)) {
      variableValues[v.getId()] = 0;
    }
  }
  renderStatus();
}

workspace.addChangeListener(() => {
  refreshProcedureRegistry(workspace);
  initVariableValues();
  refreshWorkspaceDirtyState();
});

workspace.addChangeListener((event) => {
  if (suppressDirtyTracking > 0 || !event || event.isUiEvent === true) return;
    const dirtyEventTypes = new Set([
    Blockly.Events.BLOCK_CREATE,
    Blockly.Events.BLOCK_DELETE,
    Blockly.Events.BLOCK_CHANGE,
    Blockly.Events.BLOCK_MOVE,
    Blockly.Events.VAR_CREATE,
    Blockly.Events.VAR_DELETE,
    Blockly.Events.VAR_RENAME,
    Blockly.Events.COMMENT_CREATE,
    Blockly.Events.COMMENT_DELETE,
    Blockly.Events.COMMENT_CHANGE,
    Blockly.Events.COMMENT_MOVE,
    'create',
    'delete',
    'change',
    'move',
    'var_create',
    'var_delete',
    'var_rename',
    'comment_create',
    'comment_delete',
    'comment_change',
    'comment_move'
  ]);
  if (dirtyEventTypes.has(event.type)) {
    if (event.type === Blockly.Events.BLOCK_CREATE || event.type === Blockly.Events.BLOCK_DELETE || event.type === Blockly.Events.BLOCK_CHANGE || event.type === 'create' || event.type === 'delete' || event.type === 'change') {
      renderVariableList();
      refreshVariableToolbox();
      initializeRuntimeVariableScopes();
    }
    setWorkspaceDirty(true);
  }
});

workspace.addChangeListener((event) => {
  if (!event || event.isUiEvent !== true) return;
  if (event.type !== Blockly.Events.SELECTED) return;
  const newElementId = String(event.newElementId || '').trim();
  if (!newElementId) return;
  const block = workspace.getBlockById(newElementId);
  if (block) {
    lastSelectedBlocklyBlockId = block.id;
  }
});

ensureDefaultVariable();
initVariableValues();
setBootStage('workspace-ready');

setTimeout(() => {
  log(`Embedded mode: ${EMBED_MODE || 'none'}${IS_EMBEDDED_SESSION_PLAYER ? ' (session-player)' : ''}`);
  log(`Embedded mode source: query="${new URLSearchParams(window.location.search).get('embed') || ''}", configured="${String(BRAILLE_BLOCKLY_MODE_CONFIG.embedMode || '')}", stored="${(() => { try { return window.sessionStorage.getItem('brailleBlocklyEmbedMode') || ''; } catch { return ''; } })()}"`);
  const token = getValidElevenLabsAuthToken();
  log(`Legacy authentication token at startup: ${token ? 'present' : 'missing'}`);
  if (IS_EMBEDDED_SESSION_PLAYER) return;
  void refreshOnlineScriptsIfAuthenticated('startup');
  void refreshCompoundLibraryIfAuthenticated('startup');
}, 0);

if (!IS_EMBEDDED_SESSION_PLAYER) {
  window.addEventListener('pageshow', () => {
    void refreshOnlineScriptsIfAuthenticated('pageshow');
    void refreshCompoundLibraryIfAuthenticated('pageshow');
  });

  window.addEventListener('focus', () => {
    void refreshOnlineScriptsIfAuthenticated('focus');
    void refreshCompoundLibraryIfAuthenticated('focus');
  });
}

function getVariableIdFromBlock(block) {
  const field = block.getField('VAR');
  const model = field?.getVariable();
  return model ? model.getId() : null;
}

function getVariableModelByName(name) {
  return workspace.getAllVariables().find(v => v.name === name) || null;
}

function ensureRuntimeVariableByName(name, fallback = 0) {
  const model = getVariableModelByName(name);
  if (!model) return null;
  const id = model.getId();
  if (!(id in variableValues)) {
    variableValues[id] = fallback;
  }
  return id;
}

function resetRunScopedVariables() {
  const countId = ensureRuntimeVariableByName('count', 0);
  if (countId) {
    variableValues[countId] = 0;
  }
  initializeRuntimeVariableScopes(lessonStepExternalOverrides);
  renderStatus();
}

function patchVariableFieldIds(xmlDom) {
  if (!xmlDom || typeof xmlDom.querySelectorAll !== 'function') return;
  const fields = xmlDom.querySelectorAll('field[name="VAR"]');
  fields.forEach(field => {
    const model = getVariableModelByName(field.textContent.trim());
    if (model) field.setAttribute('id', model.getId());
  });
}

function normalizeWorkspaceXmlText(text) {
  let src = String(text ?? '');
  if (src.charCodeAt(0) === 0xFEFF) src = src.slice(1);
  src = src.trim();

  // Some files may contain wrappers; keep only the Blockly <xml> payload.
  const start = src.indexOf('<xml');
  const end = src.lastIndexOf('</xml>');
  if (start >= 0 && end > start) {
    return src.slice(start, end + '</xml>'.length);
  }
  return src;
}

function parseWorkspaceXml(text) {
  const normalized = normalizeWorkspaceXmlText(text);
  if (!normalized) throw new Error('file is empty');
  try {
    const xmlDom = Blockly.utils.xml.textToDom(normalized);
    if (xmlDom && String(xmlDom.nodeName || '').toLowerCase() === 'xml') {
      return xmlDom;
    }
  } catch {
    // fall through to DOMParser fallback
  }

  const parsed = new DOMParser().parseFromString(normalized, 'text/xml');
  const parseError = parsed.querySelector('parsererror');
  if (parseError) {
    throw new Error(parseError.textContent?.trim() || 'invalid XML');
  }
  const root = parsed.documentElement;
  if (root && String(root.nodeName || '').toLowerCase() === 'xml') {
    return root;
  }
  const nested = parsed.querySelector('xml');
  if (nested) return nested;
  throw new Error('root node is not <xml>');
}

const defaultXmlText = `
<xml xmlns="https://developers.google.com/blockly/xml"></xml>
`;

function loadDefaultWorkspace() {
  suppressDirtyTracking++;
  workspace.clear();
  ensureDefaultVariable();
  const xmlDom = Blockly.utils.xml.textToDom(defaultXmlText);
  patchVariableFieldIds(xmlDom);
  Blockly.Xml.domToWorkspace(xmlDom, workspace);
  refreshProcedureRegistry(workspace);
  setScriptVariables([], { markDirty: false });
  initVariableValues();
  suppressDirtyTracking--;
  markWorkspaceSaved(getWorkspaceSignature());
  log('Default workspace loaded');
}

loadDefaultWorkspace();

/* ---------------- Evaluation ---------------- */
function highlightBlock(blockId) {
  if (!workspace || typeof workspace.highlightBlock !== 'function') {
    return;
  }
  workspace.highlightBlock(blockId);
  setTimeout(() => {
    if (workspace && typeof workspace.highlightBlock === 'function') {
      workspace.highlightBlock(null);
    }
  }, 150);
}

function toNumber(v) {
  const n = Number(v);
  return Number.isNaN(n) ? 0 : n;
}

function toList(v) {
  return Array.isArray(v) ? v : [];
}

function toListIndex(v, listLength) {
  const i = Math.floor(toNumber(v)); // 0-based index: 0 points to the first list item
  if (!Number.isFinite(i)) return -1;
  if (i < 0 || i >= listLength) return -1;
  return i;
}

function listValueEquals(a, b) {
  if (Array.isArray(a) || Array.isArray(b)) return JSON.stringify(a) === JSON.stringify(b);
  return String(a) === String(b);
}

function resetListCursor(list) {
  if (Array.isArray(list)) listNextIndex.delete(list);
}

function nextListItem(list) {
  const src = toList(list);
  if (src.length === 0) return '';
  const cur = listNextIndex.get(src) ?? 0;
  const idx = ((cur % src.length) + src.length) % src.length;
  const out = src[idx];
  listNextIndex.set(src, (idx + 1) % src.length);
  return out;
}

function parseListFromJson(text) {
  try {
    const parsed = JSON.parse(String(text ?? ''));
    if (Array.isArray(parsed)) return parsed;
    if (Array.isArray(parsed?.items)) return parsed.items;
    if (Array.isArray(parsed?.woorden)) return parsed.woorden;
    if (Array.isArray(parsed?.words)) return parsed.words;
    if (Array.isArray(parsed?.list)) return parsed.list;
    return [];
  } catch {
    return [];
  }
}

function extractWorkspaceMetadata(xmlDom, sourceName = null) {
  if (!xmlDom) {
    return {
      title: sourceName || '',
      description: '',
      instruction: '',
      prompt: ''
    };
  }
  const title = String(xmlDom.getAttribute('data-title') || sourceName || '').trim();
  const description = String(xmlDom.getAttribute('data-description') || '').trim();
  const instruction = String(xmlDom.getAttribute('data-instruction') || '').trim();
  const prompt = String(xmlDom.getAttribute('data-prompt') || '').trim();
  return { title, description, instruction, prompt };
}

function extractWorkspaceVariablesFromXml(xmlDom) {
  const raw = String(xmlDom?.getAttribute?.('data-variables') || '').trim();
  if (!raw) return [];
  try {
    return normalizeScriptVariables(JSON.parse(raw));
  } catch {
    return [];
  }
}

function getSerializedShadowDefaultValue(shadow) {
  if (!shadow || typeof shadow !== 'object') return undefined;
  const type = String(shadow.type || '').trim();
  if (type === 'text') return String(shadow.fields?.TEXT ?? '');
  if (type === 'math_number') return Number(shadow.fields?.NUM ?? 0) || 0;
  if (type === 'logic_boolean') return String(shadow.fields?.BOOL || '').toUpperCase() === 'TRUE';
  return undefined;
}

function collectExternalVariablesFromState(state) {
  const variablesByName = new Map();
  const externalBlockTypes = new Set([
    'external_variable_get',
    'external_variable_set',
    'external_variable_exists',
    'external_property_get'
  ]);
  const addExternalVariable = (name, defaultValue) => {
    const normalizedName = String(name || '').trim();
    if (!normalizedName || variablesByName.has(normalizedName)) return;
    variablesByName.set(normalizedName, normalizeScriptVariable({
      name: normalizedName,
      scope: 'external',
      type: typeof defaultValue === 'number'
        ? 'number'
        : (typeof defaultValue === 'boolean' ? 'boolean' : 'string'),
      defaultValue: defaultValue ?? '',
      description: 'External variable detected from legacy Blockly blocks. Save the script to store the new metadata format.'
    }));
  };

  const visitBlock = (block, defaultValue = undefined) => {
    if (!block || typeof block !== 'object') return;
    if (externalBlockTypes.has(String(block.type || ''))) {
      addExternalVariable(block.fields?.VAR, defaultValue);
    }
    Object.values(block.inputs || {}).forEach((input) => {
      if (!input || typeof input !== 'object') return;
      const shadowDefault = getSerializedShadowDefaultValue(input.shadow);
      visitBlock(input.block, shadowDefault);
      visitBlock(input.shadow);
    });
    visitBlock(block.next?.block);
  };

  const blocks = state?.blocks?.blocks;
  if (Array.isArray(blocks)) {
    blocks.forEach((block) => visitBlock(block));
  }
  return Array.from(variablesByName.values()).filter(Boolean);
}

function mergeWorkspaceVariablesWithDetectedExternalVariables(variables, state) {
  const normalized = normalizeScriptVariables(variables);
  const seen = new Set(normalized.map((item) => `${item.scope}:${item.name}`));
  collectExternalVariablesFromState(state).forEach((variable) => {
    const key = `${variable.scope}:${variable.name}`;
    if (seen.has(key)) return;
    seen.add(key);
    normalized.push(variable);
  });
  return normalized;
}

function extractWorkspaceVariablesFromState(state, metadata = null) {
  if (Array.isArray(state?.scriptVariables)) return mergeWorkspaceVariablesWithDetectedExternalVariables(state.scriptVariables, state);
  if (Array.isArray(state?.variablesMetadata)) return mergeWorkspaceVariablesWithDetectedExternalVariables(state.variablesMetadata, state);
  if (Array.isArray(metadata?.variables)) return mergeWorkspaceVariablesWithDetectedExternalVariables(metadata.variables, state);
  return mergeWorkspaceVariablesWithDetectedExternalVariables([], state);
}

function getBlocklySerializableState(state) {
  if (!state || typeof state !== 'object') return state;
  const { scriptVariables: _scriptVariables, variablesMetadata: _variablesMetadata, ...blocklyState } = state;
  return blocklyState;
}

function buildLessonDataCandidates(source) {
  const normalizedSource = String(source || '').trim();
  const fileName = normalizedSource.split('/').pop() || 'aanvankelijklijst.json';
  const candidates = [
    normalizedSource,
    `https://www.tastenbraille.com/braillestudio/data/klanken/${fileName}`
  ].filter(Boolean);

  return [...new Set(candidates)];
}

async function getAanvankelijklijst() {
  const source = String(
    window.currentLessonMethod?.dataSource ||
    window.lessonDataSource ||
  DEFAULT_LESSON_DATA_URL
  ).trim() || DEFAULT_LESSON_DATA_URL;

  const candidates = buildLessonDataCandidates(source);
  const cacheKey = candidates[0] || source;

  if (lessonDataCache.has(cacheKey)) {
    const cached = lessonDataCache.get(cacheKey);
    return Array.isArray(cached) ? cached : [];
  }

  let lastError = null;
  for (const candidate of candidates) {
    try {
      const res = await fetch(candidate, { cache: 'no-store' });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status} ${res.statusText}`);
      }
      const data = await res.json();
      const list = Array.isArray(data)
        ? data
        : (Array.isArray(data?.items) ? data.items : []);
      lessonDataCache.set(cacheKey, list);
      window.lessonDataSource = candidate;
      return list;
    } catch (err) {
      const message = err?.message || String(err);
      log(`Lesson data source failed: ${candidate} (${message})`);
      lastError = new Error(`${candidate}: ${message}`);
    }
  }

  throw lastError || new Error('No lesson data source responded');
}

async function getFonemenNlStandaard() {
  if (fonemenNlJsonCache && typeof fonemenNlJsonCache === 'object') {
    return fonemenNlJsonCache;
  }

  let lastError = null;
  for (const url of FONEMEN_NL_JSON_URLS) {
    try {
      log(`Loading phoneme JSON: ${url}`);
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status} ${res.statusText}`);
      }
      const data = await res.json();
      fonemenNlJsonCache = data && typeof data === 'object' ? data : { phonemes: [] };
      log(`Phoneme JSON loaded: ${url}`);
      return fonemenNlJsonCache;
    } catch (err) {
      lastError = err;
      log(`Phoneme JSON failed: ${url} :: ${err?.message || err}`);
    }
  }

  throw lastError || new Error('Failed to load phoneme JSON');
}

function splitWordToNlFonemen(word, fonemenData) {
  const normalized = String(word ?? '').trim().toLowerCase();
  if (!normalized) return [];

  const allFonemen = Array.isArray(fonemenData?.phonemes) ? fonemenData.phonemes : [];
  const tokens = allFonemen
    .map(item => String(item?.phoneme ?? '').trim().toLowerCase())
    .filter(Boolean)
    .sort((a, b) => b.length - a.length);

  const out = [];
  let i = 0;
  while (i < normalized.length) {
    const ch = normalized[i];
    if (!/[a-z]/.test(ch)) {
      i += 1;
      continue;
    }

    let matched = '';
    for (const token of tokens) {
      if (token && normalized.startsWith(token, i)) {
        matched = token;
        break;
      }
    }

    if (matched) {
      out.push(matched);
      i += matched.length;
    } else {
      out.push(ch);
      i += 1;
    }
  }

  return out;
}

function splitTextToNlFonemen(text, fonemenData) {
  const normalized = String(text ?? '').toLowerCase();
  if (!normalized.trim()) return [];
  const words = normalized.match(/[a-z]+/g) || [];
  const out = [];
  for (const word of words) {
    out.push(...splitWordToNlFonemen(word, fonemenData));
  }
  return out;
}

async function findAanvankelijklijstItemByWord(word) {
  const target = String(word ?? '').trim().toLowerCase();
  if (!target) return null;
  const list = await getAanvankelijklijst();
  return list.find(item => String(item?.word ?? '').trim().toLowerCase() === target) || null;
}

async function ensureInjectedLessonData() {
  if (Array.isArray(window.aanvankelijkData) && window.aanvankelijkData.length > 0) {
    return window.aanvankelijkData;
  }

  try {
    const list = await getAanvankelijklijst();
    window.aanvankelijkData = Array.isArray(list) ? structuredClone(list) : [];
    log(`Lesson data loaded (${window.aanvankelijkData.length}) from ${String(window.lessonDataSource || window.currentLessonMethod?.dataSource || DEFAULT_LESSON_DATA_URL)}`);
    return window.aanvankelijkData;
  } catch (err) {
    const message = err?.message || String(err);
    window.aanvankelijkData = [];
    log(`Lesson data load failed: ${message}`);
    throw err;
  }
}

function getInjectedLessonData() {
  return Array.isArray(window.aanvankelijkData) ? window.aanvankelijkData : [];
}

function setLessonMethod(method = null) {
  const normalized = method && typeof method === 'object'
    ? {
        id: String(method.id ?? '').trim(),
        title: String(method.title ?? '').trim(),
        dataSource: String(method.dataSource ?? '').trim() || DEFAULT_LESSON_DATA_URL
      }
    : {
        id: '',
        title: '',
        dataSource: DEFAULT_LESSON_DATA_URL
      };
  window.currentLessonMethod = normalized;
  window.lessonDataSource = normalized.dataSource;
  return normalized;
}

function getLessonMethod() {
  return window.currentLessonMethod && typeof window.currentLessonMethod === 'object'
    ? window.currentLessonMethod
    : setLessonMethod(null);
}

function normalizeLessonStepInputs(inputs) {
  const source = inputs && typeof inputs === 'object' ? inputs : {};
  const text = String(source.text ?? '').trim() || DEFAULT_LESSON_STEP_INPUTS.text;
  const word = String(source.word ?? '').trim() || DEFAULT_LESSON_STEP_INPUTS.word;
  const letters = Array.isArray(source.letters)
    ? source.letters.map((item) => String(item ?? '').trim()).filter(Boolean)
    : String(source.letters ?? '').split(',').map((item) => item.trim()).filter(Boolean);
  const normalized = {
    text,
    word,
    letters: letters.length ? letters : [...DEFAULT_LESSON_STEP_INPUTS.letters],
    repeat: Math.max(1, Math.floor(Number(source.repeat ?? DEFAULT_LESSON_STEP_INPUTS.repeat) || 1))
  };
  Object.entries(source).forEach(([key, value]) => {
    if (key === 'text' || key === 'word' || key === 'letters' || key === 'repeat') return;
    normalized[key] = value;
  });
  return normalized;
}

function getLessonStepInputs() {
  return window.lessonStepInputs && typeof window.lessonStepInputs === 'object'
    ? normalizeLessonStepInputs(window.lessonStepInputs)
    : {
      text: DEFAULT_LESSON_STEP_INPUTS.text,
      word: DEFAULT_LESSON_STEP_INPUTS.word,
      letters: [...DEFAULT_LESSON_STEP_INPUTS.letters],
      repeat: DEFAULT_LESSON_STEP_INPUTS.repeat
    };
}

function getExternalOverridesFromStepInputs(stepInputs = null) {
  if (!stepInputs || typeof stepInputs !== 'object') return null;
  const overrides = {};
  Object.entries(stepInputs).forEach(([key, value]) => {
    const name = normalizeVariableName(key);
    if (name) overrides[name] = value;
  });
  getScriptVariables('external').forEach((variable) => {
    if (Object.prototype.hasOwnProperty.call(stepInputs, variable.name)) {
      overrides[variable.name] = stepInputs[variable.name];
    }
  });
  return overrides;
}

function resetLessonStepRuntimeState(stepInputs = null) {
  window.lessonStepInputs = normalizeLessonStepInputs(stepInputs);
  lessonStepExternalOverrides = getExternalOverridesFromStepInputs(stepInputs);
  initializeRuntimeVariableScopes(lessonStepExternalOverrides);
  getRuntime().stepCompletion = null;
  getRuntime().lessonCompletion = null;
}

function initializeRuntimeVariableScopes(externalOverrides = null) {
  const rt = getRuntime();
  const shouldPreserveActiveStepOverrides = externalOverrides == null
    && !rt.stopped
    && lessonStepExternalOverrides
    && typeof lessonStepExternalOverrides === 'object';
  const injectedSource = shouldPreserveActiveStepOverrides ? lessonStepExternalOverrides : externalOverrides;
  const injected = injectedSource && typeof injectedSource === 'object' ? injectedSource : {};
  const external = { ...injected };
  const debug = {
    injected,
    preservedActiveStepOverrides: shouldPreserveActiveStepOverrides,
    variables: []
  };

  getScriptVariables('external').forEach((variable) => {
    const keys = [variable.name, variable.id].map((item) => String(item || '').trim()).filter(Boolean);
    const injectedKey = keys.find((key) => Object.prototype.hasOwnProperty.call(injected, key));
    const value = injectedKey
      ? injected[injectedKey]
      : parseVariableDefaultValue(variable);
    keys.forEach((key) => {
      external[key] = value;
    });
    external[variable.name] = value;
    debug.variables.push({
      name: variable.name,
      id: variable.id,
      keys,
      injectedKey: injectedKey || '',
      value
    });
  });

  rt.external = external;
  log(`External runtime initialized: ${JSON.stringify({
    injectedKeys: Object.keys(injected),
    externalKeys: Object.keys(external),
    preservedActiveStepOverrides: debug.preservedActiveStepOverrides,
    variables: debug.variables
  })}`);
  renderStatus();
}

function findExternalRuntimeKey(name) {
  const key = String(name || '').trim();
  if (!key) return '';
  const external = getRuntime().external || {};
  if (Object.prototype.hasOwnProperty.call(external, key)) return key;
  const variable = findScriptVariable('external', key);
  const keys = [variable?.name, variable?.id].map((item) => String(item || '').trim()).filter(Boolean);
  const direct = keys.find((item) => Object.prototype.hasOwnProperty.call(external, item));
  if (direct) return direct;
  const lower = key.toLowerCase();
  const matchedKey = Object.keys(external).find((item) => String(item).toLowerCase() === lower);
  if (matchedKey) return matchedKey;
  const matchedVariableKey = keys.find((item) => {
    const itemLower = item.toLowerCase();
    return Object.keys(external).some((externalKey) => String(externalKey).toLowerCase() === itemLower);
  });
  return matchedVariableKey || '';
}

function externalVariableExists(name) {
  const key = findExternalRuntimeKey(name);
  log(`External exists: requested=${JSON.stringify(String(name || ''))} resolved=${JSON.stringify(key)} exists=${key !== ''}`);
  return key !== '';
}

function getExternalVariable(name) {
  const key = findExternalRuntimeKey(name);
  if (!key) {
    log(`External get: requested=${JSON.stringify(String(name || ''))} resolved="" value=undefined`);
    return undefined;
  }
  const external = getRuntime().external || {};
  const value = external[key];
  log(`External get: requested=${JSON.stringify(String(name || ''))} resolved=${JSON.stringify(key)} value=${formatLogValue(value)}`);
  return value;
}

function setExternalVariable(name, value) {
  const key = String(name || '').trim();
  if (!key) return;
  const rt = getRuntime();
  if (!rt.external || typeof rt.external !== 'object') rt.external = {};
  rt.external[key] = value;
  const variable = findScriptVariable('external', key);
  [variable?.name, variable?.id].forEach((alias) => {
    const aliasKey = String(alias || '').trim();
    if (aliasKey) rt.external[aliasKey] = value;
  });
  log(`External set: requested=${JSON.stringify(key)} aliases=${JSON.stringify([variable?.name, variable?.id].filter(Boolean))} value=${formatLogValue(value)}`);
  renderStatus();
}

function getExternalProperty(name, property) {
  const value = getExternalVariable(name);
  const key = String(property ?? '').trim();
  if (!key || value == null) return undefined;
  if (Array.isArray(value)) {
    const index = Math.floor(Number(key));
    return Number.isInteger(index) ? value[index] : undefined;
  }
  if (typeof value === 'object') return value[key];
  return undefined;
}

window.BrailleStudioAPI = window.BrailleStudioAPI || {};
Object.assign(window.BrailleStudioAPI, {
  getExternalVariable,
  setExternalVariable,
  externalVariableExists,
  getExternalProperty
});

function readStoredResolvedSessionPayload() {
  try {
    const raw = window.localStorage?.getItem(SESSION_RESOLVED_PAYLOAD_STORAGE_KEY) || '';
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch (err) {
    log('Resolved session storage read failed: ' + (err?.message || err));
    return null;
  }
}

function buildResolvedSessionStepMeta(payload = {}) {
  const meta = payload && typeof payload.meta === 'object' ? payload.meta : {};
  return {
    id: String(payload?.stepId || '').trim(),
    code: String(payload?.code || '').trim(),
    scriptId: String(payload?.scriptId || '').trim(),
    title: String(meta?.title || '').trim(),
    instruction: String(meta?.instruction || '').trim(),
    prompt: String(meta?.prompt || '').trim(),
    localScanData: String(payload?.localScanData || '').trim()
  };
}

async function applyResolvedSessionPayload(payload = {}, options = {}) {
  if (!payload || typeof payload !== 'object') {
    throw new Error('Resolved session payload must be an object');
  }

  const scriptId = String(payload.scriptId || '').trim();
  if (!scriptId) {
    throw new Error('Resolved session payload is missing scriptId');
  }

  const signature = JSON.stringify({
    scriptId,
    stepId: String(payload.stepId || '').trim(),
    code: String(payload.code || '').trim(),
    stepInputs: payload.stepInputs && typeof payload.stepInputs === 'object' ? payload.stepInputs : {},
    localScanData: String(payload.localScanData || '').trim()
  });

  if (!options.force && signature === lastAppliedResolvedSessionSignature) {
    log('Resolved session payload skipped: already applied');
    return {
      scriptId,
      skipped: true
    };
  }

  const stepInputs = payload.stepInputs && typeof payload.stepInputs === 'object'
    ? payload.stepInputs
    : {};

  await loadWorkspaceOnline(scriptId);
  window.currentLessonStep = buildResolvedSessionStepMeta(payload);
  resetLessonStepRuntimeState(stepInputs);
  renderStatus();
  lastAppliedResolvedSessionSignature = signature;
  log(`Resolved session payload applied: script=${scriptId}, step=${String(payload.stepId || '').trim() || '(none)'}, code=${String(payload.code || '').trim() || '(none)'}`);

  if (options.autoRun) {
    await onRunClicked();
  }

  return {
    scriptId,
    stepId: String(payload.stepId || '').trim(),
    code: String(payload.code || '').trim(),
    stepInputs: structuredClone(getLessonStepInputs()),
    stepMeta: window.currentLessonStep && typeof window.currentLessonStep === 'object'
      ? structuredClone(window.currentLessonStep)
      : null
  };
}

function normalizeOptionalNumber(value) {
  if (value == null || value === '') return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function normalizeOptionalString(value) {
  if (value == null) return null;
  const text = String(value).trim();
  return text === '' ? null : text;
}

function inferCompletionCorrectness(status, answer, expectedAnswer, score, maxScore) {
  if (status === 'completed') return true;
  if (answer != null && expectedAnswer != null) {
    return String(answer) === String(expectedAnswer);
  }
  if (score != null && maxScore != null) {
    return score >= maxScore;
  }
  return status !== 'failed';
}

function normalizeLessonStepCompletionPayload(payload = {}) {
  const status = String(payload?.status ?? 'completed').trim() || 'completed';
  const output = payload && Object.prototype.hasOwnProperty.call(payload, 'output') ? payload.output : null;
  const score = normalizeOptionalNumber(payload?.score);
  const maxScore = normalizeOptionalNumber(payload?.maxScore);
  const attempts = normalizeOptionalNumber(payload?.attempts);
  const durationMs = normalizeOptionalNumber(payload?.durationMs);
  const answer = payload && Object.prototype.hasOwnProperty.call(payload, 'answer') ? payload.answer : null;
  const expectedAnswer = payload && Object.prototype.hasOwnProperty.call(payload, 'expectedAnswer') ? payload.expectedAnswer : null;
  const feedback = normalizeOptionalString(payload?.feedback);
  const metadata = payload && typeof payload.metadata === 'object' && payload.metadata !== null
    ? structuredClone(payload.metadata)
    : null;
  const stepMeta = window.currentLessonStep && typeof window.currentLessonStep === 'object'
    ? structuredClone(window.currentLessonStep)
    : null;

  return {
    status,
    output,
    analytics: {
      score,
      maxScore,
      attempts,
      durationMs,
      isCorrect: inferCompletionCorrectness(status, answer, expectedAnswer, score, maxScore)
    },
    response: {
      answer,
      expectedAnswer,
      feedback
    },
    metadata,
    stepId: String(window.currentLessonStep?.id ?? '').trim() || null,
    stepIndex: Number.isFinite(Number(window.currentLessonStep?.stepIndex)) ? Number(window.currentLessonStep.stepIndex) : null,
    lessonId: normalizeOptionalString(window.currentLessonStep?.lessonId),
    lessonTitle: normalizeOptionalString(window.currentLessonStep?.lessonTitle),
    methodId: normalizeOptionalString(window.currentLessonStep?.methodId),
    basisWord: normalizeOptionalString(window.currentLessonStep?.basisWord),
    stepMeta,
    completedAt: new Date().toISOString()
  };
}

async function signalLessonStepCompletion(payload = {}) {
  const normalized = normalizeLessonStepCompletionPayload(payload);
  getRuntime().stepCompletion = normalized;
  getRuntime().stopped = true;
  stopAllTimers();
  setExecutionUiState(
    executionPhaseFromCompletionStatus(normalized.status, 'completed'),
    normalized.status === 'completed'
      ? 'Lesson step finished.'
      : `Lesson step ended: ${normalized.status}.`
  );
  log('Lesson step completion signaled: ' + normalized.status);
  renderStatus();
  return getRuntime().stepCompletion;
}

async function signalLessonCompletion(payload = {}) {
  const normalized = {
    status: normalizeOptionalString(payload?.status) || 'completed',
    lessonId: normalizeOptionalString(window.currentLessonStep?.lessonId),
    lessonTitle: normalizeOptionalString(window.currentLessonStep?.lessonTitle),
    methodId: normalizeOptionalString(window.currentLessonStep?.methodId),
    basisWord: normalizeOptionalString(window.currentLessonStep?.basisWord),
    completedAt: new Date().toISOString()
  };
  getRuntime().lessonCompletion = normalized;
  if (!getRuntime().stepCompletion) {
    await signalLessonStepCompletion({
      status: 'completed',
      feedback: 'Lesson completed'
    });
  } else {
    getRuntime().stopped = true;
    stopAllTimers();
    setExecutionUiState(
      executionPhaseFromCompletionStatus(normalized.status, 'completed'),
      normalized.status === 'completed'
        ? 'Lesson finished.'
        : `Lesson ended: ${normalized.status}.`
    );
    renderStatus();
  }
  log('Lesson completion signaled: ' + normalized.status);
  return getRuntime().lessonCompletion;
}

function getActiveLessonRecord() {
  const record = window.currentRecord;
  return record && typeof record === 'object' ? record : null;
}

async function setActiveLessonRecordByIndex(index) {
  const list = await ensureInjectedLessonData();
  const normalizedIndex = Math.floor(toNumber(index));
  const record = normalizedIndex >= 0 && normalizedIndex < list.length ? list[normalizedIndex] : null;
  window.currentRecord = record;
  window.currentRecordIndex = record ? normalizedIndex : -1;
  log(record
    ? `Active lesson record set: index=${normalizedIndex}, word=${String(record.word || '')}`
    : `Active lesson record not found for index=${normalizedIndex} (count=${list.length})`);
  return record;
}

function getLessonFieldValue(record, field) {
  const key = String(field || '');
  if (!record || typeof record !== 'object') {
    if (key === 'word') return '';
    if (key === 'categories' || key === 'newSoundCategories' || key === 'knownSoundCategories') return {};
    return [];
  }

  const value = record[key];
  if (value == null) {
    if (key === 'word') return '';
    if (key === 'categories' || key === 'newSoundCategories' || key === 'knownSoundCategories') return {};
    return [];
  }
  return value;
}

function getLessonCategoryArray(record, source, category) {
  const sourceKey = String(source || 'categories');
  const categoryKey = String(category || '');
  if (!record || typeof record !== 'object' || !categoryKey) return [];
  const root = record[sourceKey];
  if (!root || typeof root !== 'object') return [];
  return Array.isArray(root[categoryKey]) ? root[categoryKey] : [];
}

function getKlankenSourceValue(item, source) {
  const src = String(source || 'ALL');
  if (!item || typeof item !== 'object') return [];
  if (src === 'NEW') return toList(item.newSounds);
  if (src === 'KNOWN') return toList(item.knownSounds);
  return toList(item.sounds);
}

function getKlankenCategoryValue(item, source, category) {
  const src = String(source || 'ALL');
  const key = String(category || '');
  if (!item || typeof item !== 'object' || !key) return [];

  const categorySet =
    src === 'NEW' ? item.newSoundCategories :
    src === 'KNOWN' ? item.knownSoundCategories :
    item.categories;

  if (!categorySet || typeof categorySet !== 'object') return [];
  return toList(categorySet[key]);
}

function getPhonemeCategorySet(fonemenData, selectedCategory) {
  const normalizedCategory = String(selectedCategory || 'medeklinker').trim().toLowerCase();
  return new Set(
    (Array.isArray(fonemenData?.phonemes) ? fonemenData.phonemes : [])
      .filter((item) => String(item?.category ?? '').trim().toLowerCase() === normalizedCategory)
      .map((item) => String(item?.phoneme ?? '').trim().toLowerCase())
      .filter(Boolean)
  );
}

function getPhonemeCategorySetByList(fonemenData, selectedCategories) {
  const normalized = new Set(
    (Array.isArray(selectedCategories) ? selectedCategories : [])
      .map((item) => String(item || '').trim().toLowerCase())
      .filter(Boolean)
  );
  if (!normalized.size) {
    return new Set();
  }
  return new Set(
    (Array.isArray(fonemenData?.phonemes) ? fonemenData.phonemes : [])
      .filter((item) => normalized.has(String(item?.category ?? '').trim().toLowerCase()))
      .map((item) => String(item?.phoneme ?? '').trim().toLowerCase())
      .filter(Boolean)
  );
}

async function evalValue(block) {
  if (!block) return null;

  switch (block.type) {
    case 'text':
      return block.getFieldValue('TEXT');

    case 'lists_create_with': {
      const out = [];
      const count = Number(block.itemCount_ || block.inputList?.filter?.((input) => String(input.name || '').startsWith('ADD')).length || 0);
      for (let i = 0; i < count; i++) {
        out.push(await evalValue(block.getInputTargetBlock(`ADD${i}`)));
      }
      return out;
    }

    case 'math_number':
      return Number(block.getFieldValue('NUM'));

    case 'logic_boolean':
      return block.getFieldValue('BOOL') === 'TRUE';

    case 'state_text_caret':
      return runtime.textCaret;

    case 'state_cell_caret':
      return runtime.cellCaret;

    case 'state_last_thumb_key':
      return runtime.lastThumbKey;

    case 'state_last_cursor_cell':
      return runtime.lastCursorCell;

    case 'state_last_chord':
      return runtime.lastChord;

    case 'state_last_editor_key':
      return runtime.lastEditorKey;

    case 'state_editor_mode':
      return runtime.editorMode;

    case 'state_insert_mode':
      return runtime.insertMode;

    case 'bb_current_text':
      return runtime.text;

    case 'bb_current_braille_unicode':
      return runtime.brailleUnicode;

    case 'lesson_get_data':
      return await ensureInjectedLessonData();

    case 'lesson_get_record_count':
      return (await ensureInjectedLessonData()).length;

    case 'lesson_get_active_record':
      return getActiveLessonRecord();

    case 'lesson_get_active_record_index':
      return Number.isInteger(window.currentRecordIndex) ? window.currentRecordIndex : -1;

    case 'lesson_get_active_word': {
      const record = getActiveLessonRecord();
      return String(record?.word ?? '');
    }

    case 'lesson_get_active_field': {
      const record = getActiveLessonRecord();
      return getLessonFieldValue(record, block.getFieldValue('FIELD'));
    }

    case 'lesson_get_active_sounds': {
      const record = getActiveLessonRecord();
      return getKlankenSourceValue(record, block.getFieldValue('SOURCE'));
    }

    case 'lesson_get_active_sound_count': {
      const record = getActiveLessonRecord();
      return getKlankenSourceValue(record, block.getFieldValue('SOURCE')).length;
    }

    case 'lesson_get_active_category': {
      const record = getActiveLessonRecord();
      return getLessonCategoryArray(
        record,
        block.getFieldValue('SOURCE'),
        block.getFieldValue('CATEGORY')
      );
    }

    case 'lesson_get_active_category_count': {
      const record = getActiveLessonRecord();
      return getLessonCategoryArray(
        record,
        block.getFieldValue('SOURCE'),
        block.getFieldValue('CATEGORY')
      ).length;
    }

    case 'lesson_get_step_input': {
      const field = String(block.getFieldValue('FIELD') || 'text');
      const inputs = getLessonStepInputs();
      const value = inputs[field];
      if (field === 'letters') {
        return Array.isArray(value) ? value : [];
      }
      if (field === 'repeat') {
        return Math.max(1, Math.floor(Number(value) || 1));
      }
      return value != null ? value : '';
    }

    case 'lesson_get_step_repeat': {
      const inputs = getLessonStepInputs();
      return Math.max(1, Math.floor(Number(inputs.repeat) || 1));
    }

    case 'state_last_timer_name':
      return runtime.lastTimerName;

    case 'state_last_timer_tick':
      return runtime.lastTimerTick;

    case 'state_last_sound':
      return runtime.lastSound;

    case 'list_make': {
      const out = [];
      for (let i = 0; i < (block.itemCount_ || 0); i++) {
        out.push(await evalValue(block.getInputTargetBlock('ITEM' + i)));
      }
      return out;
    }

    case 'list_empty':
      return [];

    case 'list_get_item': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const idx = toListIndex(await evalValue(block.getInputTargetBlock('INDEX')), list.length);
      return idx >= 0 ? list[idx] : '';
    }

    case 'list_length': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      return list.length;
    }

    case 'list_nrof_items': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      return list.length;
    }

    case 'list_pick_random': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.pickRandom === 'function') {
        return window.BrailleStudioAPI.pickRandom(list);
      }
      if (list.length === 0) return null;
      return list[Math.floor(Math.random() * list.length)];
    }

    case 'list_random_item': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      if (list.length === 0) return '';
      return list[Math.floor(Math.random() * list.length)];
    }

    case 'list_shuffle': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const shuffled = [...list];
      for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
      }
      return shuffled;
    }

    case 'list_next_item': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      return nextListItem(list);
    }

    case 'list_contains_item': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const item = await evalValue(block.getInputTargetBlock('ITEM'));
      return list.some(x => listValueEquals(x, item));
    }

    case 'list_from_json': {
      const jsonText = await evalValue(block.getInputTargetBlock('JSON'));
      return parseListFromJson(jsonText);
    }

    case 'list_from_text_items': {
      const text = String(await evalValue(block.getInputTargetBlock('TEXT')) ?? '');
      return text
        .split(/[\s,;]+/)
        .map(item => item.trim())
        .filter(Boolean);
    }

    case 'list_filter_text_length': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const min = Math.floor(Number(await evalValue(block.getInputTargetBlock('MIN'))) || 0);
      return list.filter(item => Array.from(String(item ?? '')).length > min);
    }

    case 'list_filter_phoneme_category': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const fonemenData = await getFonemenNlStandaard();
      const allowed = getPhonemeCategorySet(fonemenData, block.getFieldValue('CATEGORY'));
      return list.filter((item) => allowed.has(String(item ?? '').trim().toLowerCase()));
    }

    case 'list_filter_phoneme_categories': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const fonemenData = await getFonemenNlStandaard();
      const selectedCategories = [
        ['KORTEKLINKER', 'korteKlinker'],
        ['LANGEKLINKER', 'langeKlinker'],
        ['TWEETEKENKLANK', 'tweetekenklank'],
        ['DRIETEKENKLANK', 'drietekenklank'],
        ['MEDEKLINKER', 'medeklinker'],
        ['MEDEKLINKERCLUSTER', 'medeklinkercluster'],
        ['VIERTEKENKLANK', 'viertekenklank']
      ]
        .filter(([field]) => String(block.getFieldValue(field)) === 'TRUE')
        .map(([, value]) => value);
      const allowed = getPhonemeCategorySetByList(fonemenData, selectedCategories);
      return list.filter((item) => allowed.has(String(item ?? '').trim().toLowerCase()));
    }

    case 'klanken_get_aanvankelijklijst': {
      try {
        return await getAanvankelijklijst();
      } catch (err) {
        log(`Aanvankelijklijst load failed: ${err}`);
        return [];
      }
    }

    case 'klanken_word_get_sounds': {
      try {
        const word = await evalValue(block.getInputTargetBlock('WORD'));
        const item = await findAanvankelijklijstItemByWord(word);
        return toList(item?.sounds);
      } catch (err) {
        log(`Phonemes word lookup failed: ${err}`);
        return [];
      }
    }

    case 'klanken_word_get_new_sounds': {
      try {
        const word = await evalValue(block.getInputTargetBlock('WORD'));
        const item = await findAanvankelijklijstItemByWord(word);
        return toList(item?.newSounds);
      } catch (err) {
        log(`Phonemes new sounds lookup failed: ${err}`);
        return [];
      }
    }

    case 'klanken_word_get_known_sounds': {
      try {
        const word = await evalValue(block.getInputTargetBlock('WORD'));
        const item = await findAanvankelijklijstItemByWord(word);
        return toList(item?.knownSounds);
      } catch (err) {
        log(`Phonemes known sounds lookup failed: ${err}`);
        return [];
      }
    }

    case 'klanken_item_get_word': {
      const item = await evalValue(block.getInputTargetBlock('ITEM'));
      return item && typeof item === 'object' ? String(item.word ?? '') : '';
    }

    case 'klanken_item_get_sounds': {
      const item = await evalValue(block.getInputTargetBlock('ITEM'));
      return getKlankenSourceValue(item, block.getFieldValue('SOURCE'));
    }

    case 'klanken_item_get_category': {
      const item = await evalValue(block.getInputTargetBlock('ITEM'));
      return getKlankenCategoryValue(
        item,
        block.getFieldValue('SOURCE'),
        block.getFieldValue('CATEGORY')
      );
    }

    case 'list_random_other_item': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const exclude = await evalValue(block.getInputTargetBlock('EXCLUDE'));
      const filtered = list.filter(x => !listValueEquals(x, exclude));
      if (filtered.length === 0) return '';
      return filtered[Math.floor(Math.random() * filtered.length)];
    }

    case 'klanken_get_speech_audio_by_onlyletters': {
      if (!window.BrailleStudioAPI || typeof window.BrailleStudioAPI.getAudioList !== 'function') {
        throw new Error('BrailleStudioAPI.getAudioList is not available');
      }
      const onlyletters = String(await evalValue(block.getInputTargetBlock('ONLYLETTERS')) ?? '');
      return await window.BrailleStudioAPI.getAudioList({
        folder: 'speech',
        letters: '',
        klanken: '',
        onlyletters,
        onlyklanken: '',
        onlycombo: false,
        maxlength: '',
        length: '',
        limit: '',
        randomlimit: '',
        sort: ''
      });
    }

    case 'audio_get_speech_audio_by_letters_klanken': {
      if (!window.BrailleStudioAPI || typeof window.BrailleStudioAPI.getAudioList !== 'function') {
        throw new Error('BrailleStudioAPI.getAudioList is not available');
      }
      const letters = String(await evalValue(block.getInputTargetBlock('LETTERS')) ?? '');
      const klanken = String(await evalValue(block.getInputTargetBlock('KLANKEN')) ?? '');
      return await window.BrailleStudioAPI.getAudioList({
        folder: 'speech',
        letters,
        klanken,
        onlyletters: '',
        onlyklanken: '',
        onlycombo: false,
        maxlength: '',
        length: '',
        limit: '',
        randomlimit: '',
        sort: ''
      });
    }

    case 'audio_get_speech_audio_by_onlyletters_klanken_length': {
      if (!window.BrailleStudioAPI || typeof window.BrailleStudioAPI.getAudioList !== 'function') {
        throw new Error('BrailleStudioAPI.getAudioList is not available');
      }
      const onlyletters = String(await evalValue(block.getInputTargetBlock('ONLYLETTERS')) ?? '');
      const klanken = String(await evalValue(block.getInputTargetBlock('KLANKEN')) ?? '');
      const length = String(await evalValue(block.getInputTargetBlock('LENGTH')) ?? '');
      return await window.BrailleStudioAPI.getAudioList({
        folder: 'speech',
        letters: '',
        klanken,
        onlyletters,
        onlyklanken: '',
        onlycombo: false,
        maxlength: '',
        length,
        limit: '',
        randomlimit: '',
        sort: ''
      });
    }

    case 'audio_get_speech_audio_by_onlyletters_length': {
      if (!window.BrailleStudioAPI || typeof window.BrailleStudioAPI.getAudioList !== 'function') {
        throw new Error('BrailleStudioAPI.getAudioList is not available');
      }
      const onlyletters = String(await evalValue(block.getInputTargetBlock('ONLYLETTERS')) ?? '');
      const length = String(await evalValue(block.getInputTargetBlock('LENGTH')) ?? '');
      return await window.BrailleStudioAPI.getAudioList({
        folder: 'speech',
        letters: '',
        klanken: '',
        onlyletters,
        onlyklanken: '',
        onlycombo: false,
        maxlength: '',
        length,
        limit: '',
        randomlimit: '',
        sort: ''
      });
    }

    case 'audio_item_get_word': {
      const item = await evalValue(block.getInputTargetBlock('ITEM'));
      if (!item || typeof item !== 'object') return '';
      return String(item.word ?? '');
    }

    case 'audio_item_get_url': {
      const item = await evalValue(block.getInputTargetBlock('ITEM'));
      if (!item || typeof item !== 'object') return '';
      return String(item.url ?? '');
    }

    case 'instruction_get_info_by_id': {
      if (!window.BrailleStudioAPI || typeof window.BrailleStudioAPI.getInstructionById !== 'function') {
        throw new Error('BrailleStudioAPI.getInstructionById is not available');
      }
      const instructionId = String(block.getFieldValue('INSTRUCTION_ID') || '').trim();
      if (!instructionId) return { ok: false, item: null };
      return {
        ok: true,
        item: await window.BrailleStudioAPI.getInstructionById(instructionId)
      };
    }

    case 'variables_get': {
      const id = getVariableIdFromBlock(block);
      return id ? (variableValues[id] ?? 0) : 0;
    }

    case 'external_variable_get':
      return getExternalVariable(block.getFieldValue('VAR'));

    case 'external_variable_exists':
      return externalVariableExists(block.getFieldValue('VAR'));

    case 'external_property_get':
      return getExternalProperty(block.getFieldValue('VAR'), await evalValue(block.getInputTargetBlock('PROPERTY')));

    case 'math_arithmetic': {
      const a = toNumber(await evalValue(block.getInputTargetBlock('A')));
      const b = toNumber(await evalValue(block.getInputTargetBlock('B')));
      switch (block.getFieldValue('OP')) {
        case 'ADD': return a + b;
        case 'MINUS': return a - b;
        case 'MULTIPLY': return a * b;
        case 'DIVIDE': return b === 0 ? 0 : a / b;
        case 'POWER': return Math.pow(a, b);
        default: return 0;
      }
    }

    case 'math_random_10': {
      const max = Math.floor(toNumber(await evalValue(block.getInputTargetBlock('MAX'))));
      if (max <= 0) return 0;
      return Math.floor(Math.random() * max);
    }

    case 'math_random_int': {
      const from = Math.floor(toNumber(await evalValue(block.getInputTargetBlock('FROM'))));
      const to = Math.floor(toNumber(await evalValue(block.getInputTargetBlock('TO'))));
      const min = Math.min(from, to);
      const max = Math.max(from, to);
      return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    case 'math_random_float':
      return Math.random();

    case 'logic_random_boolean':
      return Math.random() < 0.5;

    case 'logic_compare': {
      const a = await evalValue(block.getInputTargetBlock('A'));
      const b = await evalValue(block.getInputTargetBlock('B'));
      switch (block.getFieldValue('OP')) {
        case 'EQ': return a == b;
        case 'NEQ': return a != b;
        case 'LT': return a < b;
        case 'LTE': return a <= b;
        case 'GT': return a > b;
        case 'GTE': return a >= b;
        default: return false;
      }
    }

    case 'logic_operation': {
      const a = Boolean(await evalValue(block.getInputTargetBlock('A')));
      const b = Boolean(await evalValue(block.getInputTargetBlock('B')));
      return block.getFieldValue('OP') === 'AND' ? (a && b) : (a || b);
    }

    case 'logic_negate':
      return !Boolean(await evalValue(block.getInputTargetBlock('BOOL')));

    case 'text_join': {
      const count = block.itemCount_ || 0;
      let out = '';
      for (let i = 0; i < count; i++) {
        out += formatTextJoinValue(await evalValue(block.getInputTargetBlock('ADD' + i)));
      }
      return out;
    }

    case 'text_join_csv': {
      const a = await evalValue(block.getInputTargetBlock('A'));
      const b = await evalValue(block.getInputTargetBlock('B'));
      const c = await evalValue(block.getInputTargetBlock('C'));
      if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.joinCsv === 'function') {
        return window.BrailleStudioAPI.joinCsv([a, b, c]);
      }
      return [a, b, c]
        .map(v => String(v ?? '').trim())
        .filter(Boolean)
        .join(',');
    }

    case 'text_concat': {
      const a = await evalValue(block.getInputTargetBlock('A'));
      const b = await evalValue(block.getInputTargetBlock('B'));
      const toText = (value) => {
        if (Array.isArray(value)) return value.map(toText).join(', ');
        if (value == null) return '';
        if (typeof value === 'object') {
          try { return JSON.stringify(value); } catch (err) { return String(value); }
        }
        return String(value);
      };
      return toText(a) + toText(b);
    }

    case 'text_first_letter': {
      const text = String(await evalValue(block.getInputTargetBlock('TEXT')) ?? '');
      const chars = Array.from(text);
      return chars[0] ?? '';
    }

    case 'text_from_list': {
      const list = toList(await evalValue(block.getInputTargetBlock('LIST')));
      const separator = String(await evalValue(block.getInputTargetBlock('SEPARATOR')) ?? ' ');
      return list.map(item => String(item ?? '')).join(separator);
    }

    case 'text_contains': {
      const text = String(await evalValue(block.getInputTargetBlock('TEXT')) ?? '');
      const find = String(await evalValue(block.getInputTargetBlock('FIND')) ?? '');
      return find !== '' && text.includes(find);
    }

    case 'text_last_letter': {
      const text = String(await evalValue(block.getInputTargetBlock('TEXT')) ?? '');
      const chars = Array.from(text);
      return chars.length ? chars[chars.length - 1] : '';
    }

    case 'text_lowercase': {
      const text = String(await evalValue(block.getInputTargetBlock('TEXT')) ?? '');
      return text.toLowerCase();
    }

    case 'text_uppercase': {
      const text = String(await evalValue(block.getInputTargetBlock('TEXT')) ?? '');
      return text.toUpperCase();
    }

    case 'klanken_split_word_phonemes_nl': {
      try {
        const word = await evalValue(block.getInputTargetBlock('WORD'));
        const fonemenData = await getFonemenNlStandaard();
        return splitWordToNlFonemen(word, fonemenData);
      } catch (err) {
        log(`Phonemes split failed: ${err}`);
        return [];
      }
    }

    case 'klanken_split_text_phonemes_nl': {
      try {
        const text = await evalValue(block.getInputTargetBlock('TEXT'));
        const fonemenData = await getFonemenNlStandaard();
        return splitTextToNlFonemen(text, fonemenData);
      } catch (err) {
        log(`Phonemes split failed: ${err}`);
        return [];
      }
    }

    default:
      return null;
  }
}

/* ---------------- Execution ---------------- */
async function runProcedureCall(block, ctx = {}) {
  const callName = getProcedureName(block);
  if (!callName) {
    log('Warning: skipped procedure call with missing NAME');
    return;
  }

  const runtimeState = ctx.runtime || getRuntime();
  const targetWorkspace = ctx.workspace || workspace;
  const generation = Number.isFinite(ctx.generation) ? ctx.generation : runGeneration;
  const procedures = runtimeState?.procedures instanceof Map ? runtimeState.procedures : new Map();

  let entry = procedures.get(callName) || null;
  let definition = entry?.block || null;
  if (!definition && entry?.blockId && targetWorkspace && typeof targetWorkspace.getBlockById === 'function') {
    definition = targetWorkspace.getBlockById(entry.blockId);
  }

  if (!definition && targetWorkspace && typeof targetWorkspace.getAllBlocks === 'function') {
    refreshProcedureRegistry(targetWorkspace);
    entry = runtimeState.procedures.get(callName) || null;
    definition = entry?.block || null;
    if (!definition && entry?.blockId && typeof targetWorkspace.getBlockById === 'function') {
      definition = targetWorkspace.getBlockById(entry.blockId);
    }
  }

  if (!definition || definition.type !== 'procedures_defnoreturn') {
    log(`Warning: procedure not found: ${callName}`);
    return;
  }

  const firstStatement = definition.getInputTargetBlock('STACK');
  if (firstStatement) {
    await executeChain(firstStatement, generation);
  }
}

async function executeChain(startBlock, generation, allowStopped = false) {
  let current = startBlock;

  while (current && (allowStopped || !getRuntime().stopped) && generation === runGeneration) {
    highlightBlock(current.id);
    try {
      switch (current.type) {
      case 'bb_set_text': {
        const text = String(await evalValue(current.getInputTargetBlock('TEXT')) ?? '');
        runtime.text = text;
        renderStatus();
        sendEnvelope('editorInput', {
          input: { kind: 'text', text, replace: true }
        });
        break;
      }

      case 'bb_append_text': {
        const text = String(await evalValue(current.getInputTargetBlock('TEXT')) ?? '');
        runtime.text += text;
        renderStatus();
        sendEnvelope('editorInput', {
          input: { kind: 'text', text }
        });
        break;
      }

      case 'bb_send_key': {
        const key = current.getFieldValue('KEY');
        sendEnvelope('editorInput', {
          input: { kind: 'key', key }
        });
        break;
      }

      case 'bb_move_caret': {
        const by = toNumber(await evalValue(current.getInputTargetBlock('DELTA')));
        const unit = current.getFieldValue('UNIT');
        sendWs({ type: 'moveCaret', by, unit });
        break;
      }

      case 'bb_set_caret': {
        const textIndex = toNumber(await evalValue(current.getInputTargetBlock('INDEX')));
        sendWs({ type: 'setCaret', textIndex });
        break;
      }

      case 'bb_set_caret_from_cell': {
        const cellIndex = toNumber(await evalValue(current.getInputTargetBlock('INDEX')));
        sendWs({ type: 'setCaretFromCell', cellIndex });
        break;
      }

      case 'bb_cursor_routing': {
        const cellIndex = toNumber(await evalValue(current.getInputTargetBlock('INDEX')));
        sendWs({ type: 'cursorRouting', cellIndex });
        break;
      }

      case 'bb_set_editor_mode': {
        const enabled = current.getFieldValue('MODE') === 'on';
        runtime.editorMode = enabled ? 'on' : 'off';
        renderStatus();
        sendEnvelope('setEditorMode', { enabled });
        break;
      }

      case 'bb_set_insert_mode': {
        const enabled = current.getFieldValue('MODE') === 'on';
        runtime.insertMode = enabled ? 'on' : 'off';
        renderStatus();
        sendWs({ type: 'setEditorInsertMode', enabled });
        break;
      }

      case 'bb_set_caret_visibility': {
        const visible = current.getFieldValue('VISIBLE') === 'true';
        runtime.caretVisible = visible;
        renderStatus();
        sendWs({ type: 'setCaretVisibility', visible });
        break;
      }

      case 'bb_get_braille_line': {
        sendWs({ type: 'getBrailleLine' });
        break;
      }

      case 'bb_set_caret_to_begin': {
        sendWs({ type: 'setCaretToBegin' });
        break;
      }

      case 'bb_set_caret_to_end': {
        sendWs({ type: 'setCaretToEnd' });
        break;
      }

      case 'bb_wait': {
        const seconds = toNumber(await evalValue(current.getInputTargetBlock('SECONDS')));
        await new Promise(resolve => setTimeout(resolve, seconds * 1000));
        break;
      }

      case 'bb_wait_ms': {
        const ms = Math.max(0, Math.floor(toNumber(await evalValue(current.getInputTargetBlock('MILLISECONDS')))));
        await new Promise(resolve => setTimeout(resolve, ms));
        break;
      }

      case 'sound_play_folder_file': {
        const folder = current.getFieldValue('FOLDER');
        const file = await evalValue(current.getInputTargetBlock('FILE'));
        const url = resolveFolderSoundUrl(folder, file);
        log(`Sound folder file: folder=${String(folder || '')}, file=${String(file ?? '')}, url=${url || '(empty)'}`);
        await playSound(url);
        break;
      }

      case 'sound_play_speech_file':
      case 'sound_play_letters_file':
      case 'sound_play_instructions_file':
      case 'sound_play_feedback_file':
      case 'sound_play_story_file':
      case 'sound_play_general_file':
      case 'sound_play_ux_file': {
        const folder = getSoundFolderFromBlockType(current.type);
        const file = await evalValue(current.getInputTargetBlock('FILE'));
        const url = resolveFolderSoundUrl(folder, file);
        log(`Sound folder file: folder=${String(folder || '')}, file=${String(file ?? '')}, url=${url || '(empty)'}`);
        await playSound(url);
        break;
      }

      case 'sound_play_ux_success': {
        await playSound(resolveFolderSoundUrl('ux', 'success'));
        break;
      }

      case 'sound_play_ux_failure': {
        await playSound(resolveFolderSoundUrl('ux', 'failure'));
        break;
      }

      case 'sound_play_instruction_by_id': {
        const instructionId = current.getFieldValue('INSTRUCTION_ID');
        await playInstructionById(instructionId);
        break;
      }

      case 'sound_play_instruction_by_id_with_phoneme': {
        const instructionId = current.getFieldValue('INSTRUCTION_ID');
        const phoneme = await evalValue(current.getInputTargetBlock('PHONEME'));
        await playInstructionById(instructionId, { phoneme });
        break;
      }

      case 'sound_play_url': {
        const url = String(await evalValue(current.getInputTargetBlock('URL')) ?? '');
        await playSound(url);
        break;
      }

      case 'sound_play_sounds_relative': {
        const path = String(await evalValue(current.getInputTargetBlock('PATH')) ?? '');
        const url = resolveSoundsRelativeUrl(path);
        await playSound(url);
        break;
      }

      case 'log_value': {
        const value = await evalValue(current.getInputTargetBlock('VALUE'));
        log(formatLogValue(value));
        break;
      }

      case 'log_variable': {
        const varModel = workspace?.getVariableById(current.getFieldValue('VAR'));
        const varName = varModel ? varModel.name : 'unknown';
        const varValue = varModel ? variableValues[varModel.getId()] : undefined;
        log(`${varName} = ${formatLogValue(varValue)}`);
        break;
      }

      case 'log_clear': {
        clearLogBox();
        break;
      }

      case 'klanken_play_word_sounds': {
        try {
          const word = await evalValue(current.getInputTargetBlock('WORD'));
          const item = await findAanvankelijklijstItemByWord(word);
          const sounds = toList(item?.sounds);
          for (const sound of sounds) {
            if (getRuntime().stopped || generation !== runGeneration) break;
            await playSound(resolveFolderSoundUrl('letters', sound));
            await waitForAudioStopped();
          }
        } catch (err) {
          log(`Phonemes play failed: ${err}`);
        }
        break;
      }

      case 'klanken_play_word_sounds_with_pause': {
        try {
          const word = await evalValue(current.getInputTargetBlock('WORD'));
          const pauseSeconds = Math.max(0, toNumber(await evalValue(current.getInputTargetBlock('SECONDS'))));
          const pauseMs = Math.round(pauseSeconds * 1000);
          const item = await findAanvankelijklijstItemByWord(word);
          const sounds = toList(item?.sounds);
          for (let i = 0; i < sounds.length; i++) {
            if (getRuntime().stopped || generation !== runGeneration) break;
            await playSound(resolveFolderSoundUrl('letters', sounds[i]));
            await waitForAudioStopped();
            if (pauseMs > 0 && i < sounds.length - 1) {
              await new Promise(resolve => setTimeout(resolve, pauseMs));
            }
          }
        } catch (err) {
          log(`Phonemes play failed: ${err}`);
        }
        break;
      }

      case 'klanken_play_word_phonemes_nl': {
        try {
          const word = await evalValue(current.getInputTargetBlock('WORD'));
          const normalizedWord = String(word ?? '').trim();
          if (!normalizedWord) {
            log('Phonemes play skipped: empty word input');
            break;
          }
          const fonemenData = await getFonemenNlStandaard();
          const fonemen = splitWordToNlFonemen(normalizedWord, fonemenData);
          log(`Phonemes play word (nl): ${normalizedWord}`);
          log(`Phonemes play list: ${fonemen.join(', ') || '(none)'}`);
          for (const foneem of fonemen) {
            if (getRuntime().stopped || generation !== runGeneration) break;
            const audioUrl = resolveFolderSoundUrl('letters', foneem);
            log(`Phoneme audio URL: ${audioUrl}`);
            await playSound(audioUrl);
            await waitForAudioStopped();
          }
        } catch (err) {
          log(`Phonemes play failed: ${err}`);
        }
        break;
      }

      case 'klanken_play_word_phonemes_nl_with_pause': {
        try {
          const word = await evalValue(current.getInputTargetBlock('WORD'));
          const normalizedWord = String(word ?? '').trim();
          if (!normalizedWord) {
            log('Phonemes play with pause skipped: empty word input');
            break;
          }
          const pauseSeconds = Math.max(0, toNumber(await evalValue(current.getInputTargetBlock('SECONDS'))));
          const pauseMs = Math.round(pauseSeconds * 1000);
          const fonemenData = await getFonemenNlStandaard();
          const fonemen = splitWordToNlFonemen(normalizedWord, fonemenData);
          log(`Phonemes play word with pause (nl): ${normalizedWord}`);
          log(`Phonemes play with pause list: ${fonemen.join(', ') || '(none)'}`);
          for (let i = 0; i < fonemen.length; i++) {
            if (getRuntime().stopped || generation !== runGeneration) break;
            const audioUrl = resolveFolderSoundUrl('letters', fonemen[i]);
            log(`Phoneme audio URL with pause: ${audioUrl}`);
            await playSound(audioUrl);
            await waitForAudioStopped();
            if (pauseMs > 0 && i < fonemen.length - 1) {
              await new Promise(resolve => setTimeout(resolve, pauseMs));
            }
          }
        } catch (err) {
          log(`Phonemes play failed: ${err}`);
        }
        break;
      }

      case 'sound_pause': {
        pauseSound();
        break;
      }

      case 'sound_resume': {
        await resumeSound();
        break;
      }

      case 'sound_stop': {
        stopSound();
        break;
      }

      case 'sound_wait_stopped': {
        await waitForAudioStopped();
        break;
      }

      case 'sound_set_volume': {
        const percent = Math.max(0, Math.min(100, toNumber(await evalValue(current.getInputTargetBlock('VOLUME')))));
        soundVolume = percent / 100;
        if (activeAudio) activeAudio.volume = soundVolume;
        log('Sound volume: ' + percent + '%');
        break;
      }

      case 'timer_start': {
        const name = current.getFieldValue('NAME');
        const seconds = Math.max(0.05, toNumber(await evalValue(current.getInputTargetBlock('SECONDS'))));
        startTimer(name, seconds, generation);
        break;
      }

      case 'timer_stop': {
        const name = current.getFieldValue('NAME');
        stopTimerByName(name);
        break;
      }

      case 'timer_stop_all': {
        stopAllTimers();
        break;
      }

      case 'list_set_item': {
        const list = toList(await evalValue(current.getInputTargetBlock('LIST')));
        const idx = toListIndex(await evalValue(current.getInputTargetBlock('INDEX')), list.length);
        const value = await evalValue(current.getInputTargetBlock('VALUE'));
        if (idx >= 0) {
          list[idx] = value;
          resetListCursor(list);
          renderStatus();
        }
        break;
      }

      case 'list_add_item': {
        const list = toList(await evalValue(current.getInputTargetBlock('LIST')));
        const value = await evalValue(current.getInputTargetBlock('ITEM'));
        list.push(value);
        resetListCursor(list);
        renderStatus();
        break;
      }

      case 'list_remove_item': {
        const list = toList(await evalValue(current.getInputTargetBlock('LIST')));
        const idx = toListIndex(await evalValue(current.getInputTargetBlock('INDEX')), list.length);
        if (idx >= 0) {
          list.splice(idx, 1);
          resetListCursor(list);
          renderStatus();
        }
        break;
      }

      case 'controls_for_each_audio_item': {
        const list = toList(await evalValue(current.getInputTargetBlock('LIST')));
        const body = current.getInputTargetBlock('DO');
        const varModel = workspace.getVariableById(current.getFieldValue('VAR'));
        const varId = varModel ? varModel.getId() : null;
        for (const item of list) {
          if (getRuntime().stopped || generation !== runGeneration) break;
          if (varId) {
            variableValues[varId] = item;
            renderStatus();
          }
          if (body) await executeChain(body, generation, allowStopped);
        }
        break;
      }

      case 'list_for_each_item': {
        const list = toList(await evalValue(current.getInputTargetBlock('LIST')));
        const body = current.getInputTargetBlock('DO');
        const varModel = workspace.getVariableById(current.getFieldValue('VAR'));
        const varId = varModel ? varModel.getId() : null;
        for (const item of list) {
          if (getRuntime().stopped || generation !== runGeneration) break;
          if (varId) {
            variableValues[varId] = item;
            renderStatus();
          }
          if (body) await executeChain(body, generation, allowStopped);
        }
        break;
      }

      case 'variables_set': {
        const id = getVariableIdFromBlock(current);
        const value = await evalValue(current.getInputTargetBlock('VALUE'));
        if (id) {
          variableValues[id] = value;
          if (Array.isArray(value)) resetListCursor(value);
          renderStatus();
        }
        break;
      }

      case 'variables_change':
      case 'math_change': {
        const id = getVariableIdFromBlock(current);
        const inputName = current.type === 'math_change' ? 'DELTA' : 'VALUE';
        const delta = toNumber(await evalValue(current.getInputTargetBlock(inputName)));
        if (id) {
          variableValues[id] = toNumber(variableValues[id] ?? 0) + delta;
          renderStatus();
        }
        break;
      }

      case 'external_variable_set': {
        setExternalVariable(current.getFieldValue('VAR'), await evalValue(current.getInputTargetBlock('VALUE')));
        break;
      }

      case 'lesson_set_active_record_index': {
        if (getRuntime().lockInjectedLessonRecord) {
          log('Ignored lesson_set_active_record_index because injected record lock is active');
        } else {
          await setActiveLessonRecordByIndex(await evalValue(current.getInputTargetBlock('INDEX')));
        }
        break;
      }

      case 'lesson_complete_step': {
        await signalLessonStepCompletion({
          status: String(current.getFieldValue('STATUS') || 'completed'),
          output: await evalValue(current.getInputTargetBlock('OUTPUT')),
          score: await evalValue(current.getInputTargetBlock('SCORE')),
          maxScore: await evalValue(current.getInputTargetBlock('MAX_SCORE')),
          attempts: await evalValue(current.getInputTargetBlock('ATTEMPTS')),
          durationMs: await evalValue(current.getInputTargetBlock('DURATION_MS')),
          answer: await evalValue(current.getInputTargetBlock('ANSWER')),
          expectedAnswer: await evalValue(current.getInputTargetBlock('EXPECTED_ANSWER')),
          feedback: await evalValue(current.getInputTargetBlock('FEEDBACK')),
          metadata: await evalValue(current.getInputTargetBlock('METADATA'))
        });
        break;
      }

      case 'lesson_complete_lesson': {
        await signalLessonCompletion({
          status: 'completed'
        });
        break;
      }

      case 'math_inc_var':
      case 'math_dec_var': {
        const varModel = workspace?.getVariableById(current.getFieldValue('VAR'));
        const varId = varModel ? varModel.getId() : null;
        if (varId) {
          const delta = current.type === 'math_inc_var' ? 1 : -1;
          variableValues[varId] = toNumber(variableValues[varId] ?? 0) + delta;
          renderStatus();
        }
        break;
      }

      case 'math_inc_nrof': {
        const varId = ensureRuntimeVariableByName('count', 0);
        if (varId) {
          variableValues[varId] = toNumber(variableValues[varId] ?? 0) + 1;
          renderStatus();
        }
        break;
      }

      case 'control_wait_until_nrof': {
        const varId = ensureRuntimeVariableByName('count', 0);
        const target = Math.floor(toNumber(await evalValue(current.getInputTargetBlock('TARGET'))));
        while (!getRuntime().stopped && generation === runGeneration) {
          const currentValue = varId ? toNumber(variableValues[varId] ?? 0) : 0;
          if (currentValue >= target) break;
          await new Promise(resolve => setTimeout(resolve, 50));
        }
        break;
      }

      case 'controls_if': {
        let handled = false;
        for (let i = 0; current.getInput('IF' + i); i++) {
          const cond = Boolean(await evalValue(current.getInputTargetBlock('IF' + i)));
          if (cond) {
            const branch = current.getInputTargetBlock('DO' + i);
            if (branch) await executeChain(branch, generation, allowStopped);
            handled = true;
            break;
          }
        }
        if (!handled && current.getInput('ELSE')) {
          const elseBranch = current.getInputTargetBlock('ELSE');
          if (elseBranch) await executeChain(elseBranch, generation, allowStopped);
        }
        break;
      }

      case 'controls_repeat_ext': {
        const count = toNumber(await evalValue(current.getInputTargetBlock('TIMES')));
        const body = current.getInputTargetBlock('DO');
        for (let i = 0; i < count && !getRuntime().stopped && generation === runGeneration; i++) {
          if (body) await executeChain(body, generation, allowStopped);
        }
        break;
      }

      case 'controls_while_do': {
        const body = current.getInputTargetBlock('DO');
        let guard = 0;
        while (
          !getRuntime().stopped &&
          generation === runGeneration &&
          Boolean(await evalValue(current.getInputTargetBlock('COND')))
        ) {
          if (++guard > 10000) {
            log('Loop stopped: while do exceeded 10000 iterations');
            break;
          }
          if (body) await executeChain(body, generation, allowStopped);
        }
        break;
      }

      case 'controls_do_while': {
        const body = current.getInputTargetBlock('DO');
        let guard = 0;
        do {
          if (getRuntime().stopped || generation !== runGeneration) break;
          if (++guard > 10000) {
            log('Loop stopped: do while exceeded 10000 iterations');
            break;
          }
          if (body) await executeChain(body, generation, allowStopped);
        } while (Boolean(await evalValue(current.getInputTargetBlock('COND'))));
        break;
      }

      case 'procedures_callnoreturn': {
        await runProcedureCall(current, { runtime, workspace, generation });
        break;
      }

      case 'procedures_defnoreturn': {
        // Procedure definitions are registered separately and executed only via explicit calls.
        break;
      }

      case 'procedures_defreturn':
      case 'procedures_ifreturn': {
        // TODO: Support return-value procedures in the runtime executor.
        log('Skipped unsupported procedure block: ' + current.type);
        break;
      }

      default:
        log('Skipped unsupported block: ' + current.type);
        break;
      }
    } catch (err) {
      const message = err?.message || String(err);
      log(`Block failed (${current.type}): ${message}`);
      throw err;
    }

    current = current.getNextBlock();
  }
}

async function eventMatches(block, event) {
  switch (block.type) {
    case 'event_when_started':
      return event.type === 'started';

    case 'event_when_program_ended':
      return event.type === 'programEnded';

    case 'event_when_timer':
      return event.type === 'timer' &&
        String(block.getFieldValue('NAME')) === String(event.name);

    case 'event_when_thumb_key':
      return event.type === 'thumbKey' &&
        String(block.getFieldValue('KEY')).toLowerCase() === String(event.key).toLowerCase();

    case 'event_when_any_thumb_key':
      return event.type === 'thumbKey';

    case 'event_when_cursor_routing': {
      if (event.type !== 'cursorRouting') return false;
      const valueBlock = block.getInputTargetBlock('CELL');
      const expectedCell = valueBlock ? Number(await evalValue(valueBlock)) : 0;
      return expectedCell === Number(event.cell);
    }

    case 'event_when_cursor_position_changed':
      return event.type === 'cursorPositionChanged';

    case 'event_when_chord': {
      if (event.type !== 'chord') return false;
      const valueBlock = block.getInputTargetBlock('DOTS');
      const expectedDots = normalizeChordValue(valueBlock ? await evalValue(valueBlock) : '1');
      const actualDots = normalizeChordValue(event.dots);
      return expectedDots === actualDots;
    }

    case 'event_when_editor_key':
      return event.type === 'editorKey' &&
        String(block.getFieldValue('KEY')) === String(event.key);

    case 'event_when_key_name': {
      if (event.type !== 'virtualKeyCode') return false;
      const expectedKey = String(block.getFieldValue('KEY') || 'F1').trim().toLowerCase();
      const actualKey = String(event.key ?? '').trim().toLowerCase();
      return expectedKey !== '' && expectedKey === actualKey;
    }

    default:
      return false;
  }
}

async function dispatchProgramEnded(generation = runGeneration, reason = 'completed') {
  const rt = getRuntime();
  if (!workspace) return;
  if (rt.programEndedGeneration === generation) return;
  rt.programEndedGeneration = generation;
  rt.programEndedCompletedGeneration = -1;

  const event = { type: 'programEnded', reason: String(reason || 'completed') };
  log('Event: ' + JSON.stringify(event));

  const topBlocks = workspace.getTopBlocks(true);
  for (const block of topBlocks) {
    if (generation !== runGeneration && reason !== 'stopped') return;
    if (await eventMatches(block, event)) {
      const first = block.getInputTargetBlock('DO');
      if (first) await executeChain(first, generation, true);
    }
  }
  rt.programEndedCompletedGeneration = generation;
  rt.stopped = true;
  setExecutionUiState(
    reason === 'stopped' ? 'stopped' : 'completed',
    reason === 'stopped'
      ? 'Script stopped.'
      : 'Script finished. You can press Run Again to start it once more.'
  );
  renderStatus();
}

async function dispatchEvent(event, generation = runGeneration) {
  const rt = getRuntime();
  if (generation !== runGeneration || rt.stopped) return;
  if (!workspace) return;
  if (shouldBlockEventDuringAudio(event)) return;

  if (event.type === 'thumbKey') {
    rt.lastThumbKey = event.key ?? '';
  }
  if (event.type === 'cursorRouting') {
    rt.lastCursorCell = Number(event.cell ?? -1);
    if (typeof event.textIndex === 'number') rt.textCaret = event.textIndex;
  }
  if (event.type === 'cursorPositionChanged') {
    rt.textCaret = Number(event.position ?? rt.textCaret);
  }
  if (event.type === 'chord') {
    rt.lastChord = String(event.dots ?? '');
  }
  if (event.type === 'editorKey') {
    rt.lastEditorKey = String(event.key ?? '');
  }
  if (event.type === 'virtualKeyCode') {
    rt.lastVirtualKeyCode = Math.floor(Number(event.keyCode) || 0);
    if (typeof event.key === 'string') {
      rt.lastEditorKey = event.key;
    }
  }
  if (event.type === 'timer') {
    rt.lastTimerName = String(event.name ?? '');
    rt.lastTimerTick = Number(event.tick ?? rt.lastTimerTick);
  }

  renderStatus();
  log('Event: ' + JSON.stringify(event));

  const topBlocks = workspace.getTopBlocks(true);
  for (const block of topBlocks) {
    if (generation !== runGeneration || getRuntime().stopped) return;
    if (await eventMatches(block, event)) {
      const first = block.getInputTargetBlock('DO');
      if (first) await executeChain(first, generation);
    }
  }

  if (generation === runGeneration && getRuntime().stopped) {
    await dispatchProgramEnded(generation, 'completed');
  }
}

/* ---------------- File actions ---------------- */
async function saveWorkspace(options = {}) {
  const { skipNoChangesLog = false } = options;
  const xmlDom = applyMetadataToXmlDom(Blockly.Xml.workspaceToDom(workspace));
  const xmlText = Blockly.Xml.domToPrettyText(xmlDom);
  const fileName = normalizeXmlFileName(currentFileHandle?.name || getSelectedFileName());

  if (!workspaceDirty && currentFileHandle && typeof currentFileHandle.createWritable === 'function') {
    if (!skipNoChangesLog) log('Save skipped: no changes');
    return true;
  }

  if (currentFileHandle && typeof currentFileHandle.createWritable === 'function') {
    try {
      await writeXmlToHandle(currentFileHandle, xmlText, fileName);
      markWorkspaceSaved(xmlText);
      log('Workspace saved to ' + fileName);
      return true;
    } catch (err) {
      log('Direct save failed: ' + err.message);
      return await saveWorkspaceAs();
    }
  }

  if (typeof window.showSaveFilePicker === 'function') {
    return await saveWorkspaceAs();
  }

  downloadXml(xmlText, fileName);
  markWorkspaceSaved(xmlText);
  log('Workspace saved as download: ' + fileName);
  return true;
}

async function saveWorkspaceAs() {
  const xmlDom = applyMetadataToXmlDom(Blockly.Xml.workspaceToDom(workspace));
  const xmlText = Blockly.Xml.domToPrettyText(xmlDom);
  const fileName = getSelectedFileName();

  if (typeof window.showSaveFilePicker !== 'function') {
    downloadXml(xmlText, fileName);
    markWorkspaceSaved(xmlText);
    log('Save As fallback download: ' + fileName);
    return true;
  }

  try {
    const handle = await window.showSaveFilePicker({
      suggestedName: fileName,
      types: [
        {
          description: 'Blockly Files',
          accept: { 'application/octet-stream': ['.blockly'] }
        }
      ]
    });
    const chosenName = normalizeXmlFileName(handle?.name || fileName);
    await writeXmlToHandle(handle, xmlText, chosenName);
    currentFileHandle = handle;
    setSelectedFileName(chosenName);
    markWorkspaceSaved(xmlText);
    log('Workspace saved as ' + chosenName);
    return true;
  } catch (err) {
    if (err && err.name === 'AbortError') {
      log('Save As cancelled');
      return false;
    }
    log('Save As failed: ' + err.message);
    return false;
  }
}

function normalizeXmlFileName(name) {
  const raw = String(name || '').trim();
  if (!raw) return 'braille-activity.blockly';
  return /\.blockly$/i.test(raw) ? raw : (raw + '.blockly');
}

function getSelectedFileName() {
  const input = document.getElementById('fileNameInput');
  return normalizeXmlFileName(input ? input.value : 'braille-activity.blockly');
}

function setSelectedFileName(name) {
  const normalized = normalizeXmlFileName(name);
  const input = document.getElementById('fileNameInput');
  if (input) input.value = normalized;
  renderStatusScriptName();
}

function downloadXml(xmlText, fileName) {
  const blob = new Blob([xmlText], { type: 'application/octet-stream' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function writeXmlToHandle(handle, xmlText, fileName) {
  const writer = await handle.createWritable();
  await writer.write(xmlText);
  await writer.close();
  setSelectedFileName(handle?.name || fileName);
}

async function openWorkspaceFile() {
  if (!(await confirmActionWithUnsavedChanges('loading another file'))) return;
  if (typeof window.showOpenFilePicker === 'function') {
    try {
      const [handle] = await window.showOpenFilePicker({
        multiple: false,
        types: [
          {
            description: 'Blockly Files',
            accept: { 'application/octet-stream': ['.blockly'] }
          }
        ]
      });
      if (handle) {
        const file = await handle.getFile();
        const text = await file.text();
        currentFileHandle = handle;
        loadWorkspaceFromText(text, file.name);
        return;
      }
    } catch (err) {
      if (!(err && err.name === 'AbortError')) {
        log('Open failed: ' + err.message);
      }
      if (err && err.name === 'AbortError') return;
    }
  }
  document.getElementById('fileInput').click();
}

function loadWorkspaceFromText(text, sourceName = null) {
  try {
    const xmlDom = parseWorkspaceXml(text);
    suppressDirtyTracking++;
    workspace.clear();
    setScriptVariables(extractWorkspaceVariablesFromXml(xmlDom), { markDirty: false });
    Blockly.Xml.domToWorkspace(xmlDom, workspace);
    refreshProcedureRegistry(workspace);
    ensureDefaultVariable();
    initVariableValues();
    renderScriptMetadata(extractWorkspaceMetadata(xmlDom, sourceName));
    if (sourceName) setSelectedFileName(sourceName);
    suppressDirtyTracking--;
    markWorkspaceSaved(getWorkspaceSignature());
    log('Workspace loaded' + (sourceName ? ': ' + sourceName : ''));
  } catch (err) {
    suppressDirtyTracking = 0;
    log('Load failed: ' + err.message);
    alert('Could not load file: ' + err.message);
  }
}

function collectBlockTypesFromState(state) {
  const result = new Set();

  function visitBlock(block) {
    if (!block || typeof block !== 'object') return;
    const type = String(block.type || '').trim();
    if (type) {
      result.add(type);
    }
    const nextBlock = block.next?.block;
    if (nextBlock) {
      visitBlock(nextBlock);
    }
    const inputs = block.inputs;
    if (inputs && typeof inputs === 'object') {
      Object.values(inputs).forEach((input) => {
        if (!input || typeof input !== 'object') return;
        if (input.block) visitBlock(input.block);
        if (input.shadow) visitBlock(input.shadow);
      });
    }
  }

  const blocks = state?.blocks?.blocks;
  if (Array.isArray(blocks)) {
    blocks.forEach(visitBlock);
  }
  return Array.from(result);
}

function ensureCompatibilityBlockDefinitions(state = null) {
  if (!window.Blockly?.Blocks) return [];

  const registered = [];
  const requiredTypes = new Set(collectBlockTypesFromState(state));
  requiredTypes.add('sound_play_sounds_relative');

  const fallbackDefinitions = {
    sound_play_sounds_relative: {
      init() {
        this.appendValueInput('PATH').appendField('play sounds path');
        this.setPreviousStatement(true);
        this.setNextStatement(true);
        this.setColour('#10B981');
      }
    }
  };

  requiredTypes.forEach((type) => {
    const fallback = fallbackDefinitions[type];
    if (!fallback) return;
    const definition = Blockly.Blocks[type];
    if (definition && typeof definition.init === 'function') return;
    Blockly.Blocks[type] = fallback;
    registered.push(type);
  });

  if (registered.length > 0) {
    log(`Compatibility block definitions registered: ${registered.join(', ')}`);
  }
  return registered;
}

function getBlockDefinitionDiagnostics(type) {
  const definition = window.Blockly?.Blocks?.[type];
  return {
    type,
    exists: definition != null,
    valueType: typeof definition,
    hasInit: !!(definition && typeof definition.init === 'function'),
    keys: definition && typeof definition === 'object' ? Object.keys(definition).slice(0, 12) : []
  };
}

function loadWorkspaceFromState(state, sourceName = null, metadata = null) {
  try {
    if (!state || typeof state !== 'object') {
      throw new Error('Invalid Blockly JSON structure');
    }
    const requiredTypes = collectBlockTypesFromState(state);
    log('Loading workspace block types: ' + requiredTypes.join(', '));
    ensureCompatibilityBlockDefinitions(state);
    log('Block definition diagnostics: ' + JSON.stringify({
      sound_play_sounds_relative: getBlockDefinitionDiagnostics('sound_play_sounds_relative'),
      list_for_each_item: getBlockDefinitionDiagnostics('list_for_each_item'),
      event_when_started: getBlockDefinitionDiagnostics('event_when_started')
    }));
    registerProcedures(state, runtime);
    suppressDirtyTracking++;
    workspace.clear();
    setScriptVariables(extractWorkspaceVariablesFromState(state, metadata), { markDirty: false });
    Blockly.serialization.workspaces.load(getBlocklySerializableState(state), workspace);
    refreshProcedureRegistry(workspace);
    ensureDefaultVariable();
    initVariableValues();
    if (metadata && typeof metadata === 'object') {
      renderScriptMetadata({
        title: String(metadata.title || sourceName || ''),
        description: String(metadata.description || ''),
        instruction: String(metadata.instruction || ''),
        prompt: String(metadata.prompt || '')
      });
    } else {
      renderScriptMetadata(sourceName ? { title: String(sourceName), description: '', instruction: '', prompt: '' } : null);
    }
    if (sourceName) setSelectedFileName(sourceName);
    suppressDirtyTracking--;
    markWorkspaceSaved(getWorkspaceSignature());
    log('Workspace loaded' + (sourceName ? ': ' + sourceName : ''));
  } catch (err) {
    suppressDirtyTracking = 0;
    try {
      log('Workspace load diagnostics failed: ' + JSON.stringify({
        error: err?.message || String(err),
        sound_play_sounds_relative: getBlockDefinitionDiagnostics('sound_play_sounds_relative'),
        list_for_each_item: getBlockDefinitionDiagnostics('list_for_each_item'),
        event_when_started: getBlockDefinitionDiagnostics('event_when_started')
      }));
    } catch {}
    log('Load failed: ' + err.message);
    alert('Could not load file: ' + err.message);
  }
}

async function newWorkspace() {
  if (!(await confirmActionWithUnsavedChanges('creating a new file'))) return;
  getRuntime().stopped = true;
  runGeneration++;
  stopAllTimers();
  stopSound();
  currentFileHandle = null;
  setOnlineCurrentScript({ id: '', title: '', status: 'draft' });
  const onlineSelect = document.getElementById('onlineScriptsSelect');
  if (onlineSelect) onlineSelect.value = '';
  setSelectedFileName('braille-activity.blockly');
  suppressDirtyTracking++;
  workspace.clear();
  refreshProcedureRegistry(workspace);
  setScriptVariables([], { markDirty: false });
  ensureDefaultVariable();
  initVariableValues();
  renderScriptMetadata(null);
  renderBrailleLine(null);
  suppressDirtyTracking--;
  markWorkspaceSaved(getWorkspaceSignature());
  log('Workspace cleared');
}

renderStatus();
renderScriptMetadata(null);
renderVariableList();
renderStatusScriptName();
renderInstructionTtsControl();
renderFileState();
setWsBadge(false);
applyEmbeddedSessionPlayerControls();

window.BrailleBlocklyApp = {
  loadWorkspaceFromText,
  loadWorkspaceFromState,
  loadWorkspaceOnline,
  async runWorkspaceTextHeadless({ text, sourceName = null, lessonData = null, lessonMethod = null, index = 0, stepInputs = null, stepMeta = null, onLog = null, lockInjectedRecord = false } = {}) {
    if (typeof onLog === 'function') {
      externalLogHandler = onLog;
    }
    if (typeof text === 'string' && text.trim()) {
      loadWorkspaceFromText(text, sourceName);
    }
    const startedBlocks = workspace ? workspace.getTopBlocks(true).filter(b => b.type === 'event_when_started') : [];
    const overridingIndexBlocks = workspace ? workspace.getAllBlocks(false).filter(b => b.type === 'lesson_set_active_record_index') : [];
    setLessonMethod(lessonMethod);
    if (lessonData != null) {
      window.aanvankelijkData = Array.isArray(lessonData) ? structuredClone(lessonData) : [];
      await setActiveLessonRecordByIndex(index);
    }
    window.currentLessonStep = stepMeta && typeof stepMeta === 'object' ? structuredClone(stepMeta) : null;
    resetLessonStepRuntimeState(stepInputs);
    clearLogBox();
    stopAllTimers();
    stopSound();
    getRuntime().stopped = false;
    getRuntime().programEndedGeneration = -1;
    getRuntime().programEndedCompletedGeneration = -1;
    getRuntime().lockInjectedLessonRecord = !!lockInjectedRecord;
    runGeneration++;
    const generation = runGeneration;
    pendingStart = false;
    resetRunScopedVariables();
    renderStatus();
    if (!workspace) {
      throw new Error('Blockly workspace is not ready');
    }
    refreshProcedureRegistry(workspace);
    log('Headless run started' + (sourceName ? ': ' + sourceName : ''));
    log('Headless when started blocks: ' + startedBlocks.length);
    log('Headless requested record index: ' + Math.max(0, Math.floor(toNumber(index))));
    log('Headless injected record index: ' + window.currentRecordIndex);
    if (overridingIndexBlocks.length > 0) {
      log(
        getRuntime().lockInjectedLessonRecord
          ? 'Workspace contains lesson_set_active_record_index blocks, but injected record lock is active'
          : 'Warning: workspace contains lesson_set_active_record_index blocks that can override the injected record'
      );
    }
    if (startedBlocks.length === 0) {
      getRuntime().lockInjectedLessonRecord = false;
      throw new Error('No "when started" block found in workspace');
    }
    try {
      await dispatchEvent({ type: 'started' }, generation);
      if (generation === runGeneration) {
        if (getRuntime().stopped) {
          await dispatchProgramEnded(generation, 'stopped');
        } else if (!hasPersistentEventBlocks()) {
          await dispatchProgramEnded(generation, 'completed');
        }
      }
      return {
        generation,
        startedBlockCount: startedBlocks.length,
        currentRecordIndex: Number.isFinite(window.currentRecordIndex) ? window.currentRecordIndex : -1,
        currentRecord: window.currentRecord && typeof window.currentRecord === 'object' ? window.currentRecord : null,
        lessonMethod: structuredClone(getLessonMethod()),
        stepCompletion: getRuntime().stepCompletion ? structuredClone(getRuntime().stepCompletion) : null,
        lessonCompletion: getRuntime().lessonCompletion ? structuredClone(getRuntime().lessonCompletion) : null,
        runtime: { ...getRuntime() }
      };
    } finally {
      getRuntime().lockInjectedLessonRecord = false;
    }
  },
  async runWorkspaceStateHeadless({ state, sourceName = null, metadata = null, lessonData = null, lessonMethod = null, index = 0, stepInputs = null, stepMeta = null, onLog = null, lockInjectedRecord = false } = {}) {
    if (typeof onLog === 'function') {
      externalLogHandler = onLog;
    }
    if (state && typeof state === 'object') {
      loadWorkspaceFromState(state, sourceName, metadata);
    }
    const startedBlocks = workspace ? workspace.getTopBlocks(true).filter(b => b.type === 'event_when_started') : [];
    const overridingIndexBlocks = workspace ? workspace.getAllBlocks(false).filter(b => b.type === 'lesson_set_active_record_index') : [];
    setLessonMethod(lessonMethod);
    if (lessonData != null) {
      window.aanvankelijkData = Array.isArray(lessonData) ? structuredClone(lessonData) : [];
      await setActiveLessonRecordByIndex(index);
    }
    window.currentLessonStep = stepMeta && typeof stepMeta === 'object' ? structuredClone(stepMeta) : null;
    resetLessonStepRuntimeState(stepInputs);
    clearLogBox();
    stopAllTimers();
    stopSound();
    getRuntime().stopped = false;
    getRuntime().programEndedGeneration = -1;
    getRuntime().programEndedCompletedGeneration = -1;
    getRuntime().lockInjectedLessonRecord = !!lockInjectedRecord;
    runGeneration++;
    const generation = runGeneration;
    pendingStart = false;
    resetRunScopedVariables();
    renderStatus();
    if (!workspace) {
      throw new Error('Blockly workspace is not ready');
    }
    refreshProcedureRegistry(workspace);
    log('Headless run started' + (sourceName ? ': ' + sourceName : ''));
    log('Headless when started blocks: ' + startedBlocks.length);
    log('Headless requested record index: ' + Math.max(0, Math.floor(toNumber(index))));
    log('Headless injected record index: ' + window.currentRecordIndex);
    if (overridingIndexBlocks.length > 0) {
      log(
        getRuntime().lockInjectedLessonRecord
          ? 'Workspace contains lesson_set_active_record_index blocks, but injected record lock is active'
          : 'Warning: workspace contains lesson_set_active_record_index blocks that can override the injected record'
      );
    }
    if (startedBlocks.length === 0) {
      getRuntime().lockInjectedLessonRecord = false;
      throw new Error('No "when started" block found in workspace');
    }
    try {
      await dispatchEvent({ type: 'started' }, generation);
      if (generation === runGeneration) {
        if (getRuntime().stopped) {
          await dispatchProgramEnded(generation, 'stopped');
        } else if (!hasPersistentEventBlocks()) {
          await dispatchProgramEnded(generation, 'completed');
        }
      }
      return {
        generation,
        startedBlockCount: startedBlocks.length,
        currentRecordIndex: Number.isFinite(window.currentRecordIndex) ? window.currentRecordIndex : -1,
        currentRecord: window.currentRecord && typeof window.currentRecord === 'object' ? window.currentRecord : null,
        lessonMethod: structuredClone(getLessonMethod()),
        stepCompletion: getRuntime().stepCompletion ? structuredClone(getRuntime().stepCompletion) : null,
        lessonCompletion: getRuntime().lessonCompletion ? structuredClone(getRuntime().lessonCompletion) : null,
        runtime: { ...getRuntime() }
      };
    } finally {
      getRuntime().lockInjectedLessonRecord = false;
    }
  },
  async runHeadlessProgram() {
    stopAllTimers();
    getRuntime().stopped = false;
    getRuntime().programEndedGeneration = -1;
    getRuntime().programEndedCompletedGeneration = -1;
    runGeneration++;
    const generation = runGeneration;
    pendingStart = false;
    resetRunScopedVariables();
    renderStatus();
    if (!workspace) {
      throw new Error('Blockly workspace is not ready');
    }
    refreshProcedureRegistry(workspace);
    await dispatchEvent({ type: 'started' }, generation);
    if (generation === runGeneration) {
      if (getRuntime().stopped) {
        await dispatchProgramEnded(generation, 'stopped');
      } else if (!hasPersistentEventBlocks()) {
        await dispatchProgramEnded(generation, 'completed');
      }
    }
    return generation;
  },
  async stopProgram() {
    await onStopClicked();
  },
  async stopAudio() {
    const hadActiveAudio = Boolean(activeAudio) || audioQueuePending > 0;
    stopSound('external-stop-audio');
    renderStatus();
    if (hadActiveAudio) {
      log('Audio stopped');
    }
    return hadActiveAudio;
  },
  async ensureBrailleBridgeConnection(timeoutMs = 5000) {
    return await ensureBrailleBridgeConnection(timeoutMs);
  },
  async warmRuntimeAssets({ preloadPhonemes = true } = {}) {
    const summary = {
      phonemesLoaded: false
    };
    if (preloadPhonemes) {
      await getFonemenNlStandaard();
      summary.phonemesLoaded = true;
    }
    return summary;
  },
  async injectLessonData(data, index = 0) {
    window.aanvankelijkData = Array.isArray(data) ? structuredClone(data) : [];
    return await setActiveLessonRecordByIndex(index);
  },
  setLessonMethod(method) {
    return structuredClone(setLessonMethod(method));
  },
  getLessonMethod() {
    return structuredClone(getLessonMethod());
  },
  async getLessonData() {
    return structuredClone(await getAanvankelijklijst());
  },
  async findLessonItemByWord(word) {
    const item = await findAanvankelijklijstItemByWord(word);
    return item && typeof item === 'object' ? structuredClone(item) : null;
  },
  setLessonStepInputs(inputs) {
    resetLessonStepRuntimeState(inputs);
    return getLessonStepInputs();
  },
  getLessonStepInputs() {
    return structuredClone(getLessonStepInputs());
  },
  async signalLessonStepCompletion(payload = {}) {
    return await signalLessonStepCompletion(payload);
  },
  async signalLessonCompletion(payload = {}) {
    return await signalLessonCompletion(payload);
  },
  getStepCompletion() {
    return getRuntime().stepCompletion ? structuredClone(getRuntime().stepCompletion) : null;
  },
  getLessonCompletion() {
    return getRuntime().lessonCompletion ? structuredClone(getRuntime().lessonCompletion) : null;
  },
  async setActiveLessonRecordIndex(index) {
    return await setActiveLessonRecordByIndex(index);
  },
  getCurrentRecord() {
    return window.currentRecord && typeof window.currentRecord === 'object' ? window.currentRecord : null;
  },
  getCurrentRecordIndex() {
    return Number.isFinite(window.currentRecordIndex) ? window.currentRecordIndex : -1;
  },
  getRuntimeSnapshot() {
    const snapshot = { ...getRuntime() };
    return {
      ...snapshot,
      isActive: isRuntimeActive(snapshot),
      activeTimers: timerHandles.size,
      hasActiveAudio: Boolean(activeAudio) || audioQueuePending > 0,
      hasPendingStart: Boolean(pendingStart),
      wsConnected
    };
  },
  async dispatchRuntimeEvent(event) {
    if (shouldBlockEventDuringAudio(event)) {
      log(`Queued runtime event until audio stopped: ${JSON.stringify(event)}`);
      await waitForAudioStopped();
      log(`Dispatching queued runtime event after audio: ${JSON.stringify(event)}`);
    }
    return await dispatchEvent(event, runGeneration);
  },
  clearLog() {
    clearLogBox();
  },
  setLogHandler(fn) {
    externalLogHandler = typeof fn === 'function' ? fn : null;
  },
  setPlayHandler(fn) {
    externalAudioPlayHandler = typeof fn === 'function' ? fn : null;
    if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.setPlayHandler === 'function') {
      window.BrailleStudioAPI.setPlayHandler(fn);
    }
  },
  async applyResolvedSessionPayload(payload = {}, options = {}) {
    return await applyResolvedSessionPayload(payload, options);
  },
  async runCurrentWorkspace() {
    return await onRunClicked();
  }
};

if (!IS_EMBEDDED_SESSION_PLAYER) {
  window.addEventListener('storage', (event) => {
    if (event.key !== SESSION_RESOLVED_PAYLOAD_STORAGE_KEY || !event.newValue) {
      return;
    }
    const payload = readStoredResolvedSessionPayload();
    if (!payload) return;
    applyResolvedSessionPayload(payload, { autoRun: false, force: false })
      .catch((err) => {
        log('Resolved session storage apply failed: ' + (err?.message || err));
      });
  });

  const pendingResolvedSessionPayload = readStoredResolvedSessionPayload();
  if (pendingResolvedSessionPayload) {
    applyResolvedSessionPayload(pendingResolvedSessionPayload, { autoRun: false, force: false })
      .catch((err) => {
        log('Resolved session boot apply failed: ' + (err?.message || err));
      });
  }
}
setBootStage('api-ready');
