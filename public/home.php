<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';

$accounts = get_all_accounts();
$domain   = get_domain();

require BASE_PATH . '/templates/home.php';
