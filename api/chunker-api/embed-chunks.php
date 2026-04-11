<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '1024M');
ignore_user_abort(true);

header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$expectedToken = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
$openAiApiKey = 'OPENAI_API_KEY_HERE';

if ($token !== $expectedToken) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$inputFile = dirname(__DIR__) . '/data/chunks.json';
$outputFile = dirname(__DIR__) . '/data/chunks-embedded.json';
$embeddingModel = 'text-embedding-3-small';
$batchSize = 50;

if (!is_file($inputFile)) {
    http_response_code(500);
    echo "Missing input file: {$inputFile}\n";
    exit;
}

$raw = file_get_contents($inputFile);
$chunks = json_decode((string)$raw, true);

if (!is_array($chunks)) {
    http_response_code(500);
    echo "Invalid JSON in {$inputFile}\n";
    exit;
}

echo "Loaded " . count($chunks) . " chunks\n";
echo "Embedding model: {$embeddingModel}\n\n";

$total = count($chunks);
$processed = 0;

for ($offset = 0; $offset < $total; $offset += $batchSize) {
    $batch = array_slice($chunks, $offset, $batchSize);
    $inputs = [];

    foreach ($batch as $chunk) {
        $text = trim((string)($chunk['text'] ?? ''));
        $inputs[] = $text;
    }

    $embeddings = createEmbeddings($openAiApiKey, $embeddingModel, $inputs);

    if (!$embeddings['ok']) {
        http_response_code(500);
        echo "Embedding request failed\n";
        echo json_encode($embeddings['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit;
    }

    foreach ($batch as $i => $chunk) {
        $chunks[$offset + $i]['embedding'] = $embeddings['data'][$i];
    }

    $processed += count($batch);
    echo "Embedded {$processed} / {$total}\n";
    @ob_flush();
    @flush();
}

$result = file_put_contents(
    $outputFile,
    json_encode($chunks, JSON_UNESCAPED_UNICODE)
);

if ($result === false) {
    http_response_code(500);
    echo "Failed to write {$outputFile}\n";
    exit;
}

echo "\nDone\n";
echo "Output: {$outputFile}\n";

function createEmbeddings(string $apiKey, string $model, array $inputs): array
{
    $payload = [
        'model' => $model,
        'input' => $inputs,
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
        CURLOPT_TIMEOUT => 300,
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

    if (!isset($data['data']) || !is_array($data['data'])) {
        return [
            'ok' => false,
            'error' => 'Embeddings response missing data array',
        ];
    }

    $vectors = [];
    foreach ($data['data'] as $item) {
        $vectors[] = $item['embedding'] ?? null;
    }

    return [
        'ok' => true,
        'data' => $vectors,
    ];
}