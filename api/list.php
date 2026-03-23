<?php

/*
==========================================================
API: list.php (tastenbraille.com/api)
==========================================================

This endpoint returns a JSON list of audio files.

----------------------------------------------------------
HOW TO USE WITH CURL
----------------------------------------------------------

Base URL:
https://tastenbraille.com/api/list.php

1. Get all files from a folder:
curl "https://tastenbraille.com/api/list.php?folder=woorden"

2. Filter by starting letters:
curl "https://tastenbraille.com/api/list.php?folder=woorden&letters=a,b,k,l"

3. Filter by klanken (phonics):
curl "https://tastenbraille.com/api/list.php?folder=woorden&klanken=aa,ee,oe"

4. Combine filters:
curl "https://tastenbraille.com/api/list.php?folder=woorden&letters=l,b&klanken=aa,ee"

5. Pretty print (Mac/Linux):
curl "https://tastenbraille.com/api/list.php?folder=woorden" | jq

----------------------------------------------------------
PARAMETERS
----------------------------------------------------------

folder   (required) → which subfolder inside /audio/
                     allowed: woorden, letters, instructies, beloningen

letters  (optional) → comma-separated list of first letters
                     example: letters=a,b,k,l

klanken  (optional) → comma-separated phonics
                     example: klanken=aa,ee,oe,sch

----------------------------------------------------------
OUTPUT FORMAT
----------------------------------------------------------

[
  { "word": "lamp", "url": "/audio/woorden/lamp.mp3" },
  { "word": "boom", "url": "/audio/woorden/boom.mp3" }
]

----------------------------------------------------------
NOTES
----------------------------------------------------------

- If no filters are given → all files are returned
- letters = filter on FIRST character
- klanken = substring match inside word
- Designed for Blockly / Braille learning apps
- Safe: folder is restricted via whitelist

==========================================================
*/


$baseDir = __DIR__ . '/audio';

// 📥 input
$folder = $_GET['folder'] ?? '';
$lettersParam = $_GET['letters'] ?? '';
$klankenParam = $_GET['klanken'] ?? '';

// 🔐 sanitize
$folder = basename($folder);

// convert inputs to arrays
$letters = array_filter(array_map('strtolower', explode(',', $lettersParam)));
$klanken = array_filter(array_map('strtolower', explode(',', $klankenParam)));

// ✅ whitelist folders
$allowed = ['woorden', 'letters', 'instructies', 'beloningen'];
if (!in_array($folder, $allowed)) {
    http_response_code(400);
    echo json_encode(["error" => "invalid folder"]);
    exit;
}

$targetDir = $baseDir . '/' . $folder;

if (!is_dir($targetDir)) {
    http_response_code(404);
    echo json_encode(["error" => "folder not found"]);
    exit;
}

// 📂 filter files
$files = array_values(array_filter(scandir($targetDir), function($file) use ($targetDir, $letters, $klanken) {

    if (!is_file($targetDir . '/' . $file)) return false;
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'mp3') return false;

    $name = strtolower(pathinfo($file, PATHINFO_FILENAME));

    // filter by letters (first character)
    if (!empty($letters)) {
        $firstLetter = substr($name, 0, 1);
        if (!in_array($firstLetter, $letters)) return false;
    }

    // filter by klanken (substring match)
    if (!empty($klanken)) {
        $match = false;
        foreach ($klanken as $klank) {
            if (str_contains($name, $klank)) {
                $match = true;
                break;
            }
        }
        if (!$match) return false;
    }

    return true;
}));

// 🔗 structured output
$files = array_map(function($file) use ($folder) {
    return [
        "word" => pathinfo($file, PATHINFO_FILENAME),
        "url" => "/audio/$folder/$file"
    ];
}, $files);

header('Content-Type: application/json');
echo json_encode($files);