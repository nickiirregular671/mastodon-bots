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

$outboxUrl = outbox_url($username);
$page      = isset($_GET['page']);

$total = (int)(db_get(
    "SELECT COUNT(*) as c FROM posts WHERE account_id = ? AND deleted_at IS NULL AND visibility IN ('public','unlisted')",
    [$account['id']]
)['c'] ?? 0);

if (!$page) {
    // Return collection stub
    $col = build_ordered_collection(
        $outboxUrl,
        $total,
        $outboxUrl . '?page=true'
    );
    json_response($col);
}

// Paginated page
$perPage = 20;
$minId   = isset($_GET['min_id']) ? (int)$_GET['min_id'] : null;
$maxId   = isset($_GET['max_id']) ? (int)$_GET['max_id'] : null;

$where  = ["account_id = ?", "deleted_at IS NULL", "visibility IN ('public','unlisted')"];
$params = [$account['id']];

if ($maxId !== null) {
    $where[]  = "id < ?";
    $params[] = $maxId;
}
if ($minId !== null) {
    $where[]  = "id > ?";
    $params[] = $minId;
}

$sql   = "SELECT * FROM posts WHERE " . implode(" AND ", $where)
       . " ORDER BY published_at DESC LIMIT " . $perPage;
$posts = db_all($sql, $params);

$items = [];
foreach ($posts as $post) {
    $attachments = load_post_attachments((int)$post['id']);
    $tags        = load_post_tags((int)$post['id']);
    $note        = build_note($post, $account, $attachments, $tags);
    $items[]     = build_create($note, $account);
}

$pageId = $outboxUrl . '?page=true';
$next   = null;
if (count($posts) === $perPage) {
    $lastId = end($posts)['id'];
    $next   = $outboxUrl . '?page=true&max_id=' . $lastId;
}

$col = build_ordered_collection_page($pageId, $outboxUrl, $items, $next);
json_response($col);
