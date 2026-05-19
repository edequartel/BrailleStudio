<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?lessonbuilder$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$lessonBuilderBase = $scriptDir;
$sessionApiBase = $appBase . '/session-api';

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$htmlUrl = static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$jsValue = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lesson Builder - Steps</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'components/braille-monitor/braillemonitor.css')) ?>">
  <script src="<?= $htmlUrl($urlFor($lessonBuilderBase, 'lessonbuilder-shared.js?v=20260407-2')) ?>"></script>
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
            <button id="authBtn" class="btn btn-outline-primary" type="button">
              <i class="ti ti-login me-2" aria-hidden="true"></i>
              Authentication
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <div class="page-pretitle">Stap 3 van 3</div>
              <h1 class="page-title">Lesson steps bouwen</h1>
            </div>
            <div class="col-auto">
              <a class="btn btn-outline-secondary" href="<?= $htmlUrl($urlFor($lessonBuilderBase, 'lessonbuilder-records.php')) ?>">
                <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>
                Vorige stap
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <div class="card mb-3">
            <div class="card-header border-0">
              <div>
                <h2 class="card-title">Braille monitor</h2>
                <div class="card-subtitle">Monitor en thumb-input voor de actieve lesson.</div>
              </div>
            </div>
            <div class="card-body">
              <div id="brailleMonitorRow">
                <div id="brailleMonitorComponent"></div>
              </div>
              <div id="scriptBrailleMonitorRow">
                <div id="scriptBrailleMonitorComponent"></div>
              </div>
              <div class="btn-list mt-3">
                <button id="simThumbLeftBtn" class="btn btn-outline-secondary" type="button">Left thumb</button>
                <button id="simCursor5Btn" class="btn btn-outline-secondary" type="button">Left middle thumb</button>
                <button id="simChord1Btn" class="btn btn-outline-secondary" type="button">Right middle thumb</button>
                <button id="simThumbRightBtn" class="btn btn-outline-secondary" type="button">Right thumb</button>
              </div>
            </div>
          </div>

          <div class="row row-cards">
            <div class="col-12 col-xl-6">
              <div class="card h-100">
                <div class="card-header border-0">
                  <div>
                    <h2 class="card-title">Les informatie</h2>
                    <div class="card-subtitle">De lesson zelf: titel, woord, beschrijving en run/save acties.</div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <input id="lessonIdInput" type="hidden">
                    <div class="col-12">
                      <label class="form-label" for="lessonTitleInput">Lesson title</label>
                      <input id="lessonTitleInput" class="form-control" type="text">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="lessonWordInput">Word</label>
                      <input id="lessonWordInput" class="form-control" type="text" readonly>
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="lessonSoundsInput">Sounds</label>
                      <input id="lessonSoundsInput" class="form-control" type="text" readonly>
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="lessonNewSoundsInput">newSounds</label>
                      <input id="lessonNewSoundsInput" class="form-control" type="text" readonly>
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="lessonKnownSoundsInput">knownSounds</label>
                      <textarea id="lessonKnownSoundsInput" class="form-control" rows="2" readonly aria-readonly="true"></textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="lessonDescriptionInput">Description</label>
                      <textarea id="lessonDescriptionInput" class="form-control" rows="4" placeholder="Lesson description" readonly aria-readonly="true"></textarea>
                    </div>
                  </div>
                  <div class="btn-list mt-3">
                    <button id="saveLessonBtn" class="btn btn-primary" type="button">
                      <i class="ti ti-device-floppy me-2" aria-hidden="true"></i>
                      Save
                    </button>
                    <button id="deleteLessonBtn" class="btn btn-danger" type="button">
                      <i class="ti ti-trash me-2" aria-hidden="true"></i>
                      Delete
                    </button>
                    <button id="runLessonBtn" class="btn btn-outline-secondary" type="button">
                      <i class="ti ti-player-play me-2" aria-hidden="true"></i>
                      Run
                    </button>
                  </div>
                  <div id="lessonActionHint" class="form-hint mt-2">Use Save to store the current steps, Delete to remove the lesson, and Run to test the current steps.</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-6">
              <div class="card h-100">
                <div class="card-header border-0">
                  <div>
                    <h2 class="card-title">Blockly informatie</h2>
                  </div>
                </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label" for="scriptsSelect">Beschikbare Blockly scripts</label>
                    <div class="row g-2">
                      <div class="col">
                        <select id="scriptsSelect" class="form-select"></select>
                      </div>
                      <div class="col-auto">
                        <button id="refreshScriptsBtn" class="btn btn-outline-secondary" type="button">Refresh</button>
                      </div>
                      <div class="col-auto">
                        <button id="addStepBtn" class="btn btn-primary" type="button">Add</button>
                      </div>
                    </div>
                    <div id="scriptsSummary" class="form-hint"></div>
                    <div id="scriptMetaPreview" class="list-group list-group-flush mt-2">
                      <div class="list-group-item border-0 py-2 text-secondary">Select a Blockly script to see its metadata.</div>
                    </div>
                  </div>

                  <div>
                    <label class="form-label" for="copyLessonSelect">Steps kopiëren uit andere lesson</label>
                    <div class="row g-2">
                      <div class="col">
                        <select id="copyLessonSelect" class="form-select">
                          <option value="">Select lesson to copy from</option>
                        </select>
                      </div>
                      <div class="col-auto">
                        <button id="refreshLessonsBtn" class="btn btn-outline-secondary" type="button">Refresh lessons</button>
                      </div>
                      <div class="col-auto">
                        <button id="replaceStepsBtn" class="btn btn-outline-warning" type="button">Replace steps</button>
                      </div>
                      <div class="col-auto">
                        <button id="appendStepsBtn" class="btn btn-outline-secondary" type="button">Append steps</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header border-0">
              <div>
                <h2 class="card-title">Steps</h2>
                <div class="card-subtitle">Een step is een Blockly-script met extra variabele lesson-informatie zoals text, word, letters en repeat.</div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter table-borderless card-table">
                <thead>
                  <tr>
                    <th>Step</th>
                    <th>Text</th>
                    <th>Word</th>
                    <th>Letters</th>
                    <th>Repeat</th>
                    <th class="w-1"></th>
                    <th class="w-1"></th>
                    <th class="w-1"></th>
                    <th class="w-1"></th>
                  </tr>
                </thead>
                <tbody id="stepsTableBody"></tbody>
              </table>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header border-0">
              <h2 class="card-title">Debug log</h2>
              <div class="card-actions">
                <div class="btn-list">
                  <button id="copyDebugLogBtn" type="button" class="btn btn-outline-secondary btn-sm">Copy log</button>
                  <button id="clearDebugLogBtn" type="button" class="btn btn-outline-secondary btn-sm">Clear log</button>
                  <button id="toggleDebugLogBtn" type="button" class="btn btn-outline-secondary btn-sm">Unhide</button>
                </div>
              </div>
            </div>
            <div id="debugLogBody" class="card-body d-none" hidden>
              <pre id="statusBox" class="form-control font-monospace mb-0" rows="8"></pre>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <iframe id="lessonRunnerFrame" class="d-none" title="Lesson runner" allow="autoplay" hidden></iframe>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script>
    const shared = window.LessonBuilderShared;
    const lessonIdInput = document.getElementById('lessonIdInput');
    const lessonTitleInput = document.getElementById('lessonTitleInput');
    const lessonWordInput = document.getElementById('lessonWordInput');
    const lessonSoundsInput = document.getElementById('lessonSoundsInput');
    const lessonNewSoundsInput = document.getElementById('lessonNewSoundsInput');
    const lessonKnownSoundsInput = document.getElementById('lessonKnownSoundsInput');
    const lessonDescriptionInput = document.getElementById('lessonDescriptionInput');
    const stepsTableBody = document.getElementById('stepsTableBody');
    const scriptsSelect = document.getElementById('scriptsSelect');
    const copyLessonSelect = document.getElementById('copyLessonSelect');
    const scriptMetaPreview = document.getElementById('scriptMetaPreview');
    const addStepBtn = document.getElementById('addStepBtn');
    const refreshLessonsBtn = document.getElementById('refreshLessonsBtn');
    const replaceStepsBtn = document.getElementById('replaceStepsBtn');
    const appendStepsBtn = document.getElementById('appendStepsBtn');
    const scriptsSummary = document.getElementById('scriptsSummary');
    const statusBox = document.getElementById('statusBox');
    const copyDebugLogBtn = document.getElementById('copyDebugLogBtn');
    const clearDebugLogBtn = document.getElementById('clearDebugLogBtn');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const debugLogBody = document.getElementById('debugLogBody');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');
    const authBtn = document.getElementById('authBtn');
    const saveLessonBtn = document.getElementById('saveLessonBtn');
    const deleteLessonBtn = document.getElementById('deleteLessonBtn');
    const lessonActionHint = document.getElementById('lessonActionHint');
    const brailleMonitorRow = document.getElementById('brailleMonitorRow');
    const scriptBrailleMonitorRow = document.getElementById('scriptBrailleMonitorRow');
    const simThumbLeftBtn = document.getElementById('simThumbLeftBtn');
    const simThumbRightBtn = document.getElementById('simThumbRightBtn');
    const simCursor5Btn = document.getElementById('simCursor5Btn');
    const simChord1Btn = document.getElementById('simChord1Btn');
    const STEP_LINK_CREATE_URL = <?= $jsValue($urlFor($sessionApiBase, 'create-link.php')) ?>;
    const STEP_LINK_UPDATE_URL = <?= $jsValue($urlFor($sessionApiBase, 'update-link.php')) ?>;

    let state = shared.loadState();
    let scriptsCache = [];
    let lessonsCache = [];
    const scriptDataCache = new Map();
    let stepConfigs = [];
    let savedStepLinkCodes = new Set();
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
        return 'http://127.0.0.1:5500/blockly/index.php?v=20260415-1';
      }
      return 'https://www.tastenbraille.com/braillestudio/blockly/index.php?v=20260415-1';
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
      if (debugLogBody) {
        debugLogBody.hidden = !isDebugLogVisible;
        debugLogBody.classList.toggle('d-none', !isDebugLogVisible);
      }
      toggleDebugLogBtn.textContent = isDebugLogVisible ? 'Hide' : 'Unhide';
    }

    function renderAuthenticationState() {
      const authenticated = Boolean(shared?.getAuthToken?.());
      if (authBtn) {
        authBtn.className = authenticated
          ? 'btn btn-outline-success'
          : 'btn btn-outline-primary';
        authBtn.innerHTML = authenticated
          ? '<i class="ti ti-shield-check me-2" aria-hidden="true"></i>Authenticated'
          : '<i class="ti ti-login me-2" aria-hidden="true"></i>Authentication';
      }
      if (saveLessonBtn) {
        saveLessonBtn.disabled = !authenticated;
        saveLessonBtn.className = authenticated
          ? 'btn btn-primary'
          : 'btn btn-secondary disabled';
        saveLessonBtn.title = authenticated ? 'Save lesson' : 'Authenticate first to save';
      }
      if (deleteLessonBtn) {
        deleteLessonBtn.disabled = !authenticated;
        deleteLessonBtn.className = authenticated
          ? 'btn btn-danger'
          : 'btn btn-secondary disabled';
        deleteLessonBtn.title = authenticated ? 'Delete lesson' : 'Authenticate first to delete';
      }
      if (lessonActionHint) {
        lessonActionHint.textContent = authenticated
          ? 'Use Save to store this lesson, Delete to remove it, and Run to test the current steps.'
          : 'Authenticate first to enable Save and Delete. Run does not require authentication.';
      }
    }

    function getCurrentMethodId() {
      const method = shared.getDraftMethodMeta(state);
      return String(method?.id || '').trim();
    }

    function renderCopyLessonSelect() {
      if (!copyLessonSelect) return;
      const currentLessonId = String(lessonIdInput.value.trim() || state.lessonId || '').trim();
      const previousValue = String(copyLessonSelect.value || '').trim();
      const options = lessonsCache
        .filter((lesson) => String(lesson?.id || '').trim() && String(lesson?.id || '').trim() !== currentLessonId)
        .sort((a, b) => {
          const aNumber = Number(a?.lessonNumber || 0);
          const bNumber = Number(b?.lessonNumber || 0);
          if (aNumber !== bNumber) return aNumber - bNumber;
          return String(a?.title || a?.id || '').localeCompare(String(b?.title || b?.id || ''));
        });

      copyLessonSelect.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = options.length ? 'Select lesson to copy from' : 'No other lessons available';
      copyLessonSelect.appendChild(placeholder);

      options.forEach((lesson) => {
        const option = document.createElement('option');
        const id = String(lesson?.id || '').trim();
        const title = String(lesson?.title || '').trim();
        const lessonNumber = Number(lesson?.lessonNumber || 0);
        option.value = id;
        option.textContent = lessonNumber > 0
          ? `${lessonNumber}. ${title || id}`
          : (title || id);
        copyLessonSelect.appendChild(option);
      });

      if (previousValue && options.some((lesson) => String(lesson?.id || '').trim() === previousValue)) {
        copyLessonSelect.value = previousValue;
      }

      const disabled = options.length === 0;
      copyLessonSelect.disabled = disabled;
      if (refreshLessonsBtn) refreshLessonsBtn.disabled = !getCurrentMethodId();
      if (replaceStepsBtn) replaceStepsBtn.disabled = disabled;
      if (appendStepsBtn) appendStepsBtn.disabled = disabled;
    }

    async function refreshLessonsCache() {
      const methodId = getCurrentMethodId();
      if (!methodId) {
        lessonsCache = [];
        renderCopyLessonSelect();
        return [];
      }
      lessonsCache = await shared.listLessons(methodId);
      renderCopyLessonSelect();
      return lessonsCache;
    }

    async function copyStepsFromLesson(mode = 'replace') {
      showDebugLog();
      const sourceLessonId = String(copyLessonSelect?.value || '').trim();
      if (!sourceLessonId) {
        setStatus('Selecteer eerst een bronlesson.');
        return;
      }

      const sourceLesson = await shared.loadLesson(sourceLessonId);
      const importedSteps = hydrateStepConfigsWithScriptMetadata(
        serializeStepConfigs(shared.normalizeStepConfigs(sourceLesson?.steps || []))
          .map((step) => ({ ...step, stepLinkCode: '' }))
      );

      if (!importedSteps.length) {
        setStatus('De bronlesson heeft geen steps.');
        appendStatus('Copy steps aborted: source lesson empty.', { sourceLessonId, mode });
        return;
      }

      stepConfigs = mode === 'append'
        ? serializeStepConfigs([...(stepConfigs || []), ...importedSteps])
        : importedSteps;
      hydrateStepConfigsWithScriptMetadata();
      ensureAutomaticStepLinkCodes();
      state = shared.updateState({ steps: stepConfigs });
      renderStepsTable();
      appendStatus('Steps copied from lesson.', {
        sourceLessonId,
        mode,
        importedCount: importedSteps.length,
        stepLinksCopied: false,
        totalCount: stepConfigs.length
      });
      setStatus(
        mode === 'append'
          ? `${importedSteps.length} step(s) appended from ${sourceLessonId}.`
          : `${importedSteps.length} step(s) replaced from ${sourceLessonId}.`
      );
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
        brailleMonitorRow.hidden = !isWsConnected;
        brailleMonitorRow.classList.toggle('d-none', !isWsConnected);
      }
      if (scriptBrailleMonitorRow) {
        scriptBrailleMonitorRow.hidden = !!isWsConnected;
        scriptBrailleMonitorRow.classList.toggle('d-none', !!isWsConnected);
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
        const label = isCurrent
          ? (isStoppingCurrentStep ? 'Stopping step' : 'Stop step')
          : 'Run step';
        button.innerHTML = getStepActionIcon(isCurrent ? 'stop' : 'run');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.disabled = isStoppingCurrentStep || isStoppingLesson || (hasActiveRun && !isCurrent);
        button.className = isCurrent
          ? 'btn btn-icon btn-danger'
          : 'btn btn-icon btn-outline-secondary';
      });
      const runLessonBtn = document.getElementById('runLessonBtn');
      if (runLessonBtn) {
        runLessonBtn.disabled = isStoppingCurrentStep || isStoppingLesson || (currentRunningStepIndex >= 0 && !isLessonRunning);
        runLessonBtn.className = isLessonRunning
          ? 'btn btn-danger'
          : 'btn btn-outline-secondary';
        runLessonBtn.innerHTML = isLessonRunning
          ? `<i class="ti ti-player-stop me-2" aria-hidden="true"></i>${isStoppingLesson ? 'Stopping...' : 'Stop'}`
          : '<i class="ti ti-player-play me-2" aria-hidden="true"></i>Run';
      }
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
      renderSelectedScriptMeta();
    }

    function renderAddStepAvailability() {
      if (!addStepBtn) return;
      addStepBtn.disabled = !String(scriptsSelect?.value || '').trim();
    }

    function updateStateStepConfigs() {
      stepConfigs = serializeStepConfigs(shared.normalizeStepConfigs(stepConfigs));
      state = shared.updateState({ steps: stepConfigs });
    }

    function refreshDerivedStepNamesForLessonTitle() {
      syncStepConfigsFromTable();
      ensureAutomaticStepLinkCodes();
      updateStateStepConfigs();
      renderStepsTable();
    }

    function getScriptItemById(id) {
      const scriptId = String(id || '').trim();
      return scriptsCache.find((item) => String(item?.id || '').trim() === scriptId) || null;
    }

    function formatDateTime(value) {
      const raw = String(value || '').trim();
      if (!raw) return '-';
      const date = new Date(raw);
      if (Number.isNaN(date.getTime())) {
        return raw;
      }
      return new Intl.DateTimeFormat('nl-NL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      }).format(date);
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function renderSelectedScriptMeta(data = null) {
      if (!scriptMetaPreview) return;
      const selectedId = String(scriptsSelect?.value || '').trim();
      if (!selectedId) {
        scriptMetaPreview.innerHTML = '<div class="list-group-item border-0 py-2 text-secondary">Select a Blockly script to see its metadata.</div>';
        return;
      }

      const item = data || getScriptItemById(selectedId) || {};
      const meta = item?.meta && typeof item.meta === 'object' ? item.meta : {};
      const title = String(meta.title || item.title || item.id || selectedId).trim();
      const description = String(meta.description || item.description || '').trim();
      const instruction = String(meta.instruction || '').trim();
      const status = String(meta.status || '').trim();
      const updatedAt = String(item.updatedAt || '').trim();

      scriptMetaPreview.innerHTML = `
        <div class="list-group-item border-0 py-2 px-0">
          <div class="row g-2">
            <div class="col-12 col-md-6">
              <label class="form-label small mb-1">Script title</label>
              <input class="form-control form-control-sm" type="text" value="${escapeHtml(title || selectedId)}" readonly aria-readonly="true">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small mb-1">Script ID</label>
              <input class="form-control form-control-sm" type="text" value="${escapeHtml(selectedId)}" readonly aria-readonly="true">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small mb-1">Status</label>
              <input class="form-control form-control-sm" type="text" value="${escapeHtml(status || '-')}" readonly aria-readonly="true">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small mb-1">Updated</label>
              <input class="form-control form-control-sm" type="text" value="${escapeHtml(formatDateTime(updatedAt))}" readonly aria-readonly="true">
            </div>
            <div class="col-12">
              <label class="form-label small mb-1">Description</label>
              <textarea class="form-control form-control-sm" rows="2" readonly aria-readonly="true">${escapeHtml(description || '-')}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label small mb-1">Instruction</label>
              <textarea class="form-control form-control-sm" rows="2" readonly aria-readonly="true">${escapeHtml(instruction || '-')}</textarea>
            </div>
          </div>
        </div>
      `;
    }

    async function refreshSelectedScriptMeta() {
      const selectedId = String(scriptsSelect?.value || '').trim();
      if (!selectedId) {
        renderSelectedScriptMeta();
        return;
      }

      renderSelectedScriptMeta();
      try {
        const fullData = await loadScriptData(selectedId);
        renderSelectedScriptMeta(fullData);
      } catch (err) {
        appendStatus('Script metadata preview fallback used.', {
          scriptId: selectedId,
          error: err.message || String(err)
        });
      }
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

    function getStepLinkCodes(items = stepConfigs) {
      return new Set((Array.isArray(items) ? items : [])
        .map((item) => String(item?.stepLinkCode || '').trim())
        .filter(Boolean));
    }

    function ensureAutomaticStepLinkCodes(items = stepConfigs) {
      (Array.isArray(items) ? items : []).forEach((item, index) => {
        if (!item || typeof item !== 'object') return;
        item.stepLinkCode = buildAutomaticStepLinkCode(index);
      });
      return items;
    }

    async function upsertStepLinkRecord(payload) {
      const res = await fetch(STEP_LINK_CREATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...payload, overwrite: true })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
      return data;
    }

    async function updateStepLinkRecord(payload) {
      const res = await fetch(STEP_LINK_UPDATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
      return data;
    }

    async function syncStepLinkActivationAfterSave() {
      ensureAutomaticStepLinkCodes();
      const method = shared.getDraftMethodMeta(state);
      const methodId = String(method.id || '').trim();
      const currentCodes = getStepLinkCodes(stepConfigs);
      const updates = [];

      stepConfigs.forEach((stepConfig, index) => {
        const code = String(stepConfig?.stepLinkCode || '').trim();
        if (!code) return;
        updates.push(upsertStepLinkRecord({
          code,
          methodId,
          scriptId: String(stepConfig.id || '').trim(),
          stepId: buildStepLinkStepId(stepConfig, index),
          active: true,
          meta: buildStepLinkMeta(stepConfig, index),
          stepInputs: shared.normalizeInputs(stepConfig.inputs || {})
        }));
      });

      savedStepLinkCodes.forEach((code) => {
        if (currentCodes.has(code)) return;
        updates.push(updateStepLinkRecord({
          originalCode: code,
          methodId,
          active: false
        }));
      });

      const results = await Promise.allSettled(updates);
      const failed = results.filter((result) => result.status === 'rejected');
      if (failed.length) {
        failed.forEach((result) => appendStatus('Step-link activation sync failed.', {
          error: result.reason?.message || String(result.reason)
        }));
        throw new Error(`${failed.length} step-link update(s) failed`);
      }
      savedStepLinkCodes = currentCodes;
      return {
        activeCodes: Array.from(currentCodes),
        updated: results.length
      };
    }

    async function deactivateStepLinkCodes(codes) {
      const method = shared.getDraftMethodMeta(state);
      const methodId = String(method.id || '').trim();
      const uniqueCodes = [...new Set(Array.from(codes || [])
        .map((code) => String(code || '').trim())
        .filter(Boolean))];
      if (!uniqueCodes.length) {
        return { deactivated: 0 };
      }
      const results = await Promise.allSettled(uniqueCodes.map((code) => updateStepLinkRecord({
        originalCode: code,
        methodId,
        active: false
      })));
      const failed = results.filter((result) => result.status === 'rejected');
      if (failed.length) {
        failed.forEach((result) => appendStatus('Step-link deactivate failed.', {
          error: result.reason?.message || String(result.reason)
        }));
        throw new Error(`${failed.length} step-link deactivate update(s) failed`);
      }
      return { deactivated: uniqueCodes.length };
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

    function hydrateStepConfigsWithScriptMetadata(items = stepConfigs) {
      const hydrated = shared.normalizeStepConfigs(items).map((cfg) => {
        const script = getScriptItemById(cfg.id);
        return {
          ...cfg,
          title: cfg.title || String(script?.title || '').trim()
        };
      });
      if (items === stepConfigs) {
        stepConfigs = hydrated;
      }
      return hydrated;
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

    function buildAutomaticStepLinkCode(stepIndex) {
      const lessonTitle = String(
        lessonTitleInput.value
        || state.lessonMetaTitle
        || state.lessonTitle
        || lessonIdInput.value
        || 'lesson'
      ).trim();
      const stepNumber = String(Math.max(1, Number(stepIndex) + 1)).padStart(4, '0');
      const rawCode = `B${[lessonTitle, stepNumber].filter(Boolean).join('-')}`;
      const suffix = `-${stepNumber}`;
      const normalized = normalizeStepLinkToken(rawCode, `step-${stepNumber}`);
      if (normalized.length <= 64) return normalized;
      return `${normalized.slice(0, Math.max(3, 64 - suffix.length)).replace(/-+$/g, '')}${suffix}`;
    }

    function formatSoundList(value) {
      if (Array.isArray(value)) {
        return value.map((item) => String(item || '').trim()).filter(Boolean).join(', ');
      }
      return String(value || '').trim();
    }

    function renderLessonMetadata(record = null) {
      const item = record && typeof record === 'object' ? record : {};
      lessonSoundsInput.value = formatSoundList(item.sounds);
      lessonNewSoundsInput.value = formatSoundList(item.newSounds);
      lessonKnownSoundsInput.value = formatSoundList(item.knownSounds);
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
      const script = getScriptItemById(stepConfig?.id);
      const scriptMeta = script?.meta && typeof script.meta === 'object' ? script.meta : {};
      return {
        title: String(stepConfig?.title || stepConfig?.id || '').trim(),
        description: String(stepConfig?.description || scriptMeta.description || script?.description || '').trim(),
        instruction: String(stepConfig?.instruction || scriptMeta.instruction || '').trim(),
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

    function getStepActionIcon(kind) {
      switch (kind) {
        case 'run':
          return '<i class="ti ti-player-play" aria-hidden="true"></i>';
        case 'stop':
          return '<i class="ti ti-player-stop" aria-hidden="true"></i>';
        case 'up':
          return '<i class="ti ti-arrow-up" aria-hidden="true"></i>';
        case 'down':
          return '<i class="ti ti-arrow-down" aria-hidden="true"></i>';
        case 'remove':
          return '<i class="ti ti-trash" aria-hidden="true"></i>';
        default:
          return '';
      }
    }

    function createStepActionButton(kind, label) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-icon btn-outline-secondary';
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
      button.innerHTML = getStepActionIcon(kind);
      return button;
    }

    function renderStepsTable() {
      stepsTableBody.innerHTML = '';
      if (!stepConfigs.length) {
        stepsTableBody.innerHTML = '<tr><td class="text-secondary" colspan="9">No steps yet. Add a script from the list.</td></tr>';
        return;
      }
      ensureAutomaticStepLinkCodes();
      stepConfigs.forEach((cfg, index) => {
        const inputs = shared.normalizeInputs(cfg.inputs || {});
        const meta = getStepDisplayMeta(cfg);
        const row = document.createElement('tr');
        row.dataset.stepIndex = String(index);

        const script = document.createElement('td');
        script.innerHTML = `
          <div class="d-flex align-items-start gap-2">
            <span class="badge bg-secondary-lt">${index + 1}</span>
            <div>
              <div class="fw-semibold">${meta.title || cfg.id}</div>
              <div class="small text-secondary">${cfg.id}</div>
            </div>
          </div>
        `;

        const text = document.createElement('textarea');
        text.dataset.field = 'text';
        text.rows = 3;
        text.placeholder = 'Text';
        text.className = 'form-control';
        text.value = inputs.text;
        text.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), text: e.target.value };
          updateStateStepConfigs();
        });

        const word = document.createElement('input');
        word.dataset.field = 'word';
        word.className = 'form-control';
        word.value = inputs.word;
        word.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...shared.normalizeInputs(stepConfigs[index].inputs || {}), word: e.target.value };
          updateStateStepConfigs();
        });

        const letters = document.createElement('input');
        letters.dataset.field = 'letters';
        letters.type = 'text';
        letters.placeholder = 'a, b, c';
        letters.className = 'form-control';
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
        repeat.className = 'form-control';
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
        stepLink.className = 'mt-2';
        stepLink.innerHTML = `
          <div class="btn-list">
            <button type="button" data-copy-step-link-code class="btn btn-outline-secondary btn-icon" aria-label="Copy link code" title="Copy link code">
              <i class="ti ti-copy" aria-hidden="true"></i>
            </button>
          </div>
          <code data-step-link-code-text class="form-control text-truncate mt-2">No code yet</code>
        `;
        script.appendChild(stepLink);
        renderStepLinkCodeCell(stepLink, cfg.stepLinkCode);
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

        const run = createStepActionButton('run', 'Run step');
        run.dataset.runStepIndex = String(index);
        run.addEventListener('click', async () => {
          try {
            await runSingleStep(index);
          } catch (err) {
            setStatus(`Step run error: ${err.message}`);
          }
        });

        const moveUp = createStepActionButton('up', 'Move step up');
        moveUp.disabled = index === 0;
        moveUp.addEventListener('click', () => {
          if (index === 0) return;
          const current = stepConfigs[index];
          stepConfigs[index] = stepConfigs[index - 1];
          stepConfigs[index - 1] = current;
          updateStateStepConfigs();
          renderStepsTable();
        });

        const moveDown = createStepActionButton('down', 'Move step down');
        moveDown.disabled = index === stepConfigs.length - 1;
        moveDown.addEventListener('click', () => {
          if (index === stepConfigs.length - 1) return;
          const current = stepConfigs[index];
          stepConfigs[index] = stepConfigs[index + 1];
          stepConfigs[index + 1] = current;
          updateStateStepConfigs();
          renderStepsTable();
        });

        const remove = createStepActionButton('remove', 'Remove step');
        remove.addEventListener('click', () => {
          stepConfigs.splice(index, 1);
          updateStateStepConfigs();
          renderStepsTable();
        });

        [text, word, letters, repeat, run, moveUp, moveDown, remove].forEach((node) => {
          const cell = document.createElement('td');
          cell.appendChild(node);
          row.appendChild(cell);
        });
        row.insertBefore(script, row.firstChild);
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
      ensureAutomaticStepLinkCodes();
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
        const linkSyncResult = await syncStepLinkActivationAfterSave();
        appendStatus('Step-link activation synced.', linkSyncResult);
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
        await refreshLessonsCache();
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
        renderLessonMetadata(basisItem || state.basisRecord || null);
        lessonDescriptionInput.value = state.lessonDescription || '';
        stepConfigs = serializeStepConfigs(shared.normalizeStepConfigs(state.steps || []));
        hydrateStepConfigsWithScriptMetadata();
        savedStepLinkCodes = getStepLinkCodes(stepConfigs);
        ensureAutomaticStepLinkCodes();
        state = shared.updateState({ steps: stepConfigs });
        renderCopyLessonSelect();
        const preloadSummary = await preloadReferencedScriptData(stepConfigs);
        await refreshSelectedScriptMeta();
        await runnerWarmupPromise;
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
        await refreshSelectedScriptMeta();
      } catch (err) {
        setStatus(`Scripts load error: ${err.message}`);
      }
    });

    scriptsSelect?.addEventListener('change', async () => {
      renderAddStepAvailability();
      await refreshSelectedScriptMeta();
    });

    refreshLessonsBtn?.addEventListener('click', async () => {
      try {
        await refreshLessonsCache();
        setStatus(`Loaded ${lessonsCache.length} lesson(s).`);
        appendStatus('Lessons refreshed.', {
          methodId: getCurrentMethodId(),
          lessonCount: lessonsCache.length
        });
      } catch (err) {
        setStatus(`Lessons load error: ${err.message}`);
      }
    });

    replaceStepsBtn?.addEventListener('click', async () => {
      try {
        await copyStepsFromLesson('replace');
      } catch (err) {
        setStatus(`Copy steps error: ${err.message}`);
      }
    });

    appendStepsBtn?.addEventListener('click', async () => {
      try {
        await copyStepsFromLesson('append');
      } catch (err) {
        setStatus(`Append steps error: ${err.message}`);
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
      ensureAutomaticStepLinkCodes();
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
        const codesToDeactivate = new Set([...savedStepLinkCodes, ...getStepLinkCodes(stepConfigs)]);
        const result = await shared.deleteLesson(lessonIdInput.value.trim());
        const deactivationResult = await deactivateStepLinkCodes(codesToDeactivate);
        appendStatus('Step-links deactivated after lesson delete.', deactivationResult);
        savedStepLinkCodes = new Set();
        state = shared.updateState({ lessonId: '', lessonTitle: '', lessonWord: '', steps: [] });
        lessonIdInput.value = '';
        lessonTitleInput.value = '';
        lessonWordInput.value = '';
        renderLessonMetadata(null);
        lessonDescriptionInput.value = '';
        stepConfigs = [];
        renderStepsTable();
        renderCopyLessonSelect();
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
      refreshDerivedStepNamesForLessonTitle();
    });

    lessonDescriptionInput.addEventListener('input', () => {
      state = shared.updateState({
        lessonDescription: lessonDescriptionInput.value.trim()
      });
    });

    window.addEventListener('load', () => {
      if (authRedirected) return;
      init();
    });
  </script>
</body>
</html>
