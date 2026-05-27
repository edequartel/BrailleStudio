<?php
declare(strict_types=1);

$target = preg_replace(
    '~/(session-api/admin\.php)$~',
    '/api/$1',
    str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/braillestudio/session-api/admin.php')
) ?: '/braillestudio/api/session-api/admin.php';
$query = $_SERVER['QUERY_STRING'] ?? '';

header('Location: ' . $target . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
