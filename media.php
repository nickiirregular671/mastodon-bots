<?php
declare(strict_types=1);

require_once LIB_PATH . '/media_helper.php';
require_once BASE_PATH . '/admin/auth.php';

// Require admin or bot API auth
if (!admin_is_logged_in()) {
    // Try HTTP Basic auth
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW']   ?? '';
    $account = $user ? get_account_by_username($user) : null;

    if (!$account || !password_verify($pass, $account['password_hash'])) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="ActivityPub Bot"');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    $botId = (int)$account['id'];
} else {
    $botId = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
    if (!$botId) {
        http_response_code(422);
        echo json_encode(['error' => 'bot_id required']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

if (empty($_FILES['file'])) {
    http_response_code(422);
    echo json_encode(['error' => 'No file uploaded (field: file)']);
    exit;
}

$altText = trim($_POST['alt_text'] ?? '');

$redirectTo = trim($_POST['redirect_to'] ?? '');

try {
    $id  = handle_media_upload($_FILES['file'], $botId, $altText);
    $att = db_get("SELECT * FROM media_attachments WHERE id = ?", [$id]);
    if ($redirectTo) {
        header('Location: ' . $redirectTo);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['id' => $id, 'url' => $att['file_url'], 'mime_type' => $att['mime_type'], 'alt_text' => $att['alt_text']]);
} catch (RuntimeException $e) {
    if ($redirectTo) {
        header('Location: ' . $redirectTo . '?upload_error=' . rawurlencode($e->getMessage()));
        exit;
    }
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
