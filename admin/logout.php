<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

admin_log_out();

header('Location: login.php');
exit;
