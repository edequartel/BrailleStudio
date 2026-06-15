<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Amsterdam');
@set_time_limit(0);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$appBase = preg_replace('~/tools$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');
$soundsRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'braillestudio-data' . DIRECTORY_SEPARATOR . 'sounds';
$audioExtensions = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'];
$selectedPath = normalize_relative_path((string)($_POST['folder_path'] ?? ''));
$selectedFiles = array_values(array_filter(
    array_map('normalize_relative_path', (array)($_POST['selected_files'] ?? [])),
    static fn (string $path): bool => $path !== ''
));
$fileSelectionEnabled = isset($_POST['file_selection_enabled']);
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$appendSuffix = $requestMethod === 'POST' ? isset($_POST['append_converted_suffix']) : true;
$outputFormat = strtolower(trim((string)($_POST['output_format'] ?? 'mp3')));
if (!in_array($outputFormat, ['mp3', 'm4a'], true)) {
    $outputFormat = 'mp3';
}
$message = null;
$messageType = 'secondary';
$results = [];

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function normalize_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('~/+~', '/', $path) ?? '';
    $path = trim($path, '/');
    if ($path === '.' || $path === '..') {
        return '';
    }
    return $path;
}

function find_ffmpeg_path(): string
{
    $publicHtmlRoot = dirname(__DIR__, 2);
    $candidates = [
        getenv('FFMPEG_PATH') ?: '',
        dirname(__DIR__) . '/api/bin/ffmpeg',
        $publicHtmlRoot . '/api/bin/ffmpeg',
        '/Users/ericdequartel/Library/Containers/com.eltima.cmd1.mas/Data/.COVolumes/_Bluehost/public_html/api/bin/ffmpeg',
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/homebrew/bin/ffmpeg',
        '/opt/local/bin/ffmpeg',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    $output = [];
    @exec('command -v ffmpeg 2>/dev/null', $output, $exitCode);
    $path = trim((string)($output[0] ?? ''));
    if ($exitCode === 0 && $path !== '' && is_file($path) && is_executable($path)) {
        return $path;
    }

    return '';
}

function is_safe_folder(string $root, string $relativePath): ?string
{
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return null;
    }

    $relativePath = normalize_relative_path($relativePath);
    $candidatePath = $relativePath === ''
        ? $rootReal
        : $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $candidate = realpath($candidatePath);

    if ($candidate === false || !is_dir($candidate)) {
        return null;
    }

    if ($candidate !== $rootReal && !str_starts_with($candidate, $rootReal . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $candidate;
}

function format_mb(float $bytes): string
{
    return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
}

function ndjson_emit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    @ob_flush();
    flush();
}

/**
 * @return array<int,array{path:string,audio_count:int,audio_files:array<int,string>}>
 */
function scan_sound_folders(string $soundsRoot, array $audioExtensions): array
{
    $rootReal = realpath($soundsRoot);
    if ($rootReal === false || !is_dir($rootReal)) {
        return [];
    }

    $folders = [
        '' => [
            'path' => '',
            'audio_count' => 0,
            'audio_files' => [],
        ],
    ];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }

        if ($item->isDir()) {
            $relativeDir = normalize_relative_path(str_replace('\\', '/', substr($item->getPathname(), strlen($rootReal))));
            $folders[$relativeDir] ??= [
                'path' => $relativeDir,
                'audio_count' => 0,
                'audio_files' => [],
            ];
            continue;
        }

        if (!$item->isFile()) {
            continue;
        }

        $extension = strtolower($item->getExtension());
        if (!in_array($extension, $audioExtensions, true)) {
            continue;
        }

        $relativeFile = normalize_relative_path(str_replace('\\', '/', substr($item->getPathname(), strlen($rootReal))));
        $relativeFolder = normalize_relative_path(dirname($relativeFile));
        $parts = $relativeFolder === '' ? [] : explode('/', $relativeFolder);
        $ancestor = '';
        $ancestorPaths = [''];

        foreach ($parts as $part) {
            $ancestor = $ancestor === '' ? $part : $ancestor . '/' . $part;
            $ancestorPaths[] = $ancestor;
        }

        foreach ($ancestorPaths as $folderPath) {
            $folders[$folderPath] ??= [
                'path' => $folderPath,
                'audio_count' => 0,
                'audio_files' => [],
            ];
            $relativeToFolder = $folderPath === ''
                ? $relativeFile
                : normalize_relative_path(substr($relativeFile, strlen($folderPath) + 1));
            $folders[$folderPath]['audio_count']++;
            $folders[$folderPath]['audio_files'][] = $relativeToFolder;
        }
    }

    $items = array_values($folders);
    usort($items, static fn (array $a, array $b): int => strnatcasecmp($a['path'], $b['path']));

    return $items;
}

