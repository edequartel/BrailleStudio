<?php

declare(strict_types=1);

set_time_limit(120);
ini_set('memory_limit', '1024M');

header('Content-Type: application/json; charset=utf-8');

$expectedToken = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
$openAiApiKey = 'OPENAI_API_KEY_HERE';
$chunksFile = dirname(__DIR__) . '/data/chunks-embedded.json';
$embeddingModel = 'text-embedding-3-small';
$model = 'gpt-5';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
if ($token !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$question = trim((string)($_POST['question'] ?? ''));
if ($question === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing question']);
    exit;
}

if (!is_file($chunksFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Embedded chunks file not found']);
    exit;
}

$raw = file_get_contents($chunksFile);
$chunks = json_decode((string)$raw, true);

if (!is_array($chunks)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Invalid embedded chunks JSON']);
    exit;
}

$questionEmbeddingResponse = createSingleEmbedding($openAiApiKey, $embeddingModel, $question);
if (!$questionEmbeddingResponse['ok']) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to embed question',
        'details' => $questionEmbeddingResponse['error']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$questionVector = $questionEmbeddingResponse['embedding'];
$topChunks = retrieveTopChunksByCosine($questionVector, $chunks, 6);

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

$payload = [
    'model' => $model,
    'instructions' => $instructions,
    'input' => $input,
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
            'score' => round((float)($chunk['_score'] ?? 0), 4),
        ];
    }, $topChunks),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

function createSingleEmbedding(string $apiKey, string $model, string $input): array
{
    $payload = [
        'model' => $model,
        'input' => $input,
    ];

    $ch = curl_init('https://api.openai.com/v1/embeddings');

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

        return ['ok' => false, 'error' => 'cURL error: ' . $error];
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($data)) {
        return [
            'ok' => false,
            'error' => [
                'http_code' => $httpCode,
                'raw' => $raw,
                'decoded' => $data,
            ],
        ];
    }

    $embedding = $data['data'][0]['embedding'] ?? null;
    if (!is_array($embedding)) {
        return ['ok' => false, 'error' => 'No embedding returned'];
    }

    return ['ok' => true, 'embedding' => $embedding];
}

function retrieveTopChunksByCosine(array $questionVector, array $chunks, int $limit = 6): array
{
    $scored = [];

    foreach ($chunks as $chunk) {
        if (!is_array($chunk)) {
            continue;
        }

        $vector = $chunk['embedding'] ?? null;
        $text = (string)($chunk['text'] ?? '');

        if (!is_array($vector) || $text === '') {
            continue;
        }

        $score = cosineSimilarity($questionVector, $vector);
        $chunk['_score'] = $score;
        $scored[] = $chunk;
    }

    usort($scored, static function (array $a, array $b): int {
        return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
    });

    return array_slice($scored, 0, $limit);
}

function cosineSimilarity(array $a, array $b): float
{
    $countA = count($a);
    $countB = count($b);

    if ($countA === 0 || $countA !== $countB) {
        return 0.0;
    }

    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    for ($i = 0; $i < $countA; $i++) {
        $x = (float)$a[$i];
        $y = (float)$b[$i];

        $dot += $x * $y;
        $normA += $x * $x;
        $normB += $y * $y;
    }

    if ($normA <= 0.0 || $normB <= 0.0) {
        return 0.0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

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

        return ['ok' => false, 'error' => 'cURL error: ' . $error];
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

    return ['ok' => true, 'data' => $data];
}

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

                if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
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