<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'developer'], 'page');

$authUser = bs_auth_current_user() ?? ['display' => '', 'role' => ''];

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?lessonbuilder$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$lessonBuilderBase = $scriptDir;
$sessionApiBase = $appBase . '/api/session-api';

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
  <title>Lesson Builder - Steps</title>
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'components/braille-monitor/braillemonitor.css?v=20260529-mode-label-1')) ?>">
  <link rel="stylesheet" href="<?= $htmlUrl($urlFor($appBase, 'components/braillebridge-status/braillebridge-status.css?v=20260526-popup-3')) ?>">
  <script src="<?= $htmlUrl($urlFor($appBase, 'api/lessonbuilder/lessonbuilder-shared.js?v=20260612-fast-methods-1')) ?>"></script>
  <style>
    .steps-list {
      display: grid;
      gap: .75rem;
    }

    .step-card {
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface);
    }

    .step-card__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: .875rem 1rem;
    }

    .step-card__title {
      min-width: 0;
    }

    .step-card__actions {
      display: flex;
      align-items: center;
      gap: .375rem;
      flex: 0 0 auto;
    }

    .step-card__body {
      border-top: var(--tblr-border-width) solid var(--tblr-border-color);
      padding: 1rem;
    }

    .step-card__body[hidden] {
      display: none;
    }

    .lessonbuilder-header-status {
      flex: 0 0 auto;
    }

    .lessonbuilder-header-status.is-collapsed {
      min-width: 0;
      flex-basis: auto;
    }

    .lessonbuilder-header-status .braillebridge-status__body {
      padding: .625rem .75rem;
    }

    .lessonbuilder-header-status .braillebridge-status__icon {
      width: 2.25rem;
      height: 2.25rem;
      font-size: 1.15rem;
    }

    .thumb-controls {
      justify-content: center;
    }

    .thumb-controls .btn {
      min-width: 3rem;
      font-weight: 600;
    }

    .step-settings {
      display: grid;
      gap: .375rem;
      max-width: 42rem;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      padding: .625rem;
    }

    .step-variable-row {
      display: grid;
      grid-template-columns: minmax(8rem, 14rem) minmax(0, 1fr);
      align-items: start;
      gap: .625rem;
    }

    .step-variable-row .form-label {
      margin-bottom: 0;
      padding-top: .25rem;
    }

    @media (max-width: 575.98px) {
      .step-variable-row {
        grid-template-columns: 1fr;
        gap: .25rem;
      }

      .step-variable-row .form-label {
        padding-top: 0;
      }
    }
  </style>
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:secure_url" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1729">
  <meta property="og:image:height" content="910">
  <meta property="og:image:alt" content="BrailleStudio">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta name="twitter:image:alt" content="BrailleStudio">
