<?php
declare(strict_types=1);

// $adminSegs: ['move','N'], ['move','N','move_away'], ['move','N','add_known_as'],
//             ['move','N','remove_known_as'], ['move','N','clear_moved_to']
$botId  = isset($adminSegs[1]) && is_numeric($adminSegs[1]) ? (int)$adminSegs[1] : null;
$action = $adminSegs[2] ?? '';

if (!$botId) redirect(admin_url('bots'));

$account = get_account_by_id($botId);
if (!$account) redirect(admin_url('bots'));

$errors   = [];
$success  = '';

// Set movedTo on this account (move away from this bot)
if ($action === 'move_away' && is_post()) {
    csrf_verify();
    $newAccountUri = trim($_POST['new_account_uri'] ?? '');

    if (empty($newAccountUri)) {
        $errors[] = 'New account URI is required.';
    } else {
        // Verify the target exists and lists this account in alsoKnownAs
        $targetActor = fetch_remote_actor($newAccountUri);
        if (!$targetActor) {
            $errors[] = 'Could not fetch remote actor at that URI.';
        } else {
            $alsoKnownAs = $targetActor['alsoKnownAs'] ?? [];
            if (!is_array($alsoKnownAs)) $alsoKnownAs = [$alsoKnownAs];
            $ourUri = actor_url($account['username']);

            if (!in_array($ourUri, $alsoKnownAs, true)) {
                $errors[] = 'The target account does not list this account in alsoKnownAs. Add it there first.';
            }
        }

        if (!$errors) {
            db_run("UPDATE accounts SET moved_to = ? WHERE id = ?", [$newAccountUri, $botId]);

            // Send Move activity to all followers
            $move = build_move($account, $newAccountUri);
            deliver_to_followers($move, $account);

            $success = 'Move activity sent to all followers. Update movedTo set to ' . $newAccountUri;
            $account = get_account_by_id($botId); // refresh
        }
    }
}

// Set alsoKnownAs (receive followers from another account)
if ($action === 'add_known_as' && is_post()) {
    csrf_verify();
    $oldAccountUri = trim($_POST['old_account_uri'] ?? '');

    if (empty($oldAccountUri)) {
        $errors[] = 'Old account handle or URI is required.';
    } else {
        // Accept handle like @user@domain or user@domain — resolve via WebFinger
        if (!str_starts_with($oldAccountUri, 'http')) {
            require_once LIB_PATH . '/autolink.php';
            $handle = ltrim($oldAccountUri, '@');
            [$uname, $udom] = array_pad(explode('@', $handle, 2), 2, '');
            if ($uname && $udom) {
                $resolved = webfinger_lookup($uname, $udom);
                if ($resolved) {
                    $oldAccountUri = $resolved;
                } else {
                    $errors[] = 'Could not resolve handle via WebFinger. Check the handle and try again.';
                }
            } else {
                $errors[] = 'Invalid handle format. Use @user@domain or a full actor URI.';
            }
        }
        if (!$errors) {
            $remoteActor = fetch_remote_actor($oldAccountUri);
            if (!$remoteActor) {
                $errors[] = 'Could not fetch remote actor. Make sure the handle/URI is correct and the remote server is reachable.';
            }
        }
    }

    if (!$errors) {
        // Use the canonical actor id from the fetched actor, not the typed URL
        $canonicalUri = $remoteActor['id'] ?? $oldAccountUri;
        $current = json_decode($account['also_known_as'] ?? '[]', true) ?? [];
        if (!in_array($canonicalUri, $current, true)) {
            $current[] = $canonicalUri;
            db_run("UPDATE accounts SET also_known_as = ? WHERE id = ?", [json_encode($current), $botId]);
            $account = get_account_by_id($botId);
            $success = 'Added ' . $canonicalUri . ' to alsoKnownAs. Remote actor verified reachable.';
        } else {
            $success = $canonicalUri . ' is already in alsoKnownAs.';
        }
    }
}

// Remove a URI from alsoKnownAs
if ($action === 'remove_known_as' && is_post()) {
    csrf_verify();
    $removeUri = trim($_POST['remove_uri'] ?? '');
    $current = json_decode($account['also_known_as'] ?? '[]', true) ?? [];
    $current = array_values(array_filter($current, fn($u) => $u !== $removeUri));
    db_run("UPDATE accounts SET also_known_as = ? WHERE id = ?", [json_encode($current), $botId]);
    $account = get_account_by_id($botId);
    $success = 'Removed from alsoKnownAs.';
}

// Build migration warnings
$migrationWarnings = [];
if ($account['manually_approves_followers']) {
    $migrationWarnings[] = 'This bot requires manual follower approval. Incoming migrated followers will get stuck as pending and will NOT be migrated automatically. Disable "Require follower approval" in Bot Settings before the old server triggers the Move.';
}

// Clear movedTo
if ($action === 'clear_moved_to' && is_post()) {
    csrf_verify();
    db_run("UPDATE accounts SET moved_to = '' WHERE id = ?", [$botId]);
    $account = get_account_by_id($botId);
    $success = 'Cleared movedTo.';
}

require BASE_PATH . '/templates/admin/move.php';