/**
 * @param array{path:string,audio_count:int,audio_files:array<int,string>} $selectedFolder
 * @return array{message:string,messageType:string,results:array<int,array{file:string,status:string,detail:string}>}
 */
function run_folder_conversion(
    array $selectedFolder,
    string $selectedPath,
    string $absoluteFolder,
    string $ffmpegPath,
    bool $appendSuffix,
    string $outputFormat,
    array $selectedFiles = [],
    bool $streamProgress = false
): array {
    $results = [];
    $convertedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    $savedBytes = 0.0;
    $audioFiles = $selectedFolder['audio_files'] ?? [];
    if ($selectedFiles !== []) {
        $allowedFiles = array_flip($audioFiles);
        $audioFiles = array_values(array_filter(
            $selectedFiles,
            static fn (string $file): bool => isset($allowedFiles[$file])
        ));
    }
    sort($audioFiles, SORT_NATURAL | SORT_FLAG_CASE);
    $total = count($audioFiles);

    if ($streamProgress) {
        ndjson_emit(['type' => 'start', 'folder' => $selectedPath, 'total' => $total]);
    }

    foreach ($audioFiles as $index => $inputName) {
        $inputName = normalize_relative_path((string)$inputName);
        $displayName = $inputName;

        if ($streamProgress) {
            ndjson_emit([
                'type' => 'progress',
                'current' => $index + 1,
                'total' => $total,
                'file' => $displayName,
            ]);
        }

        if ($inputName === '' || str_contains($inputName, '..')) {
            $row = [
                'file' => $displayName,
                'status' => 'Fout',
                'detail' => 'Ongeldig pad binnen de gekozen map.',
            ];
            $results[] = $row;
            $errorCount++;
            if ($streamProgress) {
                ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
            }
            continue;
        }

        if ($appendSuffix && str_contains(pathinfo($inputName, PATHINFO_FILENAME), '_converted')) {
            $row = [
                'file' => $displayName,
                'status' => 'Overgeslagen',
                'detail' => 'Bestand heeft al suffix _converted.',
            ];
            $results[] = $row;
            $skippedCount++;
            if ($streamProgress) {
                ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
            }
            continue;
        }

        $inputPath = $absoluteFolder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputName);
        $inputRealPath = realpath($inputPath);
        $absoluteFolderReal = realpath($absoluteFolder);
        if (
            $inputRealPath === false
            || $absoluteFolderReal === false
            || ($inputRealPath !== $absoluteFolderReal && !str_starts_with($inputRealPath, $absoluteFolderReal . DIRECTORY_SEPARATOR))
        ) {
            $row = [
                'file' => $displayName,
                'status' => 'Fout',
                'detail' => 'Bestand valt buiten de gekozen map.',
            ];
            $results[] = $row;
            $errorCount++;
            if ($streamProgress) {
                ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
            }
            continue;
        }

        if (!is_file($inputPath)) {
            $row = [
                'file' => $displayName,
                'status' => 'Fout',
                'detail' => 'Bestand bestaat niet meer.',
            ];
            $results[] = $row;
            $errorCount++;
            if ($streamProgress) {
                ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
            }
            continue;
        }

        $baseName = pathinfo($inputName, PATHINFO_FILENAME);
        $outputExtension = $outputFormat === 'mp3' ? 'mp3' : 'm4a';
        $outputName = ($appendSuffix ? $baseName . '_converted' : $baseName) . '.' . $outputExtension;
        $outputFolder = dirname($inputPath);
        $outputPath = $outputFolder . DIRECTORY_SEPARATOR . $outputName;
        $tempOutputPath = $outputFolder . DIRECTORY_SEPARATOR . '__tmp__' . md5($inputName . microtime(true)) . '.' . $outputExtension;
        $outputRelativePath = normalize_relative_path(
            ($selectedPath === '' ? '' : $selectedPath . '/')
            . (dirname($inputName) === '.' ? '' : dirname($inputName) . '/')
            . $outputName
        );
        $inputSize = (float)filesize($inputPath);

        if ($outputFormat === 'mp3') {
            $command = sprintf(
                '%s -y -i %s -map_metadata 0 -ac 1 -c:a libmp3lame -b:a 128k -write_xing 1 -id3v2_version 3 %s 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($inputPath),
                escapeshellarg($tempOutputPath)
            );
        } else {
            $command = sprintf(
                '%s -y -i %s -map_metadata 0 -movflags +faststart -ac 1 -c:a aac -b:a 128k %s 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($inputPath),
                escapeshellarg($tempOutputPath)
            );
        }

        $commandOutput = [];
        exec($command, $commandOutput, $exitCode);
        $detail = trim(implode("\n", array_slice($commandOutput, -4)));

        if ($exitCode !== 0) {
            @unlink($tempOutputPath);
            $row = [
                'file' => $displayName,
                'status' => 'Fout',
                'detail' => $detail === '' ? 'ffmpeg gaf een fout terug.' : $detail,
            ];
            $results[] = $row;
            $errorCount++;
            if ($streamProgress) {
                ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
            }
            continue;
        }

        if (!@rename($tempOutputPath, $outputPath)) {
            @unlink($tempOutputPath);
            $row = [
                'file' => $displayName,
                'status' => 'Fout',
                'detail' => 'Geconverteerd bestand kon niet worden opgeslagen in dezelfde map.',
            ];
            $results[] = $row;
            $errorCount++;
            if ($streamProgress) {
                ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
            }
            continue;
        }

        $outputSize = is_file($outputPath) ? (float)filesize($outputPath) : 0.0;
        $bytesSaved = max(0.0, $inputSize - $outputSize);
        $savedBytes += $bytesSaved;
        $row = [
            'file' => $displayName,
            'status' => 'Geconverteerd',
            'detail' => $outputName . ' · bespaard: ' . format_mb($bytesSaved),
            'outputPath' => $outputRelativePath,
        ];
        $results[] = $row;
        $convertedCount++;

        if ($streamProgress) {
            ndjson_emit(['type' => 'result', 'current' => $index + 1, 'total' => $total] + $row);
        }
    }

    $summary = sprintf(
        'Optimalisatie naar %s afgerond voor %s. %d bestand(en) geconverteerd, %d overgeslagen, %d fout(en), bespaard: %s.',
        strtoupper($outputFormat),
        $selectedPath === '' ? './sounds' : './sounds/' . $selectedPath,
        $convertedCount,
        $skippedCount,
        $errorCount,
        format_mb($savedBytes)
    );

    if ($streamProgress) {
        ndjson_emit([
            'type' => 'done',
            'message' => $summary,
            'converted' => $convertedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'saved' => format_mb($savedBytes),
            'total' => $total,
        ]);
    }

    return [
        'message' => $summary,
        'messageType' => $errorCount > 0 ? 'warning' : 'success',
        'results' => $results,
    ];
}

