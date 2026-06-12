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

function is_http_url(string $value): bool
{
    return preg_match('~^https?://~i', trim($value)) === 1;
}

function read_url(string $url): ?string
{
    if (!is_http_url($url)) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: BrailleStudioRunMethod/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (is_string($raw) && trim($raw) !== '') {
        return $raw;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'BrailleStudioRunMethod/1.0',
    ]);
    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    if (!is_string($result) || trim($result) === '' || $status >= 400) {
        return null;
    }
    return $result;
}

function read_json_url(string $url): ?array
{
    $raw = read_url($url);
    if ($raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function read_json_source(string $source): ?array
{
    if (is_http_url($source)) {
        return read_json_url($source);
    }
    return read_json_file($source);
}

function canonical_remote_base_url(): string
{
    return 'https://www.tastenbraille.com/braillestudio';
}

function canonical_remote_data_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return 'https://www.tastenbraille.com/braillestudio-data/data'
        . ($path !== '' ? '/' . $path : '');
}

function is_running_on_canonical_remote(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== 'www.tastenbraille.com' && $host !== 'tastenbraille.com') {
        return false;
    }

    $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    if ($requestPath === '') {
        $requestPath = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    }
    return $requestPath === '/braillestudio/runmethod.php'
        || str_starts_with($requestPath, '/braillestudio/');
}

function read_remote_runmethod_payload(string $methodId): ?array
{
    if ($methodId === '' || is_running_on_canonical_remote()) {
        return null;
    }

    $url = canonical_remote_base_url() . '/runmethod.php?id=' . rawurlencode($methodId);
    $html = read_url($url);
    if ($html === null) {
        return null;
    }

    if (!preg_match('/window\.RunMethodBootstrap\s*=\s*(\{.*?\});\s*<\/script>/s', $html, $matches)) {
        return null;
    }

    $payload = json_decode($matches[1], true);
    if (!is_array($payload) || (string)($payload['error'] ?? '') !== '') {
        return null;
    }
    return $payload;
}

function normalize_id(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_-]/', '-', $value);
    return trim((string)$value, '-_');
}

function local_data_dirs(string $section): array
{
    $section = trim($section, '/');
    if ($section === '') {
        return [];
    }

    if (in_array($section, ['methods', 'lessons'], true)) {
        return [
            dirname(__DIR__) . '/braillestudio-data/data/' . $section,
        ];
    }

    return array_values(array_unique([
        dirname(__DIR__) . '/braillestudio-data/data/' . $section,
        __DIR__ . '/data/' . $section,
        __DIR__ . '/api/data/' . $section,
        __DIR__ . '/XXX data/' . $section,
    ]));
}

function read_local_method(string $methodId): ?array
{
    $methodId = normalize_id($methodId);
    if ($methodId === '') {
        return null;
    }

    foreach (local_data_dirs('methods') as $dir) {
        $method = read_json_file($dir . '/' . $methodId . '.json');
        if (is_array($method)) {
            return $method;
        }
    }

    return null;
}

