<?php
declare(strict_types=1);

/**
 * Bot Posting REST API
 * Auth: HTTP Basic (username = bot username, password = bot password)
 *
 * POST /api/post       - Create a post
 * POST /api/media      - Upload media attachment (multipart/form-data)
 * DELETE /api/post     - Delete a post (?post_id=)
 * PUT /api/post        - Edit a post (?post_id=)
 * GET /api/posts       - List recent posts for this bot
 * POST /api/follow     - Follow a remote account
 * POST /api/unfollow   - Unfollow
 * POST /api/block      - Block
 * POST /api/unblock    - Unblock
 */

require_once LIB_PATH . '/activity.php';
require_once LIB_PATH . '/autolink.php';
require_once LIB_PATH . '/deliver.php';
require_once LIB_PATH . '/http_sig.php';
require_once LIB_PATH . '/logger.php';
require_once LIB_PATH . '/media_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Authenticate via HTTP Basic
$account = api_authenticate();

$apiPath = $_GET['_api_path'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];

// Route
if ($apiPath === 'post' && $method === 'POST') {
    api_create_post($account);
} elseif ($apiPath === 'post' && $method === 'PUT') {
    api_edit_post($account);
} elseif ($apiPath === 'post' && $method === 'DELETE') {
    api_delete_post($account);
} elseif ($apiPath === 'posts' && $method === 'GET') {
    api_list_posts($account);
} elseif ($apiPath === 'media' && $method === 'POST') {
    api_upload_media($account);
} elseif ($apiPath === 'follow' && $method === 'POST') {
    api_follow($account);
} elseif ($apiPath === 'unfollow' && $method === 'POST') {
    api_unfollow($account);
} elseif ($apiPath === 'block' && $method === 'POST') {
    api_block($account);
} elseif ($apiPath === 'unblock' && $method === 'POST') {
    api_unblock($account);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown endpoint']);
    exit;
}

