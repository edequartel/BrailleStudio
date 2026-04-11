<?php
declare(strict_types=1);
$token = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Run Embeddings</title>
<style>
body {
    font-family: Arial, sans-serif;
    max-width: 960px;
    margin: 40px auto;
    padding: 20px;
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
</style>
</head>
<body>
    <h1>Run Embeddings</h1>

    <div class="box">
        <p>This reads <code>../data/chunks.json</code> and writes <code>../data/chunks-embedded.json</code>.</p>
        <button id="runBtn">Create embeddings</button>
    </div>

    <iframe id="outputFrame" title="Embedding output"></iframe>

    <script>
        document.getElementById('runBtn').addEventListener('click', function () {
            document.getElementById('outputFrame').src =
                'embed-chunks.php?token=<?php echo urlencode($token); ?>&t=' + Date.now();
        });
    </script>
</body>
</html>