<?php
declare(strict_types=1);

// $adminSegs: ['followers','N']
$botId = isset($adminSegs[1]) && is_numeric($adminSegs[1]) ? (int)$adminSegs[1] : null;
if (!$botId) redirect(admin_url('bots'));

$account = get_account_by_id($botId);
if (!$account) redirect(admin_url('bots'));

$followers = db_all(
    "SELECT * FROM followers WHERE account_id = ? ORDER BY created_at DESC",
    [$account['id']]
);
$following = db_all(
    "SELECT * FROM following WHERE account_id = ? ORDER BY created_at DESC",
    [$account['id']]
);

require BASE_PATH . '/templates/admin/followers.php';
