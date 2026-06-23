<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';
bs_auth_require_login(['admin', 'developer'], 'page');

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/lib.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_api_ensure_storage_dirs();

function step_links_pdf_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function step_links_pdf_qr_data_uri(string $code): string
{
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=' . rawurlencode($code);
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
        ],
    ]);
    $data = @file_get_contents($qrUrl, false, $context);

    if ((!is_string($data) || $data === '') && function_exists('curl_init')) {
        $curl = curl_init($qrUrl);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
            ]);
            $curlData = curl_exec($curl);
            curl_close($curl);
            $data = is_string($curlData) ? $curlData : $data;
        }
    }

    return is_string($data) && $data !== ''
        ? 'data:image/png;base64,' . base64_encode($data)
        : '';
}

function step_links_pdf_records(): array
{
    $activeRaw = strtolower(trim((string)($_GET['active'] ?? '1')));
    $activeOnly = !in_array($activeRaw, ['0', 'false', 'no', 'all'], true);
    $records = [];

    foreach (session_api_list_step_link_files() as $path) {
        $record = session_api_read_json_file($path);
        if (!is_array($record)) {
            continue;
        }

        $code = trim((string)($record['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $isActive = (bool)($record['active'] ?? false);
        if ($activeOnly && !$isActive) {
            continue;
        }

        $records[] = [
            'code' => $code,
            'methodId' => trim((string)($record['methodId'] ?? session_api_step_link_method_id_from_path($path))),
            'scriptId' => trim((string)($record['scriptId'] ?? '')),
        ];
    }

    usort($records, static function (array $a, array $b): int {
        return strnatcasecmp((string)$a['code'], (string)$b['code']);
    });

    return $records;
}

$records = step_links_pdf_records();
$pages = array_chunk($records, 24);
if ($pages === []) {
    $pages = [[]];
}

$html = '<!doctype html><html><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 10mm; }
body { margin: 0; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #111827; }
.page { page-break-after: always; }
.page:last-child { page-break-after: auto; }
.sheet { width: 100%; border-collapse: collapse; table-layout: fixed; }
.sheet td { width: 50%; height: 21.6mm; border: 0.25mm solid #d7dde8; text-align: center; vertical-align: middle; padding: 1.4mm 2mm 1mm; }
.qr { width: 13.6mm; height: 13.6mm; display: block; margin: 0 auto 1mm; }
.qr-fallback { width: 13.6mm; height: 13.6mm; margin: 0 auto 1mm; border: 0.25mm solid #9ca3af; line-height: 13.6mm; font-size: 8pt; font-weight: 700; color: #6b7280; }
.code { font-size: 9pt; line-height: 1; font-weight: 700; letter-spacing: 0; }
.empty { color: transparent; }
</style>  <meta property="og:type" content="website">
  <meta property="og:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:secure_url" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1729">
  <meta property="og:image:height" content="910">
  <meta property="og:image:alt" content="BrailleStudio">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="https://www.tastenbraille.com/braillestudio-data/opengraph/social-preview.png">
  <meta name="twitter:image:alt" content="BrailleStudio">
</head><body>';

foreach ($pages as $pageRecords) {
    $html .= '<div class="page"><table class="sheet"><tbody>';
    for ($row = 0; $row < 12; $row++) {
        $html .= '<tr>';
        for ($column = 0; $column < 2; $column++) {
            $record = $pageRecords[($row * 2) + $column] ?? null;
            if (!is_array($record)) {
                $html .= '<td class="empty">&nbsp;</td>';
                continue;
            }

            $code = (string)$record['code'];
            $qrDataUri = step_links_pdf_qr_data_uri($code);
            $html .= '<td>';
            if ($qrDataUri !== '') {
                $html .= '<img class="qr" src="' . step_links_pdf_h($qrDataUri) . '" alt="">';
            } else {
                $html .= '<div class="qr-fallback">QR</div>';
            }
            $html .= '<div class="code">' . step_links_pdf_h($code) . '</div>';
            $html .= '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
}

$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('braillestudio-step-links-' . gmdate('Y-m-d') . '.pdf', ['Attachment' => true]);
