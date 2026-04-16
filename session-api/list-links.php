<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_api_handle_options();
session_api_send_common_headers();

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    session_api_error('Method not allowed', 405);
}

session_api_ensure_storage_dirs();

$activeFilterRaw = isset($_GET['active']) ? (string)$_GET['active'] : '';
$activeFilter = null;
if ($activeFilterRaw !== '') {
    $normalized = strtolower(trim($activeFilterRaw));
    if (in_array($normalized, ['1', 'true', 'yes'], true)) {
        $activeFilter = true;
    } elseif (in_array($normalized, ['0', 'false', 'no'], true)) {
        $activeFilter = false;
    } else {
        session_api_error('Invalid active filter. Use true/false, yes/no, or 1/0.', 400);
    }
}

$codeFilter = '';
if (isset($_GET['code']) && trim((string)$_GET['code']) !== '') {
    $codeFilter = session_api_normalize_token((string)$_GET['code'], 'code', 3, 64);
}

$records = [];
foreach (session_api_list_json_files(session_api_step_links_dir()) as $path) {
    $record = session_api_read_json_file($path);
    if (!is_array($record)) {
        continue;
    }

    $code = trim((string)($record['code'] ?? ''));
    if ($code === '') {
        continue;
    }

    if ($codeFilter !== '' && $code !== $codeFilter) {
        continue;
    }

    $isActive = (bool)($record['active'] ?? false);
    if ($activeFilter !== null && $isActive !== $activeFilter) {
        continue;
    }

    $records[] = [
        'code' => $code,
        'active' => $isActive,
        'stepId' => (string)($record['stepId'] ?? ''),
        'scriptId' => (string)($record['scriptId'] ?? ''),
        'updatedAt' => $record['updatedAt'] ?? null,
        'meta' => is_array($record['meta'] ?? null) ? $record['meta'] : new stdClass(),
        'stepInputs' => is_array($record['stepInputs'] ?? null) ? $record['stepInputs'] : new stdClass(),
    ];
}

session_api_respond([
    'ok' => true,
    'count' => count($records),
    'items' => $records,
]);
