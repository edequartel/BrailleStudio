<?php
declare(strict_types=1);

const BS_LANGUAGE_FALLBACK = 'nl';
const BS_LANGUAGE_SESSION_KEY = 'bs_language';
const BS_LANGUAGE_COOKIE = 'bs_language';

function bs_language_root(): string
{
    return dirname(__DIR__);
}

function bs_language_dir(): string
{
    return bs_language_root() . '/languages';
}

function bs_language_normalize_code(string $code): string
{
    $code = strtolower(trim($code));
    $code = str_replace('_', '-', $code);
    return preg_replace('/[^a-z0-9-]/', '', $code) ?? '';
}

function bs_language_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }

    if (function_exists('bs_auth_start_session')) {
        bs_auth_start_session();
        return;
    }

    session_start();
}

function bs_language_decode_file(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if (!is_string($json) || $json === '') {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function bs_language_available(): array
{
    static $available = null;
    if (is_array($available)) {
        return $available;
    }

    $available = [];
    $files = glob(bs_language_dir() . '/*.json');
    if (!is_array($files)) {
        return $available;
    }

    sort($files, SORT_STRING);
    foreach ($files as $path) {
        $fileCode = bs_language_normalize_code((string)pathinfo($path, PATHINFO_FILENAME));
        if ($fileCode === '') {
            continue;
        }

        $data = bs_language_decode_file($path);
        $meta = is_array($data['_meta'] ?? null) ? $data['_meta'] : [];
        $metaCode = bs_language_normalize_code((string)($meta['code'] ?? $fileCode));
        if ($metaCode !== $fileCode) {
            continue;
        }

        $direction = strtolower((string)($meta['direction'] ?? 'ltr'));
        $available[$fileCode] = [
            'code' => $fileCode,
            'name' => trim((string)($meta['name'] ?? strtoupper($fileCode))) ?: strtoupper($fileCode),
            'native' => trim((string)($meta['native'] ?? strtoupper($fileCode))) ?: strtoupper($fileCode),
            'direction' => $direction === 'rtl' ? 'rtl' : 'ltr',
            'path' => $path,
        ];
    }

    return $available;
}

function bs_language_is_available(string $code): bool
{
    $code = bs_language_normalize_code($code);
    $available = bs_language_available();
    return isset($available[$code]);
}

function bs_language_fallback_code(): string
{
    $available = bs_language_available();
    if (isset($available[BS_LANGUAGE_FALLBACK])) {
        return BS_LANGUAGE_FALLBACK;
    }

    return array_key_first($available) ?? BS_LANGUAGE_FALLBACK;
}

function bs_language_match_browser(?string $header): ?string
{
    $available = bs_language_available();
    if (!is_string($header) || trim($header) === '' || $available === []) {
        return null;
    }

    $candidates = [];
    foreach (explode(',', $header) as $part) {
        $bits = explode(';', trim($part));
        $code = bs_language_normalize_code((string)($bits[0] ?? ''));
        if ($code === '') {
            continue;
        }

        $quality = 1.0;
        if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $matches) === 1) {
            $quality = max(0.0, min(1.0, (float)$matches[1]));
        }
        $candidates[] = ['code' => $code, 'quality' => $quality];
    }

    usort($candidates, static fn (array $a, array $b): int => $b['quality'] <=> $a['quality']);
    foreach ($candidates as $candidate) {
        $code = $candidate['code'];
        if (isset($available[$code])) {
            return $code;
        }

        $primary = explode('-', $code)[0] ?? '';
        if ($primary !== '' && isset($available[$primary])) {
            return $primary;
        }
    }

    return null;
}

