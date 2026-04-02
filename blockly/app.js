/* ---------------- UI helpers ---------------- */
function setBootStage(stage, extra = {}) {
  if (typeof window.__setBrailleBlocklyBootStage === 'function') {
    window.__setBrailleBlocklyBootStage(stage, extra);
  }
}

setBootStage('app-script-start');

let externalLogHandler = null;
let brailleMonitorUi = null;
const BLOCKLY_GRID_SNAP_KEY = 'blockly_grid_snap';
const BLOCKLY_MONITOR_VISIBLE_KEY = 'blockly_monitor_visible';
const DEFAULT_LESSON_DATA_URL = 'https://www.tastenbraille.com/braillestudio/klanken/aanvankelijklijst.json';
const FONEMEN_NL_JSON_URL = '../klanken/fonemen_nl_standaard.json';
const ONLINE_SCRIPT_API_BASE = 'https://www.tastenbraille.com/braillestudio/blockly-api';
const WS_URL = 'ws://localhost:5000/ws';
const AUTO_RECONNECT_MS = 2000;
let currentFileHandle = null;
let ws = null;
let wsConnected = false;
let reconnectTimer = null;
let autoConnectEnabled = true;
let gridSnapEnabled = true;
let brailleMonitorVisible = true;
var pendingStart = false;
var pendingStartGeneration = 0;
var runGeneration = 0;
var workspace = null;
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
    lastSound: '',
    lastTimerName: '',
    lastTimerTick: 0,
    lastWsNotice: '',
    activeTable: '',
    lineId: 0,
    lockInjectedLessonRecord: false,
    stepCompletion: null,
    programEndedGeneration: -1,
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

function formatLogValue(value) {
  if (typeof value === 'string') return value === '' ? '""' : value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  if (value == null) return '';
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
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
  const btn = document.getElementById('wsToggleBtn');
  if (!btn) return;

  const isConnected = !!ws && ws.readyState === WebSocket.OPEN;

  btn.classList.remove('is-connected', 'is-disconnected');

  if (isConnected) {
    btn.classList.add('is-connected');
    btn.setAttribute('aria-label', 'Disconnect WebSocket');
    return;
  }

  btn.classList.add('is-disconnected');
  btn.setAttribute('aria-label', 'Connect WebSocket');
}

