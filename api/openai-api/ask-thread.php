<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

$configCandidates = [
    rtrim($_SERVER['HOME'] ?? '', '/') . '/private/openai_config.php',
    '/home3/kydjgrmy/private/openai_config.php',
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/private/openai_config.php',
];

$configPath = null;

foreach ($configCandidates as $candidate) {

    if (
        is_string($candidate)
        && $candidate !== ''
        && is_file($candidate)
        && is_readable($candidate)
    ) {
        $configPath = $candidate;
        break;
    }

}

if ($configPath === null) {
    die('<h1>Config bestand niet gevonden</h1>');
}

$config = require $configPath;

$apiKey = trim((string)($config['OPENAI_API_KEY'] ?? ''));

if ($apiKey === '') {
    die('<h2>OPENAI_API_KEY ontbreekt in config bestand.</h2>');
}

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

$apiUrl = 'https://api.openai.com/v1/responses';

$vectorStoreId = 'vs_69df252bb3d48191b27fec94575bc341';

$model = $_POST['model'] ?? 'gpt-4o-mini';

$maxResults = 8;

$contractPath = dirname(__DIR__, 2) . '/blockly/contract.txt';
$contractText = '';
if (is_file($contractPath) && is_readable($contractPath)) {
    $contractText = trim((string)file_get_contents($contractPath));
}

$instructions =
    "Gebruik contract.txt en de andere geüploade bestanden in de vector store als bron.\n\n"
    . "Maak exact één geldig JSON-object met precies deze drie hoofdvelden:\n"
    . "1. leerling_opdracht\n"
    . "2. uitleg\n"
    . "3. blockly_json\n\n"
    . "Harde regels:\n"
    . "- leerling_opdracht bevat de tekst voor de leerling.\n"
    . "- uitleg bevat uitleg voor de docent of maker.\n"
    . "- blockly_json moet importeerbare Blockly workspace JSON zijn.\n"
    . "- blockly_json mag NIET de save-wrapper zijn met id/title/meta/overwrite.\n"
    . "- blockly_json moet dus beginnen met deze shape:\n"
    . "  {\n"
    . "    \"blocks\": {\n"
    . "      \"languageVersion\": 0,\n"
    . "      \"blocks\": [ ... ]\n"
    . "    },\n"
    . "    \"variables\": [ ... ]\n"
    . "  }\n"
    . "- Geef nooit één los block terug zoals {\"type\": ... }.\n"
    . "- Gebruik alleen blocktypes, velden, inputs en structuren die echt bestaan in contract.txt of andere geüploade projectbestanden.\n"
    . "- Verzin geen blocktypes zoals event_when_right_thumb_pressed als die niet bestaan.\n"
    . "- Voor rechterduim moet je bijvoorbeeld event_when_thumb_key gebruiken met field KEY = right als dat volgens de bestanden correct is.\n"
    . "- Gebruik bij statement-ketens altijd Blockly workspace JSON structuur, dus next.block en inputs.DO.block waar nodig.\n"
    . "- Geen markdown.\n"
    . "- Geen codeblokken.\n"
    . "- Geen uitleg buiten de JSON.\n"
    . "- Output moet pure parsebare JSON zijn.";

