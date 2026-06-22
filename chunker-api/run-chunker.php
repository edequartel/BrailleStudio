<?php
declare(strict_types=1);

$token = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
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