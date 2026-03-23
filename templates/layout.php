<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="<?= isset($robotsMeta) ? h($robotsMeta) : 'index,follow' ?>">
<title><?= isset($pageTitle) ? h($pageTitle) : 'ActivityPub Bots' ?></title>
<link rel="stylesheet" href="<?= h(site_url('public/css/app.css')) ?>">
<?php if (!empty($headTags)) echo $headTags; ?>
</head>
<body>
<header>
  <div class="container">
    <p class="site-name"><a href="<?= h(base_url()) ?>">ActivityPub Bots</a></p>
    <nav>
      <a href="<?= h(base_url()) ?>">Home</a>
      <?php foreach (get_all_accounts() as $_navBot): ?>
      <a href="<?= h(profile_url($_navBot['username'])) ?>" rel="me">@<?= h($_navBot['username']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<div class="container">

