<?php
declare(strict_types=1);

// $adminSegs: ['logs'], ['logs','N'], ['logs','clear'], ['logs','N','clear']
$seg1   = $adminSegs[1] ?? '';
$seg2   = $adminSegs[2] ?? '';
$botId  = is_numeric($seg1) ? (int)$seg1 : null;
$logAction = $seg2 === 'clear' ? 'clear' : ($seg1 === 'clear' ? 'clear' : '');

if ($logAction === 'clear' && is_post()) {
    csrf_verify();
    $cleared = log_clear_all($botId ?: null);
    redirect(admin_url('logs' . ($botId ? '/' . $botId : '') . '?cleared=' . $cleared));
}

$direction = $_GET['direction'] ?? '';
$logs      = log_get_recent(200, $botId ?: null, $direction);
$accounts  = get_all_accounts();

require BASE_PATH . '/templates/admin/logs.php';
