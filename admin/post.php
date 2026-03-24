<?php
declare(strict_types=1);

// $adminSegs: ['post','N'], ['post','N','create'], ['post','N','P','edit'], ['post','N','P','delete']
$botId      = isset($adminSegs[1]) && is_numeric($adminSegs[1]) ? (int)$adminSegs[1] : null;
$postId     = isset($adminSegs[2]) && is_numeric($adminSegs[2]) ? (int)$adminSegs[2] : null;
$postAction = $postId ? ($adminSegs[3] ?? '') : ($adminSegs[2] ?? '');

if (!$botId) {
    redirect(admin_url('bots'));
}

$account = get_account_by_id($botId);
if (!$account) {
    redirect(admin_url('bots'));
}

// Create a post
if ($postAction === 'create' && is_post()) {
    csrf_verify();

    $content    = trim($_POST['content'] ?? '');
    $cw         = trim($_POST['content_warning'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    $inReplyTo  = trim($_POST['in_reply_to'] ?? '');
    $quoteUrl   = trim($_POST['quote_url'] ?? '');
    $language   = trim($_POST['language'] ?? 'en') ?: 'en';
    $mediaIds   = array_filter(array_map('intval', (array)($_POST['media_ids'] ?? [])));

    if (!in_array($visibility, ['public','unlisted','private','direct'], true)) {
        $visibility = 'public';
    }

    if (empty($content) && empty($mediaIds)) {
        // Nothing to post — clear any pending draft media and reload
        $pending = db_all(
            "SELECT id FROM media_attachments WHERE account_id = ? AND post_id IS NULL",
            [$account['id']]
        );
        foreach ($pending as $m) {
            delete_media_attachment((int)$m['id'], (int)$account['id']);
        }
        redirect(admin_url('post/' . $botId));
    } else {
        $domain  = get_domain();
        $parsed  = autolink_content($content, $domain);
        resolve_mentions($parsed['mentions']);

        $activityId = new_activity_id($account['username']);

        db_run(
            "INSERT INTO posts
                (account_id, activity_id, content_raw, content_html, content_warning,
                 visibility, in_reply_to_id, quote_url, language)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $account['id'],
                $activityId,
                $content,
                $parsed['html'],
                $cw,
                $visibility,
                $inReplyTo,
                $quoteUrl,
                $language,
            ]
        );
        $newPostId = db_last_id();

        // Attach media
        foreach ($mediaIds as $mid) {
            db_run(
                "UPDATE media_attachments SET post_id = ? WHERE id = ? AND account_id = ? AND post_id IS NULL",
                [$newPostId, $mid, $account['id']]
            );
        }

        // Store hashtags
        foreach ($parsed['hashtags'] as $tag) {
            db_run("INSERT INTO hashtags (post_id, tag) VALUES (?, ?)", [$newPostId, $tag]);
        }

        // Store + resolve mentions
        foreach ($parsed['mentions'] as $m) {
            db_run(
                "INSERT INTO mentions (post_id, actor_uri, username) VALUES (?, ?, ?)",
                [$newPostId, $m['actor_uri'], $m['username']]
            );
        }

        // Build and deliver Create{Note}
        $post        = db_get("SELECT * FROM posts WHERE id = ?", [$newPostId]);
        $attachments = load_post_attachments($newPostId);
        $tags        = load_post_tags($newPostId);
        $note        = build_note($post, $account, $attachments, $tags);
        $create      = build_create($note, $account);

        // For DMs, also add mentioned actors' inboxes
        $extraInboxes = [];
        if ($visibility === 'direct') {
            foreach ($parsed['mentions'] as $m) {
                if (!str_starts_with($m['actor_uri'], 'acct:')) {
                    $info = resolve_actor_inbox($m['actor_uri']);
                    $inbox = $info['shared_inbox'] ?: $info['inbox'];
                    if ($inbox) $extraInboxes[] = $inbox;
                }
            }
        }

        deliver_to_followers($create, $account, $extraInboxes);

        redirect(admin_url('post/' . $botId . '?created=1'));
    }
}

// Edit a post
if ($postAction === 'edit' && $postId && is_post()) {
    csrf_verify();

    $post = db_get("SELECT * FROM posts WHERE id = ? AND account_id = ?", [$postId, $account['id']]);
    if (!$post) redirect(admin_url('post/' . $botId));

    $content = trim($_POST['content'] ?? '');
    $cw      = trim($_POST['content_warning'] ?? '');

    $domain = get_domain();
    $parsed = autolink_content($content, $domain);
    resolve_mentions($parsed['mentions']);

    db_run(
        "UPDATE posts SET content_raw = ?, content_html = ?, content_warning = ?, updated_at = datetime('now')
         WHERE id = ?",
        [$content, $parsed['html'], $cw, $postId]
    );

    // Refresh hashtags and mentions
    db_run("DELETE FROM hashtags WHERE post_id = ?", [$postId]);
    db_run("DELETE FROM mentions WHERE post_id = ?", [$postId]);
    foreach ($parsed['hashtags'] as $tag) {
        db_run("INSERT INTO hashtags (post_id, tag) VALUES (?, ?)", [$postId, $tag]);
    }
    foreach ($parsed['mentions'] as $m) {
        db_run("INSERT INTO mentions (post_id, actor_uri, username) VALUES (?, ?, ?)", [$postId, $m['actor_uri'], $m['username']]);
    }

    // Send Update activity
    $updatedPost = db_get("SELECT * FROM posts WHERE id = ?", [$postId]);
    $attachments = load_post_attachments($postId);
    $tags        = load_post_tags($postId);
    $note        = build_note($updatedPost, $account, $attachments, $tags);
    $update      = build_update($note, $account);
    deliver_to_followers($update, $account);

    redirect(admin_url('post/' . $botId . '?updated=1'));
}

// Delete a post
if ($postAction === 'delete' && $postId && is_post()) {
    csrf_verify();

    $post = db_get("SELECT * FROM posts WHERE id = ? AND account_id = ?", [$postId, $account['id']]);
    if ($post) {
        // Send Delete activity before removing
        $delete = build_delete($post['activity_id'], $account);
        deliver_to_followers($delete, $account);

        // Remove media files
        delete_post_media($postId, (int)$account['id']);

        // Soft delete
        db_run("UPDATE posts SET deleted_at = datetime('now') WHERE id = ?", [$postId]);
    }

    redirect(admin_url('post/' . $botId . '?deleted=1'));
}

// Clear draft (delete all unattached pending media)
if ($postAction === 'clear_draft' && is_post()) {
    csrf_verify();

    $pending = db_all(
        "SELECT id FROM media_attachments WHERE account_id = ? AND post_id IS NULL",
        [$account['id']]
    );
    foreach ($pending as $m) {
        delete_media_attachment((int)$m['id'], (int)$account['id']);
    }

    redirect(admin_url('post/' . $botId));
}

// List posts for this bot
$botPosts = db_all(
    "SELECT * FROM posts WHERE account_id = ? AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 50",
    [$account['id']]
);

foreach ($botPosts as &$p) {
    $p['attachments'] = load_post_attachments((int)$p['id']);
}
unset($p);

$editPost = $postId ? db_get("SELECT * FROM posts WHERE id = ? AND account_id = ?", [$postId, $account['id']]) : null;

// Pending media (uploaded but not yet attached to a post)
$pendingMedia = db_all(
    "SELECT * FROM media_attachments WHERE account_id = ? AND post_id IS NULL ORDER BY created_at DESC",
    [$account['id']]
);

require BASE_PATH . '/templates/admin/post.php';
