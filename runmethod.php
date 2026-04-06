<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function read_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function normalize_id(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_-]/', '-', $value);
    return trim((string)$value, '-_');
}

function normalize_step_inputs($inputs): array
{
    if (!is_array($inputs)) {
        $inputs = [];
    }
    $letters = $inputs['letters'] ?? [];
    if (!is_array($letters)) {
        $letters = preg_split('/[\r\n,]+/', (string)$letters) ?: [];
    }
    $letters = array_values(array_filter(array_map(
        static fn($item): string => trim((string)$item),
        $letters
    )));

    return [
        'text' => trim((string)($inputs['text'] ?? '')),
        'word' => trim((string)($inputs['word'] ?? '')),
        'letters' => $letters,
        'repeat' => max(1, (int)($inputs['repeat'] ?? 1)),
    ];
}

$rootDir = __DIR__;
$methodDirs = [
    $rootDir . '/api/methods-data',
    $rootDir . '/methods-data',
];
$lessonDirs = [
    $rootDir . '/api/lessons-data',
    $rootDir . '/lessons-data',
];
$defaultRunnerUrl = '/braillestudio/blockly/index.html';
$blocklyApiBase = '/braillestudio/blockly-api';

$methodId = normalize_id((string)($_GET['id'] ?? $_GET['method'] ?? ''));
$errorMessage = '';
$method = null;
$basisRecords = [];
$lessons = [];

if ($methodId === '') {
    $errorMessage = 'Missing method id. Use runmethod.php?id=your-method-id';
} else {
    $method = null;
    foreach ($methodDirs as $methodsDir) {
        $methodPath = $methodsDir . '/' . $methodId . '.json';
        $method = read_json_file($methodPath);
        if (is_array($method)) {
            break;
        }
    }
    if (!$method) {
        $errorMessage = 'Method not found.';
    } else {
        $basisFile = trim((string)($method['basisFile'] ?? ''));
        $dataSource = trim((string)($method['dataSource'] ?? ''));
        $basisPath = '';
        if ($basisFile !== '') {
            $basisPath = $rootDir . '/klanken/' . basename($basisFile);
        } elseif ($dataSource !== '') {
            $basisPath = $rootDir . '/klanken/' . basename(parse_url($dataSource, PHP_URL_PATH) ?: '');
        }
        $basisData = read_json_file($basisPath);
        if (!is_array($basisData)) {
            $errorMessage = 'Basisbestand kon niet geladen worden.';
        } else {
            $basisRecords = array_values($basisData);
            foreach ($lessonDirs as $lessonsDir) {
                $lessonFiles = glob($lessonsDir . '/*.json') ?: [];
                foreach ($lessonFiles as $lessonFile) {
                    $lesson = read_json_file($lessonFile);
                    if (!is_array($lesson)) {
                        continue;
                    }
                    $lessonMethodId = trim((string)($lesson['methodId'] ?? ($lesson['method']['id'] ?? '')));
                    if ($lessonMethodId !== $methodId) {
                        continue;
                    }

                    $rawStepConfigs = [];
                    if (is_array($lesson['stepConfigs'] ?? null)) {
                        $rawStepConfigs = $lesson['stepConfigs'];
                    } elseif (is_array($lesson['meta']['stepConfigs'] ?? null)) {
                        $rawStepConfigs = $lesson['meta']['stepConfigs'];
                    }
                    $stepConfigs = [];
                    foreach ($rawStepConfigs as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $stepId = trim((string)($row['id'] ?? ''));
                        if ($stepId === '') {
                            continue;
                        }
                        $stepConfigs[] = [
                            'id' => $stepId,
                            'title' => trim((string)($row['title'] ?? $row['scriptTitle'] ?? '')),
                            'description' => trim((string)($row['description'] ?? $row['scriptDescription'] ?? ($row['meta']['description'] ?? ''))),
                            'instruction' => trim((string)($row['instruction'] ?? $row['scriptInstruction'] ?? ($row['meta']['instruction'] ?? ''))),
                            'inputs' => normalize_step_inputs($row['inputs'] ?? []),
                        ];
                    }

                    $lessonId = trim((string)($lesson['id'] ?? pathinfo($lessonFile, PATHINFO_FILENAME)));
                    if ($lessonId !== '' && isset($lessons[$lessonId])) {
                        continue;
                    }
                    $lessons[$lessonId] = [
                        'id' => $lessonId,
                        'title' => trim((string)($lesson['title'] ?? '')),
                        'meta' => [
                            'title' => trim((string)(($lesson['meta']['title'] ?? null) ?? ($lesson['title'] ?? ''))),
                            'description' => trim((string)(($lesson['meta']['description'] ?? null) ?? ($lesson['description'] ?? ''))),
                        ],
                        'basisIndex' => array_key_exists('basisIndex', $lesson) ? (int)$lesson['basisIndex'] : (int)($lesson['meta']['basisIndex'] ?? -1),
                        'basisWord' => trim((string)($lesson['basisWord'] ?? ($lesson['meta']['basisWord'] ?? ''))),
                        'lessonNumber' => array_key_exists('lessonNumber', $lesson) ? (int)$lesson['lessonNumber'] : (int)($lesson['meta']['lessonNumber'] ?? 1),
                        'basisRecord' => is_array($lesson['basisRecord'] ?? null) ? $lesson['basisRecord'] : (is_array($lesson['meta']['basisRecord'] ?? null) ? $lesson['meta']['basisRecord'] : []),
                        'stepConfigs' => $stepConfigs,
                    ];
                }
            }

            $lessons = array_values($lessons);

            usort($lessons, static function (array $a, array $b): int {
                $indexCompare = ((int)$a['basisIndex']) <=> ((int)$b['basisIndex']);
                if ($indexCompare !== 0) {
                    return $indexCompare;
                }
                return ((int)$a['lessonNumber']) <=> ((int)$b['lessonNumber']);
            });
        }
    }
}