</head>
<body class="bg-body">
  <div id="stepsLoadingScreen" class="page page-center">
    <div class="container-tight py-4">
      <div class="card">
        <div class="card-body text-center py-5">
          <div class="mb-3">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          </div>
          <h1 class="h2 mb-2">Steps laden</h1>
          <p id="stepsLoadingMessage" class="text-secondary mb-0">De lesson steps worden voorbereid.</p>
          <pre id="stepsLoadingDebugLog" class="form-control font-monospace text-start mt-4 mb-0" style="max-height: 16rem; overflow: auto; white-space: pre-wrap;"></pre>
        </div>
      </div>
    </div>
  </div>

  <div id="stepsAppPage" class="page d-none" hidden>
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $htmlUrl($urlFor($appBase, 'index.php')) ?>">
        <img src="../../style/logo.png" alt="" aria-hidden="true" class="me-2" style="height: 2rem; width: auto;">
        <img src="../../style/braillestudio_banner_text.png" alt="BrailleStudio" style="height: 1.5rem; width: auto;">
        </a>
        <div class="navbar-nav flex-row align-items-center ms-auto gap-2">
          <span class="navbar-text text-secondary me-2">
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
              <div class="card-actions">
                <section
                  class="lessonbuilder-header-status"
                  data-braillebridge-status
                  data-expanded="false"
                  data-popup="true"
                  data-ws-url="ws://localhost:5000/ws"
                  data-launch-url="braillebridge://"
                  data-auto-launch="true"
                  aria-label="BrailleBridge status"
                ></section>
              </div>
            </div>
            <div class="card-body">
              <div id="brailleMonitorRow">
                <div id="brailleMonitorComponent"></div>
              </div>
              <div id="scriptBrailleMonitorRow">
                <div id="scriptBrailleMonitorComponent"></div>
              </div>
              <div class="btn-list thumb-controls mt-3" aria-label="Thumb keys">
                <button id="simThumbLeftBtn" class="btn btn-outline-secondary" type="button" aria-label="Left thumb" title="Left thumb">&lt;&lt;</button>
                <button id="simCursor5Btn" class="btn btn-outline-secondary" type="button" aria-label="Left middle thumb" title="Left middle thumb">&lt;</button>
                <button id="simChord1Btn" class="btn btn-outline-secondary" type="button" aria-label="Right middle thumb" title="Right middle thumb">&gt;</button>
                <button id="simThumbRightBtn" class="btn btn-outline-secondary" type="button" aria-label="Right thumb" title="Right thumb">&gt;&gt;</button>
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
                    <button id="stopLessonBtn" class="btn btn-danger" type="button">
                      <i class="ti ti-player-stop me-2" aria-hidden="true"></i>
                      Stop
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
                <div class="card-subtitle">Een step is een Blockly-script met repeat en eventuele external variables.</div>
              </div>
            </div>
            <div class="card-body">
              <div id="stepsTableBody" class="steps-list"></div>
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
              <div id="statusBox" class="list-group list-group-flush border rounded font-monospace overflow-auto" style="max-height: 24rem"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <iframe id="lessonRunnerFrame" class="d-none" title="Lesson runner" allow="autoplay" hidden></iframe>

  <script src="<?= $htmlUrl($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="<?= $htmlUrl($urlFor($appBase, 'components/braillebridge-status/braillebridge-status.js?v=20260612-runtime-status-4')) ?>"></script>
  <script>
    const shared = window.LessonBuilderShared;
    const stepsLoadingScreen = document.getElementById('stepsLoadingScreen');
    const stepsLoadingMessage = document.getElementById('stepsLoadingMessage');
    const stepsLoadingDebugLog = document.getElementById('stepsLoadingDebugLog');
    const stepsAppPage = document.getElementById('stepsAppPage');
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
    const stopLessonBtn = document.getElementById('stopLessonBtn');
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
    const pageStartedAt = performance.now();

    function setLoadingMessage(message) {
      if (stepsLoadingMessage) {
        stepsLoadingMessage.textContent = message;
      }
    }

    function hideLoadingScreen() {
      if (stepsLoadingScreen) {
        stepsLoadingScreen.hidden = true;
        stepsLoadingScreen.classList.add('d-none');
      }
      if (stepsAppPage) {
        stepsAppPage.hidden = false;
        stepsAppPage.classList.remove('d-none');
      }
    }

    function showLoadingError(message) {
      if (stepsLoadingScreen) {
        stepsLoadingScreen.hidden = false;
        stepsLoadingScreen.classList.remove('d-none');
      }
      if (stepsAppPage) {
        stepsAppPage.hidden = true;
        stepsAppPage.classList.add('d-none');
      }
      if (stepsLoadingMessage) {
        stepsLoadingMessage.textContent = message;
        stepsLoadingMessage.classList.remove('text-secondary');
        stepsLoadingMessage.classList.add('text-danger');
      }
      if (stepsLoadingDebugLog) {
        stepsLoadingDebugLog.hidden = false;
        stepsLoadingDebugLog.classList.remove('d-none');
      }
    }

    function resolveRunnerUrl() {
      const url = new URL('../../blockly/session-player.php', window.location.href);
      url.searchParams.set('v', '20260602-headless-highlight-1');
      return url.toString();
    }

    const RUNNER_URL = resolveRunnerUrl();
    lessonRunnerFrame.src = RUNNER_URL;

    function formatDebugData(value) {
      if (value == null) return '';
      if (typeof value === 'string') return value;
      if (Array.isArray(value)) {
        return value.map((item, index) => {
          if (item && typeof item === 'object') {
            return `${index + 1}. ${formatDebugData(item).replaceAll('\n', '\n   ')}`;
          }
          return `${index + 1}. ${String(item ?? '')}`;
        }).join('\n');
      }
      if (typeof value === 'object') {
        return Object.entries(value).map(([key, item]) => {
          if (Array.isArray(item)) {
            return `${key}: ${item.map((entry) => {
              if (entry && typeof entry === 'object') {
                return formatDebugData(entry).replaceAll('\n', '; ');
              }
              return String(entry ?? '');
            }).join(', ')}`;
          }
          if (item && typeof item === 'object') {
            return `${key}:\n  ${formatDebugData(item).replaceAll('\n', '\n  ')}`;
          }
          return `${key}: ${String(item ?? '')}`;
        }).join('\n');
      }
      return String(value);
    }

    function addStatus(message, data = null, replace = false) {
      if (replace) statusBox.replaceChildren();
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const item = document.createElement('div');
      item.className = 'list-group-item py-2';
      const title = document.createElement('div');
      title.className = 'fw-medium';
      title.textContent = `[${timestamp}] ${message}`;
      item.append(title);
      const formatted = formatDebugData(data);
      if (formatted) {
        formatted.split('\n').forEach((line) => {
          const detail = document.createElement('div');
          detail.className = 'text-secondary small';
          detail.textContent = line;
          item.append(detail);
        });
      }
      statusBox.prepend(item);
      return `[${timestamp}] ${message}${formatted ? `\n${formatted}` : ''}`;
    }

    function setStatus(message, data = null) {
      addStatus(message, data, true);
    }

    function appendStatus(message, data = null) {
      const block = addStatus(message, data);
      if (stepsLoadingDebugLog) {
        stepsLoadingDebugLog.textContent = stepsLoadingDebugLog.textContent ? `${block}\n${stepsLoadingDebugLog.textContent}` : block;
        stepsLoadingDebugLog.scrollTop = 0;
      }
    }

    window.LessonBuilderSharedDebugLog = appendStatus;

    window.addEventListener('error', (event) => {
      appendStatus('Browser error.', {
        message: event.message || '',
        source: event.filename || '',
        line: event.lineno || 0,
        column: event.colno || 0
      });
    });

    window.addEventListener('unhandledrejection', (event) => {
      appendStatus('Unhandled promise rejection.', {
        reason: event.reason?.message || String(event.reason || '')
      });
    });

    async function timeStep(label, fn) {
      const startedAt = performance.now();
      appendStatus(`${label} started.`);
      try {
        const result = await fn();
        appendStatus(`${label} completed.`, {
          durationMs: Number((performance.now() - startedAt).toFixed(1))
        });
        return result;
      } catch (err) {
        appendStatus(`${label} failed.`, {
          durationMs: Number((performance.now() - startedAt).toFixed(1)),
          error: err.message || String(err)
        });
        throw err;
      }
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
      if (saveLessonBtn) {
        saveLessonBtn.disabled = false;
        saveLessonBtn.className = 'btn btn-primary';
        saveLessonBtn.title = 'Save lesson';
      }
      if (deleteLessonBtn) {
        deleteLessonBtn.disabled = false;
        deleteLessonBtn.className = 'btn btn-danger';
        deleteLessonBtn.title = 'Delete lesson';
      }
      if (lessonActionHint) {
        lessonActionHint.textContent = 'Use Save to store this lesson, Delete to remove it, and Run to test the current steps.';
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
          const startedAt = performance.now();
          appendStatus('Dynamic script load started.', { src });
          await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Failed to load ${src}`));
            document.head.appendChild(script);
          });
          appendStatus('Dynamic script load completed.', {
            src,
            durationMs: Number((performance.now() - startedAt).toFixed(1))
          });
          return;
        } catch (err) {
          appendStatus('Dynamic script load failed.', {
            src,
            error: err.message || String(err)
          });
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
      if (stopLessonBtn) {
        const isStopping = isStoppingCurrentStep || isStoppingLesson;
        stopLessonBtn.disabled = isStopping;
        stopLessonBtn.innerHTML = `<i class="ti ti-player-stop me-2" aria-hidden="true"></i>${isStopping ? 'Stopping...' : 'Stop'}`;
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
      const normalizedVariables = variables
        .map((item) => {
          const name = String(item?.name || '').trim();
          if (!name || name === 'student_code' || String(item?.scope || '').trim().toLowerCase() !== 'external') return null;
          return {
            name,
            type: String(item?.type || 'string').trim() || 'string',
            defaultValue: item?.defaultValue ?? '',
            description: String(item?.description || '').trim()
          };
        })
        .filter(Boolean);
      const seen = new Set(normalizedVariables.map((variable) => variable.name));
      collectExternalVariableNamesFromBlocks(blocklyState).forEach((name) => {
        if (name === 'student_code' || seen.has(name)) return;
        seen.add(name);
        normalizedVariables.push({
          name,
          type: 'string',
          defaultValue: '',
          description: 'External variable detected from Blockly blocks. Save the script again to add full metadata.'
        });
      });
      return normalizedVariables;
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

    function formatExternalVariableDefault(value) {
      if (value == null) return '';
      if (typeof value === 'object') {
        try {
          return JSON.stringify(value);
        } catch {
          return '';
        }
      }
      return String(value);
    }

    function parseExternalVariableDefault(variable) {
      const value = variable?.defaultValue;
      const type = String(variable?.type || 'string');
      if (type === 'number') return Number(value) || 0;
      if (type === 'boolean') {
        if (typeof value === 'boolean') return value;
        return ['true', '1', 'yes', 'ja'].includes(String(value ?? '').trim().toLowerCase());
      }
      if (type === 'array' || type === 'object') {
        if (value && typeof value === 'object') return value;
        try {
          const parsed = JSON.parse(String(value ?? '').trim() || (type === 'array' ? '[]' : '{}'));
          return type === 'array'
            ? (Array.isArray(parsed) ? parsed : [])
            : (parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {});
        } catch {
          return type === 'array' ? [] : {};
        }
      }
      return String(value ?? '');
    }

    function coerceExternalVariableInput(value, type = 'string') {
      if (type === 'number') return Number(value) || 0;
      if (type === 'boolean') return ['true', '1', 'yes', 'ja'].includes(String(value ?? '').trim().toLowerCase());
      if (type === 'array' || type === 'object') {
        try {
          const parsed = JSON.parse(String(value ?? '').trim() || (type === 'array' ? '[]' : '{}'));
          return type === 'array'
            ? (Array.isArray(parsed) ? parsed : [])
            : (parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {});
        } catch {
          return type === 'array' ? [] : {};
        }
      }
      return String(value ?? '');
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
      const externalVariables = getExternalVariablesFromScriptData(item);
      const externalVariablesHtml = externalVariables.length
        ? externalVariables.map((variable) => `
            <div class="list-group-item border-0 px-0 py-2">
              <div class="d-flex align-items-start justify-content-between gap-2">
                <div>
                  <span class="badge bg-blue-lt me-1">external</span>
                  <strong>${escapeHtml(variable.name)}</strong>
                  <span class="text-secondary">(${escapeHtml(variable.type)})</span>
                </div>
                <span class="small text-secondary">${escapeHtml(variable.type || 'string')}</span>
              </div>
              <div class="small text-secondary mt-1">${escapeHtml(variable.description || '-')}</div>
              <code class="form-control text-truncate mt-1">${escapeHtml(formatExternalVariableDefault(variable.defaultValue) || '(empty)')}</code>
            </div>
          `).join('')
        : '<div class="list-group-item border-0 px-0 py-2 text-secondary">No external variables defined.</div>';

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
        <div class="list-group-item border-0 py-2 px-0">
          <label class="form-label small mb-1">External variables</label>
          <div class="list-group list-group-flush">${externalVariablesHtml}</div>
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
      const scriptMeta = script?.meta && typeof script.meta === 'object' ? script.meta : {};
      return {
        title: String(stepConfig?.title || scriptMeta.title || script?.title || '').trim(),
        description: String(stepConfig?.description || scriptMeta.description || script?.description || '').trim(),
        instruction: String(stepConfig?.instruction || scriptMeta.instruction || '').trim(),
        exists: Boolean(script)
      };
    }

    function getExternalVariableNameSetForScript(scriptId = '') {
      const scriptData = scriptDataCache.get(String(scriptId || '').trim()) || null;
      return new Set(getExternalVariablesFromScriptData(scriptData).map((variable) => variable.name));
    }

    function normalizeEditableStepInputs(inputs = {}, scriptId = '') {
      const normalized = shared.normalizeInputs(inputs || {});
      delete normalized.student_code;
      const scriptKey = String(scriptId || '').trim();
      if (scriptKey && !scriptDataCache.has(scriptKey)) {
        normalized.repeat = Math.max(1, Math.floor(Number(normalized.repeat ?? 1) || 1));
        return normalized;
      }
      const externalNames = getExternalVariableNameSetForScript(scriptId);
      ['text', 'word', 'letters'].forEach((key) => {
        if (!externalNames.has(key)) {
          delete normalized[key];
        }
      });
      normalized.repeat = Math.max(1, Math.floor(Number(normalized.repeat ?? 1) || 1));
      return normalized;
    }

    function serializeStepConfig(stepConfig) {
      const inputs = normalizeEditableStepInputs(stepConfig?.inputs || {}, stepConfig?.id || '');
      const meta = getStepDisplayMeta(stepConfig);
      const serializedInputs = { ...inputs };
      serializedInputs.repeat = Math.max(1, Math.floor(Number(inputs.repeat ?? 1) || 1));
      return {
        id: String(stepConfig?.id || '').trim(),
        title: meta.title,
        description: meta.description,
        instruction: meta.instruction,
        stepLinkCode: String(stepConfig?.stepLinkCode || '').trim(),
        inputs: serializedInputs
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
      const raw = await res.text();
      let data = {};
      try {
        data = raw ? JSON.parse(raw) : {};
      } catch {
        throw new Error(`Non-JSON response from ${STEP_LINK_CREATE_URL} (HTTP ${res.status})`);
      }
      if (!res.ok || !data.ok) {
        throw new Error(data.error ? `${data.error} (${STEP_LINK_CREATE_URL})` : `HTTP ${res.status} (${STEP_LINK_CREATE_URL})`);
      }
      return data;
    }

    async function updateStepLinkRecord(payload) {
      const res = await fetch(STEP_LINK_UPDATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const raw = await res.text();
      let data = {};
      try {
        data = raw ? JSON.parse(raw) : {};
      } catch {
        throw new Error(`Non-JSON response from ${STEP_LINK_UPDATE_URL} (HTTP ${res.status})`);
      }
      if (!res.ok || !data.ok) {
        throw new Error(data.error ? `${data.error} (${STEP_LINK_UPDATE_URL})` : `HTTP ${res.status} (${STEP_LINK_UPDATE_URL})`);
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
          stepInputs: normalizeEditableStepInputs(stepConfig.inputs || {}, stepConfig.id || '')
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
        const repeatRaw = row.querySelector('[data-field="repeat"]')?.value ?? '1';
        const inputs = normalizeEditableStepInputs(source.inputs || {}, source.id || '');
        inputs.repeat = Math.max(1, Math.floor(Number(repeatRaw) || 1));
        row.querySelectorAll('[data-external-variable-name]').forEach((control) => {
          const name = String(control.getAttribute('data-external-variable-name') || '').trim();
          const type = String(control.getAttribute('data-external-variable-type') || 'string').trim();
          if (!name) return;
          inputs[name] = control.type === 'checkbox'
            ? !!control.checked
            : coerceExternalVariableInput(control.value, type);
        });
        built.push({
          id: String(source.id || '').trim(),
          title: meta.title,
          description: meta.description,
          instruction: meta.instruction,
          stepLinkCode: String(source.stepLinkCode || '').trim(),
          inputs
        });
      });
      return built.filter((item) => item.id);
    }

    function hydrateStepConfigsWithScriptMetadata(items = stepConfigs) {
      const hydrated = shared.normalizeStepConfigs(items).map((cfg) => {
        const script = getScriptItemById(cfg.id);
        const scriptMeta = script?.meta && typeof script.meta === 'object' ? script.meta : {};
        return {
          ...cfg,
          title: cfg.title || String(scriptMeta.title || script?.title || '').trim(),
          description: String(cfg.description || scriptMeta.description || script?.description || '').trim(),
          instruction: String(cfg.instruction || scriptMeta.instruction || '').trim()
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
      const rawCode = `B${lessonTitle}${stepNumber}`;
      const suffix = stepNumber;
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
        case 'collapse':
          return '<i class="ti ti-chevron-down" aria-hidden="true"></i>';
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

    function createExternalVariablesEditor(stepConfig, index) {
      const scriptData = scriptDataCache.get(String(stepConfig?.id || '').trim()) || null;
      const variables = getExternalVariablesFromScriptData(scriptData);
      const meta = getStepDisplayMeta(stepConfig);
      const wrapper = document.createElement('div');
      wrapper.className = 'step-settings';
      const appendReadonlyTextRow = (labelText, value, rows = 2) => {
        const group = document.createElement('div');
        group.className = 'step-variable-row';
        const label = document.createElement('label');
        label.className = 'form-label small';
        label.textContent = labelText;
        const valueColumn = document.createElement('div');
        const control = document.createElement('textarea');
        control.className = 'form-control form-control-sm';
        control.rows = rows;
        control.readOnly = true;
        control.setAttribute('aria-readonly', 'true');
        control.value = String(value || '');
        valueColumn.appendChild(control);
        group.appendChild(label);
        group.appendChild(valueColumn);
        wrapper.appendChild(group);
      };

      appendReadonlyTextRow('Description', meta.description || '-', 2);
      appendReadonlyTextRow('Instruction', meta.instruction || '-', 2);

      if (!scriptData) {
        const message = document.createElement('div');
        message.className = 'small text-secondary';
        message.textContent = 'External variables worden geladen zodra het script is opgehaald.';
        wrapper.appendChild(message);
        return wrapper;
      }
      if (!variables.length) {
        const message = document.createElement('div');
        message.className = 'small text-secondary';
        message.textContent = 'Dit Blockly script heeft geen external variables.';
        wrapper.appendChild(message);
        return wrapper;
      }

      const inputs = normalizeEditableStepInputs(stepConfig?.inputs || {}, stepConfig?.id || '');
      variables.forEach((variable) => {
        const group = document.createElement('div');
        group.className = 'step-variable-row';

        const label = document.createElement('label');
        label.className = 'form-label small';
        label.textContent = `${variable.name} (${variable.type})`;
        group.appendChild(label);

        const hasStepValue = Object.prototype.hasOwnProperty.call(inputs, variable.name);
        const value = hasStepValue ? inputs[variable.name] : parseExternalVariableDefault(variable);
        let control;
        if (variable.type === 'boolean') {
          control = document.createElement('input');
          control.type = 'checkbox';
          control.className = 'form-check-input';
          control.checked = Boolean(value);
        } else if (variable.type === 'number') {
          control = document.createElement('input');
          control.type = 'number';
          control.className = 'form-control form-control-sm';
          control.value = formatExternalVariableDefault(value);
        } else {
          control = document.createElement('textarea');
          control.rows = variable.type === 'array' || variable.type === 'object' ? 3 : 2;
          control.className = 'form-control form-control-sm';
          control.value = formatExternalVariableDefault(value);
        }
        control.dataset.externalVariableName = variable.name;
        control.dataset.externalVariableType = variable.type;
        control.title = variable.description || variable.name;

        const valueColumn = document.createElement('div');
        const applyValue = () => {
          const nextInputs = normalizeEditableStepInputs(stepConfigs[index].inputs || {}, stepConfigs[index]?.id || '');
          nextInputs[variable.name] = control.type === 'checkbox'
            ? !!control.checked
            : coerceExternalVariableInput(control.value, variable.type);
          stepConfigs[index].inputs = nextInputs;
          updateStateStepConfigs();
        };
        control.addEventListener('input', applyValue);
        control.addEventListener('change', applyValue);
        valueColumn.appendChild(control);

        if (variable.description) {
          const description = document.createElement('div');
          description.className = 'form-hint';
          description.textContent = variable.description;
          valueColumn.appendChild(description);
        }

        group.appendChild(valueColumn);
        wrapper.appendChild(group);
      });

      return wrapper;
    }

    function renderStepsTable() {
      stepsTableBody.innerHTML = '';
      if (!stepConfigs.length) {
        stepsTableBody.innerHTML = '<div class="text-secondary">No steps yet. Add a script from the list.</div>';
        return;
      }
      ensureAutomaticStepLinkCodes();
      stepConfigs.forEach((cfg, index) => {
        const inputs = normalizeEditableStepInputs(cfg.inputs || {}, cfg.id || '');
        const meta = getStepDisplayMeta(cfg);
        const card = document.createElement('section');
        card.className = 'step-card';
        card.dataset.stepIndex = String(index);

        const header = document.createElement('div');
        header.className = 'step-card__header';

        const title = document.createElement('div');
        title.className = 'step-card__title';
        title.innerHTML = `
          <div class="d-flex align-items-center flex-wrap gap-2 min-w-0">
            <span class="badge bg-secondary-lt flex-shrink-0">${index + 1}</span>
            ${meta.exists ? '' : '<i class="ti ti-alert-triangle text-danger" aria-hidden="true" title="Script bestaat niet meer"></i><span class="visually-hidden">Script bestaat niet meer</span>'}
            <span class="fw-semibold text-truncate">${escapeHtml(meta.title || cfg.id)}</span>
            <span class="small text-secondary text-truncate">${escapeHtml(cfg.id)}</span>
          </div>
        `;
        const stepLink = document.createElement('div');
        stepLink.className = 'd-flex align-items-center flex-wrap gap-2 mt-2';
        stepLink.innerHTML = `
          <code data-step-link-code-text class="text-truncate">No code yet</code>
          <button type="button" data-copy-step-link-code class="btn btn-outline-secondary btn-icon" aria-label="Copy link code" title="Copy link code">
            <i class="ti ti-copy" aria-hidden="true"></i>
          </button>
        `;
        title.appendChild(stepLink);
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

        const actions = document.createElement('div');
        actions.className = 'step-card__actions';

        const body = document.createElement('div');
        body.className = 'step-card__body';
        body.id = `stepCardBody${index}`;
        body.hidden = true;

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
          stepConfigs[index].inputs = { ...normalizeEditableStepInputs(stepConfigs[index].inputs || {}, stepConfigs[index]?.id || ''), repeat: nextRepeat };
          updateStateStepConfigs();
        };
        repeat.addEventListener('input', (e) => {
          stepConfigs[index].inputs = { ...normalizeEditableStepInputs(stepConfigs[index].inputs || {}, stepConfigs[index]?.id || ''), repeat: Math.max(1, Math.floor(Number(e.target.value) || 1)) };
        });
        repeat.addEventListener('change', (e) => applyRepeatValue(e.target));
        repeat.addEventListener('blur', (e) => applyRepeatValue(e.target));

        const repeatGroup = document.createElement('div');
        repeatGroup.className = 'step-variable-row';
        const repeatLabel = document.createElement('label');
        repeatLabel.className = 'form-label small';
        repeatLabel.textContent = 'Repeat';
        const repeatValueColumn = document.createElement('div');
        repeatValueColumn.appendChild(repeat);
        repeatGroup.appendChild(repeatLabel);
        repeatGroup.appendChild(repeatValueColumn);

        const variablesEditor = createExternalVariablesEditor(cfg, index);
        variablesEditor.prepend(repeatGroup);
        body.appendChild(variablesEditor);

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

        const collapse = createStepActionButton('collapse', 'Expand variables');
        collapse.innerHTML = '<i class="ti ti-chevron-right" aria-hidden="true"></i>';
        collapse.setAttribute('aria-expanded', 'false');
        collapse.setAttribute('aria-controls', body.id);
        collapse.addEventListener('click', () => {
          const isExpanded = collapse.getAttribute('aria-expanded') === 'true';
          collapse.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
          collapse.setAttribute('title', isExpanded ? 'Expand variables' : 'Collapse variables');
          collapse.setAttribute('aria-label', isExpanded ? 'Expand variables' : 'Collapse variables');
          collapse.innerHTML = isExpanded
            ? '<i class="ti ti-chevron-right" aria-hidden="true"></i>'
            : '<i class="ti ti-chevron-down" aria-hidden="true"></i>';
          body.hidden = isExpanded;
        });

        [run, moveUp, moveDown, remove, collapse].forEach((node) => actions.appendChild(node));
        header.appendChild(title);
        header.appendChild(actions);
        card.appendChild(header);
        card.appendChild(body);
        stepsTableBody.appendChild(card);
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
          '/braillestudio/components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1',
          'https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1'
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
          '/braillestudio/components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1',
          'https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.js?v=20260529-mode-label-1'
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
        sameOriginRunner: new URL(RUNNER_URL, window.location.href).origin === window.location.origin,
        runnerState: getRunnerDebugState()
      });
      const app = await waitForRunnerReady();
      appendStatus('Step runner ready.', getRunnerDebugState());
      const requireExplicitCompletion = workspaceStateContainsBlockType(scriptData.blockly, 'lesson_complete_step')
        || workspaceStateContainsBlockType(scriptData.blockly, 'lesson_complete_lesson');
      const result = await app.runWorkspaceStateHeadless({
        state: scriptData.blockly,
        sourceName: scriptData.title || stepConfig.id,
        lessonData: basisItems,
        lessonMethod: method,
        index: Number(state.basisIndex ?? 0),
        stepInputs: normalizeEditableStepInputs(stepConfig.inputs || {}, stepConfig.id || ''),
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
        if (app && typeof app.stopAudio === 'function') {
          await app.stopAudio();
        }
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

    async function stopLessonAndAudio() {
      appendStatus('Stop requested for lesson script and audio.');
      const app = await waitForRunnerReady(5000);
      const audioStopped = app && typeof app.stopAudio === 'function'
        ? await app.stopAudio()
        : false;

      if (currentRunningStepIndex >= 0) {
        await stopCurrentStep({ alsoStopLesson: true });
      } else {
        isStoppingLesson = true;
        renderStepRunButtons();
        try {
          if (app && typeof app.stopProgram === 'function') {
            await app.stopProgram();
          }
        } finally {
          isLessonRunning = false;
          isStoppingLesson = false;
          renderStepRunButtons();
        }
      }

      appendStatus('Lesson script and audio stopped.', {
        audioStopped: Boolean(audioStopped)
      });
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
        setLoadingMessage('Methodegegevens laden.');
        appendStatus('Initial preload started.', {
          href: window.location.href,
          runnerUrl: RUNNER_URL,
          pageAgeMs: Number((performance.now() - pageStartedAt).toFixed(1)),
          sharedLoaded: Boolean(shared)
        });
        state = shared.loadState();
        const method = shared.getDraftMethodMeta(state);
        appendStatus('Draft method state loaded.', {
          method,
          stateKeys: Object.keys(state || {}),
          lessonId: state.lessonId || '',
          basisIndex: state.basisIndex ?? null
        });
        const runnerWarmupPromise = waitForRunnerReady(15000).catch((err) => {
          appendStatus('Runner warmup failed during init.', {
            error: err.message || String(err),
            runnerState: getRunnerDebugState()
          });
          return null;
        });
        setLoadingMessage('Basisdata laden.');
        basisItems = await timeStep('Basisdata load', async () => {
          const items = await shared.loadBasisData(method.dataSource || shared.DEFAULT_BASIS_DATA_URL);
          appendStatus('Basisdata loaded.', {
            source: method.dataSource || shared.DEFAULT_BASIS_DATA_URL,
            count: Array.isArray(items) ? items.length : 0
          });
          return items;
        });
        setLoadingMessage('Blockly scripts laden.');
        scriptsCache = await timeStep('Blockly scripts list', async () => {
          const items = await shared.listScripts();
          appendStatus('Blockly scripts loaded.', {
            count: Array.isArray(items) ? items.length : 0,
            ids: Array.isArray(items) ? items.slice(0, 8).map((item) => item?.id || '') : []
          });
          return items;
        });
        setLoadingMessage('Lessons laden.');
        await timeStep('Lessons list', async () => {
          const items = await refreshLessonsCache();
          appendStatus('Lessons loaded.', {
            methodId: getCurrentMethodId(),
            count: Array.isArray(items) ? items.length : 0
          });
          return items;
        });
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
            setLoadingMessage('Lesson steps laden.');
            const loadedLesson = await timeStep('Current lesson load', () => shared.loadLesson(state.lessonId));
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
        setLoadingMessage('Blockly scripts voor de steps voorbereiden.');
        const preloadSummary = await timeStep('Referenced scripts preload', () => preloadReferencedScriptData(stepConfigs));
        setLoadingMessage('Editor klaarzetten.');
        await timeStep('Selected script metadata refresh', () => refreshSelectedScriptMeta());
        await timeStep('Runner warmup wait', () => runnerWarmupPromise);
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
        hideLoadingScreen();
      } catch (err) {
        appendStatus('Initial preload failed.', {
          error: err.message || String(err),
          stack: err.stack || '',
          runnerState: getRunnerDebugState()
        });
        setStatus(`Init error: ${err.message}`);
        showLoadingError(`Init error: ${err.message}`);
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

    addStepBtn.addEventListener('click', async () => {
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
      let externalVariables = [];
      try {
        const scriptData = await loadScriptData(scriptsSelect.value);
        externalVariables = getExternalVariablesFromScriptData(scriptData);
      } catch (err) {
        appendStatus('Could not load selected script external variables.', {
          scriptId: scriptsSelect.value,
          error: err.message || String(err)
        });
      }
      const inputs = { repeat: 1 };
      externalVariables.forEach((variable) => {
        inputs[variable.name] = parseExternalVariableDefault(variable);
      });
      appendStatus('Before add steps length.', { length: stepConfigs.length });
      stepConfigs.push({
        id: scriptsSelect.value,
        title: String(selectedScript?.title || '').trim(),
        inputs
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
      try {
        await saveCurrentLesson();
      } catch (err) {
        showDebugLog();
        setStatus(`Save error: ${err.message}`);
      }
    });

    document.getElementById('deleteLessonBtn').addEventListener('click', async () => {
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

    stopLessonBtn.addEventListener('click', async () => {
      try {
        await stopLessonAndAudio();
      } catch (err) {
        setStatus(`Stop error: ${err.message}`);
      }
    });

    toggleDebugLogBtn.addEventListener('click', () => {
      isDebugLogVisible = !isDebugLogVisible;
      renderDebugLogVisibility();
    });

    clearDebugLogBtn.addEventListener('click', () => {
      statusBox.replaceChildren();
    });

    copyDebugLogBtn.addEventListener('click', async () => {
      const text = statusBox.innerText || stepsLoadingDebugLog?.textContent || '';
      try {
        if (navigator.clipboard?.writeText) {
          try {
            await navigator.clipboard.writeText(text);
            appendStatus('Debug log copied to clipboard.');
            return;
          } catch (err) {
            appendStatus('Clipboard API copy failed, trying textarea fallback.', {
              error: err.message || String(err)
            });
          }
        }
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();
        if (!copied) {
          throw new Error('textarea copy fallback failed');
        }
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
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
