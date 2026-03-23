<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — ActivityPub Bot Server</title>
<link rel="stylesheet" href="<?= h(site_url('public/css/auth.css')) ?>">
</head>
<body>
<div class="card card--wide">
  <h1>🤖 First-time Setup</h1>
  <p>Configure your ActivityPub bot server.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert"><?= implode('<br>', array_map('h', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= h(admin_url('setup')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="form-group">
      <label for="domain">Domain (e.g. bots.example.com)</label>
      <input type="text" id="domain" name="domain" required
             placeholder="bots.example.com"
             value="<?= h($_POST['domain'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="password">Admin Password (min 8 chars)</label>
      <input type="password" id="password" name="password" required minlength="8">
    </div>

    <div class="form-group">
      <label for="password2">Confirm Password</label>
      <input type="password" id="password2" name="password2" required minlength="8">
    </div>

    <button type="submit" class="btn">Set Up Server</button>
  </form>
</div>
</body>
</html>