/**
 * @param array{path:string,audio_count:int,audio_files:array<int,string>} $selectedFolder
 * @return array{ok:bool,message:string,deleted:array<int,string>,errors:array<int,array{file:string,message:string}>}
 */
function delete_selected_files(
    array $selectedFolder,
    string $absoluteFolder,
    array $selectedFiles
): array {
    $allowedFiles = array_flip($selectedFolder['audio_files'] ?? []);
    $deleted = [];
    $errors = [];
    $absoluteFolderReal = realpath($absoluteFolder);

    if ($absoluteFolderReal === false) {
        return [
            'ok' => false,
            'message' => 'De gekozen map kan niet veilig worden geopend.',
            'deleted' => [],
            'errors' => [],
        ];
    }

    foreach ($selectedFiles as $file) {
        $file = normalize_relative_path((string)$file);
        if ($file === '' || str_contains($file, '..') || !isset($allowedFiles[$file])) {
            $errors[] = ['file' => $file, 'message' => 'Bestand hoort niet bij de gekozen map.'];
            continue;
        }

        $path = $absoluteFolder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        $realPath = realpath($path);
        if (
            $realPath === false
            || !is_file($realPath)
            || ($realPath !== $absoluteFolderReal && !str_starts_with($realPath, $absoluteFolderReal . DIRECTORY_SEPARATOR))
        ) {
            $errors[] = ['file' => $file, 'message' => 'Bestand kan niet veilig worden geopend.'];
            continue;
        }

        if (!@unlink($realPath)) {
            $errors[] = ['file' => $file, 'message' => 'Bestand kon niet worden verwijderd.'];
            continue;
        }

        $deleted[] = $file;
    }

    $message = sprintf(
        '%d bestand(en) verwijderd, %d fout(en).',
        count($deleted),
        count($errors)
    );

    return [
        'ok' => count($errors) === 0,
        'message' => $message,
        'deleted' => $deleted,
        'errors' => $errors,
    ];
}

$ffmpegPath = find_ffmpeg_path();
$folderOptions = scan_sound_folders($soundsRoot, $audioExtensions);
$folderFilesByPath = [];
foreach ($folderOptions as $folder) {
    $files = $folder['audio_files'] ?? [];
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    $folderFilesByPath[$folder['path']] = array_values($files);
}

