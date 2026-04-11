<?php

declare(strict_types=1);

set_time_limit(120);
ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=utf-8');

/**
 * CONFIG
 */
$expectedToken = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
$openAiApiKey = 'OPENAI_API_KEY_HERE'; // Better later: load from env or separate config
$chunksFile = dirname(__DIR__) . '/data/chunks.json';
$model = 'gpt-5';

/**
 * AUTH
 */
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if ($token !== $expectedToken) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized',
    ]);
    exit;
}

/**
 * INPUT
 */
$question = trim((string)($_POST['question'] ?? ''));

if ($question === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing question',
    ]);
    exit;
}

if (!is_file($chunksFile)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Chunks file not found',
        'path' => $chunksFile,
    ]);
    exit;
}

$chunksJson = file_get_contents($chunksFile);
if ($chunksJson === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not read chunks file',
    ]);
    exit;
}

$chunks = json_decode($chunksJson, true);
if (!is_array($chunks)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid chunks JSON',
    ]);
    exit;
}

/**
 * RETRIEVAL
 * Simple keyword scoring for first version.
 */
$topChunks = retrieveRelevantChunks($question, $chunks, 6);

if (empty($topChunks)) {
    echo json_encode([
        'ok' => true,
        'answer' => 'I could not find relevant information in the indexed data.',
        'sources' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$context = buildContext($topChunks);

$instructions = <<<TXT
You answer questions only from the provided context.
If the answer is not clearly supported by the context, say that it is not in the indexed data.
Be concise and accurate.
TXT;

$input = <<<TXT
CONTEXT:
$context

QUESTION:
$question
TXT;

/**
 * OPENAI REQUEST
 * Uses the Responses API.
 */
$payload = [
    'model' => $model,
    'instructions' => $instructions,
    'input' => $input,
    'temperature' => 0.2,
    'max_output_tokens' => 500,
];

$response = callOpenAIResponsesApi($openAiApiKey, $payload);

if (!$response['ok']) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'OpenAI request failed',
        'details' => $response['error'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$answer = extractResponseText($response['data']);

echo json_encode([
    'ok' => true,
    'question' => $question,
    'answer' => $answer,
    'sources' => array_map(static function (array $chunk): array {
        return [
            'title' => $chunk['title'] ?? '',
            'file_name' => $chunk['file_name'] ?? '',
            'relative_path' => $chunk['relative_path'] ?? '',
            'chunk_index' => $chunk['chunk_index'] ?? 0,
        ];
    }, $topChunks),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


/**
 * Retrieve top-N relevant chunks using simple token overlap scoring.
 */
function retrieveRelevantChunks(string $question, array $chunks, int $limit = 6): array
{
    $questionTokens = tokenize($question);
    $scored = [];

    foreach ($chunks as $chunk) {
        if (!is_array($chunk)) {
            continue;
        }

        $text = (string)($chunk['text'] ?? '');
        $title = (string)($chunk['title'] ?? '');
        $fileName = (string)($chunk['file_name'] ?? '');

        if ($text === '') {
            continue;
        }

        $score = scoreChunk($questionTokens, $text, $title, $fileName);

        if ($score > 0) {
            $chunk['_score'] = $score;
            $scored[] = $chunk;
        }
    }

    usort($scored, static function (array $a, array $b): int {
        return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
    });

    return array_slice($scored, 0, $limit);
}

/**
 * Tokenize text into normalized keywords.
 */
function tokenize(string $text): array
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', trim((string)$text)) ?: [];

    $stopwords = [
        'the','a','an','and','or','but','if','then','than','of','in','on','at','to','for','from','with','by',
        'is','are','was','were','be','been','being','as','it','this','that','these','those',
        'wat','wie','waar','wanneer','waarom','hoe','een','de','het','en','of','maar','dan','van','in','op','aan','naar','voor','met','door','is','zijn','was','waren','dit','dat','deze','die'
    ];

    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || mb_strlen($part) < 2) {
            continue;
        }
        if (in_array($part, $stopwords, true)) {
            continue;
        }
        $tokens[] = $part;
    }

    return array_values(array_unique($tokens));
}

/**
 * Basic scoring:
 * - token matches in text
 * - extra weight for title/file name matches
 */
function scoreChunk(array $questionTokens, string $text, string $title, string $fileName): int
{
    $haystackText = mb_strtolower($text, 'UTF-8');
    $haystackTitle = mb_strtolower($title, 'UTF-8');
    $haystackFile = mb_strtolower($fileName, 'UTF-8');

    $score = 0;

    foreach ($questionTokens as $token) {
        if (mb_strpos($haystackText, $token) !== false) {
            $score += 3;
        }
        if ($title !== '' && mb_strpos($haystackTitle, $token) !== false) {
            $score += 6;
        }
        if ($fileName !== '' && mb_strpos($haystackFile, $token) !== false) {
            $score += 4;
        }
    }

    return $score;
}

/**
 * Build prompt context from top chunks.
 */
function buildContext(array $chunks): string
{
    $parts = [];

    foreach ($chunks as $chunk) {
        $title = (string)($chunk['title'] ?? '');
        $path = (string)($chunk['relative_path'] ?? '');
        $index = (int)($chunk['chunk_index'] ?? 0);
        $text = trim((string)($chunk['text'] ?? ''));

        $parts[] = <<<TXT
SOURCE: {$path}
TITLE: {$title}
CHUNK: {$index}
TEXT:
{$text}
TXT;
    }

    return implode("\n\n--------------------\n\n", $parts);
}

/**
 * Call OpenAI Responses API via cURL.
 */
function callOpenAIResponsesApi(string $apiKey, array $payload): array
{
    $ch = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 120,
    ]);

    $raw = curl_exec($ch);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => false,
            'error' => 'cURL error: ' . $error,
        ];
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'ok' => false,
            'error' => [
                'http_code' => $httpCode,
                'raw' => $raw,
                'decoded' => $data,
            ],
        ];
    }

    return [
        'ok' => true,
        'data' => $data,
    ];
}

/**
 * Extract text from Responses API payload.
 * The API returns generated output items in the response object.  [oai_citation:1‡OpenAI Platform](https://platform.openai.com/docs/api-reference/responses/list?ref=test-ippon.ghost.io&utm_source=chatgpt.com)
 */
function extractResponseText(array $data): string
{
    if (!empty($data['output']) && is_array($data['output'])) {
        $texts = [];

        foreach ($data['output'] as $outputItem) {
            if (!is_array($outputItem)) {
                continue;
            }

            $content = $outputItem['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                $type = $contentItem['type'] ?? '';
                if ($type === 'output_text' && isset($contentItem['text'])) {
                    $texts[] = (string)$contentItem['text'];
                }
            }
        }

        $joined = trim(implode("\n", $texts));
        if ($joined !== '') {
            return $joined;
        }
    }

    return 'No answer text returned.';
}