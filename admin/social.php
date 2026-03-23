<?php
declare(strict_types=1);

// $adminSegs: ['social','N'], ['social','N','follow'], ['social','N','unfollow'],
//             ['social','N','block'], ['social','N','unblock'],
//             ['social','N','accept_follower'], ['social','N','reject_follower']
$botId  = isset($adminSegs[1]) && is_numeric($adminSegs[1]) ? (int)$adminSegs[1] : null;
$action = $adminSegs[2] ?? '';

if (!$botId) redirect(admin_url('bots'));

$account = get_account_by_id($botId);
if (!$account) redirect(admin_url('bots'));

// Follow a remote account
if ($action === 'follow' && is_post()) {
    csrf_verify();
    $targetUri = trim($_POST['target_uri'] ?? '');
    $errors    = [];

    if (empty($targetUri)) {
        $errors[] = 'Target actor URI is required.';
    } else {
        // Resolve actor
        $actor = fetch_remote_actor($targetUri);
        if (!$actor) {
            $errors[] = 'Could not fetch remote actor at that URI.';
        } elseif (!empty($actor['inbox'])) {
            $inboxUrl    = $actor['inbox'];
            $sharedInbox = $actor['endpoints']['sharedInbox'] ?? '';

            $followActivity = build_follow($account, $actor['id']);

            db_run(
                "INSERT OR IGNORE INTO following
                    (account_id, following_uri, following_inbox, shared_inbox, accepted, follow_activity_id)
                 VALUES (?, ?, ?, ?, 0, ?)",
                [$account['id'], $actor['id'], $inboxUrl, $sharedInbox, $followActivity['id']]
            );

            deliver_to_actor($followActivity, $account, $actor['id']);
        } else {
            $errors[] = 'Remote actor has no inbox.';
        }
    }

    if (!$errors) {
        redirect(admin_url('social/' . $botId . '?followed=1'));
    }
}

// Unfollow a remote account
if ($action === 'unfollow' && is_post()) {
    csrf_verify();
    $targetUri = trim($_POST['target_uri'] ?? '');

    $row = db_get(
        "SELECT * FROM following WHERE account_id = ? AND following_uri = ?",
        [$account['id'], $targetUri]
    );

    if ($row) {
        $undo = build_unfollow($account, $targetUri);
        deliver_to_actor($undo, $account, $targetUri);
        db_run("DELETE FROM following WHERE account_id = ? AND following_uri = ?", [$account['id'], $targetUri]);
    }

    redirect(admin_url('social/' . $botId . '?unfollowed=1'));
}

// Block a remote account
if ($action === 'block' && is_post()) {
    csrf_verify();
    $targetUri = trim($_POST['target_uri'] ?? '');

    if (!empty($targetUri)) {
        db_run(
            "INSERT OR IGNORE INTO blocks (account_id, target_uri) VALUES (?, ?)",
            [$account['id'], $targetUri]
        );
        // Remove them from followers
        db_run(
            "DELETE FROM followers WHERE account_id = ? AND follower_uri = ?",
            [$account['id'], $targetUri]
        );
        // Send Block activity
        $block = build_block($account, $targetUri);
        deliver_to_actor($block, $account, $targetUri);
    }

    redirect(admin_url('social/' . $botId . '?blocked=1'));
}

// Unblock a remote account
if ($action === 'unblock' && is_post()) {
    csrf_verify();
    $targetUri = trim($_POST['target_uri'] ?? '');

    if (!empty($targetUri)) {
        $unblock = build_unblock($account, $targetUri);
        deliver_to_actor($unblock, $account, $targetUri);
        db_run("DELETE FROM blocks WHERE account_id = ? AND target_uri = ?", [$account['id'], $targetUri]);
    }

    redirect(admin_url('social/' . $botId . '?unblocked=1'));
}

// Accept a pending follower
if ($action === 'accept_follower' && is_post()) {
    csrf_verify();
    $followerUri = trim($_POST['follower_uri'] ?? '');

    $follower = db_get(
        "SELECT * FROM followers WHERE account_id = ? AND follower_uri = ?",
        [$account['id'], $followerUri]
    );
    if ($follower && !$follower['accepted']) {
        db_run(
            "UPDATE followers SET accepted = 1 WHERE account_id = ? AND follower_uri = ?",
            [$account['id'], $followerUri]
        );
        $followActivity = ['id' => $follower['follow_activity_id'], 'type' => 'Follow', 'actor' => $followerUri, 'object' => actor_url($account['username'])];
        $accept = build_accept_follow($account, $followActivity);
        deliver_to_actor($accept, $account, $followerUri);
    }
    redirect(admin_url('social/' . $botId . '?accepted=1'));
}

// Reject a pending follower
if ($action === 'reject_follower' && is_post()) {
    csrf_verify();
    $followerUri = trim($_POST['follower_uri'] ?? '');

    $follower = db_get(
        "SELECT * FROM followers WHERE account_id = ? AND follower_uri = ?",
        [$account['id'], $followerUri]
    );
    if ($follower && !$follower['accepted']) {
        $followActivity = ['id' => $follower['follow_activity_id'], 'type' => 'Follow', 'actor' => $followerUri, 'object' => actor_url($account['username'])];
        $reject = build_reject_follow($account, $followActivity);
        deliver_to_actor($reject, $account, $followerUri);
        db_run("DELETE FROM followers WHERE account_id = ? AND follower_uri = ?", [$account['id'], $followerUri]);
    }
    redirect(admin_url('social/' . $botId . '?rejected=1'));
}

$following        = db_all("SELECT * FROM following  WHERE account_id = ? ORDER BY created_at DESC", [$account['id']]);
$pendingFollowers = db_all("SELECT * FROM followers  WHERE account_id = ? AND accepted = 0 ORDER BY created_at DESC", [$account['id']]);
$blocks           = db_all("SELECT * FROM blocks     WHERE account_id = ? ORDER BY created_at DESC", [$account['id']]);

require BASE_PATH . '/templates/admin/social.php';