if ($requestMethod === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $selectedFolder = null;
    foreach ($folderOptions as $folder) {
        if (($folder['path'] ?? '') === $selectedPath) {
            $selectedFolder = $folder;
            break;
        }
    }

    if ($selectedFolder === null) {
        echo json_encode(['ok' => false, 'message' => 'De gekozen map is niet geldig.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($selectedFiles === []) {
        echo json_encode(['ok' => false, 'message' => 'Kies minimaal een bestand om te verwijderen.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $absoluteFolder = is_safe_folder($soundsRoot, $selectedPath);
    if ($absoluteFolder === null) {
        echo json_encode(['ok' => false, 'message' => 'De gekozen map kan niet veilig worden geopend.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(delete_selected_files($selectedFolder, $absoluteFolder, $selectedFiles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/x-ndjson; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no');
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    $selectedFolder = null;
    foreach ($folderOptions as $folder) {
        if (($folder['path'] ?? '') === $selectedPath) {
            $selectedFolder = $folder;
            break;
        }
    }

    if ($selectedFolder === null) {
        ndjson_emit(['type' => 'error', 'message' => 'De gekozen map is niet geldig.']);
        exit;
    }

    if ($fileSelectionEnabled && $selectedFiles === []) {
        ndjson_emit(['type' => 'error', 'message' => 'Kies minimaal een audiobestand.']);
        exit;
    }

    if ($ffmpegPath === '') {
        ndjson_emit(['type' => 'error', 'message' => 'ffmpeg is niet gevonden op de server. Stel FFMPEG_PATH in of installeer ffmpeg.']);
        exit;
    }

    $absoluteFolder = is_safe_folder($soundsRoot, $selectedPath);
    if ($absoluteFolder === null) {
        ndjson_emit(['type' => 'error', 'message' => 'De gekozen map kan niet veilig worden geopend.']);
        exit;
    }

    run_folder_conversion($selectedFolder, $selectedPath, $absoluteFolder, $ffmpegPath, $appendSuffix, $outputFormat, $selectedFiles, true);
    exit;
}

if ($requestMethod === 'POST') {
    $selectedFolder = null;
    foreach ($folderOptions as $folder) {
        if (($folder['path'] ?? '') === $selectedPath) {
            $selectedFolder = $folder;
            break;
        }
    }

    if ($selectedFolder === null) {
        $message = 'De gekozen map is niet geldig.';
        $messageType = 'danger';
    } elseif ($fileSelectionEnabled && $selectedFiles === []) {
        $message = 'Kies minimaal een audiobestand.';
        $messageType = 'danger';
    } elseif ($ffmpegPath === '') {
        $message = 'ffmpeg is niet gevonden op de server. Stel FFMPEG_PATH in of installeer ffmpeg.';
        $messageType = 'danger';
    } else {
        $absoluteFolder = is_safe_folder($soundsRoot, $selectedPath);
        if ($absoluteFolder === null) {
            $message = 'De gekozen map kan niet veilig worden geopend.';
            $messageType = 'danger';
        } else {
            $run = run_folder_conversion($selectedFolder, $selectedPath, $absoluteFolder, $ffmpegPath, $appendSuffix, $outputFormat, $selectedFiles, false);
            $message = $run['message'];
            $messageType = $run['messageType'];
            $results = $run['results'];
        }
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sounds optimaliseren</title>
  <link rel="stylesheet" href="<?= $h($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $h($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark" href="<?= $h($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-braille text-primary" aria-hidden="true"></i></span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row ms-auto">
          <a class="btn btn-outline-secondary" href="<?= $h($urlFor($appBase, 'index.php')) ?>">
            <i class="ti ti-home me-2" aria-hidden="true"></i>Home
          </a>
        </div>
      </div>
    </header>

    <main class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-3 align-items-center">
            <div class="col">
              <div class="page-pretitle">BrailleStudio tools</div>
              <h1 class="page-title">Sounds optimaliseren</h1>
              <div class="text-secondary mt-2">Kies een map onder <code>./sounds</code>. Audio wordt geconverteerd naar mono MP3 of AAC in dezelfde map.</div>
            </div>
            <div class="col-auto">
              <span class="badge <?= $ffmpegPath !== '' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                ffmpeg <?= $ffmpegPath !== '' ? 'gevonden' : 'niet gevonden' ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <?php if (!is_dir($soundsRoot)): ?>
            <div class="alert alert-warning">
              De map <code>./sounds</code> bestaat niet in deze installatie.
            </div>
          <?php endif; ?>

          <form method="post" id="convert-form">
            <section class="card mb-3">
              <div class="card-header">
                <div>
                  <h2 class="card-title">Bestanden optimaliseren</h2>
                  <div class="card-subtitle">Selecteer een map en kies daarna de bestanden.</div>
                </div>
                <div class="card-actions">
                  <div class="d-flex align-items-center gap-2">
                    <label for="folder_path" class="form-label mb-0 text-secondary">Map</label>
                    <select name="folder_path" id="folder_path" class="form-select" required style="min-width: min(28rem, 70vw);">
                      <option value="">Kies een map</option>
                      <?php foreach ($folderOptions as $folder): ?>
                        <?php $optionValue = $folder['path'] === '' ? '.' : $folder['path']; ?>
                        <option value="<?= $h($optionValue) ?>" <?= $selectedPath === $folder['path'] ? 'selected' : '' ?>>
                          <?= $folder['path'] === '' ? './sounds' : './sounds/' . $h($folder['path']) ?> (<?= (int)$folder['audio_count'] ?> audio)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="card-body">
                <input type="hidden" name="file_selection_enabled" value="1">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
                  <label class="form-label mb-0">Bestanden</label>
                  <div class="btn-list">
                    <button type="button" id="select-all-files" class="btn btn-outline-secondary btn-sm">Alles</button>
                    <button type="button" id="select-no-files" class="btn btn-outline-secondary btn-sm">Geen</button>
                    <button type="submit" id="convert-submit" class="btn btn-primary" <?= $folderOptions === [] ? 'disabled' : '' ?>>
                      <i class="ti ti-player-track-next me-2" aria-hidden="true"></i>Optimaliseer
                    </button>
                    <button type="button" id="play-selected-file" class="btn btn-outline-secondary" disabled>
                      <i class="ti ti-player-play me-2" aria-hidden="true"></i>Play
                    </button>
                    <button type="button" id="stop-selected-file" class="btn btn-outline-secondary" disabled>
                      <i class="ti ti-player-stop me-2" aria-hidden="true"></i>Stop
                    </button>
                    <button type="button" id="delete-selected-files" class="btn btn-outline-danger">
                      <i class="ti ti-trash me-2" aria-hidden="true"></i>Verwijder geselecteerd
                    </button>
                  </div>
                </div>
                <div id="selected-files-summary" class="text-secondary small mb-2">Kies eerst een map.</div>
                <div id="selected-files-list" class="list-group list-group-flush border rounded" style="max-height: 22rem; overflow: auto;"></div>
              </div>
            </section>

            <section class="card mb-3">
              <div class="card-header">
                <h2 class="card-title">Output instellingen</h2>
              </div>
              <div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-12 col-md-6">
                    <label for="output_format" class="form-label">Bestandsformaat</label>
                    <select name="output_format" id="output_format" class="form-select">
                      <option value="mp3" <?= $outputFormat === 'mp3' ? 'selected' : '' ?>>MP3 128k mono, streaming</option>
                      <option value="m4a" <?= $outputFormat === 'm4a' ? 'selected' : '' ?>>M4A/AAC 128k mono, faststart</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" value="1" id="append_converted_suffix" name="append_converted_suffix" <?= $appendSuffix ? 'checked' : '' ?>>
                      <label class="form-check-label" for="append_converted_suffix">Maak nieuw bestand met <code>_converted</code></label>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </form>

          <section class="card mb-3">
            <div class="card-header">
              <h2 class="card-title">Voortgang</h2>
            </div>
            <div class="card-body">
              <div class="progress progress-sm mb-2">
                <div id="convert-progress-bar" class="progress-bar" style="width: 0%" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
              </div>
              <div id="convert-progress-text" class="text-secondary">Nog niet gestart.</div>
            </div>
          </section>

          <div id="convert-message">
            <?php if ($message !== null): ?>
              <section class="alert alert-<?= $h($messageType) ?> mb-3"><?= nl2br($h($message)) ?></section>
            <?php endif; ?>
          </div>

          <section class="card">
            <div class="card-header">
              <h2 class="card-title">Resultaten</h2>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>Bestand</th>
                    <th>Status</th>
                    <th>Details</th>
                    <th>Output</th>
                  </tr>
                </thead>
                <tbody id="results-body">
                  <?php foreach ($results as $result): ?>
                    <tr>
                      <td><?= $h($result['file']) ?></td>
                      <td><?= $h($result['status']) ?></td>
                      <td><pre class="mb-0" style="white-space: pre-wrap;"><?= $h($result['detail']) ?></pre></td>
                      <td>
                        <?php if (!empty($result['outputPath'])): ?>
                          <?php $audioUrl = 'https://www.tastenbraille.com/braillestudio-data/sounds/' . ltrim((string)$result['outputPath'], '/'); ?>
                          <div class="d-flex align-items-center gap-2">
                            <a class="font-monospace small" href="<?= $h($audioUrl) ?>" target="_blank" rel="noopener"><?= $h((string)$result['outputPath']) ?></a>
                            <button class="btn btn-outline-secondary" type="button" data-audio-action="play-pause" data-audio-url="<?= $h($audioUrl) ?>">
                              <i class="ti ti-player-play me-2" aria-hidden="true"></i>Play
                            </button>
                            <button class="btn btn-outline-secondary" type="button" data-audio-action="stop" data-audio-url="<?= $h($audioUrl) ?>">
                              <i class="ti ti-player-stop me-2" aria-hidden="true"></i>Stop
                            </button>
                          </div>
                        <?php else: ?>
                          <span class="text-secondary">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= $h($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.4/howler.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const soundsBaseUrl = 'https://www.tastenbraille.com/braillestudio-data/sounds/';
      const folderFiles = <?= json_encode($folderFilesByPath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const initiallySelectedFiles = new Set(<?= json_encode($selectedFiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
      const form = document.getElementById('convert-form');
      const folderSelect = document.getElementById('folder_path');
      const selectedFilesList = document.getElementById('selected-files-list');
      const selectedFilesSummary = document.getElementById('selected-files-summary');
      const selectAllFiles = document.getElementById('select-all-files');
      const selectNoFiles = document.getElementById('select-no-files');
      const deleteSelectedFiles = document.getElementById('delete-selected-files');
      const playSelectedFile = document.getElementById('play-selected-file');
      const stopSelectedFile = document.getElementById('stop-selected-file');
      const submitButton = document.getElementById('convert-submit');
      const progressBar = document.getElementById('convert-progress-bar');
      const progressText = document.getElementById('convert-progress-text');
      const resultsBody = document.getElementById('results-body');
      const messageHost = document.getElementById('convert-message');
      let currentHowl = null;
      let currentHowlUrl = '';
      let currentSoundId = null;
      let currentPlayButton = null;

      if (!form || !submitButton || !progressBar || !progressText || !resultsBody || !messageHost) {
        return;
      }

      function normalizeFolderValue(value) {
        const normalized = String(value || '').replaceAll('\\', '/').replace(/^\/+|\/+$/g, '');
        return normalized === '.' ? '' : normalized;
      }

      function getFileCheckboxes() {
        return Array.from(document.querySelectorAll('input[name="selected_files[]"]'));
      }

      function getSelectedFileValues() {
        return getFileCheckboxes()
          .filter((input) => input.checked)
          .map((input) => input.value);
      }

      function showMessage(message, type = 'info') {
        const variants = {
          info: 'alert alert-info mb-3',
          success: 'alert alert-success mb-3',
          danger: 'alert alert-danger mb-3',
          warning: 'alert alert-warning mb-3'
        };
        messageHost.innerHTML = '<section class="' + (variants[type] || variants.info) + '">' + escapeHtml(message) + '</section>';
      }

      function buildSoundUrl(outputPath) {
        return soundsBaseUrl + String(outputPath || '').split('/').map(encodeURIComponent).join('/');
      }

      function getSelectedInputUrl(file) {
        const folder = normalizeFolderValue(folderSelect?.value || '');
        const path = (folder ? folder + '/' : '') + String(file || '');
        return buildSoundUrl(path);
      }

      function resetPlayButton(button) {
        if (!button) return;
        const icon = button.querySelector('i');
        if (icon) {
          icon.className = 'ti ti-player-play';
        }
      }

      function setPauseButton(button) {
        if (!button) return;
        const icon = button.querySelector('i');
        if (icon) {
          icon.className = 'ti ti-player-pause';
        }
      }

      function stopCurrentAudio() {
        if (currentHowl) {
          try {
            currentHowl.stop();
            currentHowl.unload();
          } catch (error) {}
        }
        resetPlayButton(currentPlayButton);
        currentHowl = null;
        currentHowlUrl = '';
        currentSoundId = null;
        currentPlayButton = null;
      }

      async function playHowlerUrl(url, button) {
        if (typeof window.Howl !== 'function') {
          showMessage('Howler is niet geladen.', 'danger');
          return;
        }

        const absoluteUrl = new URL(url, window.location.href).href;
        if (currentHowl && currentHowlUrl === absoluteUrl) {
          if (currentHowl.playing(currentSoundId)) {
            currentHowl.pause(currentSoundId);
            resetPlayButton(button);
          } else {
            currentSoundId = currentHowl.play(currentSoundId || undefined);
            currentPlayButton = button;
            setPauseButton(button);
          }
          return;
        }

        stopCurrentAudio();
        currentHowlUrl = absoluteUrl;
        currentPlayButton = button;
        currentHowl = new Howl({
          src: [absoluteUrl],
          html5: true,
          preload: true,
          onend: stopCurrentAudio,
          onloaderror: function () {
            showMessage('Kon het audiobestand niet laden.', 'danger');
            stopCurrentAudio();
          },
          onplayerror: function () {
            showMessage('Kon het audiobestand niet afspelen.', 'danger');
            stopCurrentAudio();
          }
        });
        currentSoundId = currentHowl.play();
        setPauseButton(button);
      }

      function buildOutputCell(payload) {
        const outputPath = String(payload.outputPath || '');
        if (!outputPath) {
          return '<span class="text-secondary">-</span>';
        }
        const url = buildSoundUrl(outputPath);
        return '<div class="d-flex align-items-center gap-2">' +
          '<a class="font-monospace small" href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(outputPath) + '</a>' +
          '<button class="btn btn-outline-secondary" type="button" data-audio-action="play-pause" data-audio-url="' + escapeHtml(url) + '"><i class="ti ti-player-play me-2" aria-hidden="true"></i>Play</button>' +
          '<button class="btn btn-outline-secondary" type="button" data-audio-action="stop" data-audio-url="' + escapeHtml(url) + '"><i class="ti ti-player-stop me-2" aria-hidden="true"></i>Stop</button>' +
          '</div>';
      }

      function updateFileSummary() {
        if (!selectedFilesSummary) return;
        const checkboxes = getFileCheckboxes();
        const selected = checkboxes.filter((input) => input.checked).length;
        const canPreview = selected === 1;
        if (playSelectedFile) playSelectedFile.disabled = !canPreview;
        if (stopSelectedFile) stopSelectedFile.disabled = !canPreview && !currentHowl;
        if (!checkboxes.length) {
          selectedFilesSummary.textContent = 'Geen audiobestanden in deze map.';
          return;
        }
        selectedFilesSummary.textContent = selected + ' van ' + checkboxes.length + ' bestand(en) geselecteerd.';
      }

      function renderSelectedFiles() {
        if (!folderSelect || !selectedFilesList) return;
        const folder = normalizeFolderValue(folderSelect.value);
        const files = folderFiles[folder] || [];
        selectedFilesList.replaceChildren();

        if (!files.length) {
          updateFileSummary();
          return;
        }

        files.forEach((file, index) => {
          const id = 'selected_file_' + index;
          const label = document.createElement('label');
          label.className = 'list-group-item d-flex align-items-center gap-2 py-2';
          label.setAttribute('for', id);

          const input = document.createElement('input');
          input.className = 'form-check-input m-0 flex-shrink-0';
          input.type = 'checkbox';
          input.name = 'selected_files[]';
          input.value = file;
          input.id = id;
          input.checked = initiallySelectedFiles.has(file);
          input.addEventListener('change', updateFileSummary);

          const text = document.createElement('span');
          text.className = 'font-monospace small text-truncate';
          text.style.minWidth = '0';
          text.textContent = file;

          label.append(input, text);
          selectedFilesList.appendChild(label);
        });

        updateFileSummary();
      }

      folderSelect?.addEventListener('change', () => {
        initiallySelectedFiles.clear();
        renderSelectedFiles();
      });
      selectAllFiles?.addEventListener('click', () => {
        getFileCheckboxes().forEach((input) => {
          input.checked = true;
        });
        updateFileSummary();
      });
      selectNoFiles?.addEventListener('click', () => {
        getFileCheckboxes().forEach((input) => {
          input.checked = false;
        });
        updateFileSummary();
      });
      playSelectedFile?.addEventListener('click', async () => {
        const selected = getSelectedFileValues();
        if (selected.length !== 1) {
          showMessage('Kies precies een bestand om af te spelen.', 'warning');
          return;
        }
        await playHowlerUrl(getSelectedInputUrl(selected[0]), playSelectedFile);
      });
      stopSelectedFile?.addEventListener('click', () => {
        stopCurrentAudio();
        updateFileSummary();
      });
      deleteSelectedFiles?.addEventListener('click', async () => {
        const selected = getSelectedFileValues();
        if (!selected.length) {
          showMessage('Kies minimaal een bestand om te verwijderen.', 'danger');
          return;
        }
        if (!window.confirm('Verwijder ' + selected.length + ' bestand(en) uit ./sounds?')) {
          return;
        }

        const formData = new FormData(form);
        formData.append('ajax_delete', '1');
        deleteSelectedFiles.disabled = true;

        try {
          const response = await fetch('optimize-sounds.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
          });
          const payload = await response.json().catch(() => null);
          if (!response.ok || !payload) {
            throw new Error('De server gaf geen geldige delete-respons terug.');
          }
          if (!payload.ok && !Array.isArray(payload.deleted)) {
            throw new Error(payload.message || 'Verwijderen is mislukt.');
          }

          const folder = normalizeFolderValue(folderSelect.value);
          const deleted = new Set(payload.deleted || []);
          folderFiles[folder] = (folderFiles[folder] || []).filter((file) => !deleted.has(file));
          renderSelectedFiles();
          showMessage(payload.message || 'Bestanden verwijderd.', payload.ok ? 'success' : 'warning');
        } catch (error) {
          showMessage(error.message || 'Verwijderen is mislukt.', 'danger');
        } finally {
          deleteSelectedFiles.disabled = false;
        }
      });
      renderSelectedFiles();

      resultsBody.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-audio-action]');
        if (!button) return;
        const action = String(button.dataset.audioAction || '');
        const url = String(button.dataset.audioUrl || '');
        if (!url) return;

        if (action === 'stop') {
          stopCurrentAudio();
          return;
        }

        if (action !== 'play-pause') {
          return;
        }

        await playHowlerUrl(url, button);
      });

      form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (!getSelectedFileValues().length) {
          showMessage('Kies minimaal een audiobestand.', 'danger');
          return;
        }

        resultsBody.innerHTML = '';
        messageHost.innerHTML = '';
        progressBar.style.width = '0%';
        progressBar.setAttribute('aria-valuenow', '0');
        progressText.textContent = 'Starten...';
        submitButton.disabled = true;

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
          const response = await fetch('optimize-sounds.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/x-ndjson' }
          });

          if (!response.ok || !response.body) {
            throw new Error('De server gaf geen geldige voortgangsrespons terug.');
          }

          const reader = response.body.getReader();
          const decoder = new TextDecoder();
          let buffer = '';

          while (true) {
            const next = await reader.read();
            if (next.done) {
              break;
            }

            buffer += decoder.decode(next.value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            lines.forEach(function (line) {
              if (line.trim() === '') return;
              const payload = JSON.parse(line);

              if (payload.type === 'start') {
                progressText.textContent = 'Optimalisatie gestart voor ./sounds/' + payload.folder + '.';
                return;
              }

              if (payload.type === 'progress') {
                const percent = payload.total > 0 ? Math.round((payload.current - 1) / payload.total * 100) : 0;
                progressBar.style.width = percent + '%';
                progressBar.setAttribute('aria-valuenow', String(percent));
                progressText.textContent = 'Bezig met ' + payload.file + ' (' + payload.current + ' van ' + payload.total + ').';
                return;
              }

              if (payload.type === 'result') {
                const percent = payload.total > 0 ? Math.round(payload.current / payload.total * 100) : 100;
                progressBar.style.width = percent + '%';
                progressBar.setAttribute('aria-valuenow', String(percent));
                const row = document.createElement('tr');
                row.innerHTML =
                  '<td>' + escapeHtml(payload.file) + '</td>' +
                  '<td>' + escapeHtml(payload.status) + '</td>' +
                  '<td><pre class="mb-0" style="white-space: pre-wrap;">' + escapeHtml(payload.detail) + '</pre></td>' +
                  '<td>' + buildOutputCell(payload) + '</td>';
                resultsBody.appendChild(row);
                return;
              }

              if (payload.type === 'done') {
                progressBar.style.width = '100%';
                progressBar.setAttribute('aria-valuenow', '100');
                progressText.textContent = payload.message;
                showMessage(payload.message, 'success');
                return;
              }

              if (payload.type === 'error') {
                progressBar.style.width = '100%';
                progressBar.setAttribute('aria-valuenow', '100');
                progressText.textContent = payload.message;
                showMessage(payload.message, 'danger');
              }
            });
          }
        } catch (error) {
          progressText.textContent = 'Fout tijdens optimalisatie.';
          showMessage(error.message || 'Onbekende fout', 'danger');
        } finally {
          submitButton.disabled = false;
        }
      });

      function escapeHtml(value) {
        return String(value)
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#039;');
      }
    });
  </script>
  <script src="/braillestudio/components/site-footer/site-footer.js?v=20260612-1"></script>
</body>
</html>
