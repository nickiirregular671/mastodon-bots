<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>Admin</title>
<link rel="stylesheet" href="<?= h(site_url('public/css/admin.css')) ?>">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <p class="sidebar-title">🤖 Bot Admin</p>
    <nav>
      <a href="<?= h(admin_url()) ?>">Dashboard</a>
      <a href="<?= h(admin_url('bots')) ?>">Bots</a>
      <a href="<?= h(admin_url('logs')) ?>">Logs</a>
      <a href="<?= h(admin_url('settings')) ?>">Settings</a>
      <hr class="hr-divider">
      <a href="<?= h(site_url()) ?>">← Public site</a>
      <a href="<?= h(admin_url('logout')) ?>" class="logout-link">Logout</a>
    </nav>
  </aside>
  <main class="main<?= isset($extraMainClass) ? ' ' . h($extraMainClass) : '' ?>">

