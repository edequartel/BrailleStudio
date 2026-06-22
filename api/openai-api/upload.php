<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

/*
|--------------------------------------------------------------------------
| OPENAI API KEY
|--------------------------------------------------------------------------
| Put your API key here
*/
$OPENAI_API_KEY = 'myAPIKEY';


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function apiRequest(
    string $method,
    string $url,
    string $apiKey,
    array $headers = [],
    $body = null
): array {
    $ch = curl_init();

    $finalHeaders = array_merge([
        "Authorization: Bearer {$apiKey}",
    ], $headers);

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $finalHeaders,
        CURLOPT_TIMEOUT => 120,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: {$error}");
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $response;
        throw new RuntimeException("API error ({$httpCode}): {$message}");
    }

    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON response from API.");
    }

    return $decoded;
}

function uploadOpenAIFile(string $apiKey, array $uploadedFile): array
{
    $tmpPath = $uploadedFile['tmp_name'];
    $originalName = $uploadedFile['name'];

    if (!is_uploaded_file($tmpPath)) {
        throw new RuntimeException("Invalid uploaded file: {$originalName}");
    }

    $mimeType = mime_content_type($tmpPath) ?: 'application/octet-stream';

    $postFields = [
        'purpose' => 'assistants',
        'file' => new CURLFile($tmpPath, $mimeType, $originalName),
    ];

    return apiRequest(
        'POST',
        'https://api.openai.com/v1/files',
        $apiKey,
        [],
        $postFields
    );
}

function createVectorStore(string $apiKey, string $name): array
{
    return apiRequest(
        'POST',
        'https://api.openai.com/v1/vector_stores',
        $apiKey,
        ['Content-Type: application/json'],
        json_encode([
            'name' => $name,
        ], JSON_UNESCAPED_SLASHES)
    );
}

function attachFileToVectorStore(string $apiKey, string $vectorStoreId, string $fileId): array
{
    return apiRequest(
        'POST',
        "https://api.openai.com/v1/vector_stores/{$vectorStoreId}/files",
        $apiKey,
        ['Content-Type: application/json'],
        json_encode([
            'file_id' => $fileId,
        ], JSON_UNESCAPED_SLASHES)
    );
}


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/
$messages = [];
$errors = [];
$createdVectorStoreId = '';
$usedVectorStoreId = '';
$createdVectorStoreName = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (trim($OPENAI_API_KEY) === '' || $OPENAI_API_KEY === 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
            throw new RuntimeException('Please put your OpenAI API key at the top of this PHP file first.');
        }

        $existingVectorStoreId = trim($_POST['existing_vector_store_id'] ?? '');
        $newVectorStoreName = trim($_POST['new_vector_store_name'] ?? '');

        if ($existingVectorStoreId === '' && $newVectorStoreName === '') {
            throw new RuntimeException('Enter an existing vector store ID or a new vector store name.');
        }

        if (
            !isset($_FILES['files']) ||
            !isset($_FILES['files']['name']) ||
            !is_array($_FILES['files']['name']) ||
            count(array_filter($_FILES['files']['name'])) === 0
        ) {
            throw new RuntimeException('Please choose at least one file.');
        }

        if ($existingVectorStoreId !== '') {
            $usedVectorStoreId = $existingVectorStoreId;
            $messages[] = "Using existing vector store: {$usedVectorStoreId}";
        } else {
            $createdVectorStoreName = $newVectorStoreName;
            $vs = createVectorStore($OPENAI_API_KEY, $newVectorStoreName);
            $createdVectorStoreId = $vs['id'] ?? '';
            $usedVectorStoreId = $createdVectorStoreId;

            if ($usedVectorStoreId === '') {
                throw new RuntimeException('Vector store was created, but no ID was returned.');
            }

            $messages[] = "Created vector store '{$newVectorStoreName}'";
            $messages[] = "Vector store ID: {$usedVectorStoreId}";
        }

        $fileCount = count($_FILES['files']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for file: " . $_FILES['files']['name'][$i];
                continue;
            }

            $uploadedFile = [
                'name' => $_FILES['files']['name'][$i],
                'type' => $_FILES['files']['type'][$i],
                'tmp_name' => $_FILES['files']['tmp_name'][$i],
                'error' => $_FILES['files']['error'][$i],
                'size' => $_FILES['files']['size'][$i],
            ];

            try {
                $fileResponse = uploadOpenAIFile($OPENAI_API_KEY, $uploadedFile);
                $fileId = $fileResponse['id'] ?? '';

                if ($fileId === '') {
                    throw new RuntimeException('No file ID returned.');
                }

                $attachResponse = attachFileToVectorStore($OPENAI_API_KEY, $usedVectorStoreId, $fileId);

                $results[] = [
                    'name' => $uploadedFile['name'],
                    'file_id' => $fileId,
                    'status' => $attachResponse['status'] ?? 'attached',
                    'vector_store_file_id' => $attachResponse['id'] ?? '',
                ];
            } catch (Throwable $fileError) {
                $errors[] = $uploadedFile['name'] . ': ' . $fileError->getMessage();
            }
        }

        if (count($results) > 0) {
            $messages[] = count($results) . ' file(s) processed successfully.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Favicons for browsers, Apple devices, Android, and installed web apps -->
  <link rel="icon" href="/braillestudio/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/braillestudio/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/braillestudio/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/braillestudio/apple-touch-icon.png">
  <link rel="manifest" href="/braillestudio/site.webmanifest">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenAI Vector Store Uploader</title>
    <style>
        :root{
            --bg1:#0f172a;
            --bg2:#1e293b;
            --card:#ffffff;
            --text:#0f172a;
            --muted:#475569;
            --line:#e2e8f0;
            --primary:#2563eb;
            --primary-dark:#1d4ed8;
            --success-bg:#ecfdf5;
            --success-border:#10b981;
            --error-bg:#fef2f2;
            --error-border:#ef4444;
            --shadow:0 20px 50px rgba(15,23,42,.18);
            --radius:20px;
        }

        * { box-sizing: border-box; }

        body{
            margin:0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background:
                radial-gradient(circle at top left, #1d4ed8 0%, transparent 30%),
                radial-gradient(circle at top right, #0ea5e9 0%, transparent 25%),
                linear-gradient(135deg, var(--bg1), var(--bg2));
            min-height:100vh;
            color:#fff;
        }

        .wrap{
            width:min(980px, 92%);
            margin:40px auto;
        }

        .hero{
            padding:28px 30px;
            border-radius:28px;
            background:rgba(255,255,255,.08);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
            border:1px solid rgba(255,255,255,.12);
            margin-bottom:24px;
        }

        .hero h1{
            margin:0 0 8px;
            font-size:clamp(28px, 4vw, 42px);
            line-height:1.05;
        }

        .hero p{
            margin:0;
            color:rgba(255,255,255,.88);
            font-size:16px;
            line-height:1.6;
            max-width:760px;
        }

        .card{
            background:var(--card);
            color:var(--text);
            border-radius:var(--radius);
            box-shadow: var(--shadow);
            padding:28px;
        }

        .grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:18px;
        }

        @media (max-width: 760px){
            .grid{
                grid-template-columns:1fr;
            }
            .card{
                padding:18px;
            }
            .hero{
                padding:22px;
            }
        }

        .field{
            margin-bottom:18px;
        }

        label{
            display:block;
            margin-bottom:8px;
            font-weight:700;
            font-size:14px;
        }

        .hint{
            margin-top:6px;
            color:var(--muted);
            font-size:13px;
            line-height:1.45;
        }

        input[type="text"],
        input[type="file"]{
            width:100%;
            border:1px solid var(--line);
            border-radius:14px;
            padding:14px 16px;
            font-size:15px;
            outline:none;
            transition:.2s border-color, .2s box-shadow, .2s transform;
            background:#fff;
        }

        input[type="text"]:focus,
        input[type="file"]:focus{
            border-color:var(--primary);
            box-shadow:0 0 0 4px rgba(37,99,235,.12);
        }

        .divider{
            display:flex;
            align-items:center;
            gap:14px;
            margin:12px 0 18px;
            color:var(--muted);
            font-size:13px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.08em;
        }

        .divider::before,
        .divider::after{
            content:"";
            flex:1;
            height:1px;
            background:var(--line);
        }

        .actions{
            display:flex;
            gap:12px;
            align-items:center;
            flex-wrap:wrap;
            margin-top:8px;
        }

        button{
            border:none;
            background:linear-gradient(135deg, var(--primary), var(--primary-dark));
            color:#fff;
            padding:14px 20px;
            border-radius:14px;
            font-size:15px;
            font-weight:800;
            cursor:pointer;
            transition:.2s transform, .2s box-shadow, .2s opacity;
            box-shadow:0 10px 24px rgba(37,99,235,.28);
        }

        button:hover{
            transform:translateY(-1px);
        }

        button:active{
            transform:translateY(0);
        }

        .note{
            padding:14px 16px;
            border-radius:14px;
            background:#f8fafc;
            color:var(--muted);
            font-size:14px;
            line-height:1.55;
            border:1px solid var(--line);
        }

        .alert{
            border-radius:16px;
            padding:14px 16px;
            margin-bottom:14px;
            border:1px solid;
        }

        .alert.success{
            background:var(--success-bg);
            border-color:var(--success-border);
            color:#065f46;
        }

        .alert.error{
            background:var(--error-bg);
            border-color:var(--error-border);
            color:#991b1b;
        }

        .results{
            margin-top:24px;
        }

        .results h2{
            margin:0 0 14px;
            font-size:22px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            overflow:hidden;
            border-radius:16px;
            border:1px solid var(--line);
            background:#fff;
        }

        th, td{
            text-align:left;
            padding:14px 16px;
            border-bottom:1px solid var(--line);
            vertical-align:top;
            font-size:14px;
        }

        th{
            background:#f8fafc;
            font-size:13px;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:#334155;
        }

        tr:last-child td{
            border-bottom:none;
        }

        code{
            background:#eff6ff;
            color:#1d4ed8;
            padding:2px 7px;
            border-radius:8px;
            font-size:13px;
            word-break:break-all;
        }

        .footer{
            margin-top:18px;
            color:rgba(255,255,255,.8);
            font-size:13px;
            line-height:1.5;
            text-align:center;
        }

        .small-list{
            margin:8px 0 0 18px;
            padding:0;
            color:var(--muted);
        }

        .small-list li{
            margin-bottom:6px;
        }
    </style>
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:secure_url" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1729">
  <meta property="og:image:height" content="910">
  <meta property="og:image:alt" content="BrailleStudio">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta name="twitter:image:alt" content="BrailleStudio">
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>OpenAI Vector Store Uploader</h1>
        <p>
            Upload your JavaScript, Markdown, text, PHP, JSON, or other source files into an OpenAI vector store.
            Use an existing vector store ID, or create a new one directly from this page.
        </p>
    </div>

    <div class="card">
        <?php foreach ($messages as $message): ?>
            <div class="alert success"><?php echo h($message); ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert error"><?php echo nl2br(h($error)); ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="grid">
                <div class="field">
                    <label for="existing_vector_store_id">Existing vector store ID</label>
                    <input
                        type="text"
                        id="existing_vector_store_id"
                        name="existing_vector_store_id"
                        placeholder="Example: vs_abc123..."
                        value="<?php echo h($_POST['existing_vector_store_id'] ?? ''); ?>"
                    >
                    <div class="hint">
                        Fill this in if you already have a vector store and want to add files to it.
                    </div>
                </div>

                <div class="field">
                    <label for="new_vector_store_name">New vector store name</label>
                    <input
                        type="text"
                        id="new_vector_store_name"
                        name="new_vector_store_name"
                        placeholder="Example: braille-codebase"
                        value="<?php echo h($_POST['new_vector_store_name'] ?? ''); ?>"
                    >
                    <div class="hint">
                        Leave the existing ID empty if you want this page to create a new vector store.
                    </div>
                </div>
            </div>

            <div class="divider">files</div>

            <div class="field">
                <label for="files">Choose one or more files</label>
                <input
                    type="file"
                    id="files"
                    name="files[]"
                    multiple
                >
                <div class="hint">
                    Good choices: <code>.js</code>, <code>.ts</code>, <code>.md</code>, <code>.txt</code>, <code>.json</code>, <code>.php</code>, <code>.html</code>, <code>.css</code>.
                </div>
            </div>

            <div class="actions">
                <button type="submit">Upload to Vector Store</button>
            </div>

            <div class="note" style="margin-top:18px;">
                <strong>How it works:</strong>
                <ul class="small-list">
                    <li>If you enter an existing vector store ID, files are added there.</li>
                    <li>If you enter a new vector store name instead, a fresh vector store is created first.</li>
                    <li>You only need to place your API key once at the top of this PHP file.</li>
                </ul>
            </div>
        </form>

        <?php if (!empty($results)): ?>
            <div class="results">
                <h2>Upload results</h2>
                <table>
                    <thead>
                    <tr>
                        <th>File</th>
                        <th>OpenAI File ID</th>
                        <th>Status</th>
                        <th>Vector Store File ID</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo h($row['name']); ?></td>
                            <td><code><?php echo h($row['file_id']); ?></code></td>
                            <td><?php echo h($row['status']); ?></td>
                            <td><code><?php echo h($row['vector_store_file_id']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($usedVectorStoreId !== ''): ?>
                    <div class="note" style="margin-top:16px;">
                        Active vector store: <code><?php echo h($usedVectorStoreId); ?></code>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        Built for quick code and document uploads into OpenAI vector stores.
    </div>
</div>
</body>
</html>
