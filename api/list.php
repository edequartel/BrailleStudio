<?php

/*
==========================================================
API: list.php
Base URL:
https://tastenbraille.com/api/list.php

FOLDERS
-------
woorden      -> /sounds/nl/speech
letters      -> /sounds/nl/letters
instructies  -> /sounds/general/instructies
beloningen   -> /sounds/general/beloningen
story        -> /sounds/nl/story

EXAMPLES
--------
curl "https://tastenbraille.com/api/list.php?folder=letters"
curl "https://tastenbraille.com/api/list.php?folder=woorden&letters=l"
curl "https://tastenbraille.com/api/list.php?folder=woorden&klanken=aa,ee"

==========================================================
*/

header('Content-Type: application/json; charset=utf-8');

// -----------------------------
// Input
// -----------------------------
$folder = $_GET['folder'] ?? '';
$lettersParam = $_GET['letters'] ?? '';
$klankenParam = $_GET['klanken'] ?? '';

$folder = basename(trim($folder));

// -----------------------------
// Folder mapping
// -----------------------------
$folderMap = [
    'woorden' => [
        'dir' => __DIR__ . '/../sounds/nl/speech',
        'url' => 'https://www.tastenbraille.com/sounds/nl/speech',
    ],
    'letters' => [
        'dir' => __DIR__ . '/../sounds/nl/letters',
        'url' => 'https://www.tastenbraille.com/sounds/nl/letters',
    ],
    'instructies' => [
        'dir' => __DIR__ . '/../sounds/general/instructies',
        'url' => 'https://www.tastenbraille.com/sounds/general/instructies',
    ],
    'beloningen' => [
        'dir' => __DIR__ . '/../sounds/general/beloningen',
        'url' => 'https://www.tastenbraille.com/sounds/general/beloningen',
    ],
    'story' => [
        'dir' => __DIR__ . '/../sounds/nl/story',
        'url' => 'https://www.tastenbraille.com/sounds/nl/story',
    ],
];

if (!isset($folderMap[$folder])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'invalid folder',
        'allowed' => array_keys($folderMap),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$targetDir = $folderMap[$folder]['dir'];
$publicBaseUrl = $folderMap[$folder]['url'];

if (!is_dir($targetDir)) {
    http_response_code(404);
    echo json_encode([
        'error' => 'folder not found',
        'path' => $targetDir,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------
// Parse filters
// -----------------------------
$letters = array_values(array_filter(array_map(fn($v) => strtolower(trim($v)), explode(',', $lettersParam))));
$klanken = array_values(array_filter(array_map(fn($v) => strtolower(trim($v)), explode(',', $klankenParam))));

// -----------------------------
// Read files
// -----------------------------
$entries = scandir($targetDir);

$files = array_values(array_filter($entries, function ($file) use ($targetDir, $letters, $klanken) {

    $fullPath = $targetDir . '/' . $file;

    if (!is_file($fullPath)) return false;
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'mp3') return false;

    $name = strtolower(pathinfo($file, PATHINFO_FILENAME));

    // letters filter (first character)
    if (!empty($letters)) {
        if (!in_array(mb_substr($name, 0, 1), $letters, true)) {
            return false;
        }
    }

    // klanken filter (substring)
    if (!empty($klanken)) {
        foreach ($klanken as $klank) {
            if ($klank !== '' && mb_strpos($name, $klank) !== false) {
                return true;
            }
        }
        return false;
    }

    return true;
}));

sort($files, SORT_NATURAL | SORT_FLAG_CASE);

// -----------------------------
// Output
// -----------------------------
$result = array_map(function ($file) use ($publicBaseUrl) {
    return [
        'word' => pathinfo($file, PATHINFO_FILENAME),
        'url'  => $publicBaseUrl . '/' . rawurlencode($file),
    ];
}, $files);

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);