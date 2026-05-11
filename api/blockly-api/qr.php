<?php
declare(strict_types=1);

$text = trim($_POST['qr_text'] ?? '');

$finalImageBase64 = '';
$error = '';

function findFont(): ?string {

    $fonts = [
        __DIR__ . '/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ];

    foreach ($fonts as $font) {
        if (is_file($font)) {
            return $font;
        }
    }

    return null;
}

function fitFontSize(
    string $text,
    string $fontFile,
    int $targetWidth
): int {

    for ($size = 10; $size <= 200; $size++) {

        $box = imagettfbbox($size, 0, $fontFile, $text);

        $width = abs($box[2] - $box[0]);

        if ($width > $targetWidth) {
            return max(10, $size - 1);
        }
    }

    return 200;
}

if ($text !== '') {

    // Max 8 chars
    $labelText = strtoupper(mb_substr($text, 0, 8));

    $encodedText = urlencode($text);

    $qrUrl =
        "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedText}";

    $qrData = file_get_contents($qrUrl);

    if ($qrData === false) {

        $error = 'Could not generate QR code.';

    } else {

        $qr = imagecreatefromstring($qrData);

        if ($qr === false) {

            $error = 'Could not read QR image.';

        } else {

            $qrWidth = imagesx($qr);
            $qrHeight = imagesy($qr);

            $padding = 25;
            $gap = 10;

            $fontFile = findFont();

            $finalWidth = $qrWidth + ($padding * 2);

            if ($fontFile !== null) {

                // Make text nearly as wide as QR
                $targetTextWidth = (int)($qrWidth * 0.96);

                $fontSize = fitFontSize(
                    $labelText,
                    $fontFile,
                    $targetTextWidth
                );

                $box = imagettfbbox(
                    $fontSize,
                    0,
                    $fontFile,
                    $labelText
                );

                $textWidth = abs($box[2] - $box[0]);
                $textHeight = abs($box[7] - $box[1]);

            } else {

                // Standard fallback font

                $font = 5;

                // Artificial scale factor
                $scale = 4;

                $textWidth =
                    imagefontwidth($font) *
                    strlen($labelText) *
                    $scale;

                $textHeight =
                    imagefontheight($font) *
                    $scale;
            }

            $finalHeight =
                $qrHeight +
                $gap +
                $textHeight +
                ($padding * 2);

            $final = imagecreatetruecolor(
                $finalWidth,
                $finalHeight
            );

            $white = imagecolorallocate($final, 255, 255, 255);
            $black = imagecolorallocate($final, 0, 0, 0);

            imagefill($final, 0, 0, $white);

            $qrX = (int)(($finalWidth - $qrWidth) / 2);
            $qrY = $padding;

            imagecopy(
                $final,
                $qr,
                $qrX,
                $qrY,
                0,
                0,
                $qrWidth,
                $qrHeight
            );

            if ($fontFile !== null) {

                // Smooth TrueType text

                $textX =
                    (int)(($finalWidth - $textWidth) / 2);

                $textY =
                    $qrY +
                    $qrHeight +
                    $gap +
                    $textHeight;

                imagettftext(
                    $final,
                    $fontSize,
                    0,
                    $textX,
                    $textY,
                    $black,
                    $fontFile,
                    $labelText
                );

            } else {

                // Fallback enlarged bitmap text

                $font = 5;

                $baseWidth =
                    imagefontwidth($font) *
                    strlen($labelText);

                $baseHeight =
                    imagefontheight($font);

                $tmp = imagecreatetruecolor(
                    $baseWidth,
                    $baseHeight
                );

                imagefill($tmp, 0, 0, $white);

                imagestring(
                    $tmp,
                    $font,
                    0,
                    0,
                    $labelText,
                    $black
                );

                $scaled = imagecreatetruecolor(
                    $textWidth,
                    $textHeight
                );

                imagefill($scaled, 0, 0, $white);

                imagecopyresized(
                    $scaled,
                    $tmp,
                    0,
                    0,
                    0,
                    0,
                    $textWidth,
                    $textHeight,
                    $baseWidth,
                    $baseHeight
                );

                $textX =
                    (int)(($finalWidth - $textWidth) / 2);

                $textY =
                    $qrY +
                    $qrHeight +
                    $gap;

                imagecopy(
                    $final,
                    $scaled,
                    $textX,
                    $textY,
                    0,
                    0,
                    $textWidth,
                    $textHeight
                );

                imagedestroy($tmp);
                imagedestroy($scaled);
            }

            ob_start();

            imagepng($final);

            $imageData = ob_get_clean();

            $finalImageBase64 =
                base64_encode($imageData);

            imagedestroy($qr);
            imagedestroy($final);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<title>QR Generator</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<style>

body{
    margin:0;
    font-family:Arial,sans-serif;
    background:linear-gradient(135deg,#4f46e5,#06b6d4);
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
}

.container{
    background:white;
    max-width:520px;
    width:100%;
    padding:30px;
    border-radius:24px;
    box-shadow:0 20px 45px rgba(0,0,0,.25);
    text-align:center;
}

input{
    width:100%;
    padding:15px;
    border-radius:14px;
    border:2px solid #cbd5e1;
    font-size:26px;
    box-sizing:border-box;
    margin-top:12px;
    text-align:center;
    text-transform:uppercase;
    letter-spacing:2px;
    font-weight:bold;
}

button,
.download-btn{
    margin-top:15px;
    display:inline-block;
    background:#4f46e5;
    color:white;
    border:none;
    padding:14px 22px;
    border-radius:14px;
    font-size:16px;
    cursor:pointer;
    text-decoration:none;
    font-weight:bold;
}

button:hover,
.download-btn:hover{
    background:#3730a3;
}

.qr-box{
    margin-top:30px;
    padding:20px;
    border-radius:20px;
    background:#f8fafc;
    border:2px dashed #cbd5e1;
}

.qr-box img{
    max-width:100%;
    border-radius:12px;
    background:white;
}

.error{
    margin-top:20px;
    color:red;
    font-weight:bold;
}

</style>
</head>

<body>

<div class="container">

    <h1>QR Generator</h1>

    <form method="post">

        <input
            type="text"
            name="qr_text"
            maxlength="8"
            placeholder="MAX 8 CHARS"
            value="<?php echo htmlspecialchars($text); ?>"
            required
        >

        <button type="submit">
            Create QR
        </button>

    </form>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <?php if ($finalImageBase64 !== ''): ?>

        <div class="qr-box">

            <img
                src="data:image/png;base64,<?php echo $finalImageBase64; ?>"
                alt="QR Code"
            >

            <br>

            <a
                class="download-btn"
                href="data:image/png;base64,<?php echo $finalImageBase64; ?>"
                download="qr-code.png"
            >
                Download PNG
            </a>

        </div>

    <?php endif; ?>

</div>

</body>
</html>