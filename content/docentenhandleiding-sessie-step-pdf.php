<?php
declare(strict_types=1);

$markdownPath = __DIR__ . '/docentenhandleiding-sessie-step.md';
$markdown = is_readable($markdownPath) ? (string)file_get_contents($markdownPath) : '';

function bs_pdf_escape(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return preg_replace('/[^\P{C}\n\r\t]/u', '', $text) ?? '';
}

function bs_plain_markdown(string $text): string
{
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/u', '$1', $text) ?? $text;
    $text = preg_replace('/\*\*(.*?)\*\*/u', '$1', $text) ?? $text;
    $text = preg_replace('/\*(.*?)\*/u', '$1', $text) ?? $text;
    $text = preg_replace('/`([^`]*)`/u', '$1', $text) ?? $text;
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '$1 ($2)', $text) ?? $text;
    return trim($text);
}

function bs_resolve_image_path(string $source): string
{
    $source = trim($source);
    if ($source === '') {
        return '';
    }

    $urlPath = parse_url($source, PHP_URL_PATH);
    if (is_string($urlPath) && $urlPath !== '') {
        $basename = basename($urlPath);
        $localPath = __DIR__ . '/' . $basename;
        if (is_readable($localPath)) {
            return $localPath;
        }
    }

    $relativePath = __DIR__ . '/' . ltrim($source, '/');
    return is_readable($relativePath) ? $relativePath : '';
}

function bs_load_pdf_image(string $source): ?array
{
    $path = bs_resolve_image_path($source);
    if ($path === '') {
        return null;
    }

    $size = getimagesize($path);
    if (!is_array($size)) {
        return null;
    }

    [$width, $height, $type] = $size;
    if ($width <= 0 || $height <= 0) {
        return null;
    }

    if ($type === IMAGETYPE_JPEG) {
        $data = file_get_contents($path);
        return is_string($data)
            ? ['data' => $data, 'width' => $width, 'height' => $height, 'filter' => 'DCTDecode']
            : null;
    }

    if ($type !== IMAGETYPE_PNG || !function_exists('imagecreatefrompng')) {
        return null;
    }

    $sourceImage = imagecreatefrompng($path);
    if (!$sourceImage) {
        return null;
    }

    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas) {
        imagedestroy($sourceImage);
        return null;
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
    imagealphablending($canvas, true);
    imagecopy($canvas, $sourceImage, 0, 0, 0, 0, $width, $height);

    ob_start();
    imagejpeg($canvas, null, 88);
    $data = ob_get_clean();

    return is_string($data) && $data !== ''
        ? ['data' => $data, 'width' => $width, 'height' => $height, 'filter' => 'DCTDecode']
        : null;
}

function bs_wrap_pdf_text(string $text, int $maxLen): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return [];
    }

    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($candidate) <= $maxLen) {
            $line = $candidate;
            continue;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        $line = $word;
        while (mb_strlen($line) > $maxLen) {
            $lines[] = mb_substr($line, 0, $maxLen);
            $line = mb_substr($line, $maxLen);
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function bs_markdown_blocks(string $markdown): array
{
    $blocks = [];
    $paragraph = [];
    $lines = preg_split('/\R/u', $markdown) ?: [];

    $flushParagraph = static function () use (&$blocks, &$paragraph): void {
        if ($paragraph === []) {
            return;
        }
        $blocks[] = [
            'type' => 'p',
            'text' => bs_plain_markdown(implode(' ', $paragraph)),
        ];
        $paragraph = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $flushParagraph();
            continue;
        }

        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $blocks[] = [
                'type' => 'img',
                'alt' => bs_plain_markdown($matches[1]),
                'src' => trim($matches[2]),
            ];
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $blocks[] = [
                'type' => 'h' . strlen($matches[1]),
                'text' => bs_plain_markdown($matches[2]),
            ];
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $blocks[] = [
                'type' => 'li',
                'text' => bs_plain_markdown($matches[1]),
                'marker' => '-',
            ];
            continue;
        }

        if (preg_match('/^(\d+)\.\s+(.+)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $blocks[] = [
                'type' => 'li',
                'text' => bs_plain_markdown($matches[2]),
                'marker' => $matches[1] . '.',
            ];
            continue;
        }

        $paragraph[] = $trimmed;
    }

    $flushParagraph();
    return $blocks;
}

