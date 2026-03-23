<?php
$pageTitle = 'Dashboard';
require BASE_PATH . '/templates/admin/layout.php';

$accounts    = get_all_accounts();
$totalPosts  = (int)(db_get("SELECT COUNT(*) as c FROM posts WHERE deleted_at IS NULL")['c'] ?? 0);
$totalLog    = (int)(db_get("SELECT COUNT(*) as c FROM activities_log")['c'] ?? 0);
$failedLog   = (int)(db_get("SELECT COUNT(*) as c FROM activities_log WHERE status = 'failed'")['c'] ?? 0);
?>

<h1>Dashboard</h1>

<div class="stats-grid">
  <div class="card stat-card">
    <div class="stat-value"><?= count($accounts) ?></div>
    <div class="stat-label">Bots</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value"><?= $totalPosts ?></div>
    <div class="stat-label">Total Posts</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value <?= $failedLog > 0 ? 'stat-value--danger' : 'stat-value--success' ?>"><?= $failedLog ?></div>
    <div class="stat-label">Failed Deliveries</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value"><?= $totalLog ?></div>
    <div class="stat-label">Log Entries</div>
  </div>
</div>

<h2>Bots</h2>
<?php if (empty($accounts)): ?>
<div class="card">
  No bots yet. <a href="<?= h(admin_url('bots/create')) ?>">Create your first bot →</a>
</div>
<?php else: ?>
<div class="card table-no-padding">
  <table>
    <thead><tr><th>Username</th><th>Display Name</th><th>Posts</th><th>Followers</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $acc):
      $pc = (int)(db_get("SELECT COUNT(*) as c FROM posts WHERE account_id = ? AND deleted_at IS NULL", [$acc['id']])['c'] ?? 0);
      $fc = (int)(db_get("SELECT COUNT(*) as c FROM followers WHERE account_id = ? AND accepted = 1", [$acc['id']])['c'] ?? 0);
    ?>
    <tr>
      <td><a href="<?= h(profile_url($acc['username'])) ?>" target="_blank">@<?= h($acc['username']) ?></a></td>
      <td><?= h($acc['display_name'] ?: '—') ?></td>
      <td><?= $pc ?></td>
      <td><?= $fc ?></td>
      <td>
        <a href="<?= h(admin_url('post/' . $acc['id'])) ?>" class="btn btn-primary btn-sm">Post</a>
        <a href="<?= h(admin_url('bots/' . $acc['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Edit</a>
        <a href="<?= h(admin_url('social/' . $acc['id'])) ?>" class="btn btn-secondary btn-sm">Social</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="section-actions">
  <a href="<?= h(admin_url('bots/create')) ?>" class="btn btn-primary">+ Create New Bot</a>
  <a href="<?= h(admin_url('logs')) ?>" class="btn btn-secondary">View Logs</a>
</div>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
