<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';

$username = sanitize_username($_GET['username'] ?? '');
if (empty($username)) {
    error_response(400, 'Missing username');
}

$account = get_account_by_username($username);
if (!$account) {
    error_response(404, 'Account not found');
}

$followersUrl = followers_url($username);
$page         = isset($_GET['page']);

$total = (int)(db_get(
    "SELECT COUNT(*) as c FROM followers WHERE account_id = ? AND accepted = 1",
    [$account['id']]
)['c'] ?? 0);

if (!$page) {
    $col = build_ordered_collection($followersUrl, $total, $followersUrl . '?page=true');
    json_response($col);
}

$perPage = 40;
$offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$rows  = db_all(
    "SELECT follower_uri FROM followers WHERE account_id = ? AND accepted = 1
     ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$account['id'], $perPage, $offset]
);
$items = array_column($rows, 'follower_uri');

$pageId = $followersUrl . '?page=true&offset=' . $offset;
$next   = null;
if (count($rows) === $perPage) {
    $next = $followersUrl . '?page=true&offset=' . ($offset + $perPage);
}

$col = build_ordered_collection_page($pageId, $followersUrl, $items, $next);
json_response($col);