function read_local_lessons_for_method(string $methodId): array
{
    $methodId = normalize_id($methodId);
    if ($methodId === '') {
        return [];
    }

    $lessons = [];
    $seen = [];
    foreach (local_data_dirs('lessons') as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $files = glob($dir . '/*.json') ?: [];
        sort($files);
        foreach ($files as $file) {
            $lesson = read_json_file($file);
            if (!is_array($lesson)) {
                continue;
            }
            $lessonMethod = is_array($lesson['method'] ?? null) ? $lesson['method'] : [];
            $lessonMethodId = normalize_id((string)($lesson['methodId'] ?? ($lessonMethod['id'] ?? '')));
            if ($lessonMethodId !== $methodId) {
                continue;
            }
            $lessonId = normalize_id((string)($lesson['id'] ?? pathinfo($file, PATHINFO_FILENAME)));
            if ($lessonId === '' || isset($seen[$lessonId])) {
                continue;
            }
            $seen[$lessonId] = true;
            $lesson['id'] = $lessonId;
            $lesson['methodId'] = $lessonMethodId;
            $lesson['filename'] = basename($file);
            $lessons[] = $lesson;
        }
    }

    usort($lessons, static function (array $a, array $b): int {
        $aIndex = array_key_exists('basisIndex', $a) ? (int)$a['basisIndex'] : -1;
        $bIndex = array_key_exists('basisIndex', $b) ? (int)$b['basisIndex'] : -1;
        if ($aIndex !== $bIndex) {
            return $aIndex <=> $bIndex;
        }
        $aLesson = array_key_exists('lessonNumber', $a) ? (int)$a['lessonNumber'] : 1;
        $bLesson = array_key_exists('lessonNumber', $b) ? (int)$b['lessonNumber'] : 1;
        if ($aLesson !== $bLesson) {
            return $aLesson <=> $bLesson;
        }
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    return $lessons;
}

function local_basis_sources(string $basisFile, string $dataSource): array
{
    $sources = [];
    $sourcePath = $basisFile !== '' ? $basisFile : (string)(parse_url($dataSource, PHP_URL_PATH) ?: '');
    $basisName = basename($sourcePath);
    if ($basisName !== '') {
        foreach (local_data_dirs('klanken') as $dir) {
            $sources[] = $dir . '/' . $basisName;
        }
        $sources[] = dirname(__DIR__) . '/braillestudio-data/klanken/' . $basisName;
        $sources[] = __DIR__ . '/klanken/' . $basisName;
    }
    return $sources;
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

    $normalized = [
        'text' => trim((string)($inputs['text'] ?? '')),
        'word' => trim((string)($inputs['word'] ?? '')),
        'letters' => $letters,
        'repeat' => max(1, (int)($inputs['repeat'] ?? 1)),
    ];

    foreach ($inputs as $key => $value) {
        $safeKey = trim((string)$key);
        if ($safeKey === '' || in_array($safeKey, ['text', 'word', 'letters', 'repeat'], true)) {
            continue;
        }
        $normalized[$safeKey] = $value;
    }

    return $normalized;
}

$defaultRunnerUrl = './blockly/session-player.php?v=20260602-headless-highlight-1';
$blocklyApiBase = './api/blockly-api';

$methodId = normalize_id((string)($_GET['id'] ?? $_GET['method'] ?? ''));
$errorMessage = '';
$method = null;
$basisRecords = [];
$lessons = [];
$remotePayload = null;
$loadDiagnostics = [];
$basisSources = [];

$loadDiagnostics[] = [
    'stage' => 'request',
    'requestedId' => (string)($_GET['id'] ?? $_GET['method'] ?? ''),
    'normalizedId' => $methodId,
    'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
    'requestPath' => (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ($_SERVER['SCRIPT_NAME'] ?? '')),
    'canonicalRemoteFallbackAllowed' => !is_running_on_canonical_remote(),
];

if ($methodId === '') {
    $errorMessage = 'Missing method id. Use runmethod.php?id=your-method-id';
} else {
    $method = read_local_method($methodId);
    $lessons = read_local_lessons_for_method($methodId);
    foreach (local_data_dirs('methods') as $index => $dir) {
        $path = $dir . '/' . $methodId . '.json';
        $candidate = read_json_file($path);
        $loadDiagnostics[] = [
            'stage' => 'local-method',
            'source' => '../braillestudio-data/data/methods',
            'fileExists' => is_file($path),
            'readable' => is_readable($path),
            'jsonValid' => is_array($candidate),
            'storedId' => is_array($candidate) ? (string)($candidate['id'] ?? '') : '',
        ];
    }
    foreach (local_data_dirs('lessons') as $index => $dir) {
        $files = is_dir($dir) ? (glob($dir . '/*.json') ?: []) : [];
        $matching = 0;
        $seenMethodIds = [];
        foreach ($files as $file) {
            $candidate = read_json_file($file);
            if (!is_array($candidate)) {
                continue;
            }
            $candidateMethod = is_array($candidate['method'] ?? null) ? $candidate['method'] : [];
            $candidateMethodId = normalize_id((string)($candidate['methodId'] ?? ($candidateMethod['id'] ?? '')));
            if ($candidateMethodId !== '') {
                $seenMethodIds[$candidateMethodId] = true;
            }
            if ($candidateMethodId === $methodId) {
                $matching++;
            }
        }
        $loadDiagnostics[] = [
            'stage' => 'local-lessons',
            'source' => '../braillestudio-data/data/lessons',
            'directoryExists' => is_dir($dir),
            'jsonFileCount' => count($files),
            'matchingLessonCount' => $matching,
            'sampleMethodIds' => array_slice(array_keys($seenMethodIds), 0, 10),
        ];
    }

    if ($method && count($lessons) === 0) {
        $remotePayload = read_remote_runmethod_payload($methodId);
        $loadDiagnostics[] = [
            'stage' => 'remote-runmethod-fallback',
            'attempted' => !is_running_on_canonical_remote(),
            'source' => canonical_remote_base_url() . '/runmethod.php?id=' . rawurlencode($methodId),
            'payloadFound' => is_array($remotePayload),
            'skipReason' => is_running_on_canonical_remote() ? 'Current host is treated as canonical remote.' : '',
        ];
        if (is_array($remotePayload)) {
            $basisRecords = is_array($remotePayload['basisRecords'] ?? null) ? array_values($remotePayload['basisRecords']) : [];
        }
    }

    if (!$method) {
        $errorMessage = 'Method not found.';
    } elseif (count($basisRecords) === 0) {
        $basisFile = trim((string)($method['basisFile'] ?? ''));
        $dataSource = trim((string)($method['dataSource'] ?? ''));
        if ($dataSource !== '') {
            $basisSources[] = $dataSource;
        }
        if ($basisFile !== '') {
            $basisSources[] = canonical_remote_data_url('klanken/' . rawurlencode(basename($basisFile)));
        }
        array_unshift($basisSources, ...local_basis_sources($basisFile, $dataSource));

        $basisData = null;
        foreach (array_values(array_unique(array_filter($basisSources))) as $basisSource) {
            $basisData = read_json_source($basisSource);
            $loadDiagnostics[] = [
                'stage' => 'basis-source',
                'source' => is_http_url($basisSource) ? $basisSource : basename(dirname($basisSource)) . '/' . basename($basisSource),
                'loaded' => is_array($basisData),
                'recordCount' => is_array($basisData) ? count($basisData) : 0,
            ];
            if (is_array($basisData)) {
                break;
            }
        }
        if (!is_array($basisData)) {
            $errorMessage = 'Basisbestand kon niet geladen worden.';
        } else {
            $basisRecords = array_values($basisData);
        }
    }

    if ($errorMessage === '' && is_array($method) && count($basisRecords) === 0) {
        $remotePayload = $remotePayload ?? read_remote_runmethod_payload($methodId);
        if (is_array($remotePayload)) {
            if (is_array($remotePayload['basisRecords'] ?? null)) {
                $basisRecords = array_values($remotePayload['basisRecords']);
            }
        }
    }
}

$loadDiagnostics[] = [
    'stage' => 'result',
    'methodFound' => is_array($method),
    'methodId' => is_array($method) ? (string)($method['id'] ?? '') : '',
    'lessonCount' => count($lessons),
    'basisRecordCount' => count($basisRecords),
    'error' => $errorMessage,
];

$pagePayload = [
    'methodId' => $methodId,
    'method' => $method,
    'basisRecords' => $basisRecords,
    'lessons' => $lessons,
    'runnerUrl' => $defaultRunnerUrl,
    'blocklyApiBase' => $blocklyApiBase,
    'error' => $errorMessage,
    'loadDiagnostics' => $loadDiagnostics,
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($method['title'] ?? $methodId ?: 'Run Method') ?></title>
  <link rel="stylesheet" href="./tabler/core/dist/css/tabler.min.css">
  <link rel="stylesheet" href="./tabler/icons-webfont/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="./components/braille-monitor/braillemonitor.css?v=20260529-mode-label-1">
  <link rel="stylesheet" href="./components/braillebridge-status/braillebridge-status.css?v=20260526-popup-3">
  <style>
    body {
      min-height: 100vh;
      background: var(--tblr-body-bg);
    }

    .method-shell {
      max-width: 1320px;
      margin: 0 auto;
      padding: 1.5rem;
    }

    .method-stack {
      display: grid;
      gap: 1.25rem;
    }

    .method-header {
      position: relative;
      min-height: 96px;
      overflow: hidden;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius-lg);
      background: var(--tblr-bg-surface);
      box-shadow: var(--tblr-box-shadow-sm);
    }

    .method-header-image,
    .method-header-overlay {
      position: absolute;
      inset: 0;
    }

    .method-header-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .method-header-overlay {
      background: rgba(255, 255, 255, .78);
    }

    .method-header-content {
      position: relative;
      z-index: 1;
      min-height: 96px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.25rem;
    }

    .method-title-group {
      min-width: 0;
      display: flex;
      align-items: center;
      gap: .875rem;
    }

    .method-title {
      margin: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .method-thumb-status {
      flex: 0 1 auto;
      min-width: 0;
    }

    .method-thumb-status.is-collapsed {
      min-width: 0;
    }

    .method-thumb-status .braillebridge-status__body {
      padding: .625rem .75rem;
    }

    .method-thumb-status .braillebridge-status__icon {
      width: 2.25rem;
      height: 2.25rem;
      font-size: 1.15rem;
    }

    .method-thumb-status .braillebridge-status__toggle {
      width: 2.25rem;
      height: 2.25rem;
    }

    .method-thumb-status .braillebridge-status__toggle-dot {
      margin-top: -1.1rem;
      margin-left: 1.1rem;
    }

    .min-w-0 {
      min-width: 0;
    }

    .indicator-group,
    .action-row,
    .sim-row,
    .footer-row {
      display: flex;
      align-items: center;
      gap: .5rem;
      flex-wrap: wrap;
    }

    .thumb-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
      align-items: center;
      gap: .5rem;
    }

    .thumb-controls {
      display: inline-flex;
      grid-column: 2;
      gap: .5rem;
      justify-content: center;
    }

    .thumb-status {
      grid-column: 3;
      justify-self: end;
    }

    .thumb-controls .btn {
      min-width: 3rem;
      font-weight: 600;
    }

    @media (max-width: 575.98px) {
      .thumb-row {
        grid-template-columns: minmax(0, 1fr) auto;
      }

      .thumb-controls {
        grid-column: 1;
        justify-self: center;
      }

      .thumb-status {
        grid-column: 2;
      }
    }

    .indicator,
    .indicator-dot {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
    }

    .indicator {
      width: 2rem;
      height: 2rem;
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-danger-lt);
    }

    .indicator-dot {
      width: .7rem;
      height: .7rem;
      border-radius: var(--tblr-border-radius-pill);
      background: var(--tblr-danger);
    }

    .indicator.is-connected,
    .indicator.is-running {
      background: var(--tblr-success-lt);
      border-color: color-mix(in srgb, var(--tblr-success) 30%, transparent);
    }

    .indicator.is-connected .indicator-dot,
    .indicator.is-running .indicator-dot {
      background: var(--tblr-success);
    }

    .indicator.is-stopping {
      background: var(--tblr-warning-lt);
      border-color: color-mix(in srgb, var(--tblr-warning) 34%, transparent);
    }

    .indicator.is-stopping .indicator-dot {
      background: var(--tblr-warning);
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

    .run-panel,
    .instruction-panel {
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface-secondary);
      padding: 1rem;
    }

    .lesson-grid {
      display: grid;
      grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
      gap: 1.25rem;
    }

    .list-panel {
      height: 780px;
      display: flex;
      flex-direction: column;
    }

    .list-scroll {
      flex: 1 1 auto;
      min-height: 0;
      overflow: auto;
    }

    .lesson-card {
      width: 100%;
      text-align: left;
      padding: 1rem 1.25rem;
      border-left-width: 4px;
      border-left-color: transparent;
    }

    .lesson-card:hover,
    .lesson-card.is-active {
      background: var(--tblr-primary-lt);
      border-left-color: var(--tblr-primary);
    }

    .item-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: .75rem;
    }

    .item-title {
      font-weight: 600;
      color: var(--tblr-body-color);
    }

    .item-meta {
      margin-top: .25rem;
      font-size: .75rem;
      color: var(--tblr-muted);
    }

    .external-vars {
      display: flex;
      flex-wrap: wrap;
      gap: .35rem;
      margin-top: .5rem;
    }

    .external-var {
      display: inline-flex;
      align-items: center;
      gap: .25rem;
      max-width: 100%;
      padding: .125rem .4rem;
      border: var(--tblr-border-width) solid color-mix(in srgb, var(--tblr-primary) 28%, transparent);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-primary-lt);
      color: var(--tblr-primary);
      font-size: .75rem;
      line-height: 1.35;
    }

    .external-var__name {
      font-weight: 700;
    }

    .external-var__value {
      min-width: 0;
      overflow-wrap: anywhere;
      color: var(--tblr-body-color);
    }

    .status-banner {
      border: var(--tblr-border-width) solid var(--tblr-border-color);
      border-radius: var(--tblr-border-radius);
      background: var(--tblr-bg-surface-secondary);
      color: var(--tblr-secondary);
      padding: .75rem 1rem;
      font-size: .875rem;
    }

    .status-banner.is-running {
      border-color: color-mix(in srgb, var(--tblr-primary) 25%, transparent);
      background: var(--tblr-primary-lt);
      color: var(--tblr-primary);
    }

    .status-banner.is-stopping {
      border-color: color-mix(in srgb, var(--tblr-warning) 35%, transparent);
      background: var(--tblr-warning-lt);
      color: var(--tblr-warning);
    }

    .status-banner.is-completed {
      border-color: color-mix(in srgb, var(--tblr-success) 35%, transparent);
      background: var(--tblr-success-lt);
      color: var(--tblr-success);
    }

    .status-banner.is-failed {
      border-color: color-mix(in srgb, var(--tblr-danger) 35%, transparent);
      background: var(--tblr-danger-lt);
      color: var(--tblr-danger);
    }

    .return-values,
    .debug-log,
    .instruction-text {
      overflow: auto;
      line-height: 1.6;
      color: var(--tblr-secondary);
    }

    .return-values {
      height: 180px;
    }

    .instruction-text {
      max-height: 220px;
      white-space: pre-wrap;
    }

    .debug-log {
      min-height: 220px;
      font-size: .75rem;
      margin: 0;
      padding: 1rem;
      background: var(--tblr-bg-surface-secondary);
    }

    .debug-log-grid {
      display: grid;
      grid-template-columns: max-content max-content max-content max-content minmax(22rem, 1fr);
      gap: 0;
      align-items: stretch;
      overflow: auto;
      max-height: 360px;
      line-height: 1.35;
    }

    .debug-log-cell {
      min-width: 0;
      padding: .35rem .5rem;
      border-bottom: var(--tblr-border-width) solid var(--tblr-border-color);
      white-space: nowrap;
    }

    .debug-log-cell--message {
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .debug-log-head {
      position: sticky;
      top: 0;
      z-index: 1;
      background: var(--tblr-bg-surface-secondary);
      color: var(--tblr-muted);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0;
    }

    .debug-log-cell.debug-log-row-bb {
      background: color-mix(in srgb, var(--tblr-primary) 5%, transparent);
    }

    .hidden {
      display: none !important;
    }

    .footer-logo {
      height: 2rem;
      width: auto;
      object-fit: contain;
    }

    @media (max-width: 992px) {
      .method-header-content {
        align-items: stretch;
        flex-direction: column;
      }

      .lesson-grid {
        grid-template-columns: 1fr;
      }

      .list-panel {
        height: auto;
        min-height: 460px;
      }
    }
  </style>
</head>
<body>
  <div class="method-shell method-stack">
    <header class="method-header">
      <?php if (trim((string)($method['imageUrl'] ?? '')) !== ''): ?>
        <img
          src="<?= h(trim((string)$method['imageUrl'])) ?>"
          alt="Method banner"
          class="method-header-image"
        >
      <?php endif; ?>
      <div class="method-header-overlay"></div>
      <div class="method-header-content">
        <div class="method-title-group">
          <span class="avatar avatar-md bg-primary-lt text-primary">
            <i class="ti ti-player-play" aria-hidden="true"></i>
          </span>
          <div class="min-w-0">
            <div class="page-pretitle">Run method</div>
            <h1 class="method-title"><?= h($method['title'] ?? $methodId ?: 'Run Method') ?></h1>
          </div>
        </div>
      </div>
    </header>

    <?php if ($errorMessage !== ''): ?>
      <section class="alert alert-danger">
        <i class="ti ti-alert-circle me-2" aria-hidden="true"></i>
        <div><?= h($errorMessage) ?></div>
      </section>
      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Debug log</h2>
        </div>
        <div class="card-body">
          <pre class="form-control debug-log mb-0" role="log"><?= h((string)json_encode($loadDiagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
      </section>
    <?php else: ?>
      <section class="card">
        <div class="card-body method-stack">
        <div id="brailleMonitorRow" class="lesson-monitor-host">
          <div id="brailleMonitorComponent"></div>
        </div>
        <div id="scriptBrailleMonitorRow" class="lesson-monitor-host">
          <div id="scriptBrailleMonitorComponent"></div>
        </div>
        <div class="thumb-row" aria-label="Thumb keys">
          <div class="thumb-controls">
            <button id="simThumbLeftBtn" class="btn btn-outline-primary" type="button" aria-label="Left thumb" title="Left thumb">&lt;&lt;</button>
            <button id="simCursor5Btn" class="btn btn-outline-primary" type="button" aria-label="Left middle thumb" title="Left middle thumb">&lt;</button>
            <button id="simChord1Btn" class="btn btn-outline-primary" type="button" aria-label="Right middle thumb" title="Right middle thumb">&gt;</button>
            <button id="simThumbRightBtn" class="btn btn-outline-primary" type="button" aria-label="Right thumb" title="Right thumb">&gt;&gt;</button>
          </div>
          <section
            class="method-thumb-status thumb-status"
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
      </section>

      <section class="card">
        <div class="card-body method-stack">
        <div class="action-row">
          <button id="runSelectedStepBtn" class="btn btn-outline-primary" type="button">Run step</button>
          <button id="runCurrentBtn" class="btn btn-primary" type="button">Run lesson</button>
          <button id="runAllBtn" class="btn btn-outline-primary" type="button">Run all</button>
          <button id="stopRunBtn" class="btn btn-danger" type="button">Stop</button>
          <button id="toggleRunnerBtn" type="button" class="btn btn-outline-secondary ms-auto">Unhide</button>
        </div>
        <div id="runStatusBanner" class="status-banner">
          Idle. Select a lesson or step to start.
        </div>
        <div id="runnerPanel" class="hidden">
          <div class="run-panel">
            <div class="fw-semibold mb-2">Lesson return values</div>
            <div id="lessonReturnValues" class="return-values">No values yet.</div>
          </div>
        </div>
          </div>
      </section>

      <div class="lesson-grid">
        <section class="card list-panel">
          <div class="card-header">
            <h2 class="card-title">Lessons</h2>
          </div>
          <div class="list-group list-group-flush list-scroll" id="lessonsList"></div>
        </section>

        <section class="card list-panel">
          <div class="card-header">
            <h2 class="card-title">Steps</h2>
          </div>
          <div class="card-body pb-3">
            <div class="instruction-panel">
              <div class="fw-semibold mb-2">Instruction</div>
              <div id="selectedStepInstruction" class="instruction-text">No instruction for the selected step.</div>
            </div>
          </div>
          <div class="list-group list-group-flush list-scroll" id="stepsPreview"></div>
        </section>
      </div>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Debug log</h2>
          <div class="card-actions">
            <button id="copyDebugLogBtn" type="button" class="btn btn-outline-secondary">Copy</button>
            <button id="clearDebugLogBtn" type="button" class="btn btn-outline-secondary">Clear</button>
            <button id="toggleDebugLogBtn" type="button" class="btn btn-outline-secondary">Unhidden</button>
          </div>
        </div>
        <div class="card-body">
          <div id="statusBox" class="hidden form-control debug-log debug-log-grid" role="log" aria-live="polite"></div>
        </div>
      </section>

      <iframe
        id="lessonRunnerFrame"
        src="<?= h($defaultRunnerUrl) ?>"
        title="Method runner"
        allow="autoplay"
        style="position:absolute; width:1px; height:1px; border:0; opacity:0; pointer-events:none; left:-9999px; top:auto;"
      ></iframe>
    <?php endif; ?>

    <footer class="card">
      <div class="card-body footer-row justify-content-between">
      <div class="fw-medium text-secondary"><?= h($methodId) ?></div>
      <div class="footer-row">
        <span class="text-secondary">Powered by</span>
        <a href="https://www.bartimeus.nl" target="_blank" rel="noopener noreferrer">
          <img
            src="https://www.tastenbraille.com/braillestudio-data/assets/bartimeus.png"
            alt="Bartimeus logo"
            class="footer-logo"
          >
        </a>
      </div>
      </div>
    </footer>
  </div>

  <script>
    window.RunMethodBootstrap = <?= json_encode($pagePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <?php if ($errorMessage === ''): ?>
  <script src="./tabler/core/dist/js/tabler.min.js"></script>
  <script src="./components/braillebridge-status/braillebridge-status.js?v=20260608-ws-auto-start-1"></script>
  <script>
    const bootstrap = window.RunMethodBootstrap || {};
    const method = bootstrap.method || {};
    const basisRecords = Array.isArray(bootstrap.basisRecords) ? bootstrap.basisRecords : [];
    const lessons = Array.isArray(bootstrap.lessons) ? bootstrap.lessons : [];
    const runnerUrl = String(bootstrap.runnerUrl || '');
    const blocklyApiBase = String(bootstrap.blocklyApiBase || '');
    const loadDiagnostics = Array.isArray(bootstrap.loadDiagnostics) ? bootstrap.loadDiagnostics : [];
    const BRAILLE_MONITOR_PLACEHOLDER = 'Bartiméus Education';

    const lessonsList = document.getElementById('lessonsList');
    const stepsPreview = document.getElementById('stepsPreview');
    const selectedStepInstruction = document.getElementById('selectedStepInstruction');
    const statusBox = document.getElementById('statusBox');
    const lessonRunnerFrame = document.getElementById('lessonRunnerFrame');
    const brailleMonitorStatus = document.getElementById('brailleMonitorStatus');
    const lessonReturnValues = document.getElementById('lessonReturnValues');
    const runStatusBanner = document.getElementById('runStatusBanner');
    const copyDebugLogBtn = document.getElementById('copyDebugLogBtn');
    const clearDebugLogBtn = document.getElementById('clearDebugLogBtn');
    const toggleDebugLogBtn = document.getElementById('toggleDebugLogBtn');
    const toggleRunnerBtn = document.getElementById('toggleRunnerBtn');
    const runnerPanel = document.getElementById('runnerPanel');
    const stopRunBtn = document.getElementById('stopRunBtn');
    const runSelectedStepBtn = document.getElementById('runSelectedStepBtn');
    const runCurrentBtn = document.getElementById('runCurrentBtn');
    const runAllBtn = document.getElementById('runAllBtn');
    const authBtn = document.getElementById('authBtn');
    const runmethodAuthPanel = document.getElementById('runmethodAuthPanel');
    const runmethodUsernameInput = document.getElementById('runmethodUsernameInput');
    const runmethodPasswordInput = document.getElementById('runmethodPasswordInput');
    const runmethodLoginBtn = document.getElementById('runmethodLoginBtn');
    const runmethodAuthStatus = document.getElementById('runmethodAuthStatus');
    const brailleMonitorRow = document.getElementById('brailleMonitorRow');
    const scriptBrailleMonitorRow = document.getElementById('scriptBrailleMonitorRow');
    const simThumbLeftBtn = document.getElementById('simThumbLeftBtn');
    const simThumbRightBtn = document.getElementById('simThumbRightBtn');
    const simCursor5Btn = document.getElementById('simCursor5Btn');
    const simChord1Btn = document.getElementById('simChord1Btn');

    let selectedLessonIndex = lessons.length ? 0 : -1;
    let selectedStepIndex = 0;
    let isLessonRunning = false;
    let isDebugLogVisible = false;
    let isRunnerVisible = false;
    let stopRequested = false;
    let scriptsCache = [];
    const scriptDataCache = new Map();
    let runnerWarmAssetsPromise = null;
    const brailleBridgeWarmup = {
      lastAttemptAt: 0,
      connected: false,
      pending: null
    };
    let brailleMonitorUi = null;
    let scriptBrailleMonitorUi = null;
    let brailleMonitorSyncTimer = null;
    let lastBrailleSnapshot = '';
    let lastScriptBrailleSnapshot = '';
    let runSequence = 0;
    let currentRun = null;
    let lastRunBanner = {
      tone: 'idle',
      text: 'Idle. Select a lesson or step to start.'
    };
    const lessonStatuses = new Map();
    const stepStatuses = new Map();

    function getLessonStatusKey(lessonId) {
      return String(lessonId || '').trim();
    }

    function getStepStatusKey(lessonId, stepIndex) {
      return `${getLessonStatusKey(lessonId)}::${Number(stepIndex)}`;
    }

    function setLessonStatus(lessonId, status, message = '') {
      const key = getLessonStatusKey(lessonId);
      if (!key) return;
      lessonStatuses.set(key, {
        status: String(status || 'idle').trim() || 'idle',
        message: String(message || '').trim()
      });
    }

    function getLessonStatus(lessonId) {
      return lessonStatuses.get(getLessonStatusKey(lessonId)) || { status: 'idle', message: '' };
    }

    function setStepStatus(lessonId, stepIndex, status, message = '') {
      stepStatuses.set(getStepStatusKey(lessonId, stepIndex), {
        status: String(status || 'idle').trim() || 'idle',
        message: String(message || '').trim()
      });
    }

    function getStepStatus(lessonId, stepIndex) {
      return stepStatuses.get(getStepStatusKey(lessonId, stepIndex)) || { status: 'idle', message: '' };
    }

    function clearStepStatuses(lessonId) {
      const prefix = `${getLessonStatusKey(lessonId)}::`;
      Array.from(stepStatuses.keys()).forEach((key) => {
        if (key.startsWith(prefix)) {
          stepStatuses.delete(key);
        }
      });
    }

    function getStatusBadgeMarkup(status, message = '') {
      const normalized = String(status || 'idle').trim().toLowerCase();
      if (!normalized || normalized === 'idle') return '';
      const variants = {
        running: 'bg-primary-lt text-primary',
        stopping: 'bg-warning-lt text-warning',
        completed: 'bg-success-lt text-success',
        stopped: 'bg-secondary-lt text-secondary',
        failed: 'bg-danger-lt text-danger'
      };
      const labels = {
        running: 'Running',
        stopping: 'Stopping',
        completed: 'Completed',
        stopped: 'Stopped',
        failed: 'Failed'
      };
      const title = message ? ` title="${escapeHtml(message)}"` : '';
      return `<span class="badge ${variants[normalized] || variants.stopped}"${title}>${labels[normalized] || escapeHtml(normalized)}</span>`;
    }

    function getRunModeLabel(mode) {
      const normalized = String(mode || '').trim().toLowerCase();
      if (normalized === 'step') return 'step';
      if (normalized === 'lesson') return 'lesson';
      if (normalized === 'all') return 'all lessons';
      return 'run';
    }

    function getCurrentRunStepIndex() {
      if (!currentRun || !Number.isInteger(currentRun.stepIndex)) return null;
      return currentRun.stepIndex;
    }

    function renderRunControls() {
      const active = currentRun && (currentRun.status === 'running' || currentRun.status === 'stopping') ? currentRun : null;
      const runnerActive = getRunnerRuntimeSnapshot()?.isActive === true;
      const hasActiveRun = Boolean(active || runnerActive);
      const isStopping = Boolean(active && active.status === 'stopping');
      if (runSelectedStepBtn) {
        runSelectedStepBtn.disabled = hasActiveRun;
        runSelectedStepBtn.textContent = active?.mode === 'step'
          ? (isStopping ? 'Stopping step...' : 'Running step...')
          : 'Run step';
        runSelectedStepBtn.className = active?.mode === 'step'
          ? `btn ${isStopping ? 'btn-warning' : 'btn-primary'}`
          : 'btn btn-outline-primary';
      }
      if (runCurrentBtn) {
        runCurrentBtn.disabled = hasActiveRun;
        runCurrentBtn.textContent = active?.mode === 'lesson'
          ? (isStopping ? 'Stopping lesson...' : 'Running lesson...')
          : 'Run lesson';
        runCurrentBtn.className = active?.mode === 'lesson'
          ? `btn ${isStopping ? 'btn-warning' : 'btn-primary'}`
          : 'btn btn-primary';
      }
      if (runAllBtn) {
        runAllBtn.disabled = hasActiveRun;
        runAllBtn.textContent = active?.mode === 'all'
          ? (isStopping ? 'Stopping all...' : 'Running all...')
          : 'Run all';
        runAllBtn.className = active?.mode === 'all'
          ? `btn ${isStopping ? 'btn-warning' : 'btn-primary'}`
          : 'btn btn-outline-primary';
      }
      if (stopRunBtn) {
        stopRunBtn.disabled = false;
        stopRunBtn.textContent = isStopping ? 'Stopping...' : 'Stop';
        stopRunBtn.className = `btn ${isStopping ? 'btn-warning' : 'btn-danger'}`;
      }

      if (runStatusBanner) {
        if (!active && runnerActive) {
          runStatusBanner.className = 'status-banner is-running';
          runStatusBanner.textContent = 'Runner is actief.';
        } else if (!active) {
          const tones = {
            idle: 'status-banner',
            running: 'status-banner is-running',
            stopping: 'status-banner is-stopping',
            completed: 'status-banner is-completed',
            stopped: 'status-banner',
            failed: 'status-banner is-failed'
          };
          runStatusBanner.className = tones[lastRunBanner.tone] || tones.idle;
          runStatusBanner.textContent = lastRunBanner.text || 'Idle. Select a lesson or step to start.';
        } else if (isStopping) {
          runStatusBanner.className = 'status-banner is-stopping';
          runStatusBanner.textContent = `Stopping ${getRunModeLabel(active.mode)}${active.lessonTitle ? `: ${active.lessonTitle}` : ''}`;
        } else {
          runStatusBanner.className = 'status-banner is-running';
          const stepText = Number.isInteger(active.stepIndex) ? `, step ${active.stepIndex + 1}` : '';
          runStatusBanner.textContent = `Running ${getRunModeLabel(active.mode)}${active.lessonTitle ? `: ${active.lessonTitle}` : ''}${stepText}`;
        }
      }
    }

    function startRunSession(mode, lesson = null, stepIndex = null) {
      if (currentRun && (currentRun.status === 'running' || currentRun.status === 'stopping')) {
        throw new Error(`Another ${getRunModeLabel(currentRun.mode)} is already active`);
      }
      runSequence += 1;
      currentRun = {
        id: runSequence,
        mode: String(mode || 'lesson').trim().toLowerCase(),
        status: 'running',
        lessonId: String(lesson?.id || '').trim(),
        lessonTitle: String(lesson?.title || lesson?.id || '').trim(),
        stepIndex: Number.isInteger(stepIndex) ? stepIndex : null
      };
      lastRunBanner = {
        tone: 'running',
        text: `Running ${getRunModeLabel(currentRun.mode)}${currentRun.lessonTitle ? `: ${currentRun.lessonTitle}` : ''}${Number.isInteger(currentRun.stepIndex) ? `, step ${currentRun.stepIndex + 1}` : ''}`
      };
      renderRunControls();
      return currentRun;
    }

    function updateCurrentRunLesson(lesson = null) {
      if (!currentRun) return;
      currentRun.lessonId = String(lesson?.id || '').trim();
      currentRun.lessonTitle = String(lesson?.title || lesson?.id || '').trim();
      renderRunControls();
    }

    function updateCurrentRunStep(stepIndex = null) {
      if (!currentRun) return;
      currentRun.stepIndex = Number.isInteger(stepIndex) ? stepIndex : null;
      renderRunControls();
    }

    function requestStopCurrentRun() {
      if (!currentRun) return false;
      currentRun.status = 'stopping';
      lastRunBanner = {
        tone: 'stopping',
        text: `Stopping ${getRunModeLabel(currentRun.mode)}${currentRun.lessonTitle ? `: ${currentRun.lessonTitle}` : ''}`
      };
      renderRunControls();
      return true;
    }

    function finishRunSession(finalStatus = 'completed', label = '') {
      const normalized = String(finalStatus || 'completed').trim().toLowerCase();
      const isError = normalized === 'failed';
      const isStopped = normalized === 'stopped';
      lastRunBanner = {
        tone: isError ? 'failed' : (isStopped ? 'stopped' : 'completed'),
        text: label || (isError ? 'Run failed.' : (isStopped ? 'Run stopped.' : 'Run completed.'))
      };
      currentRun = null;
      renderRunControls();
    }

    function finalizeStoppedStepRun(runSnapshot = null) {
      const run = runSnapshot && typeof runSnapshot === 'object' ? runSnapshot : currentRun;
      if (!run || String(run.mode || '') !== 'step') return;
      if (run.lessonId && Number.isInteger(run.stepIndex)) {
        setStepStatus(run.lessonId, run.stepIndex, 'stopped', 'Step stopped by user');
        setLessonStatus(run.lessonId, 'stopped', 'Step stopped by user');
      }
      renderLessonsList();
      renderCurrentLesson();
      setLessonRunningState(false, 'Stopped');
      finishRunSession('stopped', `Step stopped: ${run.lessonTitle || run.lessonId || 'step run'}`);
    }

    function showBrailleMonitorPlaceholder() {
      if (!brailleMonitorUi || typeof brailleMonitorUi.setText !== 'function') return;
      brailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
      lastBrailleSnapshot = JSON.stringify({
        placeholder: BRAILLE_MONITOR_PLACEHOLDER
      });
    }

    function showScriptBrailleMonitorPlaceholder() {
      if (!scriptBrailleMonitorUi || typeof scriptBrailleMonitorUi.setText !== 'function') return;
      scriptBrailleMonitorUi.setText(BRAILLE_MONITOR_PLACEHOLDER);
      lastScriptBrailleSnapshot = JSON.stringify({
        placeholder: BRAILLE_MONITOR_PLACEHOLDER
      });
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
      const app = await waitForRunnerReady(5000);
      if (app && typeof app.dispatchRuntimeEvent === 'function') {
        await app.dispatchRuntimeEvent(event);
        return;
      }
      const legacyDispatch = runner && typeof runner.dispatchEvent === 'function'
        ? runner.dispatchEvent.bind(runner)
        : null;
      const generation = Number.isFinite(runner?.runGeneration) ? runner.runGeneration : null;
      if (legacyDispatch && generation != null) {
        await legacyDispatch(event, generation);
        return;
      }
      throw new Error('Blockly runner does not support runtime input dispatch');
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
        return JSON.stringify(value);
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

    function renderLessonReturnValues(entries = []) {
      if (!Array.isArray(entries) || !entries.length) {
        lessonReturnValues.textContent = 'No values yet.';
        return;
      }
      lessonReturnValues.innerHTML = `
        <ul class="mb-0 ps-3">
          ${entries.map((entry) => `
            <li>
              <span class="fw-semibold">${escapeHtml(entry.key)}:</span>
              <span class="text-break">${escapeHtml(entry.value)}</span>
            </li>
          `).join('')}
        </ul>
      `;
    }

    function setLessonRunningState(running, label = '') {
      isLessonRunning = Boolean(running);
      renderRunControls();
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
      toggleRunnerBtn.className = 'btn btn-outline-secondary ms-auto';
    }

    function compactDebugMessage(message) {
      const text = String(message || '').trim();
      if (!text.startsWith('BrailleBridge status: ')) return text;
      return text
        .replace(/^BrailleBridge status:\s*/, 'BB ')
        .replace(/^BB test -> start$/, 'BB TEST -> start')
        .replace(/^BB test <- done$/, 'BB TEST <- done')
        .replace(/^BB launch -> /, 'BB APP -> ')
        .replace(/^BB WS -> /, 'BB WS -> ')
        .replace(/^BB WS <- /, 'BB WS <- ')
        .replace(/^BB state$/, 'BB STATE')
        .replace(/^BB init$/, 'BB INIT');
    }

    function getDebugEntryParts(message, data = null) {
      const compactMessage = compactDebugMessage(message);
      const dataText = data != null ? formatDebugData(data) : '';
      const parts = {
        source: 'RUN',
        channel: 'APP',
        direction: '',
        event: compactMessage,
        message: dataText
      };

      if (!compactMessage.startsWith('BB ')) {
        return parts;
      }

      parts.source = 'BB';
      const rest = compactMessage.slice(3);
      const match = rest.match(/^(WS|TEST|APP|STATE|INIT)\s*(->|<-|xx)?\s*(.*)$/);
      if (!match) {
        parts.channel = 'STATUS';
        parts.event = rest;
        return parts;
      }

      parts.channel = match[1];
      parts.direction = match[2] || '';
      parts.event = (match[3] || match[1]).trim();
      if (dataText) {
        parts.message = dataText;
      }
      return parts;
    }

    function ensureDebugLogHeader() {
      if (!statusBox || statusBox.dataset.hasHeader === 'true') return;
      statusBox.dataset.hasHeader = 'true';
      ['Time', 'Source', 'Type', 'Dir', 'Message'].forEach((label) => {
        const cell = document.createElement('div');
        cell.className = 'debug-log-cell debug-log-head';
        cell.textContent = label;
        statusBox.appendChild(cell);
      });
    }

    function getDebugLogInsertBeforeNode() {
      if (!statusBox) return null;
      return statusBox.children.length > 5 ? statusBox.children[5] : null;
    }

    function appendStatus(message, data = null) {
      const timestamp = new Date().toLocaleTimeString('nl-NL', { hour12: false });
      const parts = getDebugEntryParts(message, data);
      ensureDebugLogHeader();
      const rowClass = parts.source === 'BB' ? ' debug-log-row-bb' : '';
      const fragment = document.createDocumentFragment();
      [
        timestamp,
        parts.source,
        parts.channel,
        parts.direction,
        [parts.event, parts.message].filter(Boolean).join(' | ')
      ].forEach((value, index) => {
        const cell = document.createElement('div');
        cell.className = `debug-log-cell${index === 4 ? ' debug-log-cell--message' : ''}${rowClass}`;
        cell.title = String(value || '');
        cell.textContent = String(value || '');
        fragment.appendChild(cell);
      });
      statusBox.insertBefore(fragment, getDebugLogInsertBeforeNode());
      statusBox.scrollTop = 0;
    }

    function getDebugLogText() {
      if (!statusBox) return '';
      const cells = Array.from(statusBox.querySelectorAll('.debug-log-cell'));
      const rows = [];
      for (let index = 0; index < cells.length; index += 5) {
        const row = cells.slice(index, index + 5).map((cell) => String(cell.textContent || '').trim());
        if (row.length === 5 && row.some(Boolean)) {
          rows.push(row.join('\t'));
        }
      }
      return rows.join('\n');
    }

    async function copyDebugLog() {
      const text = getDebugLogText();
      if (!text) {
        appendStatus('Copy debug log skipped.', { reason: 'empty log' });
        return;
      }
      try {
        if (navigator.clipboard?.writeText) {
          try {
            await navigator.clipboard.writeText(text);
            appendStatus('Debug log copied.');
            return;
          } catch (err) {
            appendStatus('Clipboard API failed, trying textarea fallback.', { error: err.message || String(err) });
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
        appendStatus('Debug log copied.');
      } catch (err) {
        appendStatus('Copy debug log failed.', { error: err.message || String(err) });
      }
    }

    window.appendStatus = appendStatus;
    if (Array.isArray(window.__brailleBridgeStatusLogQueue)) {
      window.__brailleBridgeStatusLogQueue.splice(0).forEach((entry) => {
        appendStatus(entry.message, entry.data ?? null);
      });
    }

    clearDebugLogBtn?.addEventListener('click', () => {
      if (!statusBox) return;
      statusBox.replaceChildren();
      delete statusBox.dataset.hasHeader;
    });

    copyDebugLogBtn?.addEventListener('click', () => {
      copyDebugLog();
    });

    function getStepDebugSnapshot(stepConfig, stepIndex) {
      const inputs = stepConfig?.inputs || {};
      const normalizedInputs = {
        text: String(inputs.text || ''),
        word: String(inputs.word || ''),
        letters: Array.isArray(inputs.letters) ? [...inputs.letters] : [],
        repeat: Number(inputs.repeat || 1)
      };
      Object.entries(inputs).forEach(([key, value]) => {
        if (key === 'text' || key === 'word' || key === 'letters' || key === 'repeat') return;
        normalizedInputs[key] = value;
      });
      return {
        stepIndex,
        scriptId: String(stepConfig?.id || '').trim(),
        title: String(stepConfig?.title || '').trim(),
        description: String(stepConfig?.description || '').trim(),
        inputs: normalizedInputs
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
        if (!Array.isArray(lesson?.steps)) {
          lesson.steps = [];
          return;
        }
        lesson.steps = lesson.steps.map((cfg) => {
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
      const steps = Array.isArray(activeLesson?.steps) ? activeLesson.steps : [];
      const selectedStep = steps[selectedStepIndex] || null;
      const meta = selectedStep ? getStepDisplayMeta(selectedStep) : null;
      const instruction = String(meta?.instruction || '').trim();
      selectedStepInstruction.textContent = instruction || 'No instruction for the selected step.';
    }

    function renderLessonsList() {
      lessonsList.innerHTML = '';
      if (!lessons.length) {
        lessonsList.innerHTML = '<div class="empty"><p class="empty-title">No lessons found for this method.</p></div>';
        return;
      }
      lessons.forEach((lesson, index) => {
        const lessonTitle = String(lesson?.title || lesson.id).trim();
        const lessonDescription = String(lesson?.description || '').trim();
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `list-group-item list-group-item-action lesson-card${index === selectedLessonIndex ? ' is-active' : ''}`;
        button.innerHTML = `
          <div class="item-head">
            <div class="item-title">${lessonTitle}</div>
            ${getStatusBadgeMarkup(getLessonStatus(lesson.id).status, getLessonStatus(lesson.id).message)}
          </div>
          ${lessonDescription ? `<div class="item-meta">${escapeHtml(lessonDescription)}</div>` : ''}
          <div class="item-meta">Word: ${lesson.basisWord || getBasisWord(lesson.basisRecord, lesson.basisIndex || index)}</div>
          <div class="item-meta">${Array.isArray(lesson.steps) ? lesson.steps.length : 0} step(s)</div>
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
      const lessonTitle = String(lesson?.title || lesson.id).trim();
      const stepCount = Array.isArray(lesson.steps) ? lesson.steps.length : 0;
      if (stepCount === 0) {
        selectedStepIndex = 0;
      } else if (selectedStepIndex < 0 || selectedStepIndex >= stepCount) {
        selectedStepIndex = 0;
      }
      const preview = (Array.isArray(lesson.steps) ? lesson.steps : []).map((step) => {
        const meta = getStepDisplayMeta(step);
        const inputs = step.inputs || {};
        const parts = [];
        const externalVariables = [];
        if (inputs.text) parts.push(`text: ${inputs.text}`);
        if (inputs.word) parts.push(`word: ${inputs.word}`);
        if (Array.isArray(inputs.letters) && inputs.letters.length) parts.push(`letters: ${inputs.letters.join(', ')}`);
        if (Number(inputs.repeat || 1) > 1) parts.push(`repeat: ${inputs.repeat}`);
        Object.entries(inputs).forEach(([key, value]) => {
          if (key === 'text' || key === 'word' || key === 'letters' || key === 'repeat') return;
          const displayValue = value && typeof value === 'object' ? JSON.stringify(value) : String(value ?? '');
          externalVariables.push({ name: key, value: displayValue });
        });
        return {
          id: step.id,
          title: meta.title,
          description: meta.description,
          detail: parts.length ? parts.join(' | ') : '',
          externalVariables
        };
      });
      stepsPreview.innerHTML = preview.length
        ? `${preview.map((item, index) => `
            <button type="button" data-step-index="${index}" class="list-group-item list-group-item-action lesson-card${index === selectedStepIndex ? ' is-active' : ''}">
              <div class="item-head">
                <div class="item-title">${index + 1}. ${escapeHtml(item.title || item.id)}</div>
                ${getStatusBadgeMarkup(getStepStatus(lesson.id, index).status, getStepStatus(lesson.id, index).message)}
              </div>
              <div class="item-meta">${escapeHtml(item.id)}</div>
              ${item.description ? `<div class="item-meta">${escapeHtml(item.description)}</div>` : ''}
              <div class="item-meta">${item.detail ? escapeHtml(item.detail) : 'No injected inputs.'}</div>
              ${item.externalVariables.length ? `
                <div class="external-vars" aria-label="External variables">
                  ${item.externalVariables.map((variable) => `
                    <span class="external-var" title="${escapeHtml(variable.name)}">
                      <span class="external-var__name">${escapeHtml(variable.name)}</span>
                      <span class="external-var__value">${escapeHtml(variable.value || '(empty)')}</span>
                    </span>
                  `).join('')}
                </div>
              ` : '<div class="item-meta">No external variables.</div>'}
            </button>
          `).join('')}`
        : '<div class="empty"><p class="empty-title">No steps.</p></div>';
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
        renderRunControls();
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

        if (scriptMonitor) {
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

    function getRunControlDebugSnapshot() {
      const runtime = getRunnerRuntimeSnapshot();
      return {
        currentRun: currentRun ? { ...currentRun } : null,
        stopRequested,
        isLessonRunning,
        controls: {
          runStepDisabled: runSelectedStepBtn?.disabled ?? null,
          runLessonDisabled: runCurrentBtn?.disabled ?? null,
          runAllDisabled: runAllBtn?.disabled ?? null,
          stopDisabled: stopRunBtn?.disabled ?? null,
          stopText: stopRunBtn?.textContent?.trim() || ''
        },
        runner: getRunnerDebugState(),
        runtime
      };
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

    async function warmRunnerAssets(app = null) {
      if (runnerWarmAssetsPromise) {
        return await runnerWarmAssetsPromise;
      }
      runnerWarmAssetsPromise = (async () => {
        const runnerApp = app || await waitForRunnerReady(5000);
        if (!runnerApp || typeof runnerApp.warmRuntimeAssets !== 'function') {
          return null;
        }
        const warmed = await runnerApp.warmRuntimeAssets({ preloadPhonemes: true });
        appendStatus('Runner assets warmed.', warmed);
        return warmed;
      })().catch((err) => {
        appendStatus('Runner asset warmup failed.', {
          error: err.message || String(err)
        });
        runnerWarmAssetsPromise = null;
        return null;
      });
      return await runnerWarmAssetsPromise;
    }

    function primeBrailleBridgeConnection(app = null, { timeoutMs = 1500, wait = false, force = false } = {}) {
      const now = Date.now();
      if (!force) {
        if (brailleBridgeWarmup.pending) {
          return wait ? brailleBridgeWarmup.pending : Promise.resolve(brailleBridgeWarmup.connected);
        }
        if (brailleBridgeWarmup.connected && now - brailleBridgeWarmup.lastAttemptAt < 30000) {
          return Promise.resolve(true);
        }
        if (!brailleBridgeWarmup.connected && now - brailleBridgeWarmup.lastAttemptAt < 15000) {
          return Promise.resolve(false);
        }
      }

      brailleBridgeWarmup.pending = (async () => {
        const runnerApp = app || await waitForRunnerReady(5000);
        if (!runnerApp || typeof runnerApp.ensureBrailleBridgeConnection !== 'function') {
          brailleBridgeWarmup.connected = false;
          brailleBridgeWarmup.lastAttemptAt = Date.now();
          return false;
        }
        const connected = await runnerApp.ensureBrailleBridgeConnection(timeoutMs);
        brailleBridgeWarmup.connected = Boolean(connected);
        brailleBridgeWarmup.lastAttemptAt = Date.now();
        appendStatus('Braille bridge warmup completed.', {
          connected: brailleBridgeWarmup.connected,
          timeoutMs
        });
        return brailleBridgeWarmup.connected;
      })().finally(() => {
        brailleBridgeWarmup.pending = null;
      });

      return wait ? brailleBridgeWarmup.pending : Promise.resolve(false);
    }

    function getBrailleStudioAuthToken() {
      const sessionPrimary = String(sessionStorage.getItem('runmethodAuthToken') || '').trim();
      if (sessionPrimary) return sessionPrimary;
      return String(localStorage.getItem('runmethodAuthToken') || '').trim();
    }

    function getBrailleStudioAuthHeaders(extra = {}) {
      const headers = { ...extra };
      const token = getBrailleStudioAuthToken();
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }
      return headers;
    }

    function setBrailleStudioAuthToken(token) {
      const normalized = String(token || '').trim();
      if (normalized) {
        sessionStorage.setItem('runmethodAuthToken', normalized);
        localStorage.setItem('runmethodAuthToken', normalized);
      } else {
        sessionStorage.removeItem('runmethodAuthToken');
        localStorage.removeItem('runmethodAuthToken');
      }
    }

    function renderRunmethodAuthStatus(message = '', isError = false) {
      if (!runmethodAuthStatus) return;
      const text = String(message || '').trim();
      runmethodAuthStatus.textContent = text;
      runmethodAuthStatus.className = isError ? 'text-danger small' : 'text-secondary small';
      runmethodAuthStatus.classList.toggle('hidden', !text);
    }

    function renderAuthButton() {
      renderRunmethodAuthStatus('');
      renderLessonsList();
      renderCurrentLesson();
    }

    function requireAuthForRun() {
      return true;
    }

    async function loginRunmethodAuth() {
      const username = String(runmethodUsernameInput?.value || '').trim();
      const password = String(runmethodPasswordInput?.value || '');
      if (!username || !password) {
        renderRunmethodAuthStatus('Enter username and password first.', true);
        return '';
      }
      if (runmethodLoginBtn) runmethodLoginBtn.disabled = true;
      renderRunmethodAuthStatus('Logging in...');
      try {
        const res = await fetch('https://www.tastenbraille.com/braillestudio/authentication-api/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username,
            password,
            audience: 'braillestudio-api'
          })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data?.ok || !data?.token) {
          throw new Error(data?.error || `HTTP ${res.status}`);
        }
        setBrailleStudioAuthToken(data.token);
        if (runmethodPasswordInput) runmethodPasswordInput.value = '';
        if (runmethodAuthPanel) {
          runmethodAuthPanel.dataset.open = '';
        }
        renderAuthButton();
        renderRunmethodAuthStatus(`Authenticated as ${String(data?.user?.username || username)}.`);
        return data.token;
      } catch (err) {
        renderRunmethodAuthStatus(`Login failed: ${err.message || String(err)}`, true);
        throw err;
      } finally {
        if (runmethodLoginBtn) runmethodLoginBtn.disabled = false;
      }
    }

    async function loadScriptData(id) {
      const scriptId = String(id || '').trim();
      if (!scriptId) throw new Error('Missing script id');
      if (scriptDataCache.has(scriptId)) {
        appendStatus('Script load cache hit.', { scriptId });
        return scriptDataCache.get(scriptId);
      }
      const url = `${blocklyApiBase}/load.php?id=${encodeURIComponent(scriptId)}`;
      appendStatus('Script load requested.', {
        scriptId,
        url,
        authenticated: Boolean(getBrailleStudioAuthToken())
      });
      const res = await fetch(url, { cache: 'no-store', headers: getBrailleStudioAuthHeaders() });
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch {
        data = null;
      }
      if (!res.ok) {
        appendStatus('Script load HTTP failure.', {
          scriptId,
          url,
          status: res.status,
          statusText: res.statusText,
          response: responseText.slice(0, 500)
        });
        throw new Error(`Failed to load script ${scriptId} (HTTP ${res.status})`);
      }
      if (!data || !data.blockly) {
        appendStatus('Script load response missing Blockly state.', {
          scriptId,
          url,
          responseKeys: data && typeof data === 'object' ? Object.keys(data) : [],
          response: responseText.slice(0, 500)
        });
        throw new Error(`Script ${scriptId} has no blockly state`);
      }
      scriptDataCache.set(scriptId, data);
      appendStatus('Script loaded.', {
        scriptId,
        url,
        title: String(data.title || ''),
        responseKeys: Object.keys(data)
      });
      return data;
    }

    async function loadScriptsList() {
      const url = `${blocklyApiBase}/list.php`;
      appendStatus('Script list requested.', {
        url,
        authenticated: Boolean(getBrailleStudioAuthToken())
      });
      const res = await fetch(url, { cache: 'no-store', headers: getBrailleStudioAuthHeaders() });
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch {
        data = null;
      }
      if (!res.ok) {
        appendStatus('Script list HTTP failure.', {
          url,
          status: res.status,
          statusText: res.statusText,
          response: responseText.slice(0, 500)
        });
        throw new Error(`Failed to load script list (HTTP ${res.status})`);
      }
      const items = Array.isArray(data?.items) ? data.items : [];
      appendStatus('Script list loaded.', {
        url,
        scriptCount: items.length,
        scriptIds: items.slice(0, 100).map((item) => String(item?.id || ''))
      });
      return items;
    }

    function getReferencedScriptIds() {
      const ids = new Set();
      lessons.forEach((lesson) => {
        const steps = Array.isArray(lesson?.steps) ? lesson.steps : [];
        steps.forEach((step) => {
          const scriptId = String(step?.id || '').trim();
          if (scriptId) ids.add(scriptId);
        });
      });
      return Array.from(ids);
    }

    async function preloadReferencedScriptData() {
      const scriptIds = getReferencedScriptIds();
      if (!scriptIds.length) {
        return { total: 0, loaded: 0, failed: 0 };
      }
      const results = await Promise.allSettled(
        scriptIds.map(async (scriptId) => {
          await loadScriptData(scriptId);
          return scriptId;
        })
      );
      results.forEach((result, index) => {
        if (result.status !== 'rejected') return;
        appendStatus('Script preload failed.', {
          scriptId: scriptIds[index],
          error: result.reason?.message || String(result.reason)
        });
      });
      const failed = results.filter((result) => result.status === 'rejected');
      return {
        total: scriptIds.length,
        loaded: results.length - failed.length,
        failed: failed.length
      };
    }

    function createStoppedCompletion() {
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

    async function waitForStopRequest(intervalMs = 50, shouldCancel = null) {
      while (!stopRequested) {
        if (typeof shouldCancel === 'function' && shouldCancel()) {
          return null;
        }
        await new Promise((resolve) => setTimeout(resolve, intervalMs));
      }
      return createStoppedCompletion();
    }

    async function waitForCompletion(app, timeoutMs = 30000, options = {}) {
	      const start = Date.now();
        let lastRuntime = null;
        const requireExplicitCompletion = Boolean(options?.requireExplicitCompletion);
	      while (Date.now() - start < timeoutMs) {
        if (stopRequested) {
          return createStoppedCompletion();
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
	        if (
            runtime?.programEndedCompletedGeneration === runtime?.programEndedGeneration &&
            runtime?.programEndedGeneration >= 0 &&
            runtime?.isActive === false
          ) {
            if (requireExplicitCompletion) {
              await new Promise((resolve) => setTimeout(resolve, 100));
              continue;
            }
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
      void primeBrailleBridgeConnection(runnerApp, { timeoutMs: 1500, wait: false });
      const basisIndex = Number(lesson.basisIndex ?? -1);
      appendStatus('runLessonStep: loading script.', {
        lessonId: lesson.id,
        basisIndex,
        step: getStepDebugSnapshot(stepConfig, stepIndex),
        runnerState: getRunnerDebugState(),
        runtimeBefore: getRunnerRuntimeSnapshot(runnerApp)
      });
      const scriptData = await loadScriptData(stepConfig.id);
      const requireExplicitCompletion = workspaceStateContainsBlockType(scriptData.blockly, 'lesson_complete_step')
        || workspaceStateContainsBlockType(scriptData.blockly, 'lesson_complete_lesson');
      appendStatus('runLessonStep: script loaded.', {
        lessonId: lesson.id,
        stepIndex,
        scriptId: stepConfig.id,
        scriptTitle: scriptData.title || '',
        hasBlockly: Boolean(scriptData.blockly),
        requireExplicitCompletion
      });
      const stoppedCompletion = createStoppedCompletion();
      let stopWatcherCancelled = false;
      const runPromise = runnerApp.runWorkspaceStateHeadless({
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
      const stopPromise = waitForStopRequest(50, () => stopWatcherCancelled).then((completion) => {
        if (!completion) return null;
        return {
          __stoppedByUser: true,
          generation: null,
          startedBlockCount: null,
          currentRecordIndex: null,
          currentRecord: null,
          lessonMethod: method && typeof method === 'object' ? structuredClone(method) : null,
          stepCompletion: completion,
          lessonCompletion: null,
          runtime: getRunnerRuntimeSnapshot(runnerApp)
        };
      });
      let result = null;
      try {
        result = await Promise.race([runPromise, stopPromise]);
      } finally {
        stopWatcherCancelled = true;
      }
      if (result?.__stoppedByUser) {
        runPromise.catch((err) => {
          appendStatus('Stopped step runner settled with an error after stop.', {
            lessonId: lesson.id,
            stepIndex,
            scriptId: stepConfig.id,
            error: err?.message || String(err)
          });
        });
      }
      const completion = stopRequested
        ? (result?.stepCompletion || stoppedCompletion)
        : (result?.stepCompletion || await waitForCompletion(runnerApp, 30000, { requireExplicitCompletion }));
      appendStatus('runLessonStep: runner returned.', {
        lessonId: lesson.id,
        stepIndex,
        scriptId: stepConfig.id,
        resultStepCompletion: result?.stepCompletion || null,
        resultLessonCompletion: result?.lessonCompletion || null,
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

    async function runLesson(lesson, options = {}) {
      if (!lesson) throw new Error('No lesson selected');
      const manageSession = options?.manageSession !== false;
      if (manageSession) {
        startRunSession('lesson', lesson, 0);
      } else {
        updateCurrentRunLesson(lesson);
        updateCurrentRunStep(0);
      }
      stopRequested = false;
      const steps = Array.isArray(lesson.steps) ? lesson.steps : [];
      selectedStepIndex = 0;
      clearStepStatuses(lesson.id);
      setLessonStatus(lesson.id, 'running', `Lesson running: ${lesson.title || lesson.id}`);
      renderLessonsList();
      renderCurrentLesson();
      const displayedValues = [
        { key: 'lessonId', value: lesson.id || '' },
        { key: 'status', value: 'running' }
      ];
      setLessonRunningState(true, manageSession ? `Running ${lesson.title || lesson.id}` : `Running all: ${lesson.title || lesson.id}`);
      renderLessonReturnValues(displayedValues);
      appendStatus('Lesson run gestart.', {
        lessonId: lesson.id,
        lessonTitle: lesson.title,
        steps: steps.length,
        selectedLessonIndex,
        selectedStepIndex,
        stepIds: steps.map((step) => String(step?.id || '')),
        runnerUrl,
        runnerState: getRunnerDebugState()
      });
      const results = [];
      while (selectedStepIndex < steps.length) {
        const stepIndex = selectedStepIndex;
        updateCurrentRunStep(stepIndex);
        renderCurrentLesson();
        if (stopRequested) {
          setLessonStatus(lesson.id, 'stopped', 'Lesson stopped by user');
          appendStatus('Lesson handmatig gestopt.', {
            lessonId: lesson.id,
            stepIndex
          });
          break;
        }
        const stepConfig = steps[stepIndex];
        setStepStatus(lesson.id, stepIndex, 'running', `Running ${stepConfig.id}`);
        renderCurrentLesson();
        appendStatus('Step gestart.', {
          lessonId: lesson.id,
          stepIndex,
          scriptId: stepConfig.id
        });
        const { completion, result } = await runLessonStep(lesson, stepConfig, stepIndex);
        const completionStatus = String(completion?.status || '').trim().toLowerCase();
        const stepFinishedStatus = stopRequested
          ? 'stopped'
          : (completionStatus === 'failed' ? 'failed' : (completionStatus === 'stopped' ? 'stopped' : 'completed'));
        setStepStatus(lesson.id, stepIndex, stepFinishedStatus, completion?.response?.feedback || completion?.status || '');
        renderCurrentLesson();
        results.push({
          scriptId: stepConfig.id,
          completion,
          lessonCompletion: result?.lessonCompletion || null
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
          lessonCompletion: result?.lessonCompletion || null,
          previousStepIndex: stepIndex
        });
        if (stepFinishedStatus === 'stopped') {
          setLessonStatus(lesson.id, 'stopped', 'Lesson stopped by user');
          renderLessonsList();
          appendStatus('Lesson run: stopped after current step.', {
            lessonId: lesson.id,
            stepIndex
          });
          break;
        }
        if (result?.lessonCompletion) {
          setLessonStatus(lesson.id, 'completed', 'Lesson completed');
          renderLessonsList();
          appendStatus('Lesson run: lesson completion detected.', {
            lessonId: lesson.id,
            stepIndex,
            lessonCompletion: result.lessonCompletion
          });
          break;
        }
        selectedStepIndex = stepIndex + 1;
        appendStatus('Lesson run: moving to next step.', {
          lessonId: lesson.id,
          previousStepIndex: stepIndex,
          nextStepIndex: selectedStepIndex,
          hasNextStep: selectedStepIndex < steps.length,
          nextStep: selectedStepIndex < steps.length ? getStepDebugSnapshot(steps[selectedStepIndex], selectedStepIndex) : null
        });
        if (selectedStepIndex < steps.length) {
          renderCurrentLesson();
        }
      }
      const finalStatus = results[results.length - 1]?.completion?.status || 'completed';
      const normalizedFinalStatus = stopRequested
        ? 'stopped'
        : (String(finalStatus).trim().toLowerCase() === 'failed' ? 'failed' : 'completed');
      setLessonStatus(
        lesson.id,
        normalizedFinalStatus,
        normalizedFinalStatus === 'completed' ? 'Lesson completed' : (normalizedFinalStatus === 'failed' ? 'Lesson failed' : 'Lesson stopped')
      );
      renderLessonsList();
      displayedValues.push({ key: 'finalStatus', value: finalStatus });
      renderLessonReturnValues(displayedValues);
      if (manageSession) {
        setLessonRunningState(false, normalizedFinalStatus === 'completed' ? 'Completed' : `Stopped (${finalStatus})`);
        finishRunSession(
          normalizedFinalStatus,
          normalizedFinalStatus === 'completed'
            ? `Lesson completed: ${lesson.title || lesson.id}`
            : (normalizedFinalStatus === 'failed'
              ? `Lesson failed: ${lesson.title || lesson.id}`
              : `Lesson stopped: ${lesson.title || lesson.id}`)
        );
      }
      stopRequested = false;
      return results;
    }

    async function runSelectedStep() {
      appendStatus('Run step knop ingedrukt.', getRunControlDebugSnapshot());
      if (!requireAuthForRun()) {
        appendStatus('Run step geblokkeerd door authenticatie.', getRunControlDebugSnapshot());
        return;
      }
      try {
        const lesson = getSelectedLesson();
        if (!lesson) throw new Error('No lesson selected');
        const steps = Array.isArray(lesson.steps) ? lesson.steps : [];
        if (!steps[selectedStepIndex]) throw new Error('No step selected');
        startRunSession('step', lesson, selectedStepIndex);
        appendStatus('Run step sessiestatus gestart.', getRunControlDebugSnapshot());
        stopRequested = false;
        setLessonStatus(lesson.id, 'running', `Manual step run in ${lesson.title || lesson.id}`);
        setStepStatus(lesson.id, selectedStepIndex, 'running', `Running ${steps[selectedStepIndex].id}`);
        renderLessonsList();
        renderCurrentLesson();
        setLessonRunningState(true, `Running from step ${selectedStepIndex + 1}`);
        appendStatus('Run vanaf geselecteerde step gestart.', {
          lessonId: lesson.id,
          stepIndex: selectedStepIndex,
          scriptId: steps[selectedStepIndex].id,
          stepIds: steps.map((step) => String(step?.id || '')),
          runnerUrl,
          runnerState: getRunnerDebugState()
        });
        const displayedValues = [
          { key: 'lessonId', value: lesson.id || '' },
          { key: 'startStepIndex', value: String(selectedStepIndex + 1) },
          { key: 'status', value: 'running' }
        ];
        renderLessonReturnValues(displayedValues);
        const stepIndex = selectedStepIndex;
        updateCurrentRunStep(stepIndex);
        const stepConfig = steps[stepIndex];
        appendStatus('Runner-start wordt aangeroepen.', {
          lessonId: lesson.id,
          stepIndex,
          scriptId: stepConfig.id,
          state: getRunControlDebugSnapshot()
        });
        const { completion, result } = await runLessonStep(lesson, stepConfig, stepIndex);
        appendStatus('Runner-start is afgerond.', {
          completion,
          resultRuntime: result?.runtime || null,
          state: getRunControlDebugSnapshot()
        });
        const finalStatus = stopRequested
          ? 'stopped'
          : (String(completion?.status || 'completed').trim().toLowerCase() || 'completed');
        setStepStatus(
          lesson.id,
          stepIndex,
          finalStatus === 'failed' ? 'failed' : (finalStatus === 'stopped' ? 'stopped' : 'completed'),
          completion?.response?.feedback || completion?.status || ''
        );
        if (result?.lessonCompletion) {
          setLessonStatus(lesson.id, 'completed', 'Lesson completed');
        } else if (finalStatus === 'failed') {
          setLessonStatus(lesson.id, 'failed', 'Step failed');
        } else if (finalStatus === 'stopped') {
          setLessonStatus(lesson.id, 'stopped', 'Step stopped');
        } else {
          setLessonStatus(lesson.id, 'completed', 'Step completed');
        }
        renderLessonsList();
        renderCurrentLesson();
        displayedValues.push({ key: `step.${stepIndex + 1}.scriptId`, value: stepConfig.id });
        flattenCompletionValues(completion).forEach((entry) => {
          displayedValues.push({
            key: `step.${stepIndex + 1}.${entry.key}`,
            value: entry.value
          });
        });
        displayedValues.push({ key: 'finalStatus', value: finalStatus });
        renderLessonReturnValues(displayedValues);
        setLessonRunningState(false, finalStatus === 'completed' ? 'Step completed' : `Stopped (${finalStatus})`);
        finishRunSession(
          finalStatus === 'failed' ? 'failed' : (finalStatus === 'stopped' ? 'stopped' : 'completed'),
          finalStatus === 'completed'
            ? `Step completed: ${stepConfig.title || stepConfig.id}`
            : (finalStatus === 'failed'
              ? `Step failed: ${stepConfig.title || stepConfig.id}`
              : `Step stopped: ${stepConfig.title || stepConfig.id}`)
        );
        appendStatus('Run vanaf geselecteerde step afgerond.', {
          lessonId: lesson.id,
          stepIndex,
          scriptId: stepConfig.id,
          completion,
          lessonCompletion: result?.lessonCompletion || null,
          state: getRunControlDebugSnapshot()
        });
        stopRequested = false;
      } catch (err) {
        setLessonRunningState(false, 'Stopped (error)');
        if (currentRun?.lessonId) {
          const failedLesson = getSelectedLesson();
          if (failedLesson?.id === currentRun.lessonId && Number.isInteger(currentRun.stepIndex)) {
            setStepStatus(failedLesson.id, currentRun.stepIndex, 'failed', err.message || String(err));
            setLessonStatus(failedLesson.id, 'failed', err.message || String(err));
            renderLessonsList();
            renderCurrentLesson();
          }
        }
        renderLessonReturnValues([
          { key: 'error', value: err.message || String(err) }
        ]);
        finishRunSession('failed', `Step run failed: ${err.message || String(err)}`);
        appendStatus('Run vanaf geselecteerde step mislukt.', {
          error: err.message || String(err),
          stack: err?.stack || '',
          state: getRunControlDebugSnapshot()
        });
        stopRequested = false;
      }
    }

    async function runCurrentLesson() {
      if (!requireAuthForRun()) return;
      const lesson = getSelectedLesson();
      try {
        selectedStepIndex = 0;
        renderCurrentLesson();
        appendStatus('Run current lesson button pressed.', {
          selectedLessonIndex,
          lessonId: lesson?.id || '',
          stepCount: Array.isArray(lesson?.steps) ? lesson.steps.length : 0
        });
        const results = await runLesson(lesson);
        appendStatus('Current lesson afgerond.', { lessonId: lesson?.id || '', results });
      } catch (err) {
        setLessonRunningState(false, 'Stopped (error)');
        if (lesson?.id) {
          setLessonStatus(lesson.id, 'failed', err.message || String(err));
          renderLessonsList();
          renderCurrentLesson();
        }
        renderLessonReturnValues([
          { key: 'error', value: err.message || String(err) }
        ]);
        finishRunSession('failed', `Lesson run failed: ${err.message || String(err)}`);
        appendStatus('Run current lesson mislukt.', {
          error: err.message || String(err),
          runnerState: getRunnerDebugState()
        });
        stopRequested = false;
      }
    }

    async function runAllLessons() {
      if (!requireAuthForRun()) return;
      startRunSession('all', null, null);
      setLessonRunningState(true, 'Running all lessons');
      stopRequested = false;
      appendStatus('Run all gestart.', { lessons: lessons.length });
      for (let index = 0; index < lessons.length; index += 1) {
        if (stopRequested) {
          setLessonRunningState(false, 'Stopped (manual)');
          appendStatus('Run all handmatig gestopt.');
          finishRunSession('stopped', 'Run all stopped.');
          stopRequested = false;
          return;
        }
        selectedLessonIndex = index;
        renderLessonsList();
        renderCurrentLesson();
        try {
          const lesson = lessons[index];
          const results = await runLesson(lesson, { manageSession: false });
        } catch (err) {
          setLessonRunningState(false, 'Stopped (error)');
          renderLessonReturnValues([
            { key: 'error', value: err.message || String(err) }
          ]);
          appendStatus('Run all mislukt.', {
            lessonId: lessons[index]?.id || '',
            error: err.message || String(err)
          });
          finishRunSession('failed', `Run all failed: ${err.message || String(err)}`);
          stopRequested = false;
          return;
        }
      }
      setLessonRunningState(false, 'Not running');
      finishRunSession('completed', 'Run all completed.');
      stopRequested = false;
      appendStatus('Run all afgerond.');
    }

    async function stopCurrentRun() {
      appendStatus('Stop knop ingedrukt.', getRunControlDebugSnapshot());
      const app = await waitForRunnerReady(5000);
      const audioStopped = typeof app?.stopAudio === 'function'
        ? await app.stopAudio()
        : false;
      if (audioStopped) {
        appendStatus('Runner audio direct gestopt.', getRunControlDebugSnapshot());
      }
      if (!currentRun) {
        const runtime = getRunnerRuntimeSnapshot(app);
        if (!runtime?.isActive || typeof app?.stopProgram !== 'function') {
          if (audioStopped) {
            setLessonRunningState(false, 'Audio stopped');
            lastRunBanner = {
              tone: 'stopped',
              text: 'Audio stopped.'
            };
            renderRunControls();
            return;
          }
          appendStatus('Stop genegeerd: geen actieve run, runner of audio.', getRunControlDebugSnapshot());
          renderRunControls();
          return;
        }
        appendStatus('Directe runner-stop wordt aangeroepen.', getRunControlDebugSnapshot());
        await app.stopProgram();
        setLessonRunningState(false, 'Stopped');
        lastRunBanner = {
          tone: 'stopped',
          text: 'Runner stopped.'
        };
        renderRunControls();
        appendStatus('Actieve runner rechtstreeks gestopt.', getRunControlDebugSnapshot());
        return;
      }
      const runSnapshot = { ...currentRun };
      stopRequested = true;
      requestStopCurrentRun();
      appendStatus('Stopstatus is ingesteld.', {
        runSnapshot,
        state: getRunControlDebugSnapshot()
      });
      setLessonRunningState(true, 'Stopping');
      if (currentRun.lessonId && Number.isInteger(getCurrentRunStepIndex())) {
        setStepStatus(currentRun.lessonId, getCurrentRunStepIndex(), 'stopping', 'Stop requested');
        renderCurrentLesson();
      }
      if (currentRun.lessonId) {
        setLessonStatus(currentRun.lessonId, 'stopping', 'Stop requested');
        renderLessonsList();
      }
      try {
        if (app && typeof app.stopProgram === 'function') {
          appendStatus('Runner stopProgram wordt aangeroepen.', getRunControlDebugSnapshot());
          await app.stopProgram();
          appendStatus('Runner stopProgram is afgerond.', getRunControlDebugSnapshot());
        }
      } catch (err) {
        appendStatus('Stop requested, but runner was not ready.', {
          error: err.message || String(err),
          stack: err?.stack || '',
          state: getRunControlDebugSnapshot()
        });
        finalizeStoppedStepRun(runSnapshot);
        if (runSnapshot.mode !== 'step') {
          finishRunSession('stopped', 'Run stop requested.');
        }
        return;
      }
      if (runSnapshot.mode === 'step') {
        appendStatus('Step stop acknowledged by runner.');
        finalizeStoppedStepRun(runSnapshot);
        return;
      }
      appendStatus('Stop requested. Active lesson or step will stop as soon as the runner finishes the current cycle.');
    }

    function shouldIgnoreGlobalShortcut(event) {
      const target = event?.target;
      if (!target || typeof target !== 'object') return false;
      const tagName = String(target.tagName || '').toUpperCase();
      return tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT' || target.isContentEditable;
    }

    function isStopAudioShortcut(event) {
      const key = String(event?.key || '').trim().toUpperCase();
      const keyCode = Math.floor(Number(event?.keyCode) || 0);
      return key === 'ESCAPE' || key === 'F3' || keyCode === 27 || keyCode === 114;
    }

    async function stopRunnerAudioFromShortcut(event) {
      if (event?.repeat || shouldIgnoreGlobalShortcut(event) || !isStopAudioShortcut(event)) {
        return false;
      }
      try {
        const app = await waitForRunnerReady(5000);
        if (!app || typeof app.stopAudio !== 'function') {
          return false;
        }
        const stopped = await app.stopAudio();
        if (!stopped) {
          return false;
        }
        appendStatus(`Audio gestopt via ${String(event?.key || 'shortcut')}.`);
        return true;
      } catch (err) {
        appendStatus('Audio stop shortcut failed.', {
          error: err.message || String(err)
        });
        return false;
      }
    }

    lessonRunnerFrame.addEventListener('load', () => {
      appendStatus('Blockly runner iframe geladen.', {
        runnerUrl,
        runnerState: getRunnerDebugState()
      });
      startBrailleMonitorSync();
    });

    if (runSelectedStepBtn) {
      runSelectedStepBtn.addEventListener('click', () => {
        runSelectedStep().catch((err) => {
          appendStatus('Onverwachte fout in Run step click-handler.', {
            error: err.message || String(err),
            stack: err?.stack || '',
            state: getRunControlDebugSnapshot()
          });
        });
      });
    }
    runCurrentBtn.addEventListener('click', runCurrentLesson);
    runAllBtn.addEventListener('click', runAllLessons);
    stopRunBtn.addEventListener('click', () => {
      stopCurrentRun().catch((err) => {
        appendStatus('Onverwachte fout in Stop click-handler.', {
          error: err.message || String(err),
          stack: err?.stack || '',
          state: getRunControlDebugSnapshot()
        });
      });
    });
    simThumbLeftBtn?.addEventListener('click', async () => {
      try {
        await dispatchRunnerInput({ type: 'thumbKey', key: 'left' });
      } catch (err) {
        appendStatus('Left thumb failed.', { error: err.message || String(err) });
      }
    });
    simThumbRightBtn?.addEventListener('click', async () => {
      try {
        await dispatchRunnerInput({ type: 'thumbKey', key: 'right' });
      } catch (err) {
        appendStatus('Right thumb failed.', { error: err.message || String(err) });
      }
    });
    simCursor5Btn?.addEventListener('click', async () => {
      try {
        await dispatchRunnerInput({ type: 'thumbKey', key: 'left-middle' });
      } catch (err) {
        appendStatus('Left middle thumb failed.', { error: err.message || String(err) });
      }
    });
    simChord1Btn?.addEventListener('click', async () => {
      try {
        await dispatchRunnerInput({ type: 'thumbKey', key: 'right-middle' });
      } catch (err) {
        appendStatus('Right middle thumb failed.', { error: err.message || String(err) });
      }
    });
    document.addEventListener('keydown', async (event) => {
      const handled = await stopRunnerAudioFromShortcut(event);
      if (!handled) return;
      event.preventDefault();
      event.stopPropagation();
    }, true);
    renderAuthButton();
    authBtn?.addEventListener('click', async () => {
      if (getBrailleStudioAuthToken()) {
        setBrailleStudioAuthToken('');
        if (runmethodAuthPanel) {
          runmethodAuthPanel.dataset.open = '1';
        }
        renderAuthButton();
        renderRunmethodAuthStatus('Logged out.');
        if (runmethodUsernameInput) {
          runmethodUsernameInput.focus();
        }
        appendStatus('Runmethod authentication logged out.');
        return;
      }
      if (runmethodAuthPanel) {
        runmethodAuthPanel.dataset.open = runmethodAuthPanel.dataset.open ? '' : '1';
      }
      renderAuthButton();
    });
    runmethodLoginBtn?.addEventListener('click', async () => {
      try {
        await loginRunmethodAuth();
        appendStatus('Runmethod authentication completed.');
      } catch (err) {
        appendStatus('Runmethod authentication failed.', { error: err.message || String(err) });
      }
    });
    runmethodPasswordInput?.addEventListener('keydown', async (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      try {
        await loginRunmethodAuth();
        appendStatus('Runmethod authentication completed.');
      } catch (err) {
        appendStatus('Runmethod authentication failed.', { error: err.message || String(err) });
      }
    });
    if (toggleRunnerBtn) {
      toggleRunnerBtn.addEventListener('click', () => {
        isRunnerVisible = !isRunnerVisible;
        renderRunnerVisibility();
      });
    }
    window.addEventListener('storage', (event) => {
      if (event.key === 'runmethodAuthToken') {
        renderAuthButton();
      }
    });

    renderMonitorSourceVisibility(false);

    if (toggleDebugLogBtn) {
      toggleDebugLogBtn.addEventListener('click', () => {
        isDebugLogVisible = !isDebugLogVisible;
        renderDebugLogVisibility();
      });
    }

    async function init() {
      let preloadSummary = { total: 0, loaded: 0, failed: 0 };
      appendStatus('RunMethod load diagnostics.', loadDiagnostics);
      try {
        appendStatus('Initial preload started.', {
          lessonCount: lessons.length,
          runnerUrl
        });
        const runnerWarmup = waitForRunnerReady(15000).then(async (app) => {
          await warmRunnerAssets(app);
          void primeBrailleBridgeConnection(app, { timeoutMs: 1500, wait: false, force: true });
          return app;
        }).catch((err) => {
          appendStatus('Runner warmup failed.', {
            error: err.message || String(err)
          });
          return null;
        });
        scriptsCache = await loadScriptsList();
        const referencedScriptIds = getReferencedScriptIds();
        const availableScriptIds = new Set(scriptsCache.map((item) => String(item?.id || '').trim()).filter(Boolean));
        appendStatus('Referenced script diagnostics.', {
          referencedScriptIds,
          missingFromScriptList: referencedScriptIds.filter((scriptId) => !availableScriptIds.has(scriptId)),
          availableScriptCount: availableScriptIds.size
        });
        hydrateLessonsWithScriptMetadata();
        preloadSummary = await preloadReferencedScriptData();
        await runnerWarmup;
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
      renderRunControls();
      startBrailleMonitorSync();
      appendStatus('Runner ready.', {
        methodId: method.id || '',
        lessons: lessons.length,
        basisRecords: basisRecords.length,
        scripts: scriptsCache.length,
        preloadedScripts: preloadSummary.loaded,
        preloadFailures: preloadSummary.failed,
        runnerUrl
      });
    }

    init();
  </script>
  <?php endif; ?>
</body>
</html>
