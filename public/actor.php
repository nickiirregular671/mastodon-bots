<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';

$username = sanitize_username($_GET['username'] ?? '');
if (empty($username)) {
    error_response(400, 'Missing username');
}

$account = get_account_by_username($username);
if (!$account) {
    http_response_code(404);
    // Return 404 as AP JSON or HTML depending on client
    if (wants_activitypub()) {
        json_response(['error' => 'Not found'], 404);
    }
    require BASE_PATH . '/templates/layout.php';
    exit;
}

// Serve ActivityPub JSON if requested
if (wants_activitypub()) {
    $actor = build_actor($account);
    json_response($actor);
}

// Redirect /users/{username} HTML requests to /@{username}
if (($_GET['_route'] ?? '') !== 'profile') {
    header('Location: ' . profile_url($username), true, 301);
    exit;
}

// Otherwise serve HTML profile
$posts = db_all(
    "SELECT p.*,
            (SELECT COUNT(*) FROM likes  WHERE post_id = p.id) AS likes_count,
            (SELECT COUNT(*) FROM boosts WHERE post_id = p.id) AS boosts_count,
            (SELECT COUNT(*) FROM quotes WHERE post_id = p.id) AS quotes_count,
            a.username, a.display_name, a.avatar_path
     FROM posts p
     JOIN accounts a ON a.id = p.account_id
     WHERE p.account_id = ? AND p.deleted_at IS NULL AND p.visibility IN ('public','unlisted')
     ORDER BY p.published_at DESC
     LIMIT 20",
    [$account['id']]
);

foreach ($posts as &$post) {
    $post['attachments'] = load_post_attachments((int)$post['id']);
}
unset($post);

require BASE_PATH . '/templates/profile.php';