$pagePayload = [
    'methodId' => $methodId,
    'method' => $method,
    'basisRecords' => $basisRecords,
    'lessons' => $lessons,
    'runnerUrl' => $defaultRunnerUrl,
    'blocklyApiBase' => $blocklyApiBase,
    'error' => $errorMessage,
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($method['title'] ?? $methodId ?: 'Run Method') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/braillestudio/components/braille-monitor/braillemonitor.css">
  <link rel="stylesheet" href="https://www.tastenbraille.com/braillestudio/components/braille-monitor/braillemonitor.css">
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="mx-auto max-w-7xl p-6 space-y-5">
    <header class="relative h-[80px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <?php if (trim((string)($method['imageUrl'] ?? '')) !== ''): ?>
        <img
          src="<?= h(trim((string)$method['imageUrl'])) ?>"
          alt="Method banner"
          class="absolute inset-0 h-full w-full object-cover"
        >
      <?php endif; ?>
      <div class="absolute inset-0 bg-white/70"></div>
      <div class="relative z-10 flex h-full items-center gap-4 px-5">
        <div class="min-w-0">
          <h1 class="truncate text-3xl font-bold"><?= h($method['title'] ?? $methodId ?: 'Run Method') ?></h1>
        </div>
      </div>
    </header>

    <?php if ($errorMessage !== ''): ?>
      <section class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-700">
        <?= h($errorMessage) ?>
      </section>
    <?php else: ?>
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div id="brailleMonitorComponent" class="overflow-hidden"></div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between gap-3">
          <span id="lessonRunIndicator" class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">
            <span id="lessonRunIndicatorDot" class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
            <span id="lessonRunIndicatorText">Not running</span>
          </span>
          <button id="toggleRunnerBtn" type="button" class="ml-auto rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Unhide</button>
        </div>
        <div class="flex flex-wrap gap-2">
          <button id="runSelectedStepBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Run from selected step</button>
          <button id="runCurrentBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Run current lesson</button>
          <button id="runAllBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Run all lessons</button>
          <button id="stopRunBtn" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Stop</button>
        </div>
        <div id="runnerPanel" class="hidden space-y-4">
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 space-y-3">
            <div class="font-semibold text-slate-900">Lesson return values</div>
            <div id="lessonReturnValues" class="h-[180px] overflow-auto text-sm leading-6 text-slate-600">No values yet.</div>
          </div>
        </div>
      </section>

      <div class="grid gap-5 lg:grid-cols-[0.95fr_1.05fr]">
        <section class="flex h-[780px] flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
          <div class="text-lg font-bold">Lessons</div>
          <div id="lessonsList" class="flex-1 space-y-2 overflow-auto"></div>
        </section>

        <section class="flex h-[780px] flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
          <div class="text-lg font-bold">Steps</div>
          <div id="stepsPreview" class="flex-1 space-y-2 overflow-auto"></div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 space-y-2">
            <div class="font-semibold text-slate-900">Instruction</div>
            <div id="selectedStepInstruction" class="max-h-[220px] overflow-auto whitespace-pre-wrap leading-6 text-slate-700">No instruction for the selected step.</div>
          </div>
        </section>
      </div>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
        <div class="flex items-center justify-between gap-3">
          <div class="text-lg font-bold">Debug log</div>
          <button id="toggleDebugLogBtn" type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Unhidden</button>
        </div>
        <pre id="statusBox" class="hidden min-h-[220px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
      </section>

      <iframe id="lessonRunnerFrame" src="<?= h($defaultRunnerUrl) ?>" title="Method runner" hidden></iframe>
    <?php endif; ?>

    <footer class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm text-slate-600 shadow-sm">
      <div class="font-medium text-slate-700"><?= h($methodId) ?></div>
      <div class="flex items-center gap-3">
        <span>Powerd by</span>
        <a href="https://www.bartimeus.nl" target="_blank" rel="noopener noreferrer">
          <img
            src="https://www.tastenbraille.com/braillestudio/assets/bartimeus.png"
            alt="Bartimeus logo"
            class="h-8 w-auto object-contain"
          >
        </a>
      </div>
    </footer>
  </div>

  <script>
    window.RunMethodBootstrap = <?= json_encode($pagePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <?php if ($errorMessage === ''): ?>
  <script>
    const bootstrap = window.RunMethodBootstrap || {};
    const method = bootstrap.method || {};
    const basisRecords = Array.isArray(bootstrap.basisRecords) ? bootstrap.basisRecords : [];
    const lessons = Array.isArray(bootstrap.lessons) ? bootstrap.lessons : [];
    const runnerUrl = String(bootstrap.runnerUrl || '');
    const blocklyApiBase = String(bootstrap.blocklyApiBase || '');
    const BRAILLE_MONITOR_PLACEHOLDER = 'Bartiméus Education';

    const lessonsList = document.getElementById('lessonsList');
    const stepsPreview = document.getElementById('stepsPreview');
    const selectedStepInstruction = document.getElementById('selectedStepInstruction');
    const statusBox = document.getElementById('statusBox');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');
    const brailleMonitorStatus = document.getElementById('brailleMonitorStatus');
    const lessonRunIndicator = document.getElementById('lessonRunIndicator');
    const lessonRunIndicatorDot = document.getElementById('lessonRunIndicatorDot');
    const lessonRunIndicatorText = document.getElementById('lessonRunIndicatorText');
    const lessonReturnValues = document.getElementById('lessonReturnValues');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const toggleRunnerBtn = document.getElementById('toggleRunnerBtn');
    const runnerPanel = document.getElementById('runnerPanel');
    const stopRunBtn = document.getElementById('stopRunBtn');
    const runSelectedStepBtn = document.getElementById('runSelectedStepBtn');

    let selectedLessonIndex = lessons.length ? 0 : -1;
    let selectedStepIndex = 0;
    let isLessonRunning = false;
    let isDebugLogVisible = false;
    let isRunnerVisible = false;
    let stopRequested = false;
    let scriptsCache = [];
    let brailleMonitorUi = null;
    let brailleMonitorSyncTimer = null;
    let lastBrailleSnapshot = '';

    function showBrailleMonitorPlaceholder() {
      if (!brailleMonitorUi || typeof brailleMonitorUi.setText !== 'function') return;
      brailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
      lastBrailleSnapshot = JSON.stringify({
        placeholder: BRAILLE_MONITOR_PLACEHOLDER
      });
    }

    function escapeHtml(value) {
      return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    function formatValue(value) {
      if (value == null) return 'null';
      if (typeof value === 'string') return value;
      if (typeof value === 'number' || typeof value === 'boolean') return String(value);
      try {
        return JSON.stringify(value);
      } catch (err) {
        return String(value);
      }
    }

    function formatDebugData(value) {
      if (value == null) return '';
      if (typeof value === 'string') return value;
      try {
        return JSON.stringify(value, null, 2);
      } catch (err) {
        return String(value);
      }
    }

    function flattenCompletionValues(completion, prefix = '') {
      const rows = [];
      if (!completion || typeof completion !== 'object') {
        return rows;
      }
      Object.entries(completion).forEach(([key, value]) => {
        const path = prefix ? `${prefix}.${key}` : key;
        if (value && typeof value === 'object' && !Array.isArray(value)) {
          rows.push(...flattenCompletionValues(value, path));
          return;
        }
        rows.push({
          key: path,
          value: formatValue(value)
        });
      });
      return rows;
    }

    function renderLessonReturnValues(entries = []) {
      if (!Array.isArray(entries) || !entries.length) {
        lessonReturnValues.textContent = 'No values yet.';
        return;
      }
      lessonReturnValues.innerHTML = `
        <ul class="list-disc space-y-1 pl-5">
          ${entries.map((entry) => `
            <li>
              <span class="font-semibold text-slate-900">${escapeHtml(entry.key)}:</span>
              <span class="break-all">${escapeHtml(entry.value)}</span>
            </li>
          `).join('')}
        </ul>
      `;
    }

    function setLessonRunningState(running, label = '') {
      isLessonRunning = Boolean(running);
      if (!lessonRunIndicator || !lessonRunIndicatorDot || !lessonRunIndicatorText) return;
      lessonRunIndicator.className = isLessonRunning
        ? 'inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-semibold text-green-700'
        : 'inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700';
      lessonRunIndicatorDot.className = isLessonRunning
        ? 'h-2.5 w-2.5 rounded-full bg-green-500'
        : 'h-2.5 w-2.5 rounded-full bg-red-500';
      lessonRunIndicatorText.textContent = label || (isLessonRunning ? 'Running' : 'Not running');
    }

    function renderDebugLogVisibility() {
      if (!statusBox || !toggleDebugLogBtn) return;
      statusBox.classList.toggle('hidden', !isDebugLogVisible);
      toggleDebugLogBtn.textContent = isDebugLogVisible ? 'Hidden' : 'Unhidden';
    }

    function renderRunnerVisibility() {
      if (!runnerPanel || !toggleRunnerBtn) return;
      runnerPanel.classList.toggle('hidden', !isRunnerVisible);
      toggleRunnerBtn.textContent = isRunnerVisible ? 'Hide' : 'Unhide';
    }

    function appendStatus(message, data = null) {
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const block = data != null
        ? `[${timestamp}] ${message}\n${formatDebugData(data)}`
        : `[${timestamp}] ${message}`;
      statusBox.textContent = statusBox.textContent ? `${statusBox.textContent}\n\n${block}` : block;
      statusBox.scrollTop = statusBox.scrollHeight;
    }

    function getStepDebugSnapshot(stepConfig, stepIndex) {
      const inputs = stepConfig?.inputs || {};
      return {
        stepIndex,
        scriptId: String(stepConfig?.id || '').trim(),
        title: String(stepConfig?.title || '').trim(),
        description: String(stepConfig?.description || '').trim(),
        inputs: {
          text: String(inputs.text || ''),
          word: String(inputs.word || ''),
          letters: Array.isArray(inputs.letters) ? [...inputs.letters] : [],
          repeat: Number(inputs.repeat || 1)
        }
      };
    }

    function getRunnerRuntimeSnapshot(app = null) {
      try {
        const runnerApp = app || getRunnerWindow()?.BrailleBlocklyApp || null;
        if (!runnerApp || typeof runnerApp.getRuntimeSnapshot !== 'function') {
          return null;
        }
        const runtime = runnerApp.getRuntimeSnapshot();
        return runtime && typeof runtime === 'object' ? runtime : null;
      } catch (err) {
        return { error: err.message || String(err) };
      }
    }

    function getBasisWord(item, fallbackIndex = 0) {
      const word = String(item?.word || '').trim();
      return word || `item-${fallbackIndex + 1}`;
    }

    function renderMethodInfo() {
    }

    function getSelectedLesson() {
      return selectedLessonIndex >= 0 ? lessons[selectedLessonIndex] : null;
    }

    function getScriptItemById(id) {
      const scriptId = String(id || '').trim();
      return scriptsCache.find((item) => String(item?.id || '').trim() === scriptId) || null;
    }

    function getStepDisplayMeta(stepConfig) {
      const script = getScriptItemById(stepConfig?.id);
      return {
        title: String(stepConfig?.title || script?.title || '').trim(),
        description: String(stepConfig?.description || script?.meta?.description || '').trim(),
        instruction: String(stepConfig?.instruction || script?.meta?.instruction || '').trim()
      };
    }

    function hydrateLessonsWithScriptMetadata() {
      lessons.forEach((lesson) => {
        if (!Array.isArray(lesson?.stepConfigs)) {
          lesson.stepConfigs = [];
          return;
        }
        lesson.stepConfigs = lesson.stepConfigs.map((cfg) => {
          const script = getScriptItemById(cfg?.id);
          return {
            ...cfg,
            title: String(cfg?.title || script?.title || '').trim(),
            description: String(cfg?.description || script?.meta?.description || '').trim(),
            instruction: String(cfg?.instruction || script?.meta?.instruction || '').trim()
          };
        });
      });
    }

    function renderSelectedStepInstruction(lesson = null) {
      if (!selectedStepInstruction) return;
      const activeLesson = lesson || getSelectedLesson();
      const steps = Array.isArray(activeLesson?.stepConfigs) ? activeLesson.stepConfigs : [];
      const selectedStep = steps[selectedStepIndex] || null;
      const meta = selectedStep ? getStepDisplayMeta(selectedStep) : null;
      const instruction = String(meta?.instruction || '').trim();
      selectedStepInstruction.textContent = instruction || 'No instruction for the selected step.';
    }

    function renderLessonsList() {
      lessonsList.innerHTML = '';
      if (!lessons.length) {
        lessonsList.innerHTML = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">No lessons found for this method.</div>';
        return;
      }
      lessons.forEach((lesson, index) => {
        const lessonTitle = String(lesson?.meta?.title || lesson.title || lesson.id).trim();
        const lessonDescription = String(lesson?.meta?.description || '').trim();
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `w-full rounded-xl border px-4 py-3 text-left ${index === selectedLessonIndex ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'}`;
        button.innerHTML = `
          <div class="font-bold">${lessonTitle}</div>
          ${lessonDescription ? `<div class="mt-1 text-xs text-slate-600">${escapeHtml(lessonDescription)}</div>` : ''}
          <div class="mt-1 text-xs text-slate-500">Word: ${lesson.basisWord || getBasisWord(lesson.basisRecord, lesson.basisIndex || index)}</div>
          <div class="mt-1 text-xs text-slate-500">${Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs.length : 0} step(s)</div>
        `;
        button.addEventListener('click', () => {
          selectedLessonIndex = index;
          renderLessonsList();
          renderCurrentLesson();
        });
        lessonsList.appendChild(button);
      });
    }

    function renderCurrentLesson() {
      const lesson = getSelectedLesson();
      if (!lesson) {
        stepsPreview.textContent = 'No steps.';
        renderSelectedStepInstruction(null);
        renderLessonReturnValues([]);
        return;
      }
      const lessonTitle = String(lesson?.meta?.title || lesson.title || lesson.id).trim();
      const stepCount = Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs.length : 0;
      if (stepCount === 0) {
        selectedStepIndex = 0;
      } else if (selectedStepIndex < 0 || selectedStepIndex >= stepCount) {
        selectedStepIndex = 0;
      }
      const preview = (Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs : []).map((step) => {
        const meta = getStepDisplayMeta(step);
        const inputs = step.inputs || {};
        const parts = [];
        if (inputs.text) parts.push(`text: ${inputs.text}`);
        if (inputs.word) parts.push(`word: ${inputs.word}`);
        if (Array.isArray(inputs.letters) && inputs.letters.length) parts.push(`letters: ${inputs.letters.join(', ')}`);
        if (Number(inputs.repeat || 1) > 1) parts.push(`repeat: ${inputs.repeat}`);
        return {
          id: step.id,
          title: meta.title,
          description: meta.description,
          detail: parts.length ? parts.join(' | ') : ''
        };
      });
      stepsPreview.innerHTML = preview.length
        ? `${preview.map((item, index) => `
            <button type="button" data-step-index="${index}" class="w-full rounded-xl border px-4 py-3 text-left ${index === selectedStepIndex ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'}">
              <div class="font-bold text-slate-900">${index + 1}. ${escapeHtml(item.title || item.id)}</div>
              <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.id)}</div>
              ${item.description ? `<div class="mt-1 text-xs text-slate-600">${escapeHtml(item.description)}</div>` : ''}
              <div class="mt-1 text-xs text-slate-500">${item.detail ? escapeHtml(item.detail) : 'No injected inputs.'}</div>
            </button>
          `).join('')}`
        : 'No steps.';
      stepsPreview.querySelectorAll('[data-step-index]').forEach((button) => {
        button.addEventListener('click', () => {
          selectedStepIndex = Number(button.getAttribute('data-step-index') || 0);
          renderCurrentLesson();
        });
      });
      if (isLessonRunning) {
        const activeButton = stepsPreview.querySelector(`[data-step-index="${selectedStepIndex}"]`);
        if (activeButton && typeof activeButton.scrollIntoView === 'function') {
          activeButton.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      }
      renderSelectedStepInstruction(lesson);
      if (!isLessonRunning) {
        renderLessonReturnValues([]);
      }
    }

    function getRunnerWindow() {
      return lessonRunnerFrame?.contentWindow || null;
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
            document.body.appendChild(script);
          });
          return src;
        } catch (err) {
          lastError = err;
        }
      }
      throw lastError || new Error('No braille monitor script could be loaded');
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

    async function syncBrailleMonitorFromRunner() {
      try {
        const monitor = await ensureBrailleMonitorReady();
        const runner = getRunnerWindow();
        const app = runner?.BrailleBlocklyApp;
        if (!app || typeof app.getRuntimeSnapshot !== 'function') {
          if (lastBrailleSnapshot !== JSON.stringify({ placeholder: BRAILLE_MONITOR_PLACEHOLDER })) {
            showBrailleMonitorPlaceholder();
          }
          return;
        }
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
        if (signature === lastBrailleSnapshot) {
          return;
        }
        lastBrailleSnapshot = signature;
        if (!brailleUnicode && !sourceText) {
          showBrailleMonitorPlaceholder();
          return;
        }
        monitor.setBrailleUnicode(brailleUnicode, sourceText, {
          caretPosition: Number.isInteger(runtime?.cellCaret) ? runtime.cellCaret : undefined,
          textCaretPosition: Number.isInteger(runtime?.textCaret) ? runtime.textCaret : undefined,
          caretVisible: typeof runtime?.caretVisible === 'boolean' ? runtime.caretVisible : true
        });
      } catch (err) {
        if (brailleMonitorUi && typeof brailleMonitorUi.setText === 'function') {
          brailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
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

    function getRunnerDebugState() {
      try {
        const runner = getRunnerWindow();
        if (!runner) return { ready: false, reason: 'no-content-window' };
        const boot = runner.BrailleBlocklyBoot || null;
        return {
          ready: Boolean(runner.BrailleBlocklyApp && boot?.stage === 'api-ready'),
          hasApp: Boolean(runner.BrailleBlocklyApp),
          bootStage: boot?.stage || '',
          bootError: boot?.error || '',
          href: runner.location?.href || '',
          title: runner.document?.title || '',
          readyState: runner.document?.readyState || ''
        };
      } catch (err) {
        return { ready: false, reason: 'runner-state-error', error: err.message || String(err) };
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
      throw new Error(`Runner not ready (stage: ${lastState.bootStage || lastState.reason || 'unknown'}${lastState.bootError ? `, error: ${lastState.bootError}` : ''})`);
    }

    async function loadScriptData(id) {
      const url = `${blocklyApiBase}/load.php?id=${encodeURIComponent(id)}`;
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error(`Failed to load script ${id} (HTTP ${res.status})`);
      const data = await res.json();
      if (!data || !data.blockly) throw new Error(`Script ${id} has no blockly state`);
      return data;
    }

    async function loadScriptsList() {
      const url = `${blocklyApiBase}/list.php`;
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error(`Failed to load script list (HTTP ${res.status})`);
      const data = await res.json();
      return Array.isArray(data?.items) ? data.items : [];
    }

	    async function waitForCompletion(app, timeoutMs = 30000) {
	      const start = Date.now();
        let lastRuntime = null;
	      while (Date.now() - start < timeoutMs) {
        if (stopRequested) {
          return {
            status: 'stopped',
            output: null,
            analytics: {
              score: null,
              maxScore: null,
              attempts: null,
              durationMs: null,
              isCorrect: false
            },
            response: {
              answer: null,
              expectedAnswer: null,
              feedback: 'Stopped by user'
            },
            metadata: null
          };
        }
	        const completion = app.getStepCompletion();
	        if (completion) {
            appendStatus('waitForCompletion: completion detected.', {
              completion,
              runtime: getRunnerRuntimeSnapshot(app)
            });
            return completion;
          }
	        const runtime = app.getRuntimeSnapshot();
          lastRuntime = runtime;
	        if (runtime?.programEndedCompletedGeneration === runtime?.programEndedGeneration && runtime?.programEndedGeneration >= 0) {
            appendStatus('waitForCompletion: program ended without explicit completion.', {
              runtime
            });
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

    async function runLessonStep(lesson, stepConfig, stepIndex, app = null) {
      if (!lesson) throw new Error('No lesson selected');
      if (!stepConfig) throw new Error('No step selected');
      const runnerApp = app || await waitForRunnerReady();
      const basisIndex = Number(lesson.basisIndex ?? -1);
      appendStatus('runLessonStep: loading script.', {
        lessonId: lesson.id,
        basisIndex,
        step: getStepDebugSnapshot(stepConfig, stepIndex),
        runnerState: getRunnerDebugState(),
        runtimeBefore: getRunnerRuntimeSnapshot(runnerApp)
      });
      const scriptData = await loadScriptData(stepConfig.id);
      appendStatus('runLessonStep: script loaded.', {
        lessonId: lesson.id,
        stepIndex,
        scriptId: stepConfig.id,
        scriptTitle: scriptData.title || '',
        hasBlockly: Boolean(scriptData.blockly)
      });
      const result = await runnerApp.runWorkspaceStateHeadless({
        state: scriptData.blockly,
        sourceName: scriptData.title || stepConfig.title || stepConfig.id,
        lessonData: basisRecords,
        lessonMethod: method,
        index: basisIndex >= 0 ? basisIndex : 0,
        stepInputs: stepConfig.inputs || {},
        stepMeta: {
          id: stepConfig.id,
          stepIndex,
          lessonId: lesson.id,
          lessonTitle: lesson.title,
          lessonNumber: Number(lesson.lessonNumber || 1),
          methodId: method.id || '',
          methodTitle: method.title || '',
          basisIndex,
          basisWord: lesson.basisWord || '',
          basisRecord: lesson.basisRecord || null
        },
        lockInjectedRecord: true
      });
      const completion = result?.stepCompletion || await waitForCompletion(runnerApp);
      appendStatus('runLessonStep: runner returned.', {
        lessonId: lesson.id,
        stepIndex,
        scriptId: stepConfig.id,
        resultStepCompletion: result?.stepCompletion || null,
        completion,
        runtimeAfter: getRunnerRuntimeSnapshot(runnerApp),
        resultSummary: {
          generation: result?.generation ?? null,
          startedBlockCount: result?.startedBlockCount ?? null,
          currentRecordIndex: result?.currentRecordIndex ?? null
        }
      });
      return { result, completion, scriptData };
    }

    async function runLesson(lesson) {
      if (!lesson) throw new Error('No lesson selected');
      stopRequested = false;
      const stepConfigs = Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs : [];
      selectedStepIndex = 0;
      renderCurrentLesson();
      const displayedValues = [
        { key: 'lessonId', value: lesson.id || '' },
        { key: 'status', value: 'running' }
      ];
      setLessonRunningState(true, `Running ${lesson.title || lesson.id}`);
      renderLessonReturnValues(displayedValues);
      appendStatus('Lesson run gestart.', {
        lessonId: lesson.id,
        lessonTitle: lesson.title,
        steps: stepConfigs.length,
        selectedLessonIndex,
        selectedStepIndex,
        stepIds: stepConfigs.map((step) => String(step?.id || '')),
        runnerUrl,
        runnerState: getRunnerDebugState()
      });
      const results = [];
      while (selectedStepIndex < stepConfigs.length) {
        const stepIndex = selectedStepIndex;
        renderCurrentLesson();
        if (stopRequested) {
          appendStatus('Lesson handmatig gestopt.', {
            lessonId: lesson.id,
            stepIndex
          });
          break;
        }
        const stepConfig = stepConfigs[stepIndex];
        appendStatus('Step gestart.', {
          lessonId: lesson.id,
          stepIndex,
          scriptId: stepConfig.id
        });
        const { completion } = await runLessonStep(lesson, stepConfig, stepIndex);
        results.push({
          scriptId: stepConfig.id,
          completion
        });
        displayedValues.push({ key: `step.${stepIndex + 1}.scriptId`, value: stepConfig.id });
        flattenCompletionValues(completion).forEach((entry) => {
          displayedValues.push({
            key: `step.${stepIndex + 1}.${entry.key}`,
            value: entry.value
          });
        });
        renderLessonReturnValues(displayedValues);
        appendStatus('Step uitgevoerd.', {
          lessonId: lesson.id,
          scriptId: stepConfig.id,
          completion,
          previousStepIndex: stepIndex
        });
        selectedStepIndex = stepIndex + 1;
        appendStatus('Lesson run: moving to next step.', {
          lessonId: lesson.id,
          previousStepIndex: stepIndex,
          nextStepIndex: selectedStepIndex,
          hasNextStep: selectedStepIndex < stepConfigs.length,
          nextStep: selectedStepIndex < stepConfigs.length ? getStepDebugSnapshot(stepConfigs[selectedStepIndex], selectedStepIndex) : null
        });
        if (selectedStepIndex < stepConfigs.length) {
          renderCurrentLesson();
        }
      }
      const finalStatus = results[results.length - 1]?.completion?.status || 'completed';
      displayedValues.push({ key: 'finalStatus', value: finalStatus });
      renderLessonReturnValues(displayedValues);
      setLessonRunningState(false, `Stopped (${finalStatus})`);
      stopRequested = false;
      return results;
    }

    async function runSelectedStep() {
      try {
        const lesson = getSelectedLesson();
        if (!lesson) throw new Error('No lesson selected');
        const stepConfigs = Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs : [];
        if (!stepConfigs[selectedStepIndex]) throw new Error('No step selected');
        stopRequested = false;
        setLessonRunningState(true, `Running from step ${selectedStepIndex + 1}`);
        appendStatus('Run vanaf geselecteerde step gestart.', {
          lessonId: lesson.id,
          stepIndex: selectedStepIndex,
          scriptId: stepConfigs[selectedStepIndex].id,
          stepIds: stepConfigs.map((step) => String(step?.id || '')),
          runnerUrl,
          runnerState: getRunnerDebugState()
        });
        const displayedValues = [
          { key: 'lessonId', value: lesson.id || '' },
          { key: 'startStepIndex', value: String(selectedStepIndex + 1) },
          { key: 'status', value: 'running' }
        ];
        renderLessonReturnValues(displayedValues);
        const results = [];
        while (selectedStepIndex < stepConfigs.length) {
          const stepIndex = selectedStepIndex;
          renderCurrentLesson();
          if (stopRequested) {
            appendStatus('Run vanaf geselecteerde step handmatig gestopt.', {
              lessonId: lesson.id,
              stepIndex
            });
            break;
          }
          const stepConfig = stepConfigs[stepIndex];
          appendStatus('Step gestart.', {
            lessonId: lesson.id,
            stepIndex,
            scriptId: stepConfig.id
          });
          const { completion } = await runLessonStep(lesson, stepConfig, stepIndex);
          results.push({
            scriptId: stepConfig.id,
            completion
          });
          displayedValues.push({ key: `step.${stepIndex + 1}.scriptId`, value: stepConfig.id });
          flattenCompletionValues(completion).forEach((entry) => {
            displayedValues.push({
              key: `step.${stepIndex + 1}.${entry.key}`,
              value: entry.value
            });
          });
          renderLessonReturnValues(displayedValues);
          appendStatus('Step uitgevoerd.', {
            lessonId: lesson.id,
            stepIndex,
            scriptId: stepConfig.id,
            completion
          });
          selectedStepIndex = stepIndex + 1;
          appendStatus('Run vanaf geselecteerde step: moving to next step.', {
            lessonId: lesson.id,
            previousStepIndex: stepIndex,
            nextStepIndex: selectedStepIndex,
            hasNextStep: selectedStepIndex < stepConfigs.length,
            nextStep: selectedStepIndex < stepConfigs.length ? getStepDebugSnapshot(stepConfigs[selectedStepIndex], selectedStepIndex) : null
          });
          if (selectedStepIndex < stepConfigs.length) {
            renderCurrentLesson();
          }
        }
        const finalStatus = results[results.length - 1]?.completion?.status || 'completed';
        displayedValues.push({ key: 'finalStatus', value: finalStatus });
        renderLessonReturnValues(displayedValues);
        setLessonRunningState(false, `Stopped (${finalStatus})`);
        appendStatus('Run vanaf geselecteerde step afgerond.', {
          lessonId: lesson.id,
          startStepIndex: selectedStepIndex,
          results
        });
      } catch (err) {
        setLessonRunningState(false, 'Stopped (error)');
        renderLessonReturnValues([
          { key: 'error', value: err.message || String(err) }
        ]);
        appendStatus('Run vanaf geselecteerde step mislukt.', {
          error: err.message || String(err)
        });
      }
    }

    async function runCurrentLesson() {
      try {
        const lesson = getSelectedLesson();
        selectedStepIndex = 0;
        renderCurrentLesson();
        appendStatus('Run current lesson button pressed.', {
          selectedLessonIndex,
          lessonId: lesson?.id || '',
          stepCount: Array.isArray(lesson?.stepConfigs) ? lesson.stepConfigs.length : 0
        });
        const results = await runLesson(lesson);
        appendStatus('Current lesson afgerond.', { lessonId: lesson?.id || '', results });
      } catch (err) {
        setLessonRunningState(false, 'Stopped (error)');
        renderLessonReturnValues([
          { key: 'error', value: err.message || String(err) }
        ]);
        appendStatus('Run current lesson mislukt.', {
          error: err.message || String(err),
          runnerState: getRunnerDebugState()
        });
      }
    }

    async function runAllLessons() {
      setLessonRunningState(false, 'Not running');
      stopRequested = false;
      appendStatus('Run all gestart.', { lessons: lessons.length });
      for (let index = 0; index < lessons.length; index += 1) {
        if (stopRequested) {
          setLessonRunningState(false, 'Stopped (manual)');
          appendStatus('Run all handmatig gestopt.');
          stopRequested = false;
          return;
        }
        selectedLessonIndex = index;
        renderLessonsList();
        renderCurrentLesson();
        try {
          const lesson = lessons[index];
          const results = await runLesson(lesson);
        } catch (err) {
          setLessonRunningState(false, 'Stopped (error)');
          renderLessonReturnValues([
            { key: 'error', value: err.message || String(err) }
          ]);
          appendStatus('Run all mislukt.', {
            lessonId: lessons[index]?.id || '',
            error: err.message || String(err)
          });
          return;
        }
      }
      setLessonRunningState(false, 'Not running');
      stopRequested = false;
      appendStatus('Run all afgerond.');
    }

    async function stopCurrentRun() {
      try {
        const app = await waitForRunnerReady(5000);
        if (app && typeof app.stopProgram === 'function') {
          await app.stopProgram();
        }
      } catch (err) {
        appendStatus('Stop requested, but runner was not ready.', {
          error: err.message || String(err)
        });
        return;
      }
      appendStatus('Current step stopped. Runner continues with the next step after program ended.');
    }

    lessonRunnerFrame.addEventListener('load', () => {
      appendStatus('Blockly runner iframe geladen.', {
        runnerUrl,
        runnerState: getRunnerDebugState()
      });
      startBrailleMonitorSync();
    });

    if (runSelectedStepBtn) {
      runSelectedStepBtn.addEventListener('click', runSelectedStep);
    }
    document.getElementById('runCurrentBtn').addEventListener('click', runCurrentLesson);
    document.getElementById('runAllBtn').addEventListener('click', runAllLessons);
    stopRunBtn.addEventListener('click', stopCurrentRun);
    if (toggleRunnerBtn) {
      toggleRunnerBtn.addEventListener('click', () => {
        isRunnerVisible = !isRunnerVisible;
        renderRunnerVisibility();
      });
    }

    if (toggleDebugLogBtn) {
      toggleDebugLogBtn.addEventListener('click', () => {
        isDebugLogVisible = !isDebugLogVisible;
        renderDebugLogVisibility();
      });
    }

    async function init() {
      try {
        scriptsCache = await loadScriptsList();
        hydrateLessonsWithScriptMetadata();
      } catch (err) {
        appendStatus('Script metadata load failed.', {
          error: err.message || String(err)
        });
      }

      renderMethodInfo();
      renderLessonsList();
      renderCurrentLesson();
      setLessonRunningState(false, 'Not running');
      renderDebugLogVisibility();
      renderRunnerVisibility();
      startBrailleMonitorSync();
      appendStatus('Runner ready.', {
        methodId: method.id || '',
        lessons: lessons.length,
        basisRecords: basisRecords.length,
        scripts: scriptsCache.length,
        runnerUrl
      });
    }

    init();
  </script>
  <?php endif; ?>
</body>
</html>
