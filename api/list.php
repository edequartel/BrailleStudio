<?php

/*
==========================================================
API: list.php
Base URL:
https://tastenbraille.com/api/list.php

PURPOSE
-------
Return a JSON list of audio files for Blockly / BrailleStudio.

PUBLIC AUDIO FOLDERS
--------------------
woorden      -> https://www.tastenbraille.com/braillestudio/sounds/nl/speech
letters      -> https://www.tastenbraille.com/braillestudio/sounds/nl/letters
instructies  -> https://www.tastenbraille.com/braillestudio/sounds/general/instructies
beloningen   -> https://www.tastenbraille.com/braillestudio/sounds/general/beloningen
story        -> https://www.tastenbraille.com/braillestudio/sounds/nl/story

EXAMPLE CURL
------------
1. All words:
curl "https://tastenbraille.com/api/list.php?folder=woorden"

2. All letters:
curl "https://tastenbraille.com/api/list.php?folder=letters"

3. All instructies:
curl "https://tastenbraille.com/api/list.php?folder=instructies"

4. All beloningen:
curl "https://tastenbraille.com/api/list.php?folder=beloningen"

5. All story files:
curl "https://tastenbraille.com/api/list.php?folder=story"

6. Filter by first letters:
curl "https://tastenbraille.com/api/list.php?folder=woorden&letters=a,b,k,l"

7. Filter by klanken:
curl "https://tastenbraille.com/api/list.php?folder=woorden&klanken=aa,ee,oe"

8. Combined:
curl "https://tastenbraille.com/api/list.php?folder=woorden&letters=l,b&klanken=aa,ee"

PARAMETERS
----------
folder   required: woorden, letters, instructies, beloningen, story
letters  optional: comma-separated first letters, e.g. a,b,k,l
klanken  optional: comma-separated substrings, e.g. aa,ee,oe,sch

OUTPUT
------
[
  {
    "word": "lamp",
    "url": "https://www.tastenbraille.com/braillestudio/sounds/nl/speech/lamp.mp3"
  }
]

NOTES
-----
- If no letters/klanken are given, all mp3 files in the folder are returned.
- letters filters on the FIRST character of the filename (without extension).
- klanken filters on substring match inside the filename.
- Response is always JSON.
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
// Assumed:
//   list.php                  -> /public_html/api/list.php
//   audio root               -> /public_html/braillestudio/sounds/...
// -----------------------------
$folderMap = [
    'woorden' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/speech',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/speech',
    ],
    'letters' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/letters',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/letters',
    ],
    'instructies' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/general/instructies',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/general/instructies',
    ],
    'beloningen' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/general/beloningen',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/general/beloningen',
    ],
    'story' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/story',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/story',
    ],
];

if (!isset($folderMap[$folder])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'invalid folder',
        'allowed' => array_keys($folderMap),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$targetDir = $folderMap[$folder]['dir'];
$publicBaseUrl = $folderMap[$folder]['url'];

if (!is_dir($targetDir)) {
    http_response_code(404);
    echo json_encode([
        'error' => 'folder not found',
        'path' => $targetDir,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// -----------------------------
// Parse filters
// -----------------------------
$letters = array_values(array_filter(array_map(function ($value) {
    return mb_strtolower(trim($value), 'UTF-8');
}, explode(',', $lettersParam))));

$klanken = array_values(array_filter(array_map(function ($value) {
    return mb_strtolower(trim($value), 'UTF-8');
}, explode(',', $klankenParam))));

// -----------------------------
// Read files
// -----------------------------
$entries = scandir($targetDir);

if ($entries === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'could not read folder',
        'path' => $targetDir,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$files = array_values(array_filter($entries, function ($file) use ($targetDir, $letters, $klanken) {
    $fullPath = $targetDir . '/' . $file;

    if (!is_file($fullPath)) {
        return false;
    }

    if (mb_strtolower(pathinfo($file, PATHINFO_EXTENSION), 'UTF-8') !== 'mp3') {
        return false;
    }

    $name = mb_strtolower(pathinfo($file, PATHINFO_FILENAME), 'UTF-8');

    // Filter by first letter
    if (!empty($letters)) {
        $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
        if (!in_array($firstLetter, $letters, true)) {
            return false;
        }
    }

    // Filter by klanken (substring match)
    if (!empty($klanken)) {
        $matched = false;

        foreach ($klanken as $klank) {
            if ($klank !== '' && mb_strpos($name, $klank, 0, 'UTF-8') !== false) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            return false;
        }
    }

    return true;
}));

natcasesort($files);
$files = array_values($files);

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