<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');
ignore_user_abort(true);

header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$expectedToken = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';

if ($token !== $expectedToken) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

/**
 * Chunk files from:
 * - ./braillestudio
 * - ./data/marechal
 *
 * Supported:
 * - .md
 * - .txt
 * - .html / .htm
 * - .docx
 *
 * Output:
 * - /braillestudio/data/chunks.json
 */

$publicHtmlRoot = dirname(__DIR__, 2);

$config = [
    'input_folders' => [
        $publicHtmlRoot . '/braillestudio',
        $publicHtmlRoot . '/data/marechal',
        $publicHtmlRoot . '/rooster',
    ],

    'output_file' => dirname(__DIR__) . '/data/chunks.json',

    'max_words_per_chunk' => 250,
    'overlap_words' => 40,

    'include_extensions' => [
        'md', 'txt', 'html', 'htm', 'docx',
    ],

    'exclude_folders_contains' => [
        '/vendor/',
        '/node_modules/',
        '/cache/',
        '/tmp/',
        '/temp/',
        '/logs/',
        '/log/',
        '/css/',
        '/js/',
        '/fonts/',
        '/img/',
        '/images/',
        '/assets/',
        '/.git/',
        '/wp-admin/',
        '/wp-includes/',
        '/chunker-api/',
    ],

    'exclude_file_names' => [
        'package-lock.json',
        'composer.lock',
        'chunks.json',
        'curls.txt',
        'lesson-curls.txt',
        'dirlist.txt',
        'mp3.txt',
    ],

    'exclude_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'css', 'js', 'map', 'pdf',
        'zip', 'rar', 'tar', 'gz',
        'mp3', 'wav', 'm4a', 'mp4', 'mov',
        'ttf', 'otf', 'woff', 'woff2',
        'php', 'json', 'xml',
    ],

    'exclude_uploads_folder' => false,
    'verbose' => true,
];

$outputDir = dirname($config['output_file']);
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        http_response_code(500);
        echo "Failed to create output directory: {$outputDir}";
        exit;
    }
}

$allChunks = [];
$fileCounter = 0;
$chunkCounter = 0;

echo "Starting chunking...\n";
echo "Scanning folders:\n";
foreach ($config['input_folders'] as $folder) {
    echo "- {$folder}\n";
}
echo "Output file: " . $config['output_file'] . "\n\n";

foreach ($config['input_folders'] as $folder) {
    if (!is_dir($folder)) {
        echo "Skipping missing folder: {$folder}\n";
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $filePath = $fileInfo->getPathname();
        $normalizedPath = str_replace('\\', '/', $filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileName = basename($filePath);

        if (shouldSkipFolderPath($normalizedPath, $config)) {
            continue;
        }

        if (in_array($fileName, $config['exclude_file_names'], true)) {
            continue;
        }

        if (in_array($extension, $config['exclude_extensions'], true)) {
            continue;
        }

        if (!in_array($extension, $config['include_extensions'], true)) {
            continue;
        }

        if ($config['verbose']) {
            echo "Including: {$normalizedPath}\n";
        }

        $rawText = extractTextFromFile($filePath, $extension);

        if ($rawText === null) {
            echo "Could not extract text: {$normalizedPath}\n\n";
            flushBuffers();
            continue;
        }

        $cleanText = cleanText($rawText);

        if ($cleanText === '') {
            echo "Empty after cleaning: {$normalizedPath}\n\n";
            flushBuffers();
            continue;
        }

        $title = deriveTitle($filePath, $cleanText);
        $chunks = chunkText(
            $cleanText,
            $config['max_words_per_chunk'],
            $config['overlap_words']
        );

        $fileCounter++;

        foreach ($chunks as $index => $chunkText) {
            $chunkCounter++;

            $allChunks[] = [
                'id' => md5($normalizedPath . '::' . $index),
                'source_path' => $normalizedPath,
                'relative_path' => makeRelativePath($normalizedPath, str_replace('\\', '/', $publicHtmlRoot)),
                'file_name' => $fileName,
                'extension' => $extension,
                'title' => $title,
                'chunk_index' => $index,
                'text' => $chunkText,
                'word_count' => str_word_count($chunkText),
            ];
        }

        echo "Processed: {$normalizedPath} (" . count($chunks) . " chunks)\n\n";
        flushBuffers();
    }
}

echo "About to write " . count($allChunks) . " chunks\n";

$tempOutputFile = $config['output_file'] . '.tmp';

$result = file_put_contents(
    $tempOutputFile,
    json_encode($allChunks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

if ($result === false) {
    http_response_code(500);
    echo "Failed to write temp output file.\n";
    exit;
}

if (!rename($tempOutputFile, $config['output_file'])) {
    http_response_code(500);
    echo "Failed to replace output file.\n";
    exit;
}

echo "Done.\n";
echo "Files processed: {$fileCounter}\n";
echo "Chunks written: {$chunkCounter}\n";
echo "Output: {$config['output_file']}\n";
echo "Written at: " . date('Y-m-d H:i:s') . "\n";
echo "Bytes written: " . (is_file($config['output_file']) ? filesize($config['output_file']) : 0) . "\n";

function flushBuffers(): void
{
    @ob_flush();
    @flush();
}

function shouldSkipFolderPath(string $path, array $config): bool
{
    foreach ($config['exclude_folders_contains'] as $part) {
        if (strpos($path, $part) !== false) {
            return true;
        }
    }

    if (!empty($config['exclude_uploads_folder']) && strpos($path, '/uploads/') !== false) {
        return true;
    }

    return false;
}

function makeRelativePath(string $fullPath, string $baseDir): string
{
    if (str_starts_with($fullPath, $baseDir)) {
        return ltrim(substr($fullPath, strlen($baseDir)), '/');
    }

    return $fullPath;
}

function extractTextFromFile(string $filePath, string $extension): ?string
{
    return match ($extension) {
        'md', 'txt' => @file_get_contents($filePath) ?: null,
        'html', 'htm' => extractTextFromHtml($filePath),
        'docx' => extractTextFromDocx($filePath),
        default => null,
    };
}

function extractTextFromHtml(string $filePath): ?string
{
    $html = @file_get_contents($filePath);
    if ($html === false) {
        return null;
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    if (!$loaded) {
        return strip_tags($html);
    }

    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//script|//style|//noscript|//svg') as $node) {
        $node->parentNode?->removeChild($node);
    }

    $body = $xpath->query('//body')->item(0);
    if ($body) {
        return $body->textContent ?? '';
    }

    return strip_tags($html);
}

function extractTextFromDocx(string $filePath): ?string
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return null;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        return null;
    }

    $xml = str_replace('</w:p>', "</w:p>\n", $xml);
    $text = strip_tags($xml);

    return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function cleanText(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    $text = preg_replace('/[[:^print:]\n\t]/u', '', $text);
    $text = trim($text);

    return $text ?? '';
}

function deriveTitle(string $filePath, string $text): string
{
    $lines = preg_split("/\n/", $text) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (preg_match('/^#{1,6}\s+(.*)$/', $line, $matches)) {
            return trim($matches[1]);
        }

        return mb_substr($line, 0, 120);
    }

    return pathinfo($filePath, PATHINFO_FILENAME);
}

function chunkText(string $text, int $maxWords, int $overlapWords): array
{
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $total = count($words);

    if ($total === 0) {
        return [];
    }

    if ($total <= $maxWords) {
        return [implode(' ', $words)];
    }

    $chunks = [];
    $start = 0;

    while ($start < $total) {
        $slice = array_slice($words, $start, $maxWords);
        if (empty($slice)) {
            break;
        }

        $chunks[] = implode(' ', $slice);

        if ($start + $maxWords >= $total) {
            break;
        }

        $start += max(1, $maxWords - $overlapWords);
    }

    return $chunks;
}