<?php
require BASE_PATH . '/templates/layout.php';
?>

<h1 class="tag-title"><?= $pageTitle ?></h1>

<?php if (empty($posts)): ?>
<div class="card empty-state">No posts with this hashtag.</div>
<?php else: ?>
  <?php foreach ($posts as $post):
    require BASE_PATH . '/templates/post_card.php';
  endforeach; ?>
<?php endif; ?>

<?php require BASE_PATH . '/templates/layout_end.php'; ?>
