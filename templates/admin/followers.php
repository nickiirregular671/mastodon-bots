<?php
$pageTitle = 'Followers/Following — @' . $account['username'];
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1>Followers & Following — @<?= h($account['username']) ?></h1>
<div class="section-nav">
  <a href="<?= h(admin_url('post/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">← Posts</a>
</div>

<div class="grid-2col">
<div class="card">
  <h2>Followers (<?= count($followers) ?>)</h2>
  <?php if (empty($followers)): ?>
  <p class="text-muted">No followers yet.</p>
  <?php else: ?>
  <div class="scrollable-list">
  <table>
    <thead><tr><th>Actor</th><th>Status</th><th>Since</th></tr></thead>
    <tbody>
    <?php foreach ($followers as $f): ?>
    <tr>
      <td class="text-sm break-all"><a href="<?= h($f['follower_uri']) ?>" target="_blank"><?= h($f['follower_uri']) ?></a></td>
      <td><?= $f['accepted'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
      <td class="text-xxs text-muted"><?= h(date('M j', strtotime($f['created_at']))) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Following (<?= count($following) ?>)</h2>
  <?php if (empty($following)): ?>
  <p class="text-muted">Not following anyone.</p>
  <?php else: ?>
  <div class="scrollable-list">
  <table>
    <thead><tr><th>Actor</th><th>Status</th><th>Since</th></tr></thead>
    <tbody>
    <?php foreach ($following as $f): ?>
    <tr>
      <td class="text-sm break-all"><a href="<?= h($f['following_uri']) ?>" target="_blank"><?= h($f['following_uri']) ?></a></td>
      <td><?= $f['accepted'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
      <td class="text-xxs text-muted"><?= h(date('M j', strtotime($f['created_at']))) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
</div>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
