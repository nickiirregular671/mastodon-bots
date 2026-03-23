<?php
declare(strict_types=1);

const ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
    'audio/mpeg' => 'mp3',
    'audio/ogg'  => 'ogg',
    'audio/wav'  => 'wav',
];

/**
 * Validate, move, and record an uploaded file.
 * Returns the new media_attachment row ID.
 */
function handle_media_upload(array $file, int $accountId, string $altText = ''): int {
    $maxBytes = (int)(db_setting('media_max_bytes') ?? 10485760);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code: ' . $file['error']);
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File too large (max ' . number_format($maxBytes / 1048576, 1) . ' MB)');
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset(ALLOWED_MIME_TYPES[$mimeType])) {
        throw new RuntimeException('Unsupported file type: ' . $mimeType);
    }

    $ext    = ALLOWED_MIME_TYPES[$mimeType];
    $uuid   = generate_uuid();
    $year   = date('Y');
    $month  = date('m');
    $relDir = "{$accountId}/{$year}/{$month}";
    $absDir = BASE_PATH . '/uploads/media/' . $relDir;

    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }

    $filename = $uuid . '.' . $ext;
    $absPath  = $absDir . '/' . $filename;
    $relPath  = $relDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
        throw new RuntimeException('Failed to move uploaded file');
    }

    $fileUrl  = base_url() . '/uploads/media/' . $relPath;
    $metadata = extract_media_metadata($absPath, $mimeType);

    db_run(
        "INSERT INTO media_attachments
            (account_id, file_path, file_url, mime_type, alt_text, blurhash, width, height, duration, file_size)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $accountId,
            $relPath,
            $fileUrl,
            $mimeType,
            $altText,
            $metadata['blurhash'],
            $metadata['width'],
            $metadata['height'],
            $metadata['duration'],
            $file['size'],
        ]
    );

    return db_last_id();
}

/**
 * Handle avatar or header upload for a bot account.
 * Returns the relative path stored in accounts table.
 */
function handle_avatar_upload(array $file, string $username, string $type = 'avatar'): string {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error: ' . $file['error']);
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowed[$mimeType])) {
        throw new RuntimeException('Only image files are allowed for avatars/headers');
    }

    $ext    = $allowed[$mimeType];
    $dir    = ($type === 'header') ? 'headers' : 'avatars';
    $absDir = BASE_PATH . '/uploads/' . $dir . '/' . $username;

    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }

    $filename = $type . '.' . $ext;
    $absPath  = $absDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
        throw new RuntimeException('Failed to move file');
    }

    return $username . '/' . $filename;
}

/**
 * Extract image dimensions for images; stub for video/audio duration.
 */
function extract_media_metadata(string $absPath, string $mimeType): array {
    $meta = ['width' => 0, 'height' => 0, 'duration' => 0.0, 'blurhash' => ''];

    if (str_starts_with($mimeType, 'image/')) {
        $size = @getimagesize($absPath);
        if ($size) {
            $meta['width']  = (int)$size[0];
            $meta['height'] = (int)$size[1];
        }
    }

    // Duration for video/audio would require ffprobe or getID3
    // Stub: returns 0 unless extended later

    return $meta;
}

/**
 * Delete a media attachment file from disk and remove from DB.
 */
function delete_media_attachment(int $attachmentId, int $accountId): bool {
    $att = db_get(
        "SELECT * FROM media_attachments WHERE id = ? AND account_id = ?",
        [$attachmentId, $accountId]
    );
    if (!$att) return false;

    $absPath = BASE_PATH . '/uploads/media/' . $att['file_path'];
    if (file_exists($absPath)) {
        unlink($absPath);
    }

    db_run("DELETE FROM media_attachments WHERE id = ?", [$attachmentId]);
    return true;
}

/**
 * Delete all media attachments for a post (files + DB rows).
 */
function delete_post_media(int $postId, int $accountId): void {
    $attachments = db_all(
        "SELECT * FROM media_attachments WHERE post_id = ? AND account_id = ?",
        [$postId, $accountId]
    );
    foreach ($attachments as $att) {
        $absPath = BASE_PATH . '/uploads/media/' . $att['file_path'];
        if (file_exists($absPath)) {
            unlink($absPath);
        }
    }
    db_run("DELETE FROM media_attachments WHERE post_id = ?", [$postId]);
}
