<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

bs_auth_require_when_direct_script(__FILE__, ['admin', 'docent'], 'page');

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$appBase = preg_replace('~/(?:api/)?qr-api$~', '', $scriptDir) ?? '';
$appBase = rtrim($appBase, '/');

$urlFor = static function (string $base, string $path): string {
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
};

$html = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$text = trim((string)($_POST['qr_text'] ?? ''));

$finalImageBase64 = '';
$error = '';

function findFont(): ?string
{
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

function fitFontSize(string $text, string $fontFile, int $targetWidth): int
{
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
    if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
        $error = 'GD image extension is not available on this server.';
    } else {
        $labelText = strtoupper(function_exists('mb_substr') ? mb_substr($text, 0, 8) : substr($text, 0, 8));
        $encodedText = urlencode($text);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedText}";
        $qrData = @file_get_contents($qrUrl);

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
                $hasTrueType = $fontFile !== null && function_exists('imagettfbbox') && function_exists('imagettftext');
                $finalWidth = $qrWidth + ($padding * 2);

                if ($hasTrueType) {
                    $targetTextWidth = (int)($qrWidth * 0.96);
                    $fontSize = fitFontSize($labelText, $fontFile, $targetTextWidth);
                    $box = imagettfbbox($fontSize, 0, $fontFile, $labelText);
                    $textWidth = abs($box[2] - $box[0]);
                    $textHeight = abs($box[7] - $box[1]);
                } else {
                    $font = 5;
                    $scale = 4;
                    $textWidth = imagefontwidth($font) * strlen($labelText) * $scale;
                    $textHeight = imagefontheight($font) * $scale;
                }

                $finalHeight = $qrHeight + $gap + $textHeight + ($padding * 2);
                $final = imagecreatetruecolor($finalWidth, $finalHeight);
                $white = imagecolorallocate($final, 255, 255, 255);
                $black = imagecolorallocate($final, 0, 0, 0);

                imagefill($final, 0, 0, $white);

                $qrX = (int)(($finalWidth - $qrWidth) / 2);
                $qrY = $padding;

                imagecopy($final, $qr, $qrX, $qrY, 0, 0, $qrWidth, $qrHeight);

                if ($hasTrueType) {
                    $textX = (int)(($finalWidth - $textWidth) / 2);
                    $textY = $qrY + $qrHeight + $gap + $textHeight;

                    imagettftext($final, $fontSize, 0, $textX, $textY, $black, $fontFile, $labelText);
                } else {
                    $font = 5;
                    $baseWidth = imagefontwidth($font) * strlen($labelText);
                    $baseHeight = imagefontheight($font);
                    $tmp = imagecreatetruecolor($baseWidth, $baseHeight);

                    imagefill($tmp, 0, 0, $white);
                    imagestring($tmp, $font, 0, 0, $labelText, $black);

                    $scaled = imagecreatetruecolor($textWidth, $textHeight);
                    imagefill($scaled, 0, 0, $white);
                    imagecopyresized($scaled, $tmp, 0, 0, 0, 0, $textWidth, $textHeight, $baseWidth, $baseHeight);

                    $textX = (int)(($finalWidth - $textWidth) / 2);
                    $textY = $qrY + $qrHeight + $gap;

                    imagecopy($final, $scaled, $textX, $textY, 0, 0, $textWidth, $textHeight);

                    imagedestroy($tmp);
                    imagedestroy($scaled);
                }

                ob_start();
                imagepng($final);
                $imageData = ob_get_clean();

                if ($imageData === false) {
                    $error = 'Could not export QR image.';
                } else {
                    $finalImageBase64 = base64_encode($imageData);
                }

                imagedestroy($qr);
                imagedestroy($final);
            }
        }
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR Generator | BrailleStudio</title>
  <link rel="stylesheet" href="<?= $html($urlFor($appBase, 'tabler/core/dist/css/tabler.min.css')) ?>">
  <link rel="stylesheet" href="<?= $html($urlFor($appBase, 'tabler/icons-webfont/dist/tabler-icons.min.css')) ?>">
</head>
<body class="bg-body">
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <a class="navbar-brand navbar-brand-autodark pe-0 pe-md-3" href="<?= $html($urlFor($appBase, 'index.php')) ?>">
          <span class="avatar avatar-sm bg-primary-lt me-2">
            <i class="ti ti-braille text-primary" aria-hidden="true"></i>
          </span>
          <span>BrailleStudio</span>
        </a>
        <div class="navbar-nav flex-row align-items-center order-md-last ms-auto">
          <div class="nav-item">
            <a class="btn btn-outline-secondary" href="<?= $html($urlFor($appBase, 'index.php')) ?>">
              <i class="ti ti-home me-2" aria-hidden="true"></i>
              Start
            </a>
          </div>
        </div>
      </div>
    </header>

    <div class="page-wrapper">
      <div class="page-body">
        <main class="container-tight py-4">
          <div class="card card-lg">
            <div class="card-body p-4 p-md-5">
              <div class="text-center mb-4">
                <span class="avatar avatar-xl bg-primary-lt mb-3">
                  <i class="ti ti-qrcode fs-1"></i>
                </span>
                <h2 class="h1 mb-2">QR-code maken</h2>
                <p class="text-secondary mb-0">
                  Vul een korte code in. De QR-code krijgt dezelfde tekst als grote label onder de code.
                </p>
              </div>

              <form method="post">
                <div class="mb-3">
                  <label class="form-label" for="qrText">QR tekst</label>
                  <div class="input-icon">
                    <span class="input-icon-addon">
                      <i class="ti ti-tag"></i>
                    </span>
                    <input
                      id="qrText"
                      class="form-control form-control-lg text-center text-uppercase fw-bold font-monospace"
                      type="text"
                      name="qr_text"
                      maxlength="8"
                      placeholder="MAX 8 CHARS"
                      value="<?= $html($text) ?>"
                      required
                    >
                  </div>
                  <div class="form-hint">Maximaal 8 tekens voor een goed leesbaar label.</div>
                </div>
                <div class="d-grid">
                  <button class="btn btn-primary btn-lg" type="submit">
                    <i class="ti ti-qrcode me-2"></i>
                    Create QR
                  </button>
                </div>
              </form>

              <?php if ($error !== ''): ?>
                <div class="alert alert-danger mt-3 mb-0" role="alert">
                  <div class="d-flex">
                    <div>
                      <i class="ti ti-alert-circle icon alert-icon"></i>
                    </div>
                    <div><?= $html($error) ?></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($finalImageBase64 !== ''): ?>
            <div class="card card-lg mt-3">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="ti ti-check text-success me-2"></i>
                  QR-code klaar
                </h3>
              </div>
              <div class="card-body p-4 text-center">
                <div class="mb-3">
                  <img
                    class="img-thumbnail"
                    src="data:image/png;base64,<?= $html($finalImageBase64) ?>"
                    alt="QR Code"
                  >
                </div>
                <a
                  class="btn btn-outline-primary btn-lg"
                  href="data:image/png;base64,<?= $html($finalImageBase64) ?>"
                  download="qr-code.png"
                >
                  <i class="ti ti-download me-2"></i>
                  Download PNG
                </a>
              </div>
            </div>
          <?php endif; ?>
        </main>
      </div>
    </div>
  </div>

  <script src="<?= $html($urlFor($appBase, 'tabler/core/dist/js/tabler.min.js')) ?>"></script>
</body>
</html>
