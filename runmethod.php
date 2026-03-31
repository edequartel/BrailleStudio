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
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="mx-auto max-w-7xl p-6 space-y-5">
    <header class="flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-blue-700">Method Runner</div>
        <h1 class="text-3xl font-bold"><?= h($method['title'] ?? $methodId ?: 'Run Method') ?></h1>
      </div>
      <div class="text-sm text-slate-600"><?= h($methodId) ?></div>
    </header>

    <?php if ($errorMessage !== ''): ?>
      <section class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-700">
        <?= h($errorMessage) ?>
      </section>
    <?php else: ?>
      <div class="grid gap-5 lg:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
          <div class="text-lg font-bold">Lessons</div>
          <div id="methodInfo" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
          <div id="lessonsList" class="max-h-[520px] space-y-2 overflow-auto"></div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
          <div class="text-lg font-bold">Runner</div>
          <div id="currentLessonInfo" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
          <div class="flex flex-wrap gap-2">
            <button id="runCurrentBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Run current lesson</button>
            <button id="runAllBtn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Run all lessons</button>
          </div>
          <div id="stepsPreview" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
        </section>
      </div>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
        <div class="text-lg font-bold">Debug log</div>
        <pre id="statusBox" class="min-h-[220px] rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap"></pre>
      </section>

      <iframe id="lessonRunnerFrame" src="<?= h($defaultRunnerUrl) ?>" title="Method runner" hidden></iframe>
    <?php endif; ?>
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

    const lessonsList = document.getElementById('lessonsList');
    const methodInfo = document.getElementById('methodInfo');
    const currentLessonInfo = document.getElementById('currentLessonInfo');
    const stepsPreview = document.getElementById('stepsPreview');
    const statusBox = document.getElementById('statusBox');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');

    let selectedLessonIndex = lessons.length ? 0 : -1;

    function appendStatus(message, data = null) {
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const block = data
        ? `[${timestamp}] ${message}\n${JSON.stringify(data, null, 2)}`
        : `[${timestamp}] ${message}`;
      statusBox.textContent = statusBox.textContent ? `${statusBox.textContent}\n\n${block}` : block;
      statusBox.scrollTop = statusBox.scrollHeight;
    }

    function getBasisWord(item, fallbackIndex = 0) {
      const word = String(item?.word || '').trim();
      return word || `item-${fallbackIndex + 1}`;
    }

    function renderMethodInfo() {
      methodInfo.innerHTML = `
        <div><strong>Method:</strong> ${method.title || method.id || '-'}</div>
        <div><strong>Basisbestand:</strong> ${method.basisFile || '-'}</div>
        <div><strong>Lessons:</strong> ${lessons.length}</div>
        <div><strong>Basisrecords:</strong> ${basisRecords.length}</div>
      `;
    }

    function getSelectedLesson() {
      return selectedLessonIndex >= 0 ? lessons[selectedLessonIndex] : null;
    }

    function renderLessonsList() {
      lessonsList.innerHTML = '';
      if (!lessons.length) {
        lessonsList.innerHTML = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">No lessons found for this method.</div>';
        return;
      }
      lessons.forEach((lesson, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `w-full rounded-xl border px-4 py-3 text-left ${index === selectedLessonIndex ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'}`;
        button.innerHTML = `
          <div class="font-bold">${lesson.title || lesson.id}</div>
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
        currentLessonInfo.textContent = 'No lesson selected.';
        stepsPreview.textContent = 'No steps.';
        return;
      }
      currentLessonInfo.innerHTML = `
        <div><strong>Lesson:</strong> ${lesson.title || lesson.id}</div>
        <div><strong>Word:</strong> ${lesson.basisWord || '-'}</div>
        <div><strong>Basisindex:</strong> ${lesson.basisIndex ?? -1}</div>
      `;
      const preview = (Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs : []).map((step) => {
        const inputs = step.inputs || {};
        const parts = [];
        if (inputs.text) parts.push(`text: ${inputs.text}`);
        if (inputs.word) parts.push(`word: ${inputs.word}`);
        if (Array.isArray(inputs.letters) && inputs.letters.length) parts.push(`letters: ${inputs.letters.join(', ')}`);
        return `${step.id}${parts.length ? ` (${parts.join(' | ')})` : ''}`;
      });
      stepsPreview.innerHTML = preview.length
        ? `<ul class="list-disc pl-5">${preview.map((item) => `<li>${item}</li>`).join('')}</ul>`
        : 'No steps.';
    }

    function getRunnerWindow() {
      return lessonRunnerFrame?.contentWindow || null;
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

    async function runLesson(lesson) {
      if (!lesson) throw new Error('No lesson selected');
      const app = await waitForRunnerReady();
      const basisIndex = Number(lesson.basisIndex ?? -1);
      const lessonData = basisRecords;
      const lessonMethod = method;
      const stepConfigs = Array.isArray(lesson.stepConfigs) ? lesson.stepConfigs : [];
      appendStatus('Lesson run gestart.', {
        lessonId: lesson.id,
        lessonTitle: lesson.title,
        steps: stepConfigs.length,
        runnerUrl,
        runnerState: getRunnerDebugState()
      });
      const results = [];
      for (let stepIndex = 0; stepIndex < stepConfigs.length; stepIndex += 1) {
        const stepConfig = stepConfigs[stepIndex];
        const scriptData = await loadScriptData(stepConfig.id);
        const result = await app.runWorkspaceStateHeadless({
          state: scriptData.blockly,
          sourceName: scriptData.title || stepConfig.id,
          lessonData,
          lessonMethod,
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
        const completion = result?.stepCompletion || await waitForCompletion(app);
        results.push({
          scriptId: stepConfig.id,
          completion
        });
        appendStatus('Step uitgevoerd.', {
          lessonId: lesson.id,
          scriptId: stepConfig.id,
          completion
        });
        if (completion && completion.status !== 'completed') {
          appendStatus('Lesson gestopt door step status.', {
            lessonId: lesson.id,
            stoppingStatus: completion.status
          });
          break;
        }
      }
      return results;
    }

    async function runCurrentLesson() {
      try {
        const lesson = getSelectedLesson();
        const results = await runLesson(lesson);
        appendStatus('Current lesson afgerond.', { lessonId: lesson?.id || '', results });
      } catch (err) {
        appendStatus('Run current lesson mislukt.', {
          error: err.message || String(err),
          runnerState: getRunnerDebugState()
        });
      }
    }

    async function runAllLessons() {
      appendStatus('Run all gestart.', { lessons: lessons.length });
      for (let index = 0; index < lessons.length; index += 1) {
        selectedLessonIndex = index;
        renderLessonsList();
        renderCurrentLesson();
        try {
          const lesson = lessons[index];
          const results = await runLesson(lesson);
          const lastCompletion = results[results.length - 1]?.completion || null;
          if (lastCompletion && lastCompletion.status && lastCompletion.status !== 'completed') {
            appendStatus('Run all gestopt.', {
              lessonId: lesson.id,
              status: lastCompletion.status
            });
            return;
          }
        } catch (err) {
          appendStatus('Run all mislukt.', {
            lessonId: lessons[index]?.id || '',
            error: err.message || String(err)
          });
          return;
        }
      }
      appendStatus('Run all afgerond.');
    }

    lessonRunnerFrame.addEventListener('load', () => {
      appendStatus('Blockly runner iframe geladen.', {
        runnerUrl,
        runnerState: getRunnerDebugState()
      });
    });

    document.getElementById('runCurrentBtn').addEventListener('click', runCurrentLesson);
    document.getElementById('runAllBtn').addEventListener('click', runAllLessons);

    renderMethodInfo();
    renderLessonsList();
    renderCurrentLesson();
    appendStatus('Runner ready.', {
      methodId: method.id || '',
      lessons: lessons.length,
      basisRecords: basisRecords.length,
      runnerUrl
    });
  </script>
  <?php endif; ?>
</body>
</html>