function api_authenticate(): array {
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW']   ?? '';

    if (empty($user) || empty($pass)) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="ActivityPub Bot API"');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $account = get_account_by_username($user);
    if (!$account || !password_verify($pass, $account['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    return $account;
}

function api_create_post(array $account): void {
    $data = request_json();

    $content    = trim($data['content']         ?? '');
    $cw         = trim($data['content_warning'] ?? '');
    $visibility = $data['visibility']            ?? 'public';
    $inReplyTo  = trim($data['in_reply_to']      ?? '');
    $quoteUrl   = trim($data['quote_url']        ?? '');
    $language   = trim($data['language']         ?? 'en') ?: 'en';
    $mediaIds   = array_filter(array_map('intval', (array)($data['media_ids'] ?? [])));

    if (!in_array($visibility, ['public','unlisted','private','direct'], true)) {
        $visibility = 'public';
    }

    if (empty($content) && empty($mediaIds)) {
        http_response_code(422);
        echo json_encode(['error' => 'content or media_ids required']);
        exit;
    }

    $domain = get_domain();
    $parsed = autolink_content($content, $domain);
    resolve_mentions($parsed['mentions']);

    $activityId = new_activity_id($account['username']);

    db_run(
        "INSERT INTO posts
            (account_id, activity_id, content_raw, content_html, content_warning,
             visibility, in_reply_to_id, quote_url, language)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $account['id'], $activityId, $content, $parsed['html'], $cw,
            $visibility, $inReplyTo, $quoteUrl, $language,
        ]
    );
    $postId = db_last_id();

    foreach ($mediaIds as $mid) {
        db_run(
            "UPDATE media_attachments SET post_id = ? WHERE id = ? AND account_id = ? AND post_id IS NULL",
            [$postId, $mid, $account['id']]
        );
    }
    foreach ($parsed['hashtags'] as $tag) {
        db_run("INSERT INTO hashtags (post_id, tag) VALUES (?, ?)", [$postId, $tag]);
    }
    foreach ($parsed['mentions'] as $m) {
        db_run("INSERT INTO mentions (post_id, actor_uri, username) VALUES (?, ?, ?)", [$postId, $m['actor_uri'], $m['username']]);
    }

    $post        = db_get("SELECT * FROM posts WHERE id = ?", [$postId]);
    $attachments = load_post_attachments($postId);
    $tags        = load_post_tags($postId);
    $note        = build_note($post, $account, $attachments, $tags);
    $create      = build_create($note, $account);

    $extraInboxes = [];
    if ($visibility === 'direct') {
        foreach ($parsed['mentions'] as $m) {
            if (!str_starts_with($m['actor_uri'], 'acct:')) {
                $info  = resolve_actor_inbox($m['actor_uri']);
                $inbox = $info['shared_inbox'] ?: $info['inbox'];
                if ($inbox) $extraInboxes[] = $inbox;
            }
        }
    }

    deliver_to_followers($create, $account, $extraInboxes);

    echo json_encode(['id' => $postId, 'activity_id' => $activityId, 'status' => 'created']);
}

function api_edit_post(array $account): void {
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    $data   = request_json();
    $post   = db_get("SELECT * FROM posts WHERE id = ? AND account_id = ?", [$postId, $account['id']]);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    $content = trim($data['content'] ?? $post['content_raw']);
    $cw      = trim($data['content_warning'] ?? $post['content_warning']);

    $domain = get_domain();
    $parsed = autolink_content($content, $domain);
    resolve_mentions($parsed['mentions']);

    db_run(
        "UPDATE posts SET content_raw = ?, content_html = ?, content_warning = ?, updated_at = datetime('now')
         WHERE id = ?",
        [$content, $parsed['html'], $cw, $postId]
    );

    db_run("DELETE FROM hashtags WHERE post_id = ?", [$postId]);
    db_run("DELETE FROM mentions WHERE post_id = ?", [$postId]);
    foreach ($parsed['hashtags'] as $tag) {
        db_run("INSERT INTO hashtags (post_id, tag) VALUES (?, ?)", [$postId, $tag]);
    }
    foreach ($parsed['mentions'] as $m) {
        db_run("INSERT INTO mentions (post_id, actor_uri, username) VALUES (?, ?, ?)", [$postId, $m['actor_uri'], $m['username']]);
    }

    $updatedPost = db_get("SELECT * FROM posts WHERE id = ?", [$postId]);
    $attachments = load_post_attachments($postId);
    $tags        = load_post_tags($postId);
    $note        = build_note($updatedPost, $account, $attachments, $tags);
    $update      = build_update($note, $account);
    deliver_to_followers($update, $account);

    echo json_encode(['id' => $postId, 'status' => 'updated']);
}

function api_delete_post(array $account): void {
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    $post   = db_get("SELECT * FROM posts WHERE id = ? AND account_id = ?", [$postId, $account['id']]);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    $delete = build_delete($post['activity_id'], $account);
    deliver_to_followers($delete, $account);
    delete_post_media($postId, (int)$account['id']);
    db_run("UPDATE posts SET deleted_at = datetime('now') WHERE id = ?", [$postId]);

    echo json_encode(['id' => $postId, 'status' => 'deleted']);
}

function api_list_posts(array $account): void {
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $posts = db_all(
        "SELECT id, activity_id, content_raw, content_warning, visibility, published_at, updated_at
         FROM posts WHERE account_id = ? AND deleted_at IS NULL ORDER BY published_at DESC LIMIT ?",
        [$account['id'], $limit]
    );
    echo json_encode(['posts' => $posts]);
}

function api_upload_media(array $account): void {
    if (empty($_FILES['file'])) {
        http_response_code(422);
        echo json_encode(['error' => 'No file uploaded (field name: file)']);
        exit;
    }

    $altText = trim($_POST['alt_text'] ?? '');

    try {
        $id  = handle_media_upload($_FILES['file'], (int)$account['id'], $altText);
        $att = db_get("SELECT * FROM media_attachments WHERE id = ?", [$id]);
        echo json_encode(['id' => $id, 'url' => $att['file_url'], 'mime_type' => $att['mime_type']]);
    } catch (RuntimeException $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function api_follow(array $account): void {
    $data      = request_json();
    $targetUri = trim($data['actor_uri'] ?? '');

    if (empty($targetUri)) {
        http_response_code(422);
        echo json_encode(['error' => 'actor_uri required']);
        exit;
    }

    $actor = fetch_remote_actor($targetUri);
    if (!$actor) {
        http_response_code(422);
        echo json_encode(['error' => 'Could not fetch actor']);
        exit;
    }

    $followActivity = build_follow($account, $actor['id']);

    db_run(
        "INSERT OR IGNORE INTO following (account_id, following_uri, following_inbox, shared_inbox, accepted, follow_activity_id) VALUES (?, ?, ?, ?, 0, ?)",
        [$account['id'], $actor['id'], $actor['inbox'] ?? '', $actor['endpoints']['sharedInbox'] ?? '', $followActivity['id']]
    );

    deliver_to_actor($followActivity, $account, $actor['id']);

    echo json_encode(['status' => 'follow_sent', 'target' => $actor['id']]);
}

function api_unfollow(array $account): void {
    $data      = request_json();
    $targetUri = trim($data['actor_uri'] ?? '');

    if (!empty($targetUri)) {
        $undo = build_unfollow($account, $targetUri);
        deliver_to_actor($undo, $account, $targetUri);
        db_run("DELETE FROM following WHERE account_id = ? AND following_uri = ?", [$account['id'], $targetUri]);
    }

    echo json_encode(['status' => 'unfollowed']);
}

function api_block(array $account): void {
    $data      = request_json();
    $targetUri = trim($data['actor_uri'] ?? '');

    if (!empty($targetUri)) {
        db_run("INSERT OR IGNORE INTO blocks (account_id, target_uri) VALUES (?, ?)", [$account['id'], $targetUri]);
        db_run("DELETE FROM followers WHERE account_id = ? AND follower_uri = ?", [$account['id'], $targetUri]);
        $block = build_block($account, $targetUri);
        deliver_to_actor($block, $account, $targetUri);
    }

    echo json_encode(['status' => 'blocked']);
}

function api_unblock(array $account): void {
    $data      = request_json();
    $targetUri = trim($data['actor_uri'] ?? '');

    if (!empty($targetUri)) {
        $unblock = build_unblock($account, $targetUri);
        deliver_to_actor($unblock, $account, $targetUri);
        db_run("DELETE FROM blocks WHERE account_id = ? AND target_uri = ?", [$account['id'], $targetUri]);
    }

    echo json_encode(['status' => 'unblocked']);
}
