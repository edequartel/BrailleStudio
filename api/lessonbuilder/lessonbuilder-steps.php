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

    <div class="grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
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
          <button id="saveLessonBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save lesson</button>
          <button id="deleteLessonBtn" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Delete lesson</button>
          <button id="runLessonBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Run lesson</button>
        </div>

        <div>
          <div class="mb-2 text-sm font-semibold text-slate-700">Steps</div>
          <div class="rounded-xl border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-[1.2fr_1fr_1fr_1.2fr_auto_auto] gap-2 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
              <div>Script</div>
              <div>Text</div>
              <div>Word</div>
              <div>Letters</div>
              <div>Run</div>
              <div></div>
            </div>
            <div id="stepsTableBody" class="divide-y divide-slate-200"></div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="text-lg font-bold">Scriptbibliotheek</div>
        <div id="scriptsSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Nog geen scripts geladen.</div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1" for="scriptsSelect">Script list</label>
          <select id="scriptsSelect" class="h-10 w-full rounded-xl border border-slate-300 px-3 py-2"></select>
        </div>
        <div class="flex flex-wrap gap-2">
          <button id="refreshScriptsBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Refresh scripts</button>
          <button id="addStepBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Add script as step</button>
        </div>
        <div>
          <div class="mb-2 text-sm font-semibold text-slate-700">Preview</div>
          <ul id="lessonStepsPreview" class="list-disc pl-5 text-sm text-slate-700"></ul>
        </div>
      </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
      <div class="text-lg font-bold">Debug log</div>
      <pre id="statusBox" class="min-h-[180px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
    </section>
  </div>

  <iframe id="lessonRunnerFrame" src="https://www.tastenbraille.com/braillestudio/blockly/index.html" title="Lesson runner" hidden></iframe>

  <script>
    const shared = window.LessonBuilderShared;
    const lessonIdInput = document.getElementById('lessonIdInput');
    const lessonTitleInput = document.getElementById('lessonTitleInput');
    const lessonWordInput = document.getElementById('lessonWordInput');
    const lessonSummary = document.getElementById('lessonSummary');
    const stepsTableBody = document.getElementById('stepsTableBody');
    const scriptsSelect = document.getElementById('scriptsSelect');
    const scriptsSummary = document.getElementById('scriptsSummary');
    const lessonStepsPreview = document.getElementById('lessonStepsPreview');
    const statusBox = document.getElementById('statusBox');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');

    let state = shared.loadState();
    let scriptsCache = [];
    let stepConfigs = [];
    let basisItems = [];
    const RUNNER_URL = 'https://www.tastenbraille.com/braillestudio/blockly/index.html';

    function setStatus(message, data = null) {
      statusBox.textContent = data ? `${message}\n\n${JSON.stringify(data, null, 2)}` : message;
    }

    function appendStatus(message, data = null) {
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const block = data
        ? `[${timestamp}] ${message}\n${JSON.stringify(data, null, 2)}`
        : `[${timestamp}] ${message}`;
      statusBox.textContent = statusBox.textContent ? `${statusBox.textContent}\n\n${block}` : block;
      statusBox.scrollTop = statusBox.scrollHeight;
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

    function renderPreview() {
      lessonStepsPreview.innerHTML = '';
      stepConfigs.forEach((cfg) => {
        const li = document.createElement('li');
        const inputs = shared.normalizeInputs(cfg.inputs || {});
        const parts = [];
        if (inputs.text) parts.push(`text: ${inputs.text}`);
        if (inputs.word) parts.push(`word: ${inputs.word}`);
        if (inputs.letters.length) parts.push(`letters: ${inputs.letters.join(', ')}`);
        li.textContent = parts.length ? `${cfg.id} (${parts.join(' | ')})` : cfg.id;
        lessonStepsPreview.appendChild(li);
      });
    }

    function updateStateStepConfigs() {
      state = shared.updateState({ stepConfigs });
      renderPreview();
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
        row.className = 'grid grid-cols-[1.2fr_1fr_1fr_1.2fr_auto_auto] gap-2 items-center px-3 py-2';

        const script = document.createElement('div');
        script.className = 'text-sm text-slate-800';
        script.textContent = cfg.id;

        const text = document.createElement('input');
        text.className = 'rounded-lg border border-slate-300 px-2 py-1 text-sm';
        text.value = inputs.text;
        text.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...inputs, text: e.target.value, word: stepConfigs[index].inputs?.word || inputs.word, letters: shared.normalizeInputs(stepConfigs[index].inputs || {}).letters };
          updateStateStepConfigs();
        });

        const word = document.createElement('input');
        word.className = 'rounded-lg border border-slate-300 px-2 py-1 text-sm';
        word.value = inputs.word;
        word.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), word: e.target.value };
          updateStateStepConfigs();
        });

        const letters = document.createElement('input');
        letters.className = 'rounded-lg border border-slate-300 px-2 py-1 text-sm';
        letters.value = inputs.letters.join(', ');
        letters.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), letters: e.target.value.split(',').map((item) => item.trim()).filter(Boolean) };
          updateStateStepConfigs();
        });

        const run = document.createElement('button');
        run.className = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
        run.textContent = 'Run';
        run.addEventListener('click', async () => {
          try {
            await runSingleStep(index);
          } catch (err) {
            setStatus(`Step run error: ${err.message}`);
          }
        });

        const remove = document.createElement('button');
        remove.className = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => {
          stepConfigs.splice(index, 1);
          updateStateStepConfigs();
          renderStepsTable();
        });

        row.append(script, text, word, letters, run, remove);
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
          href: runner.location?.href || ''
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
            lessonWord: shared.getBasisWord(basisItem, basisIndex)
          });
        }

        lessonIdInput.value = state.lessonId || '';
        lessonTitleInput.value = state.lessonTitle || '';
        lessonWordInput.value = state.lessonWord || state.basisWord || '';
        stepConfigs = shared.normalizeStepConfigs(state.stepConfigs || []);
        renderLessonSummary();
        renderPreview();
        renderStepsTable();
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
      renderPreview();
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
        renderPreview();
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

    window.addEventListener('load', init);
  </script>
</body>
</html>
