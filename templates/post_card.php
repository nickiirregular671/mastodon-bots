<?php
// Usage: include this template with $post array available
// $post must have: id, activity_id, content_html, content_warning, visibility,
//                  published_at, attachments[], username, display_name, avatar_path
$_statusId  = basename($post['activity_id'] ?? '');
$_postUrl   = profile_url($post['username'] ?? '') . '/' . rawurlencode($_statusId);
$_isDetail  = isset($isDetailPage) && $isDetailPage;
$avatar = !empty($post['avatar_path'])
    ? base_url() . '/uploads/avatars/' . $post['avatar_path']
    : 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="%231877f2"/><text x="50%" y="55%" font-size="20" text-anchor="middle" fill="white">' . strtoupper(substr($post['username'] ?? 'B', 0, 1)) . '</text></svg>';
?>
<article class="card post-card" id="post-<?= h((string)$post['id']) ?>">
  <div class="post-body">
    <img src="<?= h($avatar) ?>" alt="<?= h($post['display_name'] ?? $post['username'] ?? '') ?>"
         class="post-avatar">
    <div class="post-content">
      <div class="post-meta">
        <a href="<?= h(profile_url($post['username'] ?? '')) ?>" class="post-author">
          <?= h($post['display_name'] ?? $post['username'] ?? '') ?>
        </a>
        <span class="post-handle">@<?= h($post['username'] ?? '') ?>@<?= h(get_domain()) ?></span>
        <a href="<?= h($_postUrl) ?>" class="post-time">
          <?= h(date('M j, Y g:i A', strtotime($post['published_at'] ?? 'now'))) ?>
        </a>
      </div>

      <?php if (!empty($post['content_warning'])): ?>
      <details class="cw-details">
        <summary class="cw-summary">
          ⚠️ <?= h($post['content_warning']) ?>
        </summary>
        <div class="post-content-body"><?= $post['content_html'] ?></div>
      </details>
      <?php else: ?>
      <div class="post-content-body"><?= $post['content_html'] ?></div>
      <?php endif; ?>

      <?php if (!empty($post['in_reply_to_id'])): ?>
      <div class="post-reply-indicator">
        ↩ Reply to <a href="<?= h($post['in_reply_to_id']) ?>"><?= h($post['in_reply_to_id']) ?></a>
      </div>
      <?php endif; ?>

      <?php if (!empty($post['quote_url'])): ?>
      <div class="post-quote-box">
        💬 <a href="<?= h($post['quote_url']) ?>"><?= h($post['quote_url']) ?></a>
      </div>
      <?php endif; ?>

      <?php if (!empty($post['attachments'])): ?>
      <div class="media-grid">
        <?php foreach ($post['attachments'] as $att): ?>
          <?php if (str_starts_with($att['mime_type'], 'image/')): ?>
            <figure class="media-figure">
              <a href="<?= h($att['file_url']) ?>" target="_blank" rel="noopener">
                <img src="<?= h($att['file_url']) ?>"
                     alt="<?= h($att['alt_text']) ?>"
                     class="media-img">
              </a>
              <?php if (!empty($att['alt_text'])): ?>
              <figcaption class="media-caption"><?= h($att['alt_text']) ?></figcaption>
              <?php endif; ?>
            </figure>
          <?php elseif (str_starts_with($att['mime_type'], 'video/')): ?>
            <video controls class="media-video">
              <source src="<?= h($att['file_url']) ?>" type="<?= h($att['mime_type']) ?>">
            </video>
          <?php elseif (str_starts_with($att['mime_type'], 'audio/')): ?>
            <audio controls class="media-audio">
              <source src="<?= h($att['file_url']) ?>" type="<?= h($att['mime_type']) ?>">
            </audio>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="post-footer">
        <?php if ($post['visibility'] !== 'public'): ?>
        <span class="tag"><?= h($post['visibility']) ?></span>
        <?php endif; ?>
        ❤️ <?= (int)($post['likes_count'] ?? 0) ?>
        &nbsp;🔁 <?= (int)($post['boosts_count'] ?? 0) ?>
        &nbsp;💬 <?= (int)($post['quotes_count'] ?? 0) ?>
      </div>
    </div>
  </div>
</article>
