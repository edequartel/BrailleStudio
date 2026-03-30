<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_authentication();

$leerlingId = (int)get('leerling', '0');
if ($leerlingId <= 0) {
    redirect_with_query('index.php');
}

$leerling = fetch_leerling_by_id($db, $leerlingId);
if (!$leerling) {
    redirect_with_query('index.php');
}

$stmt = $db->prepare("
    SELECT datum, onderdeel, auteur, notitie
    FROM voortgang
    WHERE leerling_id = :leerling_id
      AND deleted_at = ''
    ORDER BY datum DESC, id DESC
");
$stmt->bindValue(':leerling_id', $leerlingId, SQLITE3_INTEGER);
$res = $stmt->execute();

$items = [];
while ($row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false) {
    $items[] = $row;
}

write_audit_log($db, 'leerling', $leerlingId, 'pdf_export', ['count' => count($items)]);

function pdf_escape(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return preg_replace('/[^\P{C}\n\r\t]/u', '', $text) ?? '';
}

function wrap_pdf_text(string $text, int $maxLen = 88): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return ['-'];
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

    return $lines ?: ['-'];
}

$pages = [];
$currentOps = [];
$y = 800;
$bottomLimit = 70;

$startPage = static function () use (&$currentOps, &$y): void {
    $currentOps = [];
    $y = 800;
};

$flushPage = static function () use (&$pages, &$currentOps): void {
    if ($currentOps !== []) {
        $pages[] = implode('', $currentOps);
    }
};

$ensureSpace = static function (int $needed) use (&$y, $bottomLimit, &$flushPage, &$startPage): void {
    if (($y - $needed) < $bottomLimit) {
        $flushPage();
        $startPage();
    }
};

$writeText = static function (string $text, int $x, int $fontSize, bool $bold = false) use (&$currentOps, &$y): void {
    $fontName = $bold ? 'F2' : 'F1';
    $safeLine = pdf_escape($text);
    $currentOps[] = "BT\n/{$fontName} {$fontSize} Tf\n1 0 0 1 {$x} {$y} Tm\n({$safeLine}) Tj\nET\n";
};

$drawLine = static function (int $x1, int $x2) use (&$currentOps, &$y): void {
    $lineY = $y + 4;
    $currentOps[] = "{$x1} {$lineY} m {$x2} {$lineY} l S\n";
};

$startPage();

$ensureSpace(110);
$writeText('BrailleVolg - Aantekeningen', 50, 16, true);
$y -= 24;
$writeText('Leerling: ' . (string)($leerling['naam'] ?? ''), 50, 11, true);
$y -= 18;
$writeText('Groep / klas: ' . (string)($leerling['groep_klas'] ?? '-'), 50, 11);
$y -= 16;
$writeText('Niveau: ' . (string)($leerling['niveau'] ?? '-'), 50, 11);
$y -= 16;
$writeText('Gegenereerd door: ' . current_auth_user() . ' op ' . gmdate('Y-m-d H:i') . ' UTC', 50, 10);
$y -= 20;
$drawLine(50, 545);
$y -= 18;

if ($items === []) {
    $ensureSpace(20);
    $writeText('Geen aantekeningen beschikbaar.', 50, 11);
    $y -= 18;
} else {
    foreach ($items as $index => $item) {
        $noteLines = wrap_pdf_text((string)($item['notitie'] ?? ''));
        $blockHeight = 18 + 16 + 16 + 16 + (count($noteLines) * 15) + 18;
        $ensureSpace($blockHeight);

        $writeText('Datum: ' . (string)($item['datum'] ?? ''), 50, 11, true);
        $y -= 18;
        $writeText('Onderdeel: ' . (string)($item['onderdeel'] ?? ''), 50, 11);
        $y -= 16;
        $writeText('Door: ' . (string)($item['auteur'] ?? ''), 50, 11);
        $y -= 16;
        $writeText('Aantekening:', 50, 11, true);
        $y -= 16;
        foreach ($noteLines as $noteLine) {
            $writeText($noteLine, 62, 10);
            $y -= 15;
        }
        if ($index < count($items) - 1) {
            $y -= 4;
            $drawLine(50, 545);
            $y -= 16;
        }
    }
}

$flushPage();

$objects = [];
$pageRefs = [];

$fontObj = 1;
$fontBoldObj = 2;
$pagesObj = 3;
$nextObj = 4;

foreach ($pages as $content) {
    $contentObj = $nextObj++;
    $pageObj = $nextObj++;

    $objects[$contentObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
    $objects[$pageObj] = "<< /Type /Page /Parent {$pagesObj} 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObj} 0 R /F2 {$fontBoldObj} 0 R >> >> /Contents {$contentObj} 0 R >>";

    $pageRefs[] = $pageObj;
}

$objects[$fontObj] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objects[$fontBoldObj] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
$objects[$pagesObj] = "<< /Type /Pages /Kids [" . implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageRefs)) . "] /Count " . count($pageRefs) . " >>";
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

$safeName = preg_replace('/[^a-z0-9_-]+/i', '-', (string)$leerling['naam']) ?: 'leerling';
$datePart = gmdate('Y-m-d');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safeName . '-' . $datePart . '-braillevolg.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
