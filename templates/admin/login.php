<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="<?= h(site_url('public/css/auth.css')) ?>">
</head>
<body>
<div class="card">
  <h1>🔐 Admin Login</h1>

  <?php if (!empty($loginError)): ?>
  <div class="alert"><?= h($loginError) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= h(admin_url('login')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label for="password">Admin Password</label>
      <input type="password" id="password" name="password" required autofocus>
    </div>
    <button type="submit" class="btn">Login</button>
  </form>
</div>
</body>
</html>
