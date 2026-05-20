<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

bs_auth_require_login(['admin', 'docent'], 'json');

require __DIR__ . '/../api/methods-api/list_basis_files.php';
