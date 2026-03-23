<?php
declare(strict_types=1);

require_once LIB_PATH . '/db.php';
require_once LIB_PATH . '/helpers.php';

function admin_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function admin_is_logged_in(): bool {
    admin_session_start();
    return !empty($_SESSION['admin_logged_in']);
}

function admin_require_auth(): void {
    if (!admin_is_logged_in()) {
        header('Location: ' . admin_url('login'));
        exit;
    }
}

function admin_login(string $password): bool {
    $hash = db_setting('admin_password_hash', '');
    if (empty($hash)) {
        // No password set yet — first-time setup
        return false;
    }
    if (password_verify($password, $hash)) {
        admin_session_start();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    return false;
}

function admin_logout(): void {
    admin_session_start();
    session_destroy();
}

function admin_needs_setup(): bool {
    $hash = db_setting('admin_password_hash', '');
    return empty($hash);
}

/**
 * Build an admin URL relative to the current script path.
 * Works in any subdirectory without needing base_url().
 * e.g. admin_url('bots') → /agent-testing/mastodon-bot/admin/bots
 *      admin_url('bots?created=1') → /agent-testing/mastodon-bot/admin/bots?created=1
 */

function admin_url(string $path = ''): string {
    return site_url('admin/' . ($path !== '' ? ltrim($path, '/') : ''));
}
