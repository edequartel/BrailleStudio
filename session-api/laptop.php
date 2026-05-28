<?php
declare(strict_types=1);

$target = preg_replace(
    '~/(session-api/laptop\.php)$~',
    '/api/session-api/laptop.html',
    str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/braillestudio/session-api/laptop.php')
) ?: '/braillestudio/api/session-api/laptop.html';
$query = $_SERVER['QUERY_STRING'] ?? '';

header('Location: ' . $target . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
