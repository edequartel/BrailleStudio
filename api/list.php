<?php

/*
==========================================================
API: list.php
Base URL
--------
https://tastenbraille.com/api/list.php

DOEL
----
Geeft een JSON-lijst terug van audiobestanden voor Blockly / BrailleStudio.

PUBLIEKE AUDIO MAPPEN
---------------------
speech       -> https://www.tastenbraille.com/braillestudio/sounds/nl/speech
letters      -> https://www.tastenbraille.com/braillestudio/sounds/nl/letters
instructions -> https://www.tastenbraille.com/braillestudio/sounds/nl/instructions
feedback     -> https://www.tastenbraille.com/braillestudio/sounds/nl/feedback
story        -> https://www.tastenbraille.com/braillestudio/sounds/nl/stories
general      -> https://www.tastenbraille.com/braillestudio/sounds/general

HOE TE GEBRUIKEN
----------------
Gebruik altijd minstens:
- folder=speech | letters | instructions | feedback | story | general

Je kunt daarna filters toevoegen. Alle filters worden gecombineerd met AND-logica.

BESCHIKBARE PARAMETERS
----------------------
folder
  verplicht
  mogelijke waarden: speech, letters, instructions, feedback, story, general

letters
  optioneel
  komma-gescheiden lijst van beginletters
  het woord moet beginnen met 1 van deze letters
  voorbeeld: letters=a,b,k,l

klanken
  optioneel
  komma-gescheiden lijst van klanken
  het woord moet minstens 1 van deze klanken bevatten
  voorbeelden: klanken=a,e,i,o,u,ei,ou,au,aa,oe

onlyletters
  optioneel
  komma-gescheiden lijst van toegestane letters
  het woord mag alleen deze letters bevatten
  voorbeeld: onlyletters=a,l,p,m

onlyklanken
  optioneel
  komma-gescheiden lijst van toegestane klanken
  het woord wordt eerst opgesplitst in klanken
  daarna moeten alle gevonden klanken in deze lijst staan
  voorbeeld: onlyklanken=a,ei,ou,l,m,p

onlycombo
  optioneel: true | false
  als true, dan worden onlyletters en onlyklanken als strikte combinatie gebruikt
  het woord moet dan tegelijk voldoen aan:
  - alleen toegestane letters
  - alleen toegestane klanken

maxlength
  optioneel
  maximale woordlengte in letters/tekens
  voorbeeld: maxlength=4

length
  optioneel
  exacte woordlengte in letters/tekens
  voorbeeld: length=3

limit
  optioneel
  maximaal aantal resultaten na filtering en sortering
  voorbeeld: limit=20

randomlimit
  optioneel
  maximaal aantal willekeurige resultaten
  voorbeeld: randomlimit=10

sort
  optioneel
  mogelijke waarden: asc, desc, random
  standaard: asc

NEDERLANDSE KLINKERS EN KLANKEN
-------------------------------
Deze API ondersteunt onder andere deze Nederlandse klanken:

Korte klinkers:
a, e, i, o, u

Lange klinkers:
aa, ee, oo, uu

Overige veelgebruikte klinkerklanken / tweeklanken:
ie, oe, eu, ui, ei, ij, ou, au, ai, oi

Veelgebruikte medeklinkercombinaties:
ng, nk, ch, sch, sj

Langere patronen:
aai, ooi, oei

Losse medeklinkers:
b, c, d, f, g, h, j, k, l, m, n, p, q, r, s, t, v, w, x, y, z

CURL VOORBEELDEN
----------------
# alle speech bestanden
curl "https://tastenbraille.com/api/list.php?folder=speech"

# alle letters
curl "https://tastenbraille.com/api/list.php?folder=letters"

# alle instructions
curl "https://tastenbraille.com/api/list.php?folder=instructions"

# alle feedback
curl "https://tastenbraille.com/api/list.php?folder=feedback"

# alle stories
curl "https://tastenbraille.com/api/list.php?folder=story"

# alles uit general
curl "https://tastenbraille.com/api/list.php?folder=general"

# speech bestanden die beginnen met a, b, k of l
curl "https://tastenbraille.com/api/list.php?folder=speech&letters=a,b,k,l"

# speech bestanden met klanken a, e, i, ou of ei
curl "https://tastenbraille.com/api/list.php?folder=speech&klanken=a,e,i,ou,ei"

# speech bestanden met alleen de letters a, l, p, m
curl "https://tastenbraille.com/api/list.php?folder=speech&onlyletters=a,l,p,m"

# speech bestanden met alleen toegestane klanken
curl "https://tastenbraille.com/api/list.php?folder=speech&onlyklanken=a,aa,ei,ou,l,m,p"

# strikte combinatie van toegestane letters én toegestane klanken
curl "https://tastenbraille.com/api/list.php?folder=speech&onlycombo=true&onlyletters=a,l,p,m&onlyklanken=a,l,p,m"

# speech bestanden met maximale lengte 4
curl "https://tastenbraille.com/api/list.php?folder=speech&maxlength=4"

# speech bestanden met exacte lengte 3
curl "https://tastenbraille.com/api/list.php?folder=speech&length=3"

# gesorteerd oplopend
curl "https://tastenbraille.com/api/list.php?folder=speech&sort=asc"

# gesorteerd aflopend
curl "https://tastenbraille.com/api/list.php?folder=speech&sort=desc"

# willekeurige volgorde
curl "https://tastenbraille.com/api/list.php?folder=speech&sort=random"

# limiet op aantal resultaten
curl "https://tastenbraille.com/api/list.php?folder=speech&letters=a,b,k,l&limit=10"

# willekeurig maximaal 10 bestanden
curl "https://tastenbraille.com/api/list.php?folder=speech&randomlimit=10"

# combinatie van filters
curl "https://tastenbraille.com/api/list.php?folder=speech&letters=b,k&klanken=aa,oe,ei&maxlength=5&sort=asc&limit=20"

UITVOER
-------
[
  {
    "word": "lamp",
    "url": "https://www.tastenbraille.com/braillestudio/sounds/nl/speech/lamp.mp3"
  }
]

OPMERKINGEN
-----------
- De response is altijd JSON
- Bestandsnaam zonder extensie wordt gebruikt als "word"
- length en maxlength tellen letters/tekens van de bestandsnaam zonder extensie
- Alle filters worden gecombineerd met AND-logica
==========================================================
*/

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Vary: Origin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -----------------------------
// Input
// -----------------------------
$folder = $_GET['folder'] ?? '';
$lettersParam = $_GET['letters'] ?? '';
$klankenParam = $_GET['klanken'] ?? '';
$onlyLettersParam = $_GET['onlyletters'] ?? '';
$onlyKlankenParam = $_GET['onlyklanken'] ?? '';
$onlyCombo = ($_GET['onlycombo'] ?? 'false') === 'true';

