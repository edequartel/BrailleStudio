<?php
declare(strict_types=1);

const SESSION_QR_REMOTE_BASE = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$data = trim((string)($_GET['data'] ?? ''));
if ($data === '' || strlen($data) > 2048) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid QR data.';
    exit;
}

$remoteUrl = SESSION_QR_REMOTE_BASE . rawurlencode($data);
$image = '';

if (function_exists('curl_init')) {
    $curl = curl_init($remoteUrl);
    if ($curl !== false) {
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'BrailleStudio session QR proxy',
        ]);
        $result = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

        if (
            is_string($result)
            && $result !== ''
            && $status >= 200
            && $status < 300
            && str_starts_with(strtolower($contentType), 'image/')
        ) {
            $image = $result;
        }
    }
}

if ($image === '') {
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'BrailleStudio session QR proxy',
        ],
    ]);
    $result = @file_get_contents($remoteUrl, false, $context);
    if (is_string($result) && $result !== '') {
        $image = $result;
    }
}

if ($image === '') {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'QR image unavailable.';
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($image));
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $image;
