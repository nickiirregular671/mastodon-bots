<?php
declare(strict_types=1);

$errors  = [];
$success = '';

if (is_post()) {
    csrf_verify();

    $domain      = trim($_POST['domain'] ?? '');
    $maxLog      = (int)($_POST['max_log_rows'] ?? 10000);
    $logDays     = (int)($_POST['log_retention_days'] ?? 0);
    $maxMedia    = (int)($_POST['media_max_mb'] ?? 10) * 1048576;
    $webhookUrl  = trim($_POST['webhook_url'] ?? '');
    $newPw       = $_POST['admin_password'] ?? '';
    $newPw2      = $_POST['admin_password2'] ?? '';

    if (!empty($newPw)) {
        if (strlen($newPw) < 8)  $errors[] = 'Admin password must be at least 8 characters.';
        if ($newPw !== $newPw2)  $errors[] = 'Passwords do not match.';
    }

    if (empty($domain)) $errors[] = 'Domain is required.';

    if (!empty($webhookUrl) && !preg_match('#^https?://#i', $webhookUrl)) {
        $errors[] = 'Webhook URL must start with http:// or https://.';
    }

    if (!$errors) {
        db_set_setting('domain', $domain);
        db_set_setting('max_log_rows', (string)$maxLog);
        db_set_setting('log_retention_days', (string)$logDays);
        db_set_setting('media_max_bytes', (string)$maxMedia);
        db_set_setting('webhook_url', $webhookUrl);
        if (!empty($newPw)) {
            db_set_setting('admin_password_hash', password_hash($newPw, PASSWORD_BCRYPT));
        }
        $success = 'Settings saved.';
    }
}

$settings = [
    'domain'              => db_setting('domain', 'example.com'),
    'max_log_rows'        => db_setting('max_log_rows', '10000'),
    'log_retention_days'  => db_setting('log_retention_days', '0'),
    'media_max_mb'        => (int)(db_setting('media_max_bytes', '10485760')) / 1048576,
    'webhook_url'         => db_setting('webhook_url', ''),
];

require BASE_PATH . '/templates/admin/settings.php';
