<?php
$text = $_POST['qr_text'] ?? '';
$qrUrl = '';

if (!empty($text)) {
    $encodedText = urlencode($text);
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedText}";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QR Code Generator</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #4f46e5, #06b6d4);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .container {
        background: white;
        max-width: 520px;
        width: 100%;
        padding: 30px;
        border-radius: 24px;
        box-shadow: 0 20px 45px rgba(0,0,0,0.25);
        text-align: center;
    }

    h1 {
        margin-top: 0;
        color: #1e293b;
        font-size: 28px;
    }

    p {
        color: #475569;
    }

    textarea {
        width: 100%;
        min-height: 120px;
        padding: 15px;
        border-radius: 14px;
        border: 2px solid #cbd5e1;
        font-size: 16px;
        resize: vertical;
        box-sizing: border-box;
    }

    textarea:focus {
        outline: none;
        border-color: #4f46e5;
    }

    button, .download-btn, .copy-btn {
        margin-top: 15px;
        display: inline-block;
        background: #4f46e5;
        color: white;
        border: none;
        padding: 14px 22px;
        border-radius: 14px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        font-weight: bold;
    }

    button:hover, .download-btn:hover, .copy-btn:hover {
        background: #3730a3;
    }

    .qr-box {
        margin-top: 30px;
        padding: 20px;
        border-radius: 20px;
        background: #f8fafc;
        border: 2px dashed #cbd5e1;
    }

    .qr-box img {
        max-width: 100%;
        border-radius: 12px;
        background: white;
        padding: 10px;
    }

    .small {
        font-size: 13px;
        color: #64748b;
        margin-top: 15px;
    }
</style>
</head>

<body>

<div class="container">
    <h1>QR Code Generator</h1>
    <p>Type or paste text below and create a QR code.</p>

    <form method="post">
        <textarea name="qr_text" id="qrText" placeholder="Paste your text, link, code or message here..."><?php echo htmlspecialchars($text); ?></textarea>
        <br>
        <button type="submit">Create QR Code</button>
        <button type="button" class="copy-btn" onclick="copyText()">Copy Text</button>
    </form>

    <?php if (!empty($qrUrl)): ?>
        <div class="qr-box">
            <h2>Your QR Code</h2>

            <img src="<?php echo $qrUrl; ?>" alt="Generated QR Code">

            <br>

            <a class="download-btn" href="<?php echo $qrUrl; ?>" download="qr-code.png">
                Save QR Code
            </a>

            <p class="small">
                Right-click or long-press the QR code to save it if the button does not work on your device.
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
function copyText() {
    const textArea = document.getElementById("qrText");
    textArea.select();
    textArea.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(textArea.value)
        .then(() => alert("Text copied!"))
        .catch(() => alert("Could not copy text."));
}
</script>

</body>
</html>