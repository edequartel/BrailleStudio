<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lesson Builder - Steps</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="./lessonbuilder-shared.js?v=20260407-2"></script>
  <link rel="stylesheet" href="/braillestudio/components/braille-monitor/braillemonitor.css">
  <link rel="stylesheet" href="https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.css">
  <style>
    .lesson-monitor-fit {
      overflow: hidden;
    }

    .lesson-monitor-host {
      overflow: hidden;
      border-radius: 5px;
    }

    .lesson-monitor-host .braille-monitor-component,
    .lesson-monitor-host .braille-monitor-cells,
    .lesson-monitor-host .braille-monitor-cell-container {
      border-radius: 5px;
    }

    .steps-grid {
      grid-template-columns: minmax(240px, 2.3fr) minmax(220px, 1.9fr) minmax(160px, 1.2fr) minmax(220px, 1.5fr) 92px minmax(150px, 1.15fr) 72px 72px 72px 88px;
      min-width: 1400px;
    }

    .steps-textarea {
      min-height: 72px;
      resize: vertical;
    }

    .lesson-content-safe {
      min-width: 0;
    }

    .status-log-safe {
      max-width: 100%;
      overflow-x: auto;
      overflow-y: auto;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .lesson-monitor-fit .braille-monitor-component {
      zoom: 0.78;
      transform-origin: top left;
    }

    @supports not (zoom: 1) {
      .lesson-monitor-fit .braille-monitor-component {
        transform: scale(0.78);
        width: calc(100% / 0.78);
      }
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-7xl mx-auto p-6 space-y-5">
    <div class="flex items-start gap-4">
      <div class="min-w-0">
        <div class="text-sm font-semibold text-blue-700">Stap 3 van 3</div>
        <h1 class="text-3xl font-bold">Lesson steps bouwen</h1>
      </div>
      <div class="ml-auto flex shrink-0 gap-2">
        <a class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-records.php">Vorige stap</a>
      </div>
    </div>

    <div class="grid gap-5">
      <section class="lesson-content-safe rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="text-lg font-bold">Lesson</div>
        <div id="brailleMonitorRow" class="lesson-monitor-fit lesson-monitor-host">
          <div id="brailleMonitorComponent"></div>
        </div>
        <div id="scriptBrailleMonitorRow" class="lesson-monitor-fit lesson-monitor-host">
          <div id="scriptBrailleMonitorComponent"></div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button id="simThumbLeftBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" type="button">Left thumb</button>
          <button id="simCursor5Btn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" type="button">Left middle thumb</button>
          <button id="simChord1Btn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" type="button">Right middle thumb</button>
          <button id="simThumbRightBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" type="button">Right thumb</button>
        </div>
        <div id="lessonSummary" class="lesson-content-safe rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 break-words"></div>
        <div class="grid gap-3 md:grid-cols-2">
          <input id="lessonIdInput" type="hidden">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="lessonTitleInput">Lesson title</label>
            <input id="lessonTitleInput" class="w-full rounded-xl border border-slate-300 px-3 py-2" type="text">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="lessonWordInput">Word</label>
            <input id="lessonWordInput" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-slate-50" type="text" readonly>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1" for="lessonDescriptionInput">Description</label>
          <textarea id="lessonDescriptionInput" class="min-h-[92px] w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Lesson description"></textarea>
        </div>

        <div class="flex flex-wrap gap-2">
          <button id="saveLessonBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
          <button id="deleteLessonBtn" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
          <button id="runLessonBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Run</button>
        </div>

        <section>
          <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center">
            <select id="scriptsSelect" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"></select>
            <button id="refreshScriptsBtn" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold md:w-auto">Refresh</button>
            <button id="addStepBtn" class="h-10 w-full rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white md:w-auto">Add</button>
          </div>
        </section>

        <div>
          <div class="mb-2 text-sm font-semibold text-slate-700">Steps</div>
          <div class="rounded-xl border border-slate-200 overflow-x-auto overflow-y-hidden">
            <div class="steps-grid grid w-full gap-2 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
              <div class="min-w-0 pr-2 text-left">Step</div>
              <div class="min-w-0 text-left">Text</div>
              <div class="min-w-0 text-left">Word</div>
              <div class="min-w-0 text-left">Letters</div>
              <div class="min-w-0 text-left">Repeat</div>
              <div class="min-w-0 text-left">Step link</div>
              <div class="text-center">Run</div>
              <div class="text-center">Up</div>
              <div class="text-center">Down</div>
              <div class="text-center">Remove</div>
            </div>
            <div id="stepsTableBody" class="divide-y divide-slate-200"></div>
          </div>
        </div>
      </section>

      <section class="lesson-content-safe rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
        <div class="flex items-center justify-between gap-3">
          <div class="text-lg font-bold">Debug log</div>
          <div class="flex items-center gap-2">
            <button id="copyDebugLogBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Copy log</button>
            <button id="clearDebugLogBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Clear log</button>
            <button id="toggleDebugLogBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Unhide</button>
          </div>
        </div>
        <pre id="statusBox" class="status-log-safe hidden min-h-[180px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800"></pre>
      </section>
    </div>
  </div>

  <iframe
    id="lessonRunnerFrame"
    title="Lesson runner"
    allow="autoplay"
    style="position:absolute; width:1px; height:1px; border:0; opacity:0; pointer-events:none; left:-9999px; top:auto;"
  ></iframe>

  <script>
    const shared = window.LessonBuilderShared;
    const lessonIdInput = document.getElementById('lessonIdInput');
    const lessonTitleInput = document.getElementById('lessonTitleInput');
    const lessonWordInput = document.getElementById('lessonWordInput');
    const lessonDescriptionInput = document.getElementById('lessonDescriptionInput');
    const lessonSummary = document.getElementById('lessonSummary');
    const stepsTableBody = document.getElementById('stepsTableBody');
    const scriptsSelect = document.getElementById('scriptsSelect');
    const addStepBtn = document.getElementById('addStepBtn');
    const scriptsSummary = document.getElementById('scriptsSummary');
    const statusBox = document.getElementById('statusBox');
    const copyDebugLogBtn = document.getElementById('copyDebugLogBtn');
    const clearDebugLogBtn = document.getElementById('clearDebugLogBtn');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');
    const authBtn = document.getElementById('authBtn');
    const saveLessonBtn = document.getElementById('saveLessonBtn');
    const deleteLessonBtn = document.getElementById('deleteLessonBtn');
    const brailleMonitorRow = document.getElementById('brailleMonitorRow');
    const scriptBrailleMonitorRow = document.getElementById('scriptBrailleMonitorRow');
    const simThumbLeftBtn = document.getElementById('simThumbLeftBtn');
    const simThumbRightBtn = document.getElementById('simThumbRightBtn');
    const simCursor5Btn = document.getElementById('simCursor5Btn');
    const simChord1Btn = document.getElementById('simChord1Btn');
    const STEP_LINK_CREATE_URL = '../session-api/create-link.php';

    let state = shared.loadState();
    let scriptsCache = [];
    const scriptDataCache = new Map();
    let stepConfigs = [];
    let basisItems = [];
    let isDebugLogVisible = false;
    let currentRunningStepIndex = -1;
    let isStoppingCurrentStep = false;
    let isLessonRunning = false;
    let isStoppingLesson = false;
    let activeStopPromise = null;
    let brailleMonitorUi = null;
    let scriptBrailleMonitorUi = null;
    let brailleMonitorSyncTimer = null;
    let lastBrailleSnapshot = '';
    let lastScriptBrailleSnapshot = '';
    const BRAILLE_MONITOR_PLACEHOLDER = 'Bartiméus Education';
    const authRedirected = Boolean(shared?.requireAuthOnProduction?.());

    function resolveRunnerUrl() {
      const host = String(window.location.hostname || '').toLowerCase();
      if (host === '127.0.0.1' || host === 'localhost') {
        return 'http://127.0.0.1:5500/blockly/index.html?v=20260415-1';
      }
      return 'https://www.tastenbraille.com/braillestudio/blockly/index.html?v=20260415-1';
    }

    const RUNNER_URL = resolveRunnerUrl();
    lessonRunnerFrame.src = RUNNER_URL;

    function formatDebugData(value) {
      if (value == null) return '';
      if (typeof value === 'string') return value;
      try {
        return JSON.stringify(value, null, 2);
      } catch (err) {
        return String(value);
      }
    }

    function setStatus(message, data = null) {
      statusBox.textContent = data != null ? `${message}\n\n${formatDebugData(data)}` : message;
    }

    function appendStatus(message, data = null) {
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const block = data
        ? `[${timestamp}] ${message}\n${formatDebugData(data)}`
        : `[${timestamp}] ${message}`;
      statusBox.textContent = statusBox.textContent ? `${statusBox.textContent}\n\n${block}` : block;
      statusBox.scrollTop = statusBox.scrollHeight;
    }

    function showDebugLog() {
      if (isDebugLogVisible) return;
      isDebugLogVisible = true;
      renderDebugLogVisibility();
    }

    function renderDebugLogVisibility() {
      statusBox.classList.toggle('hidden', !isDebugLogVisible);
      toggleDebugLogBtn.textContent = isDebugLogVisible ? 'Hide' : 'Unhide';
    }

    function renderAuthenticationState() {
      const authenticated = Boolean(shared?.getAuthToken?.());
      if (authBtn) {
        authBtn.textContent = authenticated ? 'Authenticated' : 'Authentication';
        authBtn.className = authenticated
          ? 'rounded-xl border border-green-300 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700'
          : 'rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold';
      }
      if (saveLessonBtn) {
        saveLessonBtn.disabled = !authenticated;
        saveLessonBtn.className = authenticated
          ? 'rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white'
          : 'rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 cursor-not-allowed';
        saveLessonBtn.title = authenticated ? 'Save lesson' : 'Authenticate first to save';
      }
      if (deleteLessonBtn) {
        deleteLessonBtn.disabled = !authenticated;
        deleteLessonBtn.className = authenticated
          ? 'rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white'
          : 'rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 cursor-not-allowed';
        deleteLessonBtn.title = authenticated ? 'Delete lesson' : 'Authenticate first to delete';
      }
    }

    function showBrailleMonitorPlaceholder() {
      if (!brailleMonitorUi || typeof brailleMonitorUi.setText !== 'function') return;
      brailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
      lastBrailleSnapshot = JSON.stringify({ placeholder: BRAILLE_MONITOR_PLACEHOLDER });
    }

    function showScriptBrailleMonitorPlaceholder() {
      if (!scriptBrailleMonitorUi || typeof scriptBrailleMonitorUi.setText !== 'function') return;
      scriptBrailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
      lastScriptBrailleSnapshot = JSON.stringify({ placeholder: BRAILLE_MONITOR_PLACEHOLDER });
    }

    function renderMonitorSourceVisibility(isWsConnected = false) {
      if (brailleMonitorRow) {
        brailleMonitorRow.classList.toggle('hidden', !isWsConnected);
      }
      if (scriptBrailleMonitorRow) {
        scriptBrailleMonitorRow.classList.toggle('hidden', !!isWsConnected);
      }
    }

    async function dispatchRunnerInput(event) {
      const runner = getRunnerWindow();
      appendStatus('Dispatch runner input requested.', {
        event,
        before: getRunnerInputDebugSnapshot()
      });
      const app = await waitForRunnerReady(5000);
      if (app && typeof app.dispatchRuntimeEvent === 'function') {
        appendStatus('Dispatch runner input via app.dispatchRuntimeEvent.', {
          event,
          snapshot: getRunnerInputDebugSnapshot(app)
        });
        await app.dispatchRuntimeEvent(event);
        appendStatus('Dispatch runner input completed via app.dispatchRuntimeEvent.', {
          event,
          after: getRunnerInputDebugSnapshot(app)
        });
        return;
      }
      const legacyDispatch = runner && typeof runner.dispatchEvent === 'function'
        ? runner.dispatchEvent.bind(runner)
        : null;
      const generation = Number.isFinite(runner?.runGeneration) ? runner.runGeneration : null;
      if (legacyDispatch && generation != null) {
        appendStatus('Dispatch runner input via legacy runner.dispatchEvent.', {
          event,
          generation,
          snapshot: getRunnerInputDebugSnapshot(app)
        });
        await legacyDispatch(event, generation);
        appendStatus('Dispatch runner input completed via legacy runner.dispatchEvent.', {
          event,
          generation,
          after: getRunnerInputDebugSnapshot(app)
        });
        return;
      }
      appendStatus('Dispatch runner input unsupported.', {
        event,
        snapshot: getRunnerInputDebugSnapshot(app)
      });
      throw new Error('Blockly runner does not support runtime input dispatch');
    }

    async function loadScriptCandidates(candidates) {
      let lastError = null;
      for (const src of candidates) {
        try {
          await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Failed to load ${src}`));
            document.head.appendChild(script);
          });
          return;
        } catch (err) {
          lastError = err;
        }
      }
      throw lastError || new Error('No braille monitor script could be loaded');
    }

    function renderStepRunButtons() {
      Array.from(stepsTableBody.querySelectorAll('[data-run-step-index]')).forEach((button) => {
        const index = Number(button.getAttribute('data-run-step-index') || -1);
        const isCurrent = index === currentRunningStepIndex;
        const hasActiveRun = currentRunningStepIndex >= 0;
        button.textContent = isCurrent ? (isStoppingCurrentStep ? 'Stopping...' : 'Stop') : 'Run';
        button.disabled = isStoppingCurrentStep || isStoppingLesson || (hasActiveRun && !isCurrent);
        button.className = isCurrent
          ? 'w-full rounded-lg bg-red-600 px-2 py-1 text-xs font-semibold text-white'
          : 'w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
      });
      const runLessonBtn = document.getElementById('runLessonBtn');
      if (runLessonBtn) {
        runLessonBtn.textContent = isLessonRunning ? (isStoppingLesson ? 'Stopping...' : 'Stop') : 'Run';
        runLessonBtn.disabled = isStoppingCurrentStep || isStoppingLesson || (currentRunningStepIndex >= 0 && !isLessonRunning);
        runLessonBtn.className = isLessonRunning
          ? 'rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white'
          : 'rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold';
      }
    }

    function renderLessonSummary() {
      const method = shared.getDraftMethodMeta(state);
      lessonSummary.innerHTML = `
        <div><strong>Method:</strong> ${method.title || method.id || '-'}</div>
        <div><strong>Basisrecord:</strong> ${state.basisWord || '-'}</div>
        <div><strong>Lesson:</strong> ${lessonTitleInput.value || lessonIdInput.value || '-'}</div>
        <div><strong>Description:</strong> ${lessonDescriptionInput.value.trim() || '-'}</div>
        <div><strong>Lesnummer:</strong> ${state.lessonNumber || 1}</div>
      `;
    }

    function renderScriptsSelect(items) {
      scriptsSelect.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = '-- Select script --';
      scriptsSelect.appendChild(placeholder);
      items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.title ? `${item.id} - ${item.title}` : item.id;
        scriptsSelect.appendChild(option);
      });
      if (Array.isArray(items) && items.length > 0) {
        scriptsSelect.value = String(items[0]?.id || '');
      } else {
        scriptsSelect.value = '';
      }
      renderAddStepAvailability();
    }

    function renderAddStepAvailability() {
      if (!addStepBtn) return;
      addStepBtn.disabled = !String(scriptsSelect?.value || '').trim();
    }

    function updateStateStepConfigs() {
      stepConfigs = serializeStepConfigs(shared.normalizeStepConfigs(stepConfigs));
      state = shared.updateState({ steps: stepConfigs });
      renderLessonSummary();
    }

    function getScriptItemById(id) {
      const scriptId = String(id || '').trim();
      return scriptsCache.find((item) => String(item?.id || '').trim() === scriptId) || null;
    }

    async function loadScriptData(id) {
      const scriptId = String(id || '').trim();
      if (!scriptId) {
        throw new Error('Missing script id');
      }
      if (scriptDataCache.has(scriptId)) {
        return scriptDataCache.get(scriptId);
      }
      const data = await shared.loadScript(scriptId);
      if (!data || !data.blockly) {
        throw new Error(`Script ${scriptId} has no blockly state`);
      }
      scriptDataCache.set(scriptId, data);
      return data;
    }

    function getReferencedScriptIds(items = stepConfigs) {
      const ids = new Set();
      (Array.isArray(items) ? items : []).forEach((step) => {
        const scriptId = String(step?.id || '').trim();
        if (scriptId) ids.add(scriptId);
      });
      return Array.from(ids);
    }

    async function preloadReferencedScriptData(items = stepConfigs) {
      const scriptIds = getReferencedScriptIds(items);
      if (!scriptIds.length) {
        return { total: 0, loaded: 0, failed: 0 };
      }
      const results = await Promise.allSettled(
        scriptIds.map(async (scriptId) => {
          await loadScriptData(scriptId);
          return scriptId;
        })
      );
      let failedCount = 0;
      results.forEach((result, index) => {
        if (result.status !== 'rejected') return;
        failedCount += 1;
        appendStatus('Script preload failed.', {
          scriptId: scriptIds[index],
          error: result.reason?.message || String(result.reason)
        });
      });
      return {
        total: scriptIds.length,
        loaded: results.length - failedCount,
        failed: failedCount
      };
    }

    function getStepDisplayMeta(stepConfig) {
      const script = getScriptItemById(stepConfig?.id);
      return {
        title: String(stepConfig?.title || script?.title || '').trim()
      };
    }

    function serializeStepConfig(stepConfig) {
      const inputs = shared.normalizeInputs(stepConfig?.inputs || {});
      const meta = getStepDisplayMeta(stepConfig);
      return {
        id: String(stepConfig?.id || '').trim(),
        title: meta.title,
        stepLinkCode: String(stepConfig?.stepLinkCode || '').trim(),
        inputs: {
          text: String(inputs.text || ''),
          word: String(inputs.word || ''),
          letters: Array.isArray(inputs.letters) ? inputs.letters : [],
          repeat: Math.max(1, Math.floor(Number(inputs.repeat ?? 1) || 1))
        }
      };
    }

    function serializeStepConfigs(items = stepConfigs) {
      return (Array.isArray(items) ? items : [])
        .map((item) => serializeStepConfig(item))
        .filter((item) => item.id);
    }

    function buildStepConfigsFromTable() {
      const rows = stepsTableBody.querySelectorAll('[data-step-index]');
      const built = [];
      rows.forEach((row) => {
        const index = Number(row.getAttribute('data-step-index') || -1);
        const source = stepConfigs[index] || {};
        const meta = getStepDisplayMeta(source);
        const textValue = row.querySelector('[data-field="text"]')?.value ?? '';
        const wordValue = row.querySelector('[data-field="word"]')?.value ?? '';
        const lettersValue = row.querySelector('[data-field="letters"]')?.value ?? '';
        const repeatRaw = row.querySelector('[data-field="repeat"]')?.value ?? '1';
        built.push({
          id: String(source.id || '').trim(),
          title: meta.title,
          stepLinkCode: String(source.stepLinkCode || '').trim(),
          inputs: {
            text: String(textValue || ''),
            word: String(wordValue || ''),
            letters: String(lettersValue).split(',').map((item) => item.trim()).filter(Boolean),
            repeat: Math.max(1, Math.floor(Number(repeatRaw) || 1))
          }
        });
      });
      return built.filter((item) => item.id);
    }

    function hydrateStepConfigsWithScriptMetadata() {
      stepConfigs = shared.normalizeStepConfigs(stepConfigs).map((cfg) => {
        const script = getScriptItemById(cfg.id);
        return {
          ...cfg,
          title: cfg.title || String(script?.title || '').trim()
        };
      });
    }

    function normalizeStepLinkToken(value, fallback = 'step') {
      let token = String(value || '').trim().replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
      if (!token) token = String(fallback || 'step').trim().replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'step';
      if (!/^[a-zA-Z0-9]/.test(token)) {
        token = `s-${token}`;
      }
      while (token.length < 3) {
        token += '-x';
      }
      return token.slice(0, 128).replace(/-+$/g, '') || 'step';
    }

    function buildStepLinkStepId(stepConfig, stepIndex) {
      const lessonId = normalizeStepLinkToken(lessonIdInput.value.trim() || state.lessonId || 'lesson', 'lesson');
      const stepNumber = String(Math.max(1, Number(stepIndex) + 1)).padStart(2, '0');
      const scriptId = normalizeStepLinkToken(stepConfig?.id || 'script', 'script');
      return normalizeStepLinkToken(`${lessonId}-step-${stepNumber}-${scriptId}`, 'step');
    }

    function buildStepLinkMeta(stepConfig, stepIndex) {
      const method = shared.getDraftMethodMeta(state);
      const basisRecord = basisItems[Number(state.basisIndex ?? -1)] || state.basisRecord || null;
      return {
        title: String(stepConfig?.title || stepConfig?.id || '').trim(),
        lessonId: String(lessonIdInput.value.trim() || state.lessonId || '').trim(),
        lessonTitle: String(lessonTitleInput.value.trim() || state.lessonTitle || '').trim(),
        lessonNumber: Number(state.lessonNumber || 1),
        methodId: String(method.id || '').trim(),
        methodTitle: String(method.title || '').trim(),
        basisIndex: Number(state.basisIndex ?? -1),
        basisWord: String(state.basisWord || '').trim(),
        basisRecord,
        stepIndex: Number(stepIndex),
        stepNumber: Number(stepIndex) + 1,
        scriptTitle: String(stepConfig?.title || '').trim()
      };
    }

    function renderStepLinkCodeCell(cell, code = '') {
      const normalizedCode = String(code || '').trim();
      cell.querySelector('[data-step-link-code-text]').textContent = normalizedCode || 'No code yet';
      const copyBtn = cell.querySelector('[data-copy-step-link-code]');
      if (copyBtn) {
        copyBtn.disabled = !normalizedCode;
      }
    }

    async function createStepLinkForStep(index, cell = null) {
      syncStepConfigsFromTable();
      const stepConfig = stepConfigs[index];
      if (!stepConfig) {
        throw new Error('Step not found');
      }
      if (!String(stepConfig.id || '').trim()) {
        throw new Error('Step has no script id');
      }
      const existingCode = String(stepConfig.stepLinkCode || '').trim();
      const payload = {
        scriptId: String(stepConfig.id || '').trim(),
        stepId: buildStepLinkStepId(stepConfig, index),
        active: true,
        overwrite: Boolean(existingCode),
        meta: buildStepLinkMeta(stepConfig, index),
        stepInputs: shared.normalizeInputs(stepConfig.inputs || {})
      };
      if (existingCode) {
        payload.code = existingCode;
      }

      appendStatus('Step-link create gestart.', payload);
      const res = await fetch(STEP_LINK_CREATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
      stepConfigs[index] = {
        ...stepConfig,
        stepLinkCode: String(data.code || '').trim()
      };
      updateStateStepConfigs();
      if (cell) {
        renderStepLinkCodeCell(cell, stepConfigs[index].stepLinkCode);
      }
      appendStatus('Step-link aangemaakt.', data);
      setStatus(`Step-link code: ${stepConfigs[index].stepLinkCode}`);
      return data;
    }

    function renderStepsTable() {
      stepsTableBody.innerHTML = '';
      if (!stepConfigs.length) {
        stepsTableBody.innerHTML = '<div class="px-3 py-3 text-sm text-slate-500">No steps yet. Add a script from the list.</div>';
        return;
      }
      stepConfigs.forEach((cfg, index) => {
        const inputs = shared.normalizeInputs(cfg.inputs || {});
        const meta = getStepDisplayMeta(cfg);
        const row = document.createElement('div');
        row.dataset.stepIndex = String(index);
        row.className = 'steps-grid grid w-full gap-2 items-start px-3 py-2';

        const script = document.createElement('div');
        script.className = 'min-w-0 pr-2 pt-1 text-sm text-slate-800 break-words leading-5';
        script.innerHTML = `
          <div class="font-semibold text-slate-900">${meta.title || cfg.id}</div>
          <div class="text-xs text-slate-500">${cfg.id}</div>
        `;

        const text = document.createElement('textarea');
        text.dataset.field = 'text';
        text.rows = 3;
        text.placeholder = 'Text';
        text.className = 'steps-textarea block w-full min-w-0 rounded-lg border border-slate-300 px-2 py-1 text-sm';
        text.value = inputs.text;
        text.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), text: e.target.value };
          updateStateStepConfigs();
        });

        const word = document.createElement('input');
        word.dataset.field = 'word';
        word.className = 'block h-10 w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm';
        word.value = inputs.word;
        word.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), word: e.target.value };
          updateStateStepConfigs();
        });

        const letters = document.createElement('textarea');
        letters.dataset.field = 'letters';
        letters.rows = 3;
        letters.placeholder = 'a, b, c';
        letters.className = 'steps-textarea block w-full min-w-0 rounded-lg border border-slate-300 px-2 py-1 text-sm';
        letters.value = inputs.letters.join(', ');
        letters.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), letters: e.target.value.split(',').map((item) => item.trim()).filter(Boolean) };
          updateStateStepConfigs();
        });

        const repeat = document.createElement('input');
        repeat.dataset.field = 'repeat';
        repeat.type = 'number';
        repeat.min = '1';
        repeat.step = '1';
        repeat.className = 'block h-10 w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm';
        repeat.value = String(inputs.repeat || 1);
        const applyRepeatValue = (target) => {
          const nextRepeat = Math.max(1, Math.floor(Number(target.value) || 1));
          target.value = String(nextRepeat);
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), repeat: nextRepeat };
          updateStateStepConfigs();
        };
        repeat.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), repeat: Math.max(1, Math.floor(Number(e.target.value) || 1)) };
        });
        repeat.addEventListener('change', (e) => applyRepeatValue(e.target));
        repeat.addEventListener('blur', (e) => applyRepeatValue(e.target));

        const stepLink = document.createElement('div');
        stepLink.className = 'min-w-0 space-y-1';
        stepLink.innerHTML = `
          <button type="button" data-create-step-link class="w-full rounded-lg border border-blue-300 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">Create link</button>
          <div class="flex min-w-0 items-center gap-1">
            <code data-step-link-code-text class="block min-w-0 flex-1 truncate rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">No code yet</code>
            <button type="button" data-copy-step-link-code class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700">Copy</button>
          </div>
        `;
        renderStepLinkCodeCell(stepLink, cfg.stepLinkCode);
        const createStepLinkBtn = stepLink.querySelector('[data-create-step-link]');
        createStepLinkBtn.addEventListener('click', async () => {
          createStepLinkBtn.disabled = true;
          createStepLinkBtn.textContent = 'Creating...';
          try {
            await createStepLinkForStep(index, stepLink);
          } catch (err) {
            showDebugLog();
            setStatus(`Step-link error: ${err.message}`);
            appendStatus('Step-link create failed.', {
              stepIndex: index,
              error: err.message || String(err)
            });
          } finally {
            createStepLinkBtn.disabled = false;
            createStepLinkBtn.textContent = 'Create link';
          }
        });
        stepLink.querySelector('[data-copy-step-link-code]').addEventListener('click', async () => {
          const code = String(stepConfigs[index]?.stepLinkCode || '').trim();
          if (!code) return;
          try {
            await navigator.clipboard.writeText(code);
            setStatus(`Step-link code copied: ${code}`);
          } catch (err) {
            setStatus(`Copy step-link error: ${err.message || String(err)}`);
          }
        });

        const run = document.createElement('button');
        run.dataset.runStepIndex = String(index);
        run.className = 'w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
        run.textContent = 'Run';
        run.addEventListener('click', async () => {
          try {
            await runSingleStep(index);
          } catch (err) {
            setStatus(`Step run error: ${err.message}`);
          }
        });

        const moveUp = document.createElement('button');
        moveUp.className = 'w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
        moveUp.textContent = 'Up';
        moveUp.disabled = index === 0;
        moveUp.addEventListener('click', () => {
          if (index === 0) return;
          const current = stepConfigs[index];
          stepConfigs[index] = stepConfigs[index - 1];
          stepConfigs[index - 1] = current;
          updateStateStepConfigs();
          renderStepsTable();
        });

        const moveDown = document.createElement('button');
        moveDown.className = 'w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
        moveDown.textContent = 'Down';
        moveDown.disabled = index === stepConfigs.length - 1;
        moveDown.addEventListener('click', () => {
          if (index === stepConfigs.length - 1) return;
          const current = stepConfigs[index];
          stepConfigs[index] = stepConfigs[index + 1];
          stepConfigs[index + 1] = current;
          updateStateStepConfigs();
          renderStepsTable();
        });

        const remove = document.createElement('button');
        remove.className = 'w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => {
          stepConfigs.splice(index, 1);
          updateStateStepConfigs();
          renderStepsTable();
        });

        row.append(script, text, word, letters, repeat, stepLink, run, moveUp, moveDown, remove);
        stepsTableBody.appendChild(row);
      });
      renderStepRunButtons();
    }

    function syncStepConfigsFromTable() {
      stepConfigs = buildStepConfigsFromTable();
      state = shared.updateState({ steps: stepConfigs });
    }

    function getRepeatDomSnapshot() {
      return Array.from(stepsTableBody.querySelectorAll('[data-step-index]')).map((row) => ({
        index: Number(row.getAttribute('data-step-index') || -1),
        scriptId: String(stepConfigs[Number(row.getAttribute('data-step-index') || -1)]?.id || ''),
        repeatValue: row.querySelector('[data-field="repeat"]')?.value ?? ''
      }));
    }

    function getRunnerWindow() {
      return lessonRunnerFrame?.contentWindow || null;
    }

    function getRunnerDebugState() {
      try {
        const runner = getRunnerWindow();
        if (!runner) {
          return { ready: false, reason: 'no-content-window' };
        }
        const boot = runner.BrailleBlocklyBoot || null;
        return {
          ready: Boolean(runner.BrailleBlocklyApp && boot?.stage === 'api-ready'),
          hasApp: Boolean(runner.BrailleBlocklyApp),
          bootStage: boot?.stage || '',
          bootError: boot?.error || '',
          href: runner.location?.href || '',
          title: runner.document?.title || '',
          readyState: runner.document?.readyState || '',
          hasBootObject: Boolean(runner.BrailleBlocklyBoot),
          hasBootSetter: typeof runner.__setBrailleBlocklyBootStage === 'function',
          hasBlocklyGlobal: Boolean(runner.Blockly)
        };
      } catch (err) {
        return {
          ready: false,
          reason: 'runner-state-error',
          error: err.message || String(err)
        };
      }
    }

    function getRunnerInputDebugSnapshot(app = null) {
      try {
        const runner = getRunnerWindow();
        const resolvedApp = app || runner?.BrailleBlocklyApp || null;
        const runtime = typeof resolvedApp?.getRuntimeSnapshot === 'function'
          ? resolvedApp.getRuntimeSnapshot()
          : null;
        return {
          runnerState: getRunnerDebugState(),
          hasDispatchRuntimeEvent: typeof resolvedApp?.dispatchRuntimeEvent === 'function',
          hasLegacyDispatchEvent: typeof runner?.dispatchEvent === 'function',
          runGeneration: Number.isFinite(runner?.runGeneration) ? runner.runGeneration : null,
          runtime: runtime && typeof runtime === 'object'
            ? {
                stopped: Boolean(runtime.stopped),
                isActive: Boolean(runtime.isActive),
                text: String(runtime.text || ''),
                textCaret: Number.isInteger(runtime.textCaret) ? runtime.textCaret : runtime.textCaret ?? null,
                cellCaret: Number.isInteger(runtime.cellCaret) ? runtime.cellCaret : runtime.cellCaret ?? null,
                wsConnected: Boolean(runtime.wsConnected),
                hasPendingStart: Boolean(runtime.hasPendingStart),
                hasActiveAudio: Boolean(runtime.hasActiveAudio),
                lastThumbKey: String(runtime.lastThumbKey || ''),
                lastCursorCell: Number.isFinite(runtime.lastCursorCell) ? runtime.lastCursorCell : null,
                lastChord: String(runtime.lastChord || '')
              }
            : null
        };
      } catch (err) {
        return {
          runnerState: getRunnerDebugState(),
          snapshotError: err.message || String(err)
        };
      }
    }

    async function waitForRunnerReady(timeoutMs = 30000) {
      const start = Date.now();
      let lastState = getRunnerDebugState();
      while (Date.now() - start < timeoutMs) {
        lastState = getRunnerDebugState();
        const runner = getRunnerWindow();
        if (lastState.ready && runner?.BrailleBlocklyApp) {
          return runner.BrailleBlocklyApp;
        }
        await new Promise((resolve) => setTimeout(resolve, 100));
      }
      throw new Error(`Blockly runner did not become ready in time (stage: ${lastState.bootStage || lastState.reason || 'unknown'}${lastState.bootError ? `, error: ${lastState.bootError}` : ''})`);
    }

    async function ensureBrailleMonitorReady() {
      if (brailleMonitorUi) return brailleMonitorUi;
      if (!window.BrailleMonitor) {
        await loadScriptCandidates([
          '/braillestudio/components/braille-monitor/braillemonitor.js',
          'https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.js'
        ]);
      }
      if (!window.BrailleMonitor || typeof window.BrailleMonitor.init !== 'function') {
        throw new Error('BrailleMonitor component is not available');
      }
      brailleMonitorUi = window.BrailleMonitor.init({
        containerId: 'brailleMonitorComponent',
        showInfo: false
      });
      showBrailleMonitorPlaceholder();
      return brailleMonitorUi;
    }

    async function ensureScriptBrailleMonitorReady() {
      if (scriptBrailleMonitorUi) return scriptBrailleMonitorUi;
      if (!window.BrailleMonitor) {
        await loadScriptCandidates([
          '/braillestudio/components/braille-monitor/braillemonitor.js',
          'https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.js'
        ]);
      }
      if (!window.BrailleMonitor || typeof window.BrailleMonitor.init !== 'function') {
        throw new Error('BrailleMonitor component is not available');
      }
      scriptBrailleMonitorUi = window.BrailleMonitor.init({
        containerId: 'scriptBrailleMonitorComponent',
        showInfo: false
      });
      showScriptBrailleMonitorPlaceholder();
      return scriptBrailleMonitorUi;
    }

    async function syncBrailleMonitorFromRunner() {
      try {
        const monitor = await ensureBrailleMonitorReady();
        const scriptMonitor = await ensureScriptBrailleMonitorReady();
        const runner = getRunnerWindow();
        const app = runner?.BrailleBlocklyApp;
        if (!app || typeof app.getRuntimeSnapshot !== 'function') {
          renderMonitorSourceVisibility(false);
          if (lastBrailleSnapshot !== JSON.stringify({ placeholder: BRAILLE_MONITOR_PLACEHOLDER })) {
            showBrailleMonitorPlaceholder();
          }
          if (lastScriptBrailleSnapshot !== JSON.stringify({ placeholder: BRAILLE_MONITOR_PLACEHOLDER })) {
            showScriptBrailleMonitorPlaceholder();
          }
          return;
        }

        const runtime = app.getRuntimeSnapshot();
        const isWsConnected = Boolean(runtime?.wsConnected);
        renderMonitorSourceVisibility(isWsConnected);

        const brailleUnicode = String(runtime?.brailleUnicode || '');
        const sourceText = String(runtime?.text || '');
        const signature = JSON.stringify({
          brailleUnicode,
          sourceText,
          cellCaret: runtime?.cellCaret ?? null,
          textCaret: runtime?.textCaret ?? null,
          caretVisible: runtime?.caretVisible ?? true
        });

        if (signature !== lastBrailleSnapshot) {
          lastBrailleSnapshot = signature;
          if (!brailleUnicode && !sourceText) {
            showBrailleMonitorPlaceholder();
          } else {
            monitor.setBrailleUnicode(brailleUnicode, sourceText, {
              caretPosition: Number.isInteger(runtime?.cellCaret) ? runtime.cellCaret : undefined,
              textCaretPosition: Number.isInteger(runtime?.textCaret) ? runtime.textCaret : undefined,
              caretVisible: typeof runtime?.caretVisible === 'boolean' ? runtime.caretVisible : true
            });
          }
        }

        const scriptSignature = JSON.stringify({
          sourceText,
          textCaret: runtime?.textCaret ?? null
        });

        if (scriptSignature !== lastScriptBrailleSnapshot) {
          lastScriptBrailleSnapshot = scriptSignature;
          if (!sourceText) {
            showScriptBrailleMonitorPlaceholder();
          } else {
            scriptMonitor.setText(sourceText);
            if (typeof scriptMonitor.setCaretPosition === 'function') {
              scriptMonitor.setCaretPosition(Number.isInteger(runtime?.textCaret) ? runtime.textCaret : null);
            }
          }
        }
      } catch (err) {
        renderMonitorSourceVisibility(false);
        if (brailleMonitorUi && typeof brailleMonitorUi.setText === 'function') {
          brailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
        }
        if (scriptBrailleMonitorUi && typeof scriptBrailleMonitorUi.setText === 'function') {
          scriptBrailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
        }
      }
    }

    function startBrailleMonitorSync() {
      if (brailleMonitorSyncTimer) return;
      brailleMonitorSyncTimer = window.setInterval(() => {
        syncBrailleMonitorFromRunner();
      }, 250);
      syncBrailleMonitorFromRunner();
    }

    function buildStepMeta(stepConfig, stepIndex) {
      const method = shared.getDraftMethodMeta(state);
      const basisRecord = basisItems[Number(state.basisIndex ?? -1)] || state.basisRecord || null;
      return {
        id: stepConfig.id,
        stepIndex,
        lessonId: lessonIdInput.value.trim(),
        lessonTitle: lessonTitleInput.value.trim(),
        lessonNumber: Number(state.lessonNumber || 1),
        methodId: method.id,
        methodTitle: method.title,
        basisIndex: Number(state.basisIndex ?? -1),
        basisWord: state.basisWord || '',
        basisRecord
      };
    }

    function workspaceStateContainsBlockType(state, targetType) {
      const wanted = String(targetType || '').trim();
      if (!wanted || !state || typeof state !== 'object') return false;
      const stack = [state];
      while (stack.length) {
        const current = stack.pop();
        if (!current || typeof current !== 'object') continue;
        if (String(current.type || '').trim() === wanted) {
          return true;
        }
        if (Array.isArray(current)) {
          for (const item of current) stack.push(item);
          continue;
        }
        for (const value of Object.values(current)) {
          if (value && typeof value === 'object') {
            stack.push(value);
          }
        }
      }
      return false;
    }

    async function waitForCompletion(app, timeoutMs = 30000, options = {}) {
      const start = Date.now();
      let lastRuntime = null;
      const requireExplicitCompletion = Boolean(options?.requireExplicitCompletion);
      while (Date.now() - start < timeoutMs) {
        const completion = app.getStepCompletion();
        if (completion) return completion;
        const runtime = app.getRuntimeSnapshot();
        lastRuntime = runtime;
        if (
          runtime?.programEndedCompletedGeneration === runtime?.programEndedGeneration &&
          runtime?.programEndedGeneration >= 0 &&
          runtime?.isActive === false
        ) {
          if (requireExplicitCompletion) {
            await new Promise((resolve) => setTimeout(resolve, 100));
            continue;
          }
          return null;
        }
        await new Promise((resolve) => setTimeout(resolve, 100));
      }
      appendStatus('waitForCompletion: timeout.', {
        timeoutMs,
        runtime: lastRuntime
      });
      throw new Error('Step timed out');
    }

    async function runStepConfig(stepConfig, stepIndex) {
      const method = shared.getDraftMethodMeta(state);
      const scriptData = await loadScriptData(stepConfig.id);
      showDebugLog();
      appendStatus('Step run gestart.', {
        scriptId: stepConfig.id,
        runnerUrl: RUNNER_URL,
        runnerState: getRunnerDebugState()
      });
      const app = await waitForRunnerReady();
      const requireExplicitCompletion = workspaceStateContainsBlockType(scriptData.blockly, 'lesson_complete_step')
        || workspaceStateContainsBlockType(scriptData.blockly, 'lesson_complete_lesson');
      const result = await app.runWorkspaceStateHeadless({
        state: scriptData.blockly,
        sourceName: scriptData.title || stepConfig.id,
        lessonData: basisItems,
        lessonMethod: method,
        index: Number(state.basisIndex ?? 0),
        stepInputs: shared.normalizeInputs(stepConfig.inputs || {}),
        stepMeta: buildStepMeta(stepConfig, stepIndex),
        onLog: (line) => {
          appendStatus('Blockly log.', line);
        },
        lockInjectedRecord: true
      });
      const completion = result?.stepCompletion || await waitForCompletion(app, 30000, { requireExplicitCompletion });
      return { result, completion, scriptId: stepConfig.id };
    }

    async function stopCurrentStep(options = {}) {
      const alsoStopLesson = Boolean(options?.alsoStopLesson);
      if (currentRunningStepIndex < 0 || isStoppingCurrentStep) {
        return;
      }
      if (activeStopPromise) {
        await activeStopPromise;
        return;
      }
      const stoppedStepIndex = currentRunningStepIndex;
      const shouldStopLesson = isLessonRunning || alsoStopLesson;
      isStoppingCurrentStep = true;
      if (shouldStopLesson) {
        isStoppingLesson = true;
      }
      appendStatus('Stop requested for current step.', {
        stepIndex: stoppedStepIndex,
        stopLesson: shouldStopLesson
      });
      renderStepRunButtons();
      activeStopPromise = (async () => {
        const app = await waitForRunnerReady(5000);
        if (app && typeof app.stopProgram === 'function') {
          await app.stopProgram();
        }
        appendStatus('Step stop bevestigd.', {
          stepIndex: stoppedStepIndex
        });
      })().catch((err) => {
        appendStatus('Stop step failed.', {
          error: err.message || String(err)
        });
        throw err;
      }).finally(() => {
        currentRunningStepIndex = -1;
        isStoppingCurrentStep = false;
        if (shouldStopLesson) {
          isLessonRunning = false;
          isStoppingLesson = false;
        }
        activeStopPromise = null;
        renderStepRunButtons();
      });
      await activeStopPromise;
    }

    async function runSingleStep(index) {
      if (activeStopPromise) {
        await activeStopPromise;
      }
      if (currentRunningStepIndex === index) {
        await stopCurrentStep();
        return;
      }
      if (isLessonRunning) {
        appendStatus('Lesson draait al. Gebruik lesson Stop of de huidige step Stop.', {
          currentStepIndex: currentRunningStepIndex,
          requestedStepIndex: index
        });
        return;
      }
      if (currentRunningStepIndex >= 0) {
        appendStatus('Er draait al een step.', {
          currentStepIndex: currentRunningStepIndex,
          requestedStepIndex: index
        });
        return;
      }
      syncStepConfigsFromTable();
      const stepConfig = stepConfigs[index];
      if (!stepConfig) throw new Error('Step not found');
      currentRunningStepIndex = index;
      isStoppingCurrentStep = false;
      renderStepRunButtons();
      try {
        const outcome = await runStepConfig(stepConfig, index);
        const wasStopped = Boolean(
          outcome?.completion?.status === 'stopped' ||
          outcome?.result?.runtime?.stopped
        );
        appendStatus(wasStopped ? `Step gestopt: ${stepConfig.id}` : `Step uitgevoerd: ${stepConfig.id}`, outcome);
      } finally {
        currentRunningStepIndex = -1;
        isStoppingCurrentStep = false;
        renderStepRunButtons();
      }
    }

    async function runLesson() {
      if (activeStopPromise) {
        await activeStopPromise;
      }
      if (isLessonRunning) {
        appendStatus('Stop requested for current lesson.');
        isStoppingLesson = true;
        renderStepRunButtons();
        if (currentRunningStepIndex >= 0) {
          await stopCurrentStep({ alsoStopLesson: true });
        } else {
          isLessonRunning = false;
          isStoppingLesson = false;
          renderStepRunButtons();
        }
        appendStatus('Lesson stop bevestigd.');
        return;
      }
      if (currentRunningStepIndex >= 0) {
        appendStatus('Er draait al een step.', {
          currentStepIndex: currentRunningStepIndex
        });
        return;
      }
      syncStepConfigsFromTable();
      if (!stepConfigs.length) {
        setStatus('Deze lesson heeft nog geen steps.');
        return;
      }
      isLessonRunning = true;
      isStoppingLesson = false;
      renderStepRunButtons();
      const results = [];
      try {
        for (let index = 0; index < stepConfigs.length; index++) {
          if (isStoppingLesson) {
            appendStatus(`Lesson gestopt vóór step ${index + 1}`, results);
            return;
          }
          currentRunningStepIndex = index;
          renderStepRunButtons();
          const outcome = await runStepConfig(stepConfigs[index], index);
          results.push({
            scriptId: stepConfigs[index].id,
            completion: outcome.completion,
            lessonCompletion: outcome?.result?.lessonCompletion || null,
            stopped: Boolean(outcome?.completion?.status === 'stopped' || outcome?.result?.runtime?.stopped)
          });
          currentRunningStepIndex = -1;
          renderStepRunButtons();
          if (outcome?.result?.lessonCompletion) {
            appendStatus(`Lesson completed bij step ${index + 1}`, {
              lessonCompletion: outcome.result.lessonCompletion,
              results
            });
            return;
          }
          if (outcome.completion && outcome.completion.status !== 'completed') {
            appendStatus(`Lesson gestopt bij step ${index + 1}`, results);
            return;
          }
          if (outcome?.result?.runtime?.stopped || isStoppingLesson) {
            appendStatus(`Lesson gestopt bij step ${index + 1}`, results);
            return;
          }
        }
        appendStatus('Lesson uitgevoerd.', results);
      } finally {
        currentRunningStepIndex = -1;
        isLessonRunning = false;
        isStoppingLesson = false;
        renderStepRunButtons();
      }
    }

    async function saveCurrentLesson() {
      showDebugLog();
      syncStepConfigsFromTable();
      stepConfigs = buildStepConfigsFromTable();
      state = shared.updateState({ steps: stepConfigs });
      const method = shared.getDraftMethodMeta(state);
      const basisIndex = Number(state.basisIndex ?? -1);
      const basisItem = basisItems[basisIndex] || state.basisRecord || null;
      if (!method.id) {
        setStatus('Sla eerst de methode op in stap 1.');
        return;
      }
      if (basisIndex < 0 || !basisItem) {
        setStatus('Kies eerst een basisrecord in stap 2.');
        return;
      }
      const payload = {
        id: lessonIdInput.value.trim(),
        title: lessonTitleInput.value.trim(),
        description: String(lessonDescriptionInput.value.trim() || '').trim(),
        methodId: method.id,
        method: method,
        methodTitle: method.title,
        methodDataSource: method.dataSource,
        basisIndex,
        basisWord: state.basisWord || shared.getBasisWord(basisItem, basisIndex),
        lessonNumber: Number(state.lessonNumber || 1),
        basisRecord: basisItem,
        steps: serializeStepConfigs(stepConfigs),
        overwrite: true
      };
      appendStatus('Repeat DOM snapshot.', getRepeatDomSnapshot());
      appendStatus('Lesson save gestart.', payload);
      let result;
      try {
        result = await shared.saveLesson(payload);
      } catch (err) {
        appendStatus('Lesson save error.', {
          message: err?.message || String(err),
          payload
        });
        throw err;
      }
      state = shared.updateState({
        lessonId: payload.id,
        lessonTitle: payload.title,
        lessonMetaTitle: String(payload.title || '').trim(),
        lessonDescription: String(payload.description || '').trim(),
        lessonWord: payload.basisWord,
        steps: stepConfigs
      });
      appendStatus('Lesson save response.', result);
      appendStatus(`Lesson saved: ${payload.id}`, {
        payload,
        result
      });
    }

    async function init() {
      try {
        appendStatus('Initial preload started.');
        state = shared.loadState();
        const method = shared.getDraftMethodMeta(state);
        const runnerWarmupPromise = waitForRunnerReady(15000).catch((err) => {
          appendStatus('Runner warmup failed during init.', {
            error: err.message || String(err),
            runnerState: getRunnerDebugState()
          });
          return null;
        });
        basisItems = await shared.loadBasisData(method.dataSource || shared.DEFAULT_BASIS_DATA_URL);
        scriptsCache = await shared.listScripts();
        renderScriptsSelect(scriptsCache);
        if (scriptsSummary) {
          scriptsSummary.textContent = `${scriptsCache.length} online script(s) beschikbaar.`;
        }

        const basisIndex = Number(state.basisIndex ?? -1);
        const basisItem = basisIndex >= 0 ? basisItems[basisIndex] : null;
        if (!state.lessonId && method.id && basisItem) {
          const lessonNumber = Number(state.lessonNumber || 1);
          state = shared.updateState({
            lessonId: shared.buildLessonIdFromBasis(method.id, basisIndex, basisItem, lessonNumber),
            lessonTitle: shared.buildLessonTitleFromBasis(basisItem, lessonNumber, basisIndex),
            lessonWord: shared.getBasisWord(basisItem, basisIndex),
            steps: []
          });
        }

        if (state.lessonId) {
          try {
            const loadedLesson = await shared.loadLesson(state.lessonId);
            state = shared.updateState({
              lessonId: loadedLesson.id || state.lessonId,
              lessonTitle: loadedLesson.title || state.lessonTitle || '',
              lessonMetaTitle: String(loadedLesson?.title || state.lessonMetaTitle || '').trim(),
              lessonDescription: String(loadedLesson?.description || state.lessonDescription || '').trim(),
              lessonNumber: loadedLesson.lessonNumber || state.lessonNumber || 1,
              lessonWord: loadedLesson.basisWord || state.lessonWord || state.basisWord || '',
              steps: shared.normalizeStepConfigs(loadedLesson.steps || [])
            });
          } catch (err) {
            appendStatus('Lesson load fallback gebruikt.', {
              lessonId: state.lessonId,
              error: err.message || String(err)
            });
          }
        }

        lessonIdInput.value = state.lessonId || '';
        lessonTitleInput.value = state.lessonMetaTitle || state.lessonTitle || (state.lessonWord ? `les - ${state.lessonWord}` : '');
        lessonWordInput.value = state.lessonWord || state.basisWord || '';
        lessonDescriptionInput.value = state.lessonDescription || '';
        stepConfigs = serializeStepConfigs(shared.normalizeStepConfigs(state.steps || []));
        hydrateStepConfigsWithScriptMetadata();
        state = shared.updateState({ steps: stepConfigs });
        const preloadSummary = await preloadReferencedScriptData(stepConfigs);
        await runnerWarmupPromise;
        renderLessonSummary();
        renderStepsTable();
        renderDebugLogVisibility();
        renderAuthenticationState();
        renderMonitorSourceVisibility(false);
        simThumbLeftBtn?.addEventListener('click', async () => {
          try {
            appendStatus('Left thumb clicked.', {
              snapshot: getRunnerInputDebugSnapshot()
            });
            await dispatchRunnerInput({ type: 'thumbKey', key: 'left' });
          } catch (err) {
            appendStatus('Left thumb failed.', { error: err.message || String(err) });
          }
        });
        simThumbRightBtn?.addEventListener('click', async () => {
          try {
            appendStatus('Right thumb clicked.', {
              snapshot: getRunnerInputDebugSnapshot()
            });
            await dispatchRunnerInput({ type: 'thumbKey', key: 'right' });
          } catch (err) {
            appendStatus('Right thumb failed.', { error: err.message || String(err) });
          }
        });
        simCursor5Btn?.addEventListener('click', async () => {
          try {
            appendStatus('Left middle thumb clicked.', {
              snapshot: getRunnerInputDebugSnapshot()
            });
            await dispatchRunnerInput({ type: 'thumbKey', key: 'left-middle' });
          } catch (err) {
            appendStatus('Left middle thumb failed.', { error: err.message || String(err) });
          }
        });
        simChord1Btn?.addEventListener('click', async () => {
          try {
            appendStatus('Right middle thumb clicked.', {
              snapshot: getRunnerInputDebugSnapshot()
            });
            await dispatchRunnerInput({ type: 'thumbKey', key: 'right-middle' });
          } catch (err) {
            appendStatus('Right middle thumb failed.', { error: err.message || String(err) });
          }
        });
        startBrailleMonitorSync();
        setStatus('Ready.');
        appendStatus('Initial preload completed.', {
          scriptsLoaded: scriptsCache.length,
          preloadedScripts: preloadSummary.loaded,
          preloadFailures: preloadSummary.failed,
          runnerReady: getRunnerDebugState().ready
        });
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
      }
    }

    lessonRunnerFrame.addEventListener('load', () => {
      appendStatus('Blockly runner iframe geladen.', {
        runnerUrl: RUNNER_URL,
        runnerState: getRunnerDebugState()
      });
      startBrailleMonitorSync();
    });

    document.getElementById('refreshScriptsBtn').addEventListener('click', async () => {
      try {
        scriptsCache = await shared.listScripts();
        renderScriptsSelect(scriptsCache);
        if (scriptsSummary) {
          scriptsSummary.textContent = `${scriptsCache.length} online script(s) beschikbaar.`;
        }
        hydrateStepConfigsWithScriptMetadata();
        state = shared.updateState({ steps: stepConfigs });
        const preloadSummary = await preloadReferencedScriptData(stepConfigs);
        renderStepsTable();
        setStatus(`Loaded ${scriptsCache.length} script(s).`);
        appendStatus('Scripts refreshed.', preloadSummary);
      } catch (err) {
        setStatus(`Scripts load error: ${err.message}`);
      }
    });

    scriptsSelect.addEventListener('change', () => {
      renderAddStepAvailability();
    });

    addStepBtn.addEventListener('click', () => {
      showDebugLog();
      appendStatus('Add step clicked.', {
        selectedScriptId: String(scriptsSelect.value || '').trim(),
        scriptsLoaded: scriptsCache.length
      });
      if (!scriptsSelect.value) {
        setStatus('Kies eerst een script.');
        return;
      }
      const selectedScript = getScriptItemById(scriptsSelect.value);
      appendStatus('Before add steps length.', { length: stepConfigs.length });
      stepConfigs.push({
        id: scriptsSelect.value,
        title: String(selectedScript?.title || '').trim(),
        inputs: { text: '', word: '', letters: [], repeat: 1 }
      });
      stepConfigs = serializeStepConfigs(stepConfigs);
      state = shared.updateState({ steps: stepConfigs });
      appendStatus('After add steps length.', {
        length: stepConfigs.length,
        lastAdded: stepConfigs[stepConfigs.length - 1] || null
      });
      renderStepsTable();
      setStatus(`Script toegevoegd: ${scriptsSelect.value}`);
    });

    document.getElementById('saveLessonBtn').addEventListener('click', async () => {
      if (!shared?.getAuthToken?.()) {
        showDebugLog();
        setStatus('Authenticate first to save this lesson.');
        renderAuthenticationState();
        return;
      }
      try {
        await saveCurrentLesson();
      } catch (err) {
        showDebugLog();
        setStatus(`Save error: ${err.message}`);
      }
    });

    document.getElementById('deleteLessonBtn').addEventListener('click', async () => {
      if (!shared?.getAuthToken?.()) {
        showDebugLog();
        setStatus('Authenticate first to delete this lesson.');
        renderAuthenticationState();
        return;
      }
      if (!lessonIdInput.value.trim()) {
        setStatus('Geen lesson geselecteerd.');
        return;
      }
      try {
        const result = await shared.deleteLesson(lessonIdInput.value.trim());
        state = shared.updateState({ lessonId: '', lessonTitle: '', lessonWord: '', steps: [] });
        lessonIdInput.value = '';
        lessonTitleInput.value = '';
        lessonWordInput.value = '';
        lessonDescriptionInput.value = '';
        stepConfigs = [];
        renderLessonSummary();
        renderStepsTable();
        setStatus('Lesson deleted.', result);
      } catch (err) {
        setStatus(`Delete error: ${err.message}`);
      }
    });

    document.getElementById('runLessonBtn').addEventListener('click', async () => {
      try {
        await runLesson();
      } catch (err) {
        setStatus(`Run error: ${err.message}`);
      }
    });

    authBtn?.addEventListener('click', async () => {
      isDebugLogVisible = true;
      renderDebugLogVisibility();
      setStatus('Authentication starten...');
      try {
        if (!shared || typeof shared.openAuthenticationPopup !== 'function') {
          throw new Error('lessonbuilder-shared.js is not up to date or did not load');
        }
        await shared.openAuthenticationPopup();
        renderAuthenticationState();
        setStatus('Authentication completed.');
      } catch (err) {
        renderAuthenticationState();
        setStatus(`Authentication error: ${err.message}`);
      }
    });

    window.addEventListener('storage', (event) => {
      if (event.key === 'braillestudioAuthToken' || event.key === 'elevenlabsAuthToken') {
        renderAuthenticationState();
      }
    });

    toggleDebugLogBtn.addEventListener('click', () => {
      isDebugLogVisible = !isDebugLogVisible;
      renderDebugLogVisibility();
    });

    clearDebugLogBtn.addEventListener('click', () => {
      statusBox.textContent = '';
    });

    copyDebugLogBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(statusBox.textContent || '');
        appendStatus('Debug log copied to clipboard.');
      } catch (err) {
        setStatus(`Copy log error: ${err.message || String(err)}`);
      }
    });

    lessonTitleInput.addEventListener('input', () => {
      state = shared.updateState({
        lessonTitle: lessonTitleInput.value.trim(),
        lessonMetaTitle: lessonTitleInput.value.trim()
      });
      renderLessonSummary();
    });

    lessonDescriptionInput.addEventListener('input', () => {
      state = shared.updateState({
        lessonDescription: lessonDescriptionInput.value.trim()
      });
      renderLessonSummary();
    });

    window.addEventListener('load', () => {
      if (authRedirected) return;
      init();
    });
  </script>
</body>
</html>
