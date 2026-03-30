<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

logout_user();
redirect_with_query('login.php');