$pages = [];
$currentOps = [];
$currentImages = [];
$images = [];
$imageNamesBySource = [];
$y = 792;
$bottomLimit = 58;

$startPage = static function () use (&$currentOps, &$currentImages, &$y): void {
    $currentOps = ["1 1 1 rg\n0 0 595 842 re f\n0 0 0 rg\n"];
    $currentImages = [];
    $y = 792;
};

$flushPage = static function () use (&$pages, &$currentOps, &$currentImages): void {
    if ($currentOps !== []) {
        $pages[] = [
            'content' => implode('', $currentOps),
            'images' => array_keys($currentImages),
        ];
    }
};

$ensureSpace = static function (int $needed) use (&$y, $bottomLimit, $flushPage, $startPage): void {
    if (($y - $needed) < $bottomLimit) {
        $flushPage();
        $startPage();
    }
};

$writeText = static function (string $text, int $x, int $fontSize, bool $bold = false) use (&$currentOps, &$y): void {
    $fontName = $bold ? 'F2' : 'F1';
    $safeLine = bs_pdf_escape($text);
    $currentOps[] = "BT\n/{$fontName} {$fontSize} Tf\n1 0 0 1 {$x} {$y} Tm\n({$safeLine}) Tj\nET\n";
};

$drawImage = static function (array $image, int $x, int $width, int $height) use (&$currentOps, &$currentImages, &$y): void {
    $name = (string)$image['name'];
    $drawY = $y - $height;
    $currentOps[] = "q\n{$width} 0 0 {$height} {$x} {$drawY} cm\n/{$name} Do\nQ\n";
    $currentImages[$name] = true;
    $y = $drawY;
};

$startPage();

foreach (bs_markdown_blocks($markdown) as $block) {
    $type = (string)($block['type'] ?? 'p');
    $text = (string)($block['text'] ?? '');
    if ($text === '' && $type !== 'img') {
        continue;
    }

    if ($type === 'img') {
        $src = (string)($block['src'] ?? '');
        if (!isset($imageNamesBySource[$src])) {
            $image = bs_load_pdf_image($src);
            if ($image !== null) {
                $name = 'Im' . (count($images) + 1);
                $image['name'] = $name;
                $images[$name] = $image;
                $imageNamesBySource[$src] = $name;
            }
        }

        $imageName = $imageNamesBySource[$src] ?? '';
        if ($imageName === '' || !isset($images[$imageName])) {
            $fallback = trim((string)($block['alt'] ?? 'Afbeelding'));
            $lines = bs_wrap_pdf_text($fallback !== '' ? '[' . $fallback . ']' : '[Afbeelding niet beschikbaar]', 88);
            $ensureSpace(max(1, count($lines)) * 14 + 10);
            foreach ($lines as $line) {
                $writeText($line, 50, 10);
                $y -= 14;
            }
            $y -= 6;
            continue;
        }

        $image = $images[$imageName];
        $maxWidth = 495;
        $maxHeight = 300;
        $scale = min($maxWidth / (int)$image['width'], $maxHeight / (int)$image['height'], 1);
        $drawWidth = (int)round((int)$image['width'] * $scale);
        $drawHeight = (int)round((int)$image['height'] * $scale);
        $ensureSpace($drawHeight + 18);
        $drawImage($image, 50, $drawWidth, $drawHeight);
        $y -= 12;
        continue;
    }

    if ($type === 'h1') {
        $lines = bs_wrap_pdf_text($text, 48);
        $ensureSpace((count($lines) * 22) + 18);
        foreach ($lines as $line) {
            $writeText($line, 50, 18, true);
            $y -= 22;
        }
        $y -= 8;
        continue;
    }

    if ($type === 'h2') {
        $lines = bs_wrap_pdf_text($text, 58);
        $ensureSpace((count($lines) * 18) + 12);
        $y -= 4;
        foreach ($lines as $line) {
            $writeText($line, 50, 14, true);
            $y -= 18;
        }
        $y -= 4;
        continue;
    }

    if ($type === 'h3') {
        $lines = bs_wrap_pdf_text($text, 64);
        $ensureSpace((count($lines) * 16) + 10);
        foreach ($lines as $line) {
            $writeText($line, 50, 12, true);
            $y -= 16;
        }
        $y -= 3;
        continue;
    }

    if ($type === 'li') {
        $marker = (string)($block['marker'] ?? '-');
        $lines = bs_wrap_pdf_text($text, 82);
        $ensureSpace(max(1, count($lines)) * 14 + 6);
        foreach ($lines as $index => $line) {
            $writeText($index === 0 ? $marker : '', 60, 10);
            $writeText($line, 84, 10);
            $y -= 14;
        }
        $y -= 2;
        continue;
    }

    $lines = bs_wrap_pdf_text($text, 88);
    $ensureSpace(max(1, count($lines)) * 14 + 10);
    foreach ($lines as $line) {
        $writeText($line, 50, 10);
        $y -= 14;
    }
    $y -= 6;
}

