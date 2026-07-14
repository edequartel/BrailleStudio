<?php
declare(strict_types=1);

function bs_site_usage_log_path(): string
{
    return dirname(__DIR__) . '/data/site-usage/opens.jsonl';
}

function bs_site_usage_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
}

function bs_site_usage_visitor_key(?array $authUser): string
{
    if ($authUser !== null && (int)($authUser['id'] ?? 0) > 0) {
        return 'user:' . (int)$authUser['id'];
    }

    if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
        return 'guest-session:' . substr(hash('sha256', session_id()), 0, 16);
    }

    $fallback = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return 'guest:' . substr(hash('sha256', $fallback), 0, 16);
}

function bs_site_usage_log_open(?array $authUser): void
{
    $path = bs_site_usage_log_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    $now = bs_site_usage_now();
    $record = [
        'openedAt' => $now->format(DateTimeInterface::ATOM),
        'visitorKey' => bs_site_usage_visitor_key($authUser),
        'display' => $authUser['display'] ?? 'Guest',
        'email' => $authUser['email'] ?? '',
        'role' => $authUser['role'] ?? 'public',
        'path' => (string)($_SERVER['REQUEST_URI'] ?? 'index.php'),
        'userAgent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 240),
    ];

    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($line)) {
        return;
    }

    $handle = @fopen($path, 'ab');
    if (!is_resource($handle)) {
        return;
    }

    try {
        if (flock($handle, LOCK_EX)) {
            fwrite($handle, $line . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }
}

function bs_site_usage_format_time(string $isoTime): string
{
    try {
        return (new DateTimeImmutable($isoTime))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i');
    } catch (Throwable $e) {
        return $isoTime;
    }
}

function bs_site_usage_stats(int $recentLimit = 25): array
{
    $path = bs_site_usage_log_path();
    if (!is_file($path)) {
        return [
            'total' => 0,
            'unique' => 0,
            'lastOpenedAt' => '',
            'recent' => [],
        ];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [
            'total' => 0,
            'unique' => 0,
            'lastOpenedAt' => '',
            'recent' => [],
        ];
    }

    $total = 0;
    $visitors = [];
    $recent = [];
    foreach ($lines as $line) {
        $record = json_decode($line, true);
        if (!is_array($record)) {
            continue;
        }

        $total++;
        $visitorKey = trim((string)($record['visitorKey'] ?? ''));
        if ($visitorKey !== '') {
            $visitors[$visitorKey] = true;
        }
        $recent[] = $record;
    }

    $recent = array_slice(array_reverse($recent), 0, $recentLimit);
    return [
        'total' => $total,
        'unique' => count($visitors),
        'lastOpenedAt' => (string)($recent[0]['openedAt'] ?? ''),
        'recent' => $recent,
    ];
}
