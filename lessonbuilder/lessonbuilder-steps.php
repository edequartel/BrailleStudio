<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

bs_auth_require_login(['admin', 'docent']);

require __DIR__ . '/../api/lessonbuilder/lessonbuilder-steps.php';
