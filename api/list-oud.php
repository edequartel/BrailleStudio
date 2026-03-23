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

PARAMETERS
----------
folder            required

letters           → first letter filter
klanken           → contains filter

onlyletters       → ONLY these letters allowed
onlyklanken       → ONLY these phonics allowed

onlycombo         → STRICT combination of letters + phonics
                    word must satisfy BOTH:
                    - all characters ∈ onlyletters
                    - all phonics ∈ onlyklanken

EXAMPLE
-------
curl "...?folder=woorden&onlycombo=true&onlyletters=a,l,p,m&onlyklanken=a,aa,l,p,m"

LOGIC
-----
- All filters use AND logic
- onlycombo makes letter + klank restriction BOTH required

==========================================================
*/

header('Content-Type: application/json; charset=utf-8');

// -----------------------------
// Input
// -----------------------------
$folder = $_GET['folder'] ?? '';
$lettersParam = $_GET['letters'] ?? '';
$klankenParam = $_GET['klanken'] ?? '';
$onlyLettersParam = $_GET['onlyletters'] ?? '';
$onlyKlankenParam = $_GET['onlyklanken'] ?? '';
$onlyCombo = ($_GET['onlycombo'] ?? 'false') === 'true';

$folder = basename(trim($folder));

// -----------------------------
// Folder mapping
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
    echo json_encode(['error' => 'invalid folder']);
    exit;
}

$targetDir = $folderMap[$folder]['dir'];
$publicBaseUrl = $folderMap[$folder]['url'];

if (!is_dir($targetDir)) {
    http_response_code(404);
    echo json_encode(['error' => 'folder not found']);
    exit;
}

// -----------------------------
// Parse filters
// -----------------------------
$letters = array_values(array_filter(array_map(fn($v) => mb_strtolower(trim($v)), explode(',', $lettersParam))));
$klanken = array_values(array_filter(array_map(fn($v) => mb_strtolower(trim($v)), explode(',', $klankenParam))));
$onlyLetters = array_values(array_filter(array_map(fn($v) => mb_strtolower(trim($v)), explode(',', $onlyLettersParam))));
$onlyKlanken = array_values(array_filter(array_map(fn($v) => mb_strtolower(trim($v)), explode(',', $onlyKlankenParam))));

// -----------------------------
// Helper: klank tokenizer
// -----------------------------
function splitKlanken($word) {
    $patterns = ['sch', 'ng', 'aa', 'ee', 'oo', 'uu', 'oe', 'ei', 'ij', 'ui', 'au', 'ou'];
    $result = [];

    while ($word !== '') {
        $matched = false;

        foreach ($patterns as $p) {
            if (mb_substr($word, 0, mb_strlen($p)) === $p) {
                $result[] = $p;
                $word = mb_substr($word, mb_strlen($p));
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $result[] = mb_substr($word, 0, 1);
            $word = mb_substr($word, 1);
        }
    }

    return $result;
}

// -----------------------------
// Read files
// -----------------------------
$entries = scandir($targetDir);

$files = array_values(array_filter($entries, function ($file) use ($targetDir, $letters, $klanken, $onlyLetters, $onlyKlanken, $onlyCombo) {

    $fullPath = $targetDir . '/' . $file;

    if (!is_file($fullPath)) return false;
    if (mb_strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'mp3') return false;

    $name = mb_strtolower(pathinfo($file, PATHINFO_FILENAME));

    // first letter
    if (!empty($letters)) {
        if (!in_array(mb_substr($name, 0, 1), $letters, true)) return false;
    }

    // contains klanken
    if (!empty($klanken)) {
        $matched = false;
        foreach ($klanken as $k) {
            if (mb_strpos($name, $k) !== false) {
                $matched = true;
                break;
            }
        }
        if (!$matched) return false;
    }

    // onlyletters
    if (!empty($onlyLetters)) {
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            if (!in_array($char, $onlyLetters, true)) return false;
        }
    }

    // onlyklanken
    if (!empty($onlyKlanken)) {
        $wordKlanken = splitKlanken($name);
        foreach ($wordKlanken as $klank) {
            if (!in_array($klank, $onlyKlanken, true)) return false;
        }
    }

    // STRICT COMBINATION (NEW)
    if ($onlyCombo && (!empty($onlyLetters) || !empty($onlyKlanken))) {

        // must satisfy BOTH if provided
        if (!empty($onlyLetters)) {
            $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $char) {
                if (!in_array($char, $onlyLetters, true)) return false;
            }
        }

        if (!empty($onlyKlanken)) {
            $wordKlanken = splitKlanken($name);
            foreach ($wordKlanken as $klank) {
                if (!in_array($klank, $onlyKlanken, true)) return false;
            }
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