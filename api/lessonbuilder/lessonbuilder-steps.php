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
  <script src="./lessonbuilder-shared.js"></script>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="max-w-7xl mx-auto p-6 space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-blue-700">Stap 3 van 3</div>
        <h1 class="text-3xl font-bold">Lesson steps bouwen</h1>
      </div>
      <div class="flex gap-2">
        <a class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold" href="https://www.tastenbraille.com/braillestudio/lessonbuilder/lessonbuilder-records.php">Vorige stap</a>
      </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.95fr)]">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="text-lg font-bold">Lesson</div>
        <div id="lessonSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
        <div class="grid gap-3 md:grid-cols-2">
          <input id="lessonIdInput" type="hidden">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="lessonTitleInput">Lesson title</label>
            <input id="lessonTitleInput" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-slate-50" type="text" readonly>
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="lessonWordInput">Word</label>
            <input id="lessonWordInput" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-slate-50" type="text" readonly>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button id="saveLessonBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
          <button id="deleteLessonBtn" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
          <button id="runLessonBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Run</button>
        </div>

        <div>
          <div class="mb-2 text-sm font-semibold text-slate-700">Steps</div>
          <div class="rounded-xl border border-slate-200 overflow-hidden">
            <div class="grid w-full grid-cols-[minmax(0,2.8fr)_minmax(0,1.8fr)_minmax(0,1fr)_minmax(0,1fr)_64px_64px_64px_80px] gap-2 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
              <div class="min-w-0 pr-2 text-left">Step</div>
              <div class="min-w-0 text-left">Text</div>
              <div class="min-w-0 text-left">Word</div>
              <div class="min-w-0 text-left">Letters</div>
              <div class="text-center">Run</div>
              <div class="text-center">Up</div>
              <div class="text-center">Down</div>
              <div class="text-center">Remove</div>
            </div>
            <div id="stepsTableBody" class="divide-y divide-slate-200"></div>
          </div>
        </div>
      </section>

      <section class="min-h-[360px] rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="text-lg font-bold">Bibliotheek</div>
        <div id="scriptsSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Nog geen scripts geladen.</div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1" for="scriptsSelect">Script list</label>
          <select id="scriptsSelect" class="h-10 w-full rounded-xl border border-slate-300 px-3 py-2"></select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button id="refreshScriptsBtn" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Refresh</button>
          <button id="addStepBtn" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Add</button>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2 lg:col-span-2">
        <div class="flex items-center justify-between gap-3">
          <div class="text-lg font-bold">Debug log</div>
          <button id="toggleDebugLogBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Unhide</button>
        </div>
        <pre id="statusBox" class="hidden min-h-[180px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
      </section>
    </div>
  </div>

  <iframe id="lessonRunnerFrame" title="Lesson runner" hidden></iframe>

  <script>
    const shared = window.LessonBuilderShared;
    const lessonIdInput = document.getElementById('lessonIdInput');
    const lessonTitleInput = document.getElementById('lessonTitleInput');
    const lessonWordInput = document.getElementById('lessonWordInput');
    const lessonSummary = document.getElementById('lessonSummary');
    const stepsTableBody = document.getElementById('stepsTableBody');
    const scriptsSelect = document.getElementById('scriptsSelect');
    const scriptsSummary = document.getElementById('scriptsSummary');
    const statusBox = document.getElementById('statusBox');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');

    let state = shared.loadState();
    let scriptsCache = [];
    let stepConfigs = [];
    let basisItems = [];
    let isDebugLogVisible = false;

    function resolveRunnerUrl() {
      const host = String(window.location.hostname || '').toLowerCase();
      if (host === '127.0.0.1' || host === 'localhost') {
        return 'http://127.0.0.1:5500/blockly/index.html';
      }
      return 'https://www.tastenbraille.com/braillestudio/blockly/index.html';
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

    function renderDebugLogVisibility() {
      statusBox.classList.toggle('hidden', !isDebugLogVisible);
      toggleDebugLogBtn.textContent = isDebugLogVisible ? 'Hide' : 'Unhide';
    }

    function renderLessonSummary() {
      const method = shared.getDraftMethodMeta(state);
      lessonSummary.innerHTML = `
        <div><strong>Method:</strong> ${method.title || method.id || '-'}</div>
        <div><strong>Basisrecord:</strong> ${state.basisWord || '-'}</div>
        <div><strong>Lesson:</strong> ${lessonTitleInput.value || lessonIdInput.value || '-'}</div>
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
    }

    function updateStateStepConfigs() {
      state = shared.updateState({ stepConfigs });
      renderLessonSummary();
    }

    function renderStepsTable() {
      stepsTableBody.innerHTML = '';
      if (!stepConfigs.length) {
        stepsTableBody.innerHTML = '<div class="px-3 py-3 text-sm text-slate-500">No steps yet. Add a script from the list.</div>';
        return;
      }
      stepConfigs.forEach((cfg, index) => {
        const inputs = shared.normalizeInputs(cfg.inputs || {});
        const row = document.createElement('div');
        row.className = 'grid w-full grid-cols-[minmax(0,2.8fr)_minmax(0,1.8fr)_minmax(0,1fr)_minmax(0,1fr)_64px_64px_64px_80px] gap-2 items-start px-3 py-2';

        const script = document.createElement('div');
        script.className = 'min-w-0 pr-2 pt-1 text-sm text-slate-800 break-words leading-5';
        script.textContent = cfg.id;

        const text = document.createElement('input');
        text.className = 'block w-full min-w-0 rounded-lg border border-slate-300 px-2 py-1 text-sm';
        text.value = inputs.text;
        text.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...inputs, text: e.target.value, word: stepConfigs[index].inputs?.word || inputs.word, letters: shared.normalizeInputs(stepConfigs[index].inputs || {}).letters };
          updateStateStepConfigs();
        });

        const word = document.createElement('input');
        word.className = 'block w-full min-w-0 rounded-lg border border-slate-300 px-2 py-1 text-sm';
        word.value = inputs.word;
        word.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), word: e.target.value };
          updateStateStepConfigs();
        });

        const letters = document.createElement('input');
        letters.className = 'block w-full min-w-0 rounded-lg border border-slate-300 px-2 py-1 text-sm';
        letters.value = inputs.letters.join(', ');
        letters.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), letters: e.target.value.split(',').map((item) => item.trim()).filter(Boolean) };
          updateStateStepConfigs();
        });

        const run = document.createElement('button');
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

        row.append(script, text, word, letters, run, moveUp, moveDown, remove);
        stepsTableBody.appendChild(row);
      });
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

    async function waitForCompletion(app, timeoutMs = 30000) {
      const start = Date.now();
      while (Date.now() - start < timeoutMs) {
        const completion = app.getStepCompletion();
        if (completion) return completion;
        const runtime = app.getRuntimeSnapshot();
        if (runtime?.stopped) return null;
        await new Promise((resolve) => setTimeout(resolve, 100));
      }
      throw new Error('Step timed out');
    }

    async function runStepConfig(stepConfig, stepIndex) {
      const method = shared.getDraftMethodMeta(state);
      const scriptData = await shared.loadScript(stepConfig.id);
      appendStatus('Step run gestart.', {
        scriptId: stepConfig.id,
        runnerUrl: RUNNER_URL,
        runnerState: getRunnerDebugState()
      });
      const app = await waitForRunnerReady();
      const result = await app.runWorkspaceStateHeadless({
        state: scriptData.blockly,
        sourceName: scriptData.title || stepConfig.id,
        lessonData: basisItems,
        lessonMethod: method,
        index: Number(state.basisIndex ?? 0),
        stepInputs: shared.normalizeInputs(stepConfig.inputs || {}),
        stepMeta: buildStepMeta(stepConfig, stepIndex),
        lockInjectedRecord: true
      });
      const completion = result?.stepCompletion || await waitForCompletion(app);
      return { result, completion, scriptId: stepConfig.id };
    }

    async function runSingleStep(index) {
      const stepConfig = stepConfigs[index];
      if (!stepConfig) throw new Error('Step not found');
      const outcome = await runStepConfig(stepConfig, index);
      setStatus(`Step uitgevoerd: ${stepConfig.id}`, outcome);
    }

    async function runLesson() {
      if (!stepConfigs.length) {
        setStatus('Deze lesson heeft nog geen steps.');
        return;
      }
      const results = [];
      for (let index = 0; index < stepConfigs.length; index++) {
        const outcome = await runStepConfig(stepConfigs[index], index);
        results.push({
          scriptId: stepConfigs[index].id,
          completion: outcome.completion
        });
        if (outcome.completion && outcome.completion.status !== 'completed') {
          setStatus(`Lesson gestopt bij step ${index + 1}`, results);
          return;
        }
      }
      setStatus('Lesson uitgevoerd.', results);
    }

    async function saveCurrentLesson() {
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
        methodId: method.id,
        method: method,
        methodTitle: method.title,
        methodDataSource: method.dataSource,
        basisIndex,
        basisWord: state.basisWord || shared.getBasisWord(basisItem, basisIndex),
        lessonNumber: Number(state.lessonNumber || 1),
        basisRecord: basisItem,
        word: lessonWordInput.value.trim(),
        steps: stepConfigs.map((item) => item.id),
        stepConfigs: stepConfigs,
        meta: {
          method,
          basisIndex,
          basisWord: state.basisWord || shared.getBasisWord(basisItem, basisIndex),
          lessonNumber: Number(state.lessonNumber || 1),
          basisRecord: basisItem,
          stepConfigs
        },
        overwrite: true
      };
      const result = await shared.saveLesson(payload);
      state = shared.updateState({
        lessonId: payload.id,
        lessonTitle: payload.title,
        lessonWord: payload.word,
        stepConfigs
      });
      setStatus(`Lesson saved: ${payload.id}`, result);
    }

    async function init() {
      try {
        state = shared.loadState();
        const method = shared.getDraftMethodMeta(state);
        basisItems = await shared.loadBasisData(method.dataSource || shared.DEFAULT_BASIS_DATA_URL);
        scriptsCache = await shared.listScripts();
        renderScriptsSelect(scriptsCache);
        scriptsSummary.textContent = `${scriptsCache.length} online script(s) beschikbaar.`;

        const basisIndex = Number(state.basisIndex ?? -1);
        const basisItem = basisIndex >= 0 ? basisItems[basisIndex] : null;
        if (!state.lessonId && method.id && basisItem) {
          const lessonNumber = Number(state.lessonNumber || 1);
          state = shared.updateState({
            lessonId: shared.buildLessonIdFromBasis(method.id, basisIndex, basisItem, lessonNumber),
            lessonTitle: shared.buildLessonTitleFromBasis(basisItem, lessonNumber, basisIndex),
            lessonWord: shared.getBasisWord(basisItem, basisIndex),
            stepConfigs: []
          });
        }

        if (state.lessonId) {
          try {
            const loadedLesson = await shared.loadLesson(state.lessonId);
            state = shared.updateState({
              lessonId: loadedLesson.id || state.lessonId,
              lessonTitle: loadedLesson.title || state.lessonTitle || '',
              lessonNumber: loadedLesson.lessonNumber || state.lessonNumber || 1,
              lessonWord: loadedLesson.basisWord || loadedLesson.word || state.lessonWord || state.basisWord || '',
              stepConfigs: shared.normalizeStepConfigs(loadedLesson.stepConfigs || loadedLesson?.meta?.stepConfigs || [])
            });
          } catch (err) {
            appendStatus('Lesson load fallback gebruikt.', {
              lessonId: state.lessonId,
              error: err.message || String(err)
            });
          }
        }

        lessonIdInput.value = state.lessonId || '';
        lessonTitleInput.value = state.lessonWord ? `les - ${state.lessonWord}` : (state.lessonTitle || '');
        lessonWordInput.value = state.lessonWord || state.basisWord || '';
        stepConfigs = shared.normalizeStepConfigs(state.stepConfigs || []);
        renderLessonSummary();
        renderStepsTable();
        renderDebugLogVisibility();
        setStatus('Ready.');
      } catch (err) {
        setStatus(`Init error: ${err.message}`);
      }
    }

    lessonRunnerFrame.addEventListener('load', () => {
      appendStatus('Blockly runner iframe geladen.', {
        runnerUrl: RUNNER_URL,
        runnerState: getRunnerDebugState()
      });
    });

    document.getElementById('refreshScriptsBtn').addEventListener('click', async () => {
      try {
        scriptsCache = await shared.listScripts();
        renderScriptsSelect(scriptsCache);
        scriptsSummary.textContent = `${scriptsCache.length} online script(s) beschikbaar.`;
        setStatus(`Loaded ${scriptsCache.length} script(s).`);
      } catch (err) {
        setStatus(`Scripts load error: ${err.message}`);
      }
    });

    document.getElementById('addStepBtn').addEventListener('click', () => {
      if (!scriptsSelect.value) {
        setStatus('Kies eerst een script.');
        return;
      }
      stepConfigs.push({ id: scriptsSelect.value, inputs: { text: '', word: '', letters: [] } });
      state = shared.updateState({ stepConfigs });
      renderStepsTable();
      setStatus(`Script toegevoegd: ${scriptsSelect.value}`);
    });

    document.getElementById('saveLessonBtn').addEventListener('click', async () => {
      try {
        await saveCurrentLesson();
      } catch (err) {
        setStatus(`Save error: ${err.message}`);
      }
    });

    document.getElementById('deleteLessonBtn').addEventListener('click', async () => {
      if (!lessonIdInput.value.trim()) {
        setStatus('Geen lesson geselecteerd.');
        return;
      }
      try {
        const result = await shared.deleteLesson(lessonIdInput.value.trim());
        state = shared.updateState({ lessonId: '', lessonTitle: '', lessonWord: '', stepConfigs: [] });
        lessonIdInput.value = '';
        lessonTitleInput.value = '';
        lessonWordInput.value = '';
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

    toggleDebugLogBtn.addEventListener('click', () => {
      isDebugLogVisible = !isDebugLogVisible;
      renderDebugLogVisibility();
    });

    window.addEventListener('load', init);
  </script>
</body>
</html>