$flushPage();

if ($pages === []) {
    $pages[] = [
        'content' => "BT\n/F1 12 Tf\n1 0 0 1 50 792 Tm\n(Document niet beschikbaar.) Tj\nET\n",
        'images' => [],
    ];
}

$objects = [];
$pageRefs = [];
$fontObj = 1;
$fontBoldObj = 2;
$pagesObj = 3;
$nextObj = 4;
$imageObjectIds = [];

foreach ($images as $name => $image) {
    $imageObjectIds[$name] = $nextObj++;
}

foreach ($pages as $content) {
    $pageContent = (string)$content['content'];
    $pageImageNames = is_array($content['images'] ?? null) ? $content['images'] : [];
    $xObjects = [];
    foreach ($pageImageNames as $imageName) {
        if (isset($imageObjectIds[$imageName])) {
            $xObjects[] = '/' . $imageName . ' ' . $imageObjectIds[$imageName] . ' 0 R';
        }
    }
    $xObjectResource = $xObjects === [] ? '' : ' /XObject << ' . implode(' ', $xObjects) . ' >>';

    $contentObj = $nextObj++;
    $pageObj = $nextObj++;
    $objects[$contentObj] = "<< /Length " . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream";
    $objects[$pageObj] = "<< /Type /Page /Parent {$pagesObj} 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObj} 0 R /F2 {$fontBoldObj} 0 R >>{$xObjectResource} >> /Contents {$contentObj} 0 R >>";
    $pageRefs[] = $pageObj;
}

foreach ($images as $name => $image) {
    $id = $imageObjectIds[$name];
    $imageData = (string)$image['data'];
    $objects[$id] = "<< /Type /XObject /Subtype /Image /Width " . (int)$image['width'] . " /Height " . (int)$image['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /" . $image['filter'] . " /Length " . strlen($imageData) . " >>\nstream\n" . $imageData . "\nendstream";
}

$objects[$fontObj] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objects[$fontBoldObj] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
$objects[$pagesObj] = "<< /Type /Pages /Kids [" . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageRefs)) . "] /Count " . count($pageRefs) . " >>";
$catalogObj = $nextObj++;
$objects[$catalogObj] = "<< /Type /Catalog /Pages {$pagesObj} 0 R >>";

$pdf = "%PDF-1.4\n";
$offsets = [0];
ksort($objects);
foreach ($objects as $id => $body) {
    $offsets[$id] = strlen($pdf);
    $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . ($catalogObj + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i <= $catalogObj; $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
}
$pdf .= "trailer\n<< /Size " . ($catalogObj + 1) . " /Root {$catalogObj} 0 R >>\n";
$pdf .= "startxref\n{$xrefOffset}\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="braillestudio-handleiding-sessie-step.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