$systemText =
    "Je bent een gespecialiseerde Blockly JSON-generator voor BrailleStudio.\n\n"
    . "Je primaire taak is: maak blockly_json dat direct via Blockly import gebruikt kan worden.\n"
    . "Dat betekent:\n"
    . "- geen fictieve of vereenvoudigde blokdefinities\n"
    . "- geen losse fragments zoals {\"type\": \"...\"}\n"
    . "- geen Blockly block definition JSON met message0/previousStatement/nextStatement\n"
    . "- wel echte Blockly workspace state JSON\n\n"
    . "Output altijd exact deze structuur:\n\n"
    . "{\n"
    . "  \"leerling_opdracht\": \"...\",\n"
    . "  \"uitleg\": \"...\",\n"
    . "  \"blockly_json\": {\n"
    . "    \"blocks\": {\n"
    . "      \"languageVersion\": 0,\n"
    . "      \"blocks\": []\n"
    . "    },\n"
    . "    \"variables\": []\n"
    . "  }\n"
    . "}\n\n"
    . "Validatie voordat je antwoord geeft:\n"
    . "- Bestaat elk gebruikt blocktype echt in de projectbestanden?\n"
    . "- Is blockly_json een workspace state object?\n"
    . "- Heeft elke statement-link de vorm next.block?\n"
    . "- Gebruiken statement inputs de vorm inputs.DO.block als relevant?\n"
    . "- Is het antwoord pure JSON zonder extra tekst?\n\n"
    . "Contract bron:\n"
    . ($contractText !== '' ? $contractText : 'contract.txt not available in prompt context.');

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['last_student_task'])) {
    $_SESSION['last_student_task'] = '';
}

if (!isset($_SESSION['last_explanation'])) {
    $_SESSION['last_explanation'] = '';
}

if (!isset($_SESSION['last_blockly_json'])) {
    $_SESSION['last_blockly_json'] = '';
}