$maxLength = isset($_GET['maxlength']) ? (int) $_GET['maxlength'] : 0;
$exactLength = isset($_GET['length']) ? (int) $_GET['length'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
$randomLimit = isset($_GET['randomlimit']) ? (int) $_GET['randomlimit'] : 0;
$sort = mb_strtolower(trim($_GET['sort'] ?? 'asc'), 'UTF-8');

$folder = basename(trim($folder));

// -----------------------------
// Veilig maken van numerieke waarden
// -----------------------------
$maxLength = max(0, $maxLength);
$exactLength = max(0, $exactLength);
$limit = max(0, min($limit, 1000));
$randomLimit = max(0, min($randomLimit, 1000));

if (!in_array($sort, ['asc', 'desc', 'random'], true)) {
    $sort = 'asc';
}

// -----------------------------
// Folder mapping
// -----------------------------
$folderMap = [
    'speech' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/speech',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/speech',
    ],
    'letters' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/letters',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/letters',
    ],
    'instructions' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/instructions',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions',
    ],
    'feedback' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/feedback',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/feedback',
    ],
    'story' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/nl/stories',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/nl/stories',
    ],
    'general' => [
        'dir' => __DIR__ . '/../braillestudio/sounds/general',
        'url' => 'https://www.tastenbraille.com/braillestudio/sounds/general',
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
function parseCsvList(string $value): array
{
    return array_values(array_unique(array_filter(array_map(
        fn($v) => mb_strtolower(trim($v), 'UTF-8'),
        explode(',', $value)
    ))));
}

$letters = parseCsvList($lettersParam);
$klanken = parseCsvList($klankenParam);
$onlyLetters = parseCsvList($onlyLettersParam);
$onlyKlanken = parseCsvList($onlyKlankenParam);

// -----------------------------
// Helper: woord opsplitsen in Nederlandse klanken
// Langste patronen eerst
// -----------------------------
function splitKlanken(string $word): array
{
    static $patterns = [
        // langere patronen
        'sch', 'aai', 'ooi', 'oei',

        // veelvoorkomende combinaties
        'ng', 'nk', 'ch', 'sj',

        // lange klinkers en tweetekens
        'aa', 'ee', 'oo', 'uu',
        'oe', 'eu', 'ui', 'ie',
        'ei', 'ij', 'ou', 'au',
        'oi', 'ai',

        // losse klinkers
        'a', 'e', 'i', 'o', 'u',

        // losse medeklinkers
        'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm',
        'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z',
    ];

    $result = [];

    while ($word !== '') {
        $matched = false;

        foreach ($patterns as $p) {
            $len = mb_strlen($p, 'UTF-8');
            if (mb_substr($word, 0, $len, 'UTF-8') === $p) {
                $result[] = $p;
                $word = mb_substr($word, $len, null, 'UTF-8');
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $result[] = mb_substr($word, 0, 1, 'UTF-8');
            $word = mb_substr($word, 1, null, 'UTF-8');
        }
    }

    return $result;
}

function containsRequestedKlank(array $wordKlanken, array $requestedKlanken): bool
{
    if (empty($requestedKlanken)) {
        return true;
    }

    foreach ($wordKlanken as $klank) {
        if (in_array($klank, $requestedKlanken, true)) {
            return true;
        }
    }

    return false;
}

// -----------------------------
// Bestanden lezen
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

$files = array_values(array_filter(
    $entries,
    function ($file) use (
        $targetDir,
        $letters,
        $klanken,
        $onlyLetters,
        $onlyKlanken,
        $onlyCombo,
        $maxLength,
        $exactLength
    ) {
        $fullPath = $targetDir . '/' . $file;

        if (!is_file($fullPath)) {
            return false;
        }

        if (mb_strtolower(pathinfo($file, PATHINFO_EXTENSION), 'UTF-8') !== 'mp3') {
            return false;
        }

        $name = mb_strtolower(pathinfo($file, PATHINFO_FILENAME), 'UTF-8');
        $charLength = mb_strlen($name, 'UTF-8');
        $wordKlanken = null;

        // exacte lengte
        if ($exactLength > 0 && $charLength !== $exactLength) {
            return false;
        }

        // maximale lengte
        if ($maxLength > 0 && $charLength > $maxLength) {
            return false;
        }

        // beginletter
        if (!empty($letters)) {
            $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
            if (!in_array($firstLetter, $letters, true)) {
                return false;
            }
        }

        // moet minstens 1 van deze klanken bevatten
        if (!empty($klanken)) {
            $wordKlanken = $wordKlanken ?? splitKlanken($name);
            if (!containsRequestedKlank($wordKlanken, $klanken)) {
                return false;
            }
        }

        // alleen toegestane letters
        if (!empty($onlyLetters)) {
            $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $char) {
                if (!in_array($char, $onlyLetters, true)) {
                    return false;
                }
            }
        }

        // alleen toegestane klanken
        if (!empty($onlyKlanken)) {
            $wordKlanken = $wordKlanken ?? splitKlanken($name);
            foreach ($wordKlanken as $klank) {
                if (!in_array($klank, $onlyKlanken, true)) {
                    return false;
                }
            }
        }

        // strikte combinatie
        if ($onlyCombo) {
            if (!empty($onlyLetters)) {
                $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($chars as $char) {
                    if (!in_array($char, $onlyLetters, true)) {
                        return false;
                    }
                }
            }

            if (!empty($onlyKlanken)) {
                $wordKlanken = $wordKlanken ?? splitKlanken($name);
                foreach ($wordKlanken as $klank) {
                    if (!in_array($klank, $onlyKlanken, true)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
));

// -----------------------------
// Sorteren
// -----------------------------
if ($sort === 'random') {
    shuffle($files);
} else {
    natcasesort($files);
    $files = array_values($files);

    if ($sort === 'desc') {
        $files = array_reverse($files);
    }
}

// -----------------------------
// Willekeurige limiet
// -----------------------------
if ($randomLimit > 0) {
    if ($sort !== 'random') {
        shuffle($files);
    }
    $files = array_slice($files, 0, $randomLimit);
}

// -----------------------------
// Gewone limiet
// -----------------------------
if ($limit > 0) {
    $files = array_slice($files, 0, $limit);
}

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
