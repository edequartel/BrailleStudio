<?php
declare(strict_types=1);

$token = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Chunker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 960px;
            margin: 40px auto;
            padding: 20px;
            line-height: 1.5;
        }
        h1 {
            margin-bottom: 10px;
        }
        .box {
            border: 1px solid #ccc;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        button {
            padding: 12px 18px;
            font-size: 16px;
            cursor: pointer;
        }
        iframe {
            width: 100%;
            height: 650px;
            border: 1px solid #ccc;
            background: #fafafa;
        }
        code {
            background: #f3f3f3;
            padding: 2px 5px;
            border-radius: 4px;
        }
        .note {
            color: #555;
        }
    </style>
</head>
<body>
    <h1>Run Chunker</h1>

    <div class="box">
        <p>This runs <code>chunker.php</code> and writes the chunks file outside <code>public_html</code>.</p>
        <p class="note">Use the same token here and in <code>chunker.php</code>.</p>
        <button id="runBtn">Run chunker</button>
    </div>

    <div class="box">
        <p><strong>Output location:</strong> <code>../data/chunks.json</code></p>
        <p class="note">This file is intentionally not linked publicly.</p>
    </div>

    <iframe id="outputFrame" title="Chunker output"></iframe>

    <script>
        document.getElementById('runBtn').addEventListener('click', function () {
            const frame = document.getElementById('outputFrame');
            frame.src = 'chunker.php?token=<?php echo urlencode($token); ?>&t=' + Date.now();
        });
    </script>
</body>
</html>