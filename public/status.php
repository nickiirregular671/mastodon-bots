<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';

$username = sanitize_username($_GET['username'] ?? '');
$statusId = $_GET['status_id'] ?? '';

if (empty($username) || empty($statusId)) {
    error_response(404, 'Not found');
}

$account = get_account_by_username($username);
if (!$account) {
    error_response(404, 'Not found');
}

// activity_id is the full URL
$activityId = actor_url($username) . '/statuses/' . $statusId;
$post = db_get(
    "SELECT p.*,
            (SELECT COUNT(*) FROM likes  WHERE post_id = p.id) AS likes_count,
            (SELECT COUNT(*) FROM boosts WHERE post_id = p.id) AS boosts_count,
            (SELECT COUNT(*) FROM quotes WHERE post_id = p.id) AS quotes_count
     FROM posts p
     WHERE p.activity_id = ? AND p.account_id = ? AND p.deleted_at IS NULL",
    [$activityId, $account['id']]
);

if (!$post) {
    error_response(404, 'Not found');
}

// Serve ActivityPub JSON if requested
if (wants_activitypub()) {
    $attachments = load_post_attachments((int)$post['id']);
    $tags        = load_post_tags((int)$post['id']);
    $note        = build_note($post, $account, $attachments, $tags);
    json_response($note);
}

// Redirect /users/… to /@… for browsers
if (!str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/@')) {
    header('Location: ' . profile_url($username) . '/' . rawurlencode($statusId), true, 301);
    exit;
}

// HTML: render post page
$post['attachments'] = load_post_attachments((int)$post['id']);
$post['username']    = $account['username'];
$post['display_name'] = $account['display_name'];
$post['avatar_path'] = $account['avatar_path'];

$pageTitle = ($account['display_name'] ?: $account['username']) . ': ' . mb_substr(strip_tags($post['content_html']), 0, 80);
require BASE_PATH . '/templates/layout.php';

echo '<div class="back-nav"><a href="' . h(profile_url($username)) . '" class="btn btn-secondary btn-sm">← Back to profile</a></div>';
$_h1Text = trim(strtok(html_entity_decode(strip_tags($post['content_html']), ENT_QUOTES | ENT_HTML5, 'UTF-8'), "\n"));
if (empty($_h1Text)) $_h1Text = $account['display_name'] ?: $account['username'];
echo '<h1 class="post-detail-heading">' . h(mb_substr($_h1Text, 0, 120)) . '</h1>';
$isDetailPage = true;
require BASE_PATH . '/templates/post_card.php';

require BASE_PATH . '/templates/layout_end.php';