if (isset($_POST['reset_chat'])) {

    $_SESSION['last_student_task'] = '';
    $_SESSION['last_explanation'] = '';
    $_SESSION['last_blockly_json'] = '';

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$errorMessage = '';

$userText = trim((string)(
    $_POST['user_text'] ?? ''
));

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function h(string $text): string
{
    return htmlspecialchars(
        $text,
        ENT_QUOTES,
        'UTF-8'
    );
}

function extractOutputText(array $response): string
{
    $texts = [];

    foreach ($response['output'] ?? [] as $item) {

        foreach ($item['content'] ?? [] as $content) {

            if (
                ($content['type'] ?? '') === 'output_text'
                && isset($content['text'])
            ) {

                $texts[] = $content['text'];

            }

        }

    }

    return trim(
        implode("\n\n", $texts)
    );
}

function unwrapJsonText(string $text): string
{
    $text = trim($text);

    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/^```\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $text = trim($text);

    $firstObject = strpos($text, '{');
    $lastObject = strrpos($text, '}');

    if (
        $firstObject !== false
        && $lastObject !== false
        && $lastObject > $firstObject
    ) {

        return trim(
            substr(
                $text,
                $firstObject,
                $lastObject - $firstObject + 1
            )
        );

    }

    return $text;
}

function prettyJsonIfValid(mixed $value): string
{
    return json_encode(
        $value,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ) ?: '';
}

/*
|--------------------------------------------------------------------------
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $userText !== ''
) {

    try {

        $payload = [

            'model' => $model,

            'instructions' => $instructions,

            'tools' => [

                [
                    'type' => 'file_search',

                    'vector_store_ids' => [
                        $vectorStoreId,
                    ],

                    'max_num_results' => $maxResults,
                ],

            ],

            'input' => [

                [
                    'role' => 'system',
                    'content' => $systemText,
                ],

                [
                    'role' => 'user',
                    'content' => $userText,
                ],

            ],

        ];

        $ch = curl_init($apiUrl);

        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_HTTPHEADER => [

                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',

            ],

            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            CURLOPT_TIMEOUT => 120,

        ]);

        $responseBody = curl_exec($ch);

        if ($responseBody === false) {

            throw new RuntimeException(
                'cURL fout: ' . curl_error($ch)
            );

        }

        $httpCode = (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        $decodedResponse = json_decode(
            $responseBody,
            true
        );

        if (!is_array($decodedResponse)) {

            throw new RuntimeException(
                'Geen geldige JSON ontvangen.'
            );

        }

        if ($httpCode >= 400) {

            $message =
                $decodedResponse['error']['message']
                ?? ('HTTP fout ' . $httpCode);

            throw new RuntimeException($message);

        }

        $assistantText =
            extractOutputText($decodedResponse);

        if ($assistantText === '') {

            throw new RuntimeException(
                'Geen antwoord ontvangen.'
            );

        }

        $assistantText =
            unwrapJsonText($assistantText);

        $generated =
            json_decode($assistantText, true);

        if (!is_array($generated)) {

            throw new RuntimeException(
                'Het model gaf geen parsebare JSON terug.'
            );

        }

        $_SESSION['last_student_task'] =
            (string)(
                $generated['leerling_opdracht']
                ?? ''
            );

        $_SESSION['last_explanation'] =
            (string)(
                $generated['uitleg']
                ?? ''
            );

        $_SESSION['last_blockly_json'] =
            prettyJsonIfValid(
                $generated['blockly_json']
                ?? new stdClass()
            );

        header('Location: ' . $_SERVER['PHP_SELF']);

        exit;

    } catch (Throwable $e) {

        $errorMessage = $e->getMessage();

        $_SESSION['last_student_task'] = '';

        $_SESSION['last_explanation'] =
            'Fout: ' . $errorMessage;

        $_SESSION['last_blockly_json'] =
            prettyJsonIfValid([
                'error' => $errorMessage,
            ]);
    }

}

$lastStudentTask =
    $_SESSION['last_student_task'] ?? '';

$lastExplanation =
    $_SESSION['last_explanation'] ?? '';

$lastBlocklyJson =
    $_SESSION['last_blockly_json'] ?? '';

?>
<!DOCTYPE html>
<html lang="nl">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>BrailleStudio Blockly JSON Generator</title>

<style>

body{
    margin:0;
    padding:30px;
    background:#f3f6fb;
    font-family:Arial,sans-serif;
    color:#1f2937;
}

.wrapper{
    max-width:1000px;
    margin:0 auto;
}

.card{
    background:#fff;
    border-radius:20px;
    padding:24px;
    margin-bottom:24px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

h1,h2{
    margin-top:0;
}

.small{
    font-size:13px;
    color:#6b7280;
}

.field{
    margin-bottom:20px;
}

label{
    display:block;
    margin-bottom:8px;
    font-weight:bold;
}

input,
textarea{
    width:100%;
    box-sizing:border-box;
    padding:14px;
    border-radius:14px;
    border:1px solid #d1d5db;
    font-size:15px;
}

textarea{
    min-height:140px;
    resize:vertical;
}

.button-row{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

button{
    background:#2563eb;
    color:white;
    border:0;
    padding:14px 18px;
    border-radius:12px;
    font-size:15px;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#1d4ed8;
}

.reset{
    background:#6b7280;
}

.reset:hover{
    background:#4b5563;
}

.copy-btn{
    background:#10b981;
}

.copy-btn:hover{
    background:#059669;
}

.output-box{
    background:#f9fafb;
    color:#111827;
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:18px;
    overflow:auto;
    white-space:pre-wrap;
    font-size:15px;
    line-height:1.6;
    min-height:90px;
}

.json-box{
    background:#0f172a;
    color:#e5e7eb;
    font-family:Consolas, Monaco, monospace;
    white-space:pre;
    min-height:260px;
}

.output-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:12px;
}

.error{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#991b1b;
    padding:16px;
    border-radius:12px;
    margin-bottom:20px;
}

/*
|--------------------------------------------------------------------------
| LOADING OVERLAY
|--------------------------------------------------------------------------
*/

.loading-overlay{
    position:fixed;
    inset:0;
    background:rgba(255,255,255,.82);
    backdrop-filter:blur(4px);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
}

.loading-box{
    background:white;
    padding:28px 34px;
    border-radius:20px;
    box-shadow:0 10px 40px rgba(0,0,0,.15);
    text-align:center;
    min-width:260px;
}

.spinner{
    width:56px;
    height:56px;
    border:6px solid #dbeafe;
    border-top:6px solid #2563eb;
    border-radius:50%;
    animation:spin 1s linear infinite;
    margin:0 auto 18px auto;
}

.loading-title{
    font-size:18px;
    font-weight:bold;
    margin-bottom:8px;
}

.loading-sub{
    color:#6b7280;
    font-size:14px;
}

@keyframes spin{
    from{
        transform:rotate(0deg);
    }
    to{
        transform:rotate(360deg);
    }
}

</style>

</head>

<body>

<div
    id="loadingOverlay"
    class="loading-overlay"
>

    <div class="loading-box">

        <div class="spinner"></div>

        <div class="loading-title">
            Blockly JSON genereren...
        </div>

        <div class="loading-sub">
            Contract.txt en vector store worden doorzocht
        </div>

    </div>

</div>

<div class="wrapper">

    <div class="card">

        <h1>BrailleStudio Blockly JSON Generator</h1>

        <p class="small">
            Genereert leerlingtekst, uitleg en Blockly JSON-script.
        </p>

    </div>

    <div class="card">

        <form
            method="post"
            id="generatorForm"
        >

            <div class="field">

                <label>Model</label>

                <input
                    type="text"
                    name="model"
                    value="<?= h((string)$model) ?>"
                >

            </div>

            <div class="field">

                <label>Opdracht voor generator</label>

                <textarea
                    name="user_text"
                    placeholder="Bijvoorbeeld: Maak een script waarin de leerling het woord maan leest en daarna op cursor-routingtoets 3 drukt."
                    autofocus
                ></textarea>

            </div>

            <div class="button-row">

                <button type="submit">
                    Genereer
                </button>

                <button
                    type="submit"
                    name="reset_chat"
                    value="1"
                    class="reset"
                >
                    Nieuwe thread
                </button>

            </div>

        </form>

    </div>

    <?php if ($errorMessage): ?>

        <div class="error">
            <?= h($errorMessage) ?>
        </div>

    <?php endif; ?>

    <div class="card">

        <div class="output-header">

            <h2>Opdracht voor leerling</h2>

            <button
                type="button"
                class="copy-btn"
                onclick="copyBox('studentTask', this)"
            >
                Kopieer
            </button>

        </div>

        <div
            id="studentTask"
            class="output-box"
        ><?= h(
            $lastStudentTask !== ''
                ? $lastStudentTask
                : 'Nog geen opdracht gegenereerd.'
        ) ?></div>

    </div>

    <div class="card">

        <div class="output-header">

            <h2>Uitleg</h2>

            <button
                type="button"
                class="copy-btn"
                onclick="copyBox('explanationBox', this)"
            >
                Kopieer
            </button>

        </div>

        <div
            id="explanationBox"
            class="output-box"
        ><?= h(
            $lastExplanation !== ''
                ? $lastExplanation
                : 'Nog geen uitleg gegenereerd.'
        ) ?></div>

    </div>

    <div class="card">

        <div class="output-header">

            <h2>Blockly JSON script</h2>

            <button
                type="button"
                class="copy-btn"
                onclick="copyBox('blocklyJsonBox', this)"
            >
                Kopieer JSON
            </button>

        </div>

        <pre
            id="blocklyJsonBox"
            class="output-box json-box"
        ><?= h(
            $lastBlocklyJson !== ''
                ? $lastBlocklyJson
                : '{ "status": "nog_geen_script" }'
        ) ?></pre>

    </div>

</div>

<script>

function copyBox(id, button) {

    const text =
        document
        .getElementById(id)
        .innerText;

    navigator.clipboard.writeText(text);

    const original =
        button.innerText;

    button.innerText =
        'Gekopieerd';

    setTimeout(() => {

        button.innerText =
            original;

    }, 1500);

}

document
    .getElementById('generatorForm')
    .addEventListener('submit', function(){

        document
            .getElementById('loadingOverlay')
            .style.display = 'flex';

});

</script>

</body>
</html>
