<?php
$pageTitle = 'ActivityPub Bots — ' . get_domain();
require BASE_PATH . '/templates/layout.php';
?>

<h1 class="page-title">Welcome to <?= h(get_domain()) ?></h1>
<p class="page-subtitle">ActivityPub-powered bot server on the Fediverse.</p>

<?php if (empty($accounts)): ?>
<div class="card empty-state">No bots yet.</div>
<?php else: ?>
<div class="bots-grid">
  <?php foreach ($accounts as $acc):
    $avatar = !empty($acc['avatar_path'])
        ? base_url() . '/uploads/avatars/' . $acc['avatar_path']
        : null;
    $postCount = (int)(db_get("SELECT COUNT(*) as c FROM posts WHERE account_id = ? AND deleted_at IS NULL", [$acc['id']])['c'] ?? 0);
    $followerCount = (int)(db_get("SELECT COUNT(*) as c FROM followers WHERE account_id = ? AND accepted = 1", [$acc['id']])['c'] ?? 0);
  ?>
  <?php
    $recentPosts = db_all(
        "SELECT p.id, p.content_html, p.published_at FROM posts p
         WHERE p.account_id = ? AND p.deleted_at IS NULL AND p.visibility = 'public'
         ORDER BY p.published_at DESC LIMIT 3",
        [$acc['id']]
    );
    foreach ($recentPosts as &$rp) {
        $thumb = db_get(
            "SELECT file_url, mime_type FROM media_attachments WHERE post_id = ? AND mime_type LIKE 'image/%' LIMIT 1",
            [$rp['id']]
        );
        $rp['thumb'] = $thumb ?: null;
    }
    unset($rp);
  ?>
  <a href="<?= h(profile_url($acc['username'])) ?>" class="card bot-card-link">
    <div class="bot-card-header">
      <?php if ($avatar): ?>
      <img src="<?= h($avatar) ?>" alt="" class="bot-avatar">
      <?php else: ?>
      <div class="bot-avatar-placeholder">
        <?= strtoupper(substr($acc['username'], 0, 1)) ?>
      </div>
      <?php endif; ?>
      <div>
        <div class="bot-name"><?= h($acc['display_name'] ?: $acc['username']) ?></div>
        <div class="bot-handle">@<?= h($acc['username']) ?>@<?= h($domain) ?></div>
        <div class="bot-stats"><?= $postCount ?> posts · <?= $followerCount ?> followers</div>
      </div>
    </div>
    <?php if (!empty($acc['bio'])): ?>
    <p class="bot-bio"><?= h(mb_substr($acc['bio'], 0, 120)) ?><?= mb_strlen($acc['bio']) > 120 ? '…' : '' ?></p>
    <?php endif; ?>
    <?php if (!empty($recentPosts)): ?>
    <table class="posts-table">
      <?php foreach ($recentPosts as $rp): ?>
      <tr>
        <?php if ($rp['thumb']): ?>
        <td class="col-thumb">
          <img src="<?= h($rp['thumb']['file_url']) ?>" alt="">
        </td>
        <?php endif; ?>
        <td class="col-content">
          <?php $_plain = html_entity_decode(strip_tags($rp['content_html']), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
          <?= h(mb_substr($_plain, 0, 80)) ?><?= mb_strlen($_plain) > 80 ? '…' : '' ?>
        </td>
        <td class="col-time">
          <?= h(date('M j', strtotime($rp['published_at']))) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/templates/layout_end.php'; ?>