function bs_language_detect(): string
{
    static $detected = null;
    if (is_string($detected)) {
        return $detected;
    }

    bs_language_start_session();
    $available = bs_language_available();
    if ($available === []) {
        $detected = BS_LANGUAGE_FALLBACK;
        return $detected;
    }

    $sources = [
        bs_language_normalize_code((string)($_GET['lang'] ?? '')),
        bs_language_normalize_code((string)($_SESSION[BS_LANGUAGE_SESSION_KEY] ?? '')),
        bs_language_normalize_code((string)($_COOKIE[BS_LANGUAGE_COOKIE] ?? '')),
        bs_language_match_browser($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null) ?? '',
        bs_language_fallback_code(),
    ];

    foreach ($sources as $code) {
        if ($code !== '' && isset($available[$code])) {
            $detected = $code;
            $_SESSION[BS_LANGUAGE_SESSION_KEY] = $code;
            if (!headers_sent()) {
                setcookie(BS_LANGUAGE_COOKIE, $code, [
                    'expires' => time() + 31536000,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            }
            return $detected;
        }
    }

    $detected = array_key_first($available) ?? BS_LANGUAGE_FALLBACK;
    return $detected;
}

function bs_language_current(): string
{
    return bs_language_detect();
}

function bs_language_meta(?string $code = null): array
{
    $code = bs_language_normalize_code($code ?? bs_language_current());
    $available = bs_language_available();
    return $available[$code] ?? [
        'code' => $code !== '' ? $code : BS_LANGUAGE_FALLBACK,
        'name' => strtoupper($code !== '' ? $code : BS_LANGUAGE_FALLBACK),
        'native' => strtoupper($code !== '' ? $code : BS_LANGUAGE_FALLBACK),
        'direction' => 'ltr',
        'path' => '',
    ];
}

function bs_language_translations(string $code): array
{
    static $translations = [];
    $code = bs_language_normalize_code($code);
    if (isset($translations[$code])) {
        return $translations[$code];
    }

    $available = bs_language_available();
    $path = (string)($available[$code]['path'] ?? '');
    $data = $path !== '' ? bs_language_decode_file($path) : [];
    unset($data['_meta']);
    $translations[$code] = $data;
    return $translations[$code];
}

function bs_language_lookup(array $translations, string $key): ?string
{
    if (array_key_exists($key, $translations) && is_scalar($translations[$key])) {
        return (string)$translations[$key];
    }

    $value = $translations;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return is_scalar($value) ? (string)$value : null;
}

function t(string $key, array $params = []): string
{
    $value = bs_language_lookup(bs_language_translations(bs_language_current()), $key);
    $fallback = bs_language_fallback_code();
    if ($value === null && bs_language_current() !== $fallback) {
        $value = bs_language_lookup(bs_language_translations($fallback), $key);
    }
    if ($value === null) {
        $value = '[[' . $key . ']]';
    }

    foreach ($params as $name => $replacement) {
        $value = str_replace('{' . (string)$name . '}', (string)$replacement, $value);
    }

    return $value;
}

function bs_language_html_attrs(): string
{
    $meta = bs_language_meta();
    return 'lang="' . htmlspecialchars($meta['code'], ENT_QUOTES, 'UTF-8') . '" dir="' . htmlspecialchars($meta['direction'], ENT_QUOTES, 'UTF-8') . '"';
}

function bs_language_url(string $code): string
{
    $code = bs_language_normalize_code($code);
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $parts = parse_url($uri);
    $path = (string)($parts['path'] ?? '');
    $query = [];
    if (isset($parts['query'])) {
        parse_str((string)$parts['query'], $query);
    }
    $query['lang'] = $code;
    $queryString = http_build_query($query);
    return $path . ($queryString !== '' ? '?' . $queryString : '');
}

function language_switcher(string $class = ''): string
{
    $available = bs_language_available();
    if (count($available) < 2) {
        return '';
    }

    $current = bs_language_current();
    $currentMeta = bs_language_meta($current);
    $classAttr = trim('dropdown ' . $class);
    $html = '<div class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
    $html .= '<i class="ti ti-language me-2" aria-hidden="true"></i>' . htmlspecialchars($currentMeta['native'], ENT_QUOTES, 'UTF-8');
    $html .= '</button><div class="dropdown-menu dropdown-menu-end">';

    foreach ($available as $code => $meta) {
        $active = $code === $current;
        $html .= '<a class="dropdown-item' . ($active ? ' active' : '') . '" href="' . htmlspecialchars(bs_language_url($code), ENT_QUOTES, 'UTF-8') . '"' . ($active ? ' aria-current="true"' : '') . '>';
        $html .= htmlspecialchars($meta['native'], ENT_QUOTES, 'UTF-8');
        $html .= '<span class="text-secondary ms-2">' . htmlspecialchars($meta['code'], ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</a>';
    }

    $html .= '</div></div>';
    return $html;
}

bs_language_detect();