function setWsBadge(isConnected) {
  wsConnected = !!isConnected;
  renderWsControl();
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

function renderScriptMetadata(meta = null) {
  const titleInput = document.getElementById('scriptMetaTitle');
  const descriptionInput = document.getElementById('scriptMetaDescription');
  if (titleInput) titleInput.value = String(meta?.title || '');
  if (descriptionInput) descriptionInput.value = String(meta?.description || '');
}

function readScriptMetadataFromInputs() {
  const titleInput = document.getElementById('scriptMetaTitle');
  const descriptionInput = document.getElementById('scriptMetaDescription');
  return {
    title: String(titleInput?.value || '').trim(),
    description: String(descriptionInput?.value || '').trim()
  };
}

function applyMetadataToXmlDom(xmlDom) {
  if (!xmlDom) return xmlDom;
  const meta = readScriptMetadataFromInputs();
  if (meta.title) xmlDom.setAttribute('data-title', meta.title);
  else xmlDom.removeAttribute('data-title');
  if (meta.description) xmlDom.setAttribute('data-description', meta.description);
  else xmlDom.removeAttribute('data-description');
  return xmlDom;
}

function renderGridSnapControl() {
  const btn = document.getElementById('gridSnapBtn');
  if (!btn) return;
  btn.classList.toggle('is-active', !!gridSnapEnabled);
  btn.setAttribute('aria-pressed', gridSnapEnabled ? 'true' : 'false');
  btn.textContent = gridSnapEnabled ? 'Snap On' : 'Snap Off';
}

function renderSidebarToggleControl() {
  const btn = document.getElementById('sidebarToggleBtn');
  const main = document.getElementById('main');
  if (!btn || !main) return;
  const isHidden = main.classList.contains('is-sidebar-hidden');
  const isVisible = !isHidden;
  btn.classList.toggle('is-active', isVisible);
  btn.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  btn.setAttribute('aria-label', isVisible ? 'Hide status panel' : 'Show status panel');
  btn.title = isVisible ? 'Hide status panel' : 'Show status panel';
  btn.textContent = 'Status';
}

function renderBrailleMonitorToggleControl() {
  const btn = document.getElementById('monitorToggleBtn');
  const row = document.getElementById('brailleMonitorRow');
  if (!btn || !row) return;
  const isVisible = !!brailleMonitorVisible;
  row.classList.toggle('is-hidden', !isVisible);
  btn.classList.toggle('is-active', isVisible);
  btn.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  btn.setAttribute('aria-label', isVisible ? 'Hide monitor' : 'Show monitor');
  btn.title = isVisible ? 'Hide monitor' : 'Show monitor';
  btn.textContent = 'Monitor';
}

function applyBrailleMonitorVisibility(visible) {
  brailleMonitorVisible = !!visible;
  localStorage.setItem(BLOCKLY_MONITOR_VISIBLE_KEY, brailleMonitorVisible ? 'true' : 'false');
  renderBrailleMonitorToggleControl();
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

function renderFileState() {
  const badge = document.getElementById('fileStateBadge');
  const text = document.getElementById('fileStateText');
  const saveBtn = document.getElementById('onlineSaveBtn');
  if (!badge) return;
  const setText = (value) => {
    if (text) text.textContent = value;
    else badge.textContent = value;
  };
  setText('');
  badge.classList.toggle('is-dirty', workspaceDirty);
  if (saveBtn) {
    saveBtn.classList.toggle('is-dirty', workspaceDirty);
  }
}

function getWorkspaceSignature() {
  if (!workspace) return '';
  const xmlDom = applyMetadataToXmlDom(Blockly.Xml.workspaceToDom(workspace));
  return Blockly.Xml.domToPrettyText(xmlDom);
}

function setWorkspaceDirty(isDirty) {
  workspaceDirty = !!isDirty;
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
const timerHandles = new Map();
const listNextIndex = new WeakMap();
let soundVolume = 1;
let activeAudio = null;
let activeAudioCleanup = null;
let audioStoppedWaiters = [];
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
window.BrailleBlocklyDefaultLessonPlaceholders = window.BrailleBlocklyDefaultLessonPlaceholders || {
  word: 'bal',
  soundsCsv: 'b,a,l'
};
let fonemenNlJsonCache = null;
let workspaceDirty = false;
let lastSavedWorkspaceSignature = '';
let lastSavedOnlineScriptTitle = '';
let lastSavedOnlineScriptStatus = 'draft';
let suppressDirtyTracking = 0;
let currentOnlineScriptId = '';

function renderStatus() {
  const rt = getRuntime();
  const varLines = workspace
    ? workspace.getAllVariables().map(v => `${v.name} = ${variableValues[v.getId()] ?? 0}`)
    : [];
  const isRunning = !rt.stopped;
  const runBtn = document.getElementById('runBtn');
  const stopBtn = document.getElementById('stopBtn');

  if (runBtn) {
    runBtn.classList.toggle('is-active', isRunning || pendingStart);
    runBtn.setAttribute('aria-pressed', isRunning || pendingStart ? 'true' : 'false');
  }

  if (stopBtn) {
    stopBtn.classList.toggle('is-active', !isRunning && !pendingStart);
    stopBtn.setAttribute('aria-pressed', !isRunning && !pendingStart ? 'true' : 'false');
  }

  document.getElementById('statusBox').textContent =
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
last sound       : ${rt.lastSound}
last timer name  : ${rt.lastTimerName}
last timer tick  : ${rt.lastTimerTick}
last ws notice   : ${rt.lastWsNotice}
active table     : ${rt.activeTable}
line id          : ${rt.lineId}
stopped          : ${rt.stopped}

variables:
${varLines.length ? varLines.join('\n') : '(none)'}`;
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
  return [...new Set([ONLINE_SCRIPT_API_BASE])];
}

async function onlineApiFetchJson(path, options = {}) {
  const method = String(options.method || 'GET').toUpperCase();
  let lastError = null;

  for (const base of getOnlineApiBases()) {
    const url = `${base}${path}`;
    try {
      log(`Online API -> ${method} ${url}`);
      const res = await fetch(url, options);
      const raw = await res.text();
      let data = null;
      try {
        data = JSON.parse(raw);
      } catch {
        throw new Error(`Non-JSON response for ${method} ${url} (HTTP ${res.status})`);
      }
      if (!res.ok || (data && data.ok === false)) {
        const msg = data && data.error ? data.error : `HTTP ${res.status}`;
        throw new Error(`${msg} (${method} ${url})`);
      }
      log(`Online API <- ${res.status} ${method} ${url}`);
      return data;
    } catch (err) {
      log(`Online API failed: ${method} ${url} :: ${err.message}`);
      lastError = err;
    }
  }

  throw lastError || new Error(`Online API failed for ${method} ${path}`);
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
}

function setOnlineCurrentScript({ id = '', title = '', status = 'draft' } = {}) {
  currentOnlineScriptId = String(id || '').trim();
  setOnlineScriptInputs({
    id: currentOnlineScriptId,
    title: String(title || '').trim(),
    status: String(status || 'draft')
  });
  renderFileState();
}

function renderOnlineScriptsSelect(items) {
  const select = document.getElementById('onlineScriptsSelect');
  if (!select) return;

  const normalized = Array.isArray(items) ? items : [];
  select.innerHTML = '';

  const placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = '-- Select online script --';
  select.appendChild(placeholder);

  const normalizeStatus = (value) => {
    const raw = String(value || '').trim().toLowerCase();
    if (raw === 'started') return 'started';
    if (raw === 'in review') return 'in review';
    if (raw === 'approved') return 'approved';
    return 'draft';
  };
  const statusDot = (status) => {
    if (status === 'started') return '🟡';
    if (status === 'in review') return '🔵';
    if (status === 'approved') return '🟢';
    return '⚪';
  };

  const applySelectedStatusColor = () => {
    const selected = select.selectedOptions && select.selectedOptions[0]
      ? select.selectedOptions[0]
      : null;
    select.style.removeProperty('color');
  };

  for (const item of normalized) {
    const id = String(item?.id || '').trim();
    if (!id) continue;
    const title = String(item?.title || '').trim();
    const status = normalizeStatus(item?.meta?.status);
    const option = document.createElement('option');
    option.value = id;
    option.dataset.title = title;
    option.dataset.status = status;
    option.textContent = title
      ? `${statusDot(status)}\u00A0\u00A0${id} - ${title}`
      : `${statusDot(status)}\u00A0\u00A0${id}`;
    select.appendChild(option);
  }

  if (currentOnlineScriptId) {
    select.value = currentOnlineScriptId;
  }
  applySelectedStatusColor();
}

async function listOnlineScripts() {
  log('Online scripts: listing');
  const data = await onlineApiFetchJson(`/list.php?_=${Date.now()}`);
  const count = Array.isArray(data?.items) ? data.items.length : 0;
  log(`Online scripts: list received (${count})`);
  return Array.isArray(data?.items) ? data.items : [];
}

async function refreshOnlineScripts() {
  const items = await listOnlineScripts();
  renderOnlineScriptsSelect(items);
  log(`Online scripts refreshed (${items.length})`);
  return items;
}

async function saveWorkspaceOnline({ overwrite = true } = {}) {
  if (!workspace) throw new Error('Blockly workspace is not ready');

  const inputs = readOnlineScriptInputs();
  let id = String(inputs.id || '').trim();
  const title = String(inputs.title || '').trim();
  const status = String(inputs.status || 'draft').trim() || 'draft';

  if (!id) {
    id = resolveOnlineScriptId();
  }
  if (!id) {
    const entered = prompt('Script id is required. Enter script id:', '');
    if (!entered) {
      throw new Error('Online script id is required');
    }
    id = String(entered).trim();
  }
  if (!id) {
    throw new Error('Online script id is required');
  }

  const metadata = readScriptMetadataFromInputs();
  const payload = {
    id,
    title: title || metadata.title || id,
    overwrite: !!overwrite,
    blockly: Blockly.serialization.workspaces.save(workspace),
    meta: {
      title: metadata.title || '',
      description: metadata.description || '',
      status
    }
  };
  const blockCount = Array.isArray(payload?.blockly?.blocks?.blocks)
    ? payload.blockly.blocks.blocks.length
    : 0;
  log(`Online save requested: id=${payload.id}, overwrite=${payload.overwrite}, blocks=${blockCount}`);

  const data = await onlineApiFetchJson('/save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  setOnlineCurrentScript({ id: data?.id || id, title: payload.title, status });
  markWorkspaceSaved(getWorkspaceSignature());
  await refreshOnlineScripts();
  log(`Online script saved: ${data?.id || id}`);
  return data;
}

async function saveWorkspaceOnlineAs() {
  const current = readOnlineScriptInputs();
  const suggestedId = current.id || currentOnlineScriptId || '';
  const suggestedTitle = current.title || readScriptMetadataFromInputs().title || suggestedId;

  const newId = prompt('Online script id:', suggestedId);
  if (!newId) return false;
  const normalizedId = String(newId).trim();
  if (!normalizedId) return false;

  const newTitle = prompt('Online script title:', suggestedTitle || normalizedId) || normalizedId;
  log(`Online Save As requested: id=${normalizedId}`);
  setOnlineScriptInputs({ id: normalizedId, title: String(newTitle).trim() || normalizedId });
  await saveWorkspaceOnline({ overwrite: false });
  return true;
}

async function copyWorkspaceJsonToClipboard() {
  if (!workspace) {
    throw new Error('Blockly workspace is not ready');
  }

  const data = Blockly.serialization.workspaces.save(workspace);
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
  Blockly.serialization.workspaces.load(state, workspace);
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
  const targetId = String(id || '').trim();
  if (!targetId) throw new Error('Online script id is required');
  log(`Online load requested: id=${targetId}`);

  const data = await onlineApiFetchJson(`/load.php?id=${encodeURIComponent(targetId)}&_=${Date.now()}`);
  const state = data?.blockly;
  if (!state || typeof state !== 'object') {
    throw new Error('Loaded script does not contain valid Blockly JSON');
  }

  registerProcedures(state, runtime);
  suppressDirtyTracking++;
  workspace.clear();
  Blockly.serialization.workspaces.load(state, workspace);
  refreshProcedureRegistry(workspace);
  ensureDefaultVariable();
  initVariableValues();
  suppressDirtyTracking--;

  const meta = data?.meta && typeof data.meta === 'object' ? data.meta : {};
  renderScriptMetadata({
    title: String(meta.title || data.title || targetId),
    description: String(meta.description || '')
  });
  setOnlineCurrentScript({
    id: String(data?.id || targetId),
    title: String(data?.title || ''),
    status: String(meta.status || 'draft')
  });
  markWorkspaceSaved(getWorkspaceSignature());
  log(`Online script loaded: ${targetId}`);
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
      ensureDefaultVariable();
      initVariableValues();
      renderScriptMetadata(null);
      renderBrailleLine(null);
      suppressDirtyTracking--;
      markWorkspaceSaved(getWorkspaceSignature());
      setSelectedFileName('braille-activity.blockly');
      log('Workspace cleared after online delete');
    }
    setOnlineCurrentScript({ id: '', title: '', status: 'draft' });
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
    throw err;
  }
  if (!parsed?.item || typeof parsed.item !== 'object') {
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
  activeAudio = null;
}

function waitForAudioStopped() {
  if (!activeAudio) return Promise.resolve();
  return new Promise(resolve => {
    audioStoppedWaiters.push(resolve);
  });
}

function stopSound(reason = 'stopped') {
  if (!activeAudio) {
    if (reason === 'stopped') resolveAudioStoppedWaiters();
    return;
  }
  try {
    activeAudio.pause();
    activeAudio.currentTime = 0;
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

async function playSound(input) {
  const url = resolveSoundUrl(input);
  if (!url) {
    log('Sound skipped: empty filename/url');
    return;
  }
  stopSound('replaced');
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
    log('Sound failed: playback error');
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
  wsConnected = false;
  runtime.lastWsNotice = 'Disconnected';
  renderWsControl();
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
    wsConnected = true;
    getRuntime().lastWsNotice = '';
    clearReconnectTimer();
    renderWsControl();
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
    wsConnected = false;
    runtime.lastWsNotice = 'Disconnected';
    renderWsControl();
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
    const payload = wsPayload(msg);
    const name = payload.Name ?? payload.name ?? '';
    const press = !!(payload.Press ?? payload.press);
    runtime.lastThumbKey = normalizeThumb(name);
    renderStatus();
    if (press) {
      await dispatchEvent({ type: 'thumbKey', key: runtime.lastThumbKey }, runGeneration);
    }
    return;
  }

  if (type === 'editorKey') {
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
    runtime.lastChord = extractChord(msg);
    renderStatus();
    await dispatchEvent({ type: 'chord', dots: runtime.lastChord }, runGeneration);
    return;
  }
}

function normalizeThumb(name) {
  const s = String(name || '').toLowerCase();
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
  await dispatchEvent({ type: 'started' }, generation);
  if (generation === runGeneration && !rt.stopped) {
    await dispatchProgramEnded(generation, 'completed');
  }
  sendWs({ type: 'getBrailleLine' });
}

function bindWsControls() {
  const wsToggleBtn = document.getElementById('wsToggleBtn');
  if (wsToggleBtn && !wsToggleBtn.dataset.wsBound) {
    wsToggleBtn.addEventListener('click', () => {
      if (isWsOpen() || (ws && ws.readyState === WebSocket.CONNECTING)) {
        disconnectWs();
      } else {
        connectWs('manual');
      }
    });
    wsToggleBtn.dataset.wsBound = '1';
  }
  renderWsControl();
}

async function onRunClicked() {
  stopAllTimers();
  getRuntime().stopped = false;
  getRuntime().programEndedGeneration = -1;
  runGeneration++;
  const generation = runGeneration;
  renderStatus();

  if (!workspace) {
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

function onStopClicked() {
  const generation = runGeneration;
  const rt = getRuntime();
  if (!rt.stopped) {
    void dispatchProgramEnded(generation, 'stopped');
  }
  getRuntime().stopped = true;
  runGeneration++;
  pendingStart = false;
  stopAllTimers();
  stopSound();
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
    try {
      await refreshOnlineScripts();
    } catch (err) {
      log('Online refresh failed: ' + err.message);
      alert('Could not refresh online scripts: ' + err.message);
    }
  });
  bind('onlineSaveBtn', 'click', async () => {
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
    try {
      await saveWorkspaceOnlineAs();
    } catch (err) {
      log('Online Save As failed: ' + err.message);
      alert('Could not save online script: ' + err.message);
    }
  });
  bind('onlineLoadBtn', 'click', async () => {
    const idInput = document.getElementById('onlineScriptIdInput');
    const select = document.getElementById('onlineScriptsSelect');
    const id = String(idInput?.value || '').trim() || String(select?.value || '').trim();
    if (!id) {
      alert('Select or enter an online script id first.');
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
    const idInput = document.getElementById('onlineScriptIdInput');
    const select = document.getElementById('onlineScriptsSelect');
    const id = String(idInput?.value || '').trim() || String(select?.value || '').trim() || currentOnlineScriptId;
    if (!id) {
      alert('Select or enter an online script id first.');
      return;
    }
    if (!confirm(`Delete online script "${id}"?`)) return;
    try {
      await deleteWorkspaceOnline(id);
    } catch (err) {
      log('Online delete failed: ' + err.message);
      alert('Could not delete online script: ' + err.message);
    }
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
    getRuntime().stopped = false;
    runGeneration++;
    await dispatchEvent({ type: 'thumbKey', key: 'left' }, runGeneration);
  });
  bind('simThumbRightBtn', 'click', async () => {
    getRuntime().stopped = false;
    runGeneration++;
    await dispatchEvent({ type: 'thumbKey', key: 'right' }, runGeneration);
  });
  bind('simCursor5Btn', 'click', async () => {
    getRuntime().stopped = false;
    runGeneration++;
    await dispatchEvent({ type: 'cursorRouting', cell: 5, textIndex: getRuntime().textCaret }, runGeneration);
  });
  bind('simChord1Btn', 'click', async () => {
    getRuntime().stopped = false;
    runGeneration++;
    await dispatchEvent({ type: 'chord', dots: '1' }, runGeneration);
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
  bind('clearLogBtn', 'click', clearLogBox);
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
  const onlineTitle = document.getElementById('onlineScriptTitleInput');
  const onlineStatus = document.getElementById('onlineScriptStatusInput');
  if (metaTitle && !metaTitle.dataset.initialized) {
    metaTitle.addEventListener('input', () => refreshWorkspaceDirtyState());
    metaTitle.dataset.initialized = '1';
  }
  if (metaDescription && !metaDescription.dataset.initialized) {
    metaDescription.addEventListener('input', () => refreshWorkspaceDirtyState());
    metaDescription.dataset.initialized = '1';
  }
  if (onlineTitle && !onlineTitle.dataset.initialized) {
    onlineTitle.addEventListener('input', () => refreshWorkspaceDirtyState());
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
}

bindWsControls();
bindAppControls();
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

preloadLessonToolboxPlaceholders();
ensureProcedureToolboxCategory();
verifyProcedureBlocksAvailable();

setBootStage('before-workspace-inject');
workspace = Blockly.inject('blocklyDiv', {
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
setBootStage('workspace-injected');

applyGridSnap(localStorage.getItem(BLOCKLY_GRID_SNAP_KEY) !== 'false');

function resizeBlockly() {
  if (!workspace) return;
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
  ro.observe(document.getElementById('workspaceWrap'));
}
setTimeout(resizeBlockly, 0);
setTimeout(blurToolboxFocusAfterPointerSelection, 0);

function ensureDefaultVariable() {
  const names = workspace.getAllVariables().map(v => v.name);
  if (!names.includes('score')) workspace.createVariable('score');
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

ensureDefaultVariable();
initVariableValues();
setBootStage('workspace-ready');

setTimeout(() => {
  refreshOnlineScripts().catch(err => {
    log('Online scripts startup load failed: ' + err.message);
  });
}, 0);

function getVariableIdFromBlock(block) {
  const field = block.getField('VAR');
  const model = field?.getVariable();
  return model ? model.getId() : null;
}

function getVariableModelByName(name) {
  return workspace.getAllVariables().find(v => v.name === name) || null;
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
  initVariableValues();
  suppressDirtyTracking--;
  markWorkspaceSaved(getWorkspaceSignature());
  log('Default workspace loaded');
}

loadDefaultWorkspace();

/* ---------------- Evaluation ---------------- */
function highlightBlock(blockId) {
  workspace.highlightBlock(blockId);
  setTimeout(() => workspace.highlightBlock(null), 150);
}

function toNumber(v) {
  const n = Number(v);
  return Number.isNaN(n) ? 0 : n;
}

function toList(v) {
  return Array.isArray(v) ? v : [];
}

function toListIndex(v, listLength) {
  const i = Math.floor(toNumber(v)) - 1; // 1-based index for teacher-friendly blocks
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
      description: ''
    };
  }
  const title = String(xmlDom.getAttribute('data-title') || sourceName || '').trim();
  const description = String(xmlDom.getAttribute('data-description') || '').trim();
  return { title, description };
}

function buildLessonDataCandidates(source) {
  const normalizedSource = String(source || '').trim();
  const fileName = normalizedSource.split('/').pop() || 'aanvankelijklijst.json';
  const candidates = [
    normalizedSource,
    `../klanken/${fileName}`,
    `./klanken/${fileName}`,
    `/braillestudio/klanken/${fileName}`,
    `/klanken/${fileName}`,
    `https://www.tastenbraille.com/braillestudio/klanken/${fileName}`
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

  const res = await fetch(FONEMEN_NL_JSON_URL, { cache: 'no-store' });
  if (!res.ok) {
    throw new Error(`HTTP ${res.status} ${res.statusText}`);
  }

  const data = await res.json();
  fonemenNlJsonCache = data && typeof data === 'object' ? data : { phonemes: [] };
  return fonemenNlJsonCache;
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
  return {
    text: String(source.text ?? '').trim(),
    word: String(source.word ?? '').trim(),
    letters: Array.isArray(source.letters)
      ? source.letters.map((item) => String(item ?? '').trim()).filter(Boolean)
      : String(source.letters ?? '').split(',').map((item) => item.trim()).filter(Boolean)
  };
}

function getLessonStepInputs() {
  return window.lessonStepInputs && typeof window.lessonStepInputs === 'object'
    ? window.lessonStepInputs
    : { text: '', word: '', letters: [] };
}

function resetLessonStepRuntimeState(stepInputs = null) {
  window.lessonStepInputs = normalizeLessonStepInputs(stepInputs);
  getRuntime().stepCompletion = null;
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
  log('Lesson step completion signaled: ' + normalized.status);
  renderStatus();
  return getRuntime().stepCompletion;
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
      return value != null ? value : '';
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

async function executeChain(startBlock, generation) {
  let current = startBlock;

  while (current && !getRuntime().stopped && generation === runGeneration) {
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
        await playSound(resolveFolderSoundUrl(folder, file));
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
        await playSound(resolveFolderSoundUrl(folder, file));
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
        if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.playUrl === 'function') {
          log('Sound play: ' + url);
          await window.BrailleStudioAPI.playUrl(url);
        } else {
          await playSound(url);
        }
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
          const fonemenData = await getFonemenNlStandaard();
          const fonemen = splitWordToNlFonemen(word, fonemenData);
          for (const foneem of fonemen) {
            if (getRuntime().stopped || generation !== runGeneration) break;
            await playSound(resolveFolderSoundUrl('letters', foneem));
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
          const pauseSeconds = Math.max(0, toNumber(await evalValue(current.getInputTargetBlock('SECONDS'))));
          const pauseMs = Math.round(pauseSeconds * 1000);
          const fonemenData = await getFonemenNlStandaard();
          const fonemen = splitWordToNlFonemen(word, fonemenData);
          for (let i = 0; i < fonemen.length; i++) {
            if (getRuntime().stopped || generation !== runGeneration) break;
            await playSound(resolveFolderSoundUrl('letters', fonemen[i]));
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
          if (body) await executeChain(body, generation);
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
          if (body) await executeChain(body, generation);
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

      case 'variables_change': {
        const id = getVariableIdFromBlock(current);
        const delta = toNumber(await evalValue(current.getInputTargetBlock('VALUE')));
        if (id) {
          variableValues[id] = toNumber(variableValues[id] ?? 0) + delta;
          renderStatus();
        }
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

      case 'controls_if': {
        let handled = false;
        for (let i = 0; current.getInput('IF' + i); i++) {
          const cond = Boolean(await evalValue(current.getInputTargetBlock('IF' + i)));
          if (cond) {
            const branch = current.getInputTargetBlock('DO' + i);
            if (branch) await executeChain(branch, generation);
            handled = true;
            break;
          }
        }
        if (!handled && current.getInput('ELSE')) {
          const elseBranch = current.getInputTargetBlock('ELSE');
          if (elseBranch) await executeChain(elseBranch, generation);
        }
        break;
      }

      case 'controls_repeat_ext': {
        const count = toNumber(await evalValue(current.getInputTargetBlock('TIMES')));
        const body = current.getInputTargetBlock('DO');
        for (let i = 0; i < count && !getRuntime().stopped && generation === runGeneration; i++) {
          if (body) await executeChain(body, generation);
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
          if (body) await executeChain(body, generation);
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
          if (body) await executeChain(body, generation);
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

    default:
      return false;
  }
}

async function dispatchProgramEnded(generation = runGeneration, reason = 'completed') {
  const rt = getRuntime();
  if (!workspace) return;
  if (rt.programEndedGeneration === generation) return;
  rt.programEndedGeneration = generation;

  const event = { type: 'programEnded', reason: String(reason || 'completed') };
  log('Event: ' + JSON.stringify(event));

  const topBlocks = workspace.getTopBlocks(true);
  for (const block of topBlocks) {
    if (generation !== runGeneration && reason !== 'stopped') return;
    if (await eventMatches(block, event)) {
      const first = block.getInputTargetBlock('DO');
      if (first) await executeChain(first, generation);
    }
  }
}

async function dispatchEvent(event, generation = runGeneration) {
  const rt = getRuntime();
  if (generation !== runGeneration || rt.stopped) return;
  if (!workspace) return;

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

function loadWorkspaceFromState(state, sourceName = null, metadata = null) {
  try {
    if (!state || typeof state !== 'object') {
      throw new Error('Invalid Blockly JSON structure');
    }
    registerProcedures(state, runtime);
    suppressDirtyTracking++;
    workspace.clear();
    Blockly.serialization.workspaces.load(state, workspace);
    refreshProcedureRegistry(workspace);
    ensureDefaultVariable();
    initVariableValues();
    if (metadata && typeof metadata === 'object') {
      renderScriptMetadata({
        title: String(metadata.title || sourceName || ''),
        description: String(metadata.description || '')
      });
    } else {
      renderScriptMetadata(sourceName ? { title: String(sourceName), description: '' } : null);
    }
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

async function newWorkspace() {
  if (!(await confirmActionWithUnsavedChanges('creating a new file'))) return;
  getRuntime().stopped = true;
  runGeneration++;
  stopAllTimers();
  stopSound();
  currentFileHandle = null;
  setSelectedFileName('braille-activity.blockly');
  suppressDirtyTracking++;
  workspace.clear();
  refreshProcedureRegistry(workspace);
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
renderFileState();
setWsBadge(false);

if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.preloadAudioList === 'function') {
  window.BrailleStudioAPI.preloadAudioList({ folder: 'speech' })
    .then((items) => {
      log(`Speech library preloaded (${Array.isArray(items) ? items.length : 0})`);
    })
    .catch((err) => {
      log(`Speech library preload failed: ${err?.message || err}`);
    });
}

window.BrailleBlocklyApp = {
  loadWorkspaceFromText,
  loadWorkspaceFromState,
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
    getRuntime().lockInjectedLessonRecord = !!lockInjectedRecord;
    runGeneration++;
    const generation = runGeneration;
    pendingStart = false;
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
      if (generation === runGeneration && !getRuntime().stopped) {
        await dispatchProgramEnded(generation, 'completed');
      }
      return {
        generation,
        startedBlockCount: startedBlocks.length,
        currentRecordIndex: Number.isFinite(window.currentRecordIndex) ? window.currentRecordIndex : -1,
        currentRecord: window.currentRecord && typeof window.currentRecord === 'object' ? window.currentRecord : null,
        lessonMethod: structuredClone(getLessonMethod()),
        stepCompletion: getRuntime().stepCompletion ? structuredClone(getRuntime().stepCompletion) : null,
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
    getRuntime().lockInjectedLessonRecord = !!lockInjectedRecord;
    runGeneration++;
    const generation = runGeneration;
    pendingStart = false;
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
      if (generation === runGeneration && !getRuntime().stopped) {
        await dispatchProgramEnded(generation, 'completed');
      }
      return {
        generation,
        startedBlockCount: startedBlocks.length,
        currentRecordIndex: Number.isFinite(window.currentRecordIndex) ? window.currentRecordIndex : -1,
        currentRecord: window.currentRecord && typeof window.currentRecord === 'object' ? window.currentRecord : null,
        lessonMethod: structuredClone(getLessonMethod()),
        stepCompletion: getRuntime().stepCompletion ? structuredClone(getRuntime().stepCompletion) : null,
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
    runGeneration++;
    const generation = runGeneration;
    pendingStart = false;
    renderStatus();
    if (!workspace) {
      throw new Error('Blockly workspace is not ready');
    }
    refreshProcedureRegistry(workspace);
    await dispatchEvent({ type: 'started' }, generation);
    if (generation === runGeneration && !getRuntime().stopped) {
      await dispatchProgramEnded(generation, 'completed');
    }
    return generation;
  },
  stopProgram() {
    onStopClicked();
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
  getStepCompletion() {
    return getRuntime().stepCompletion ? structuredClone(getRuntime().stepCompletion) : null;
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
    return { ...getRuntime() };
  },
  clearLog() {
    clearLogBox();
  },
  setLogHandler(fn) {
    externalLogHandler = typeof fn === 'function' ? fn : null;
  },
  setPlayHandler(fn) {
    if (window.BrailleStudioAPI && typeof window.BrailleStudioAPI.setPlayHandler === 'function') {
      window.BrailleStudioAPI.setPlayHandler(fn);
    }
  }
};
setBootStage('api-ready');
