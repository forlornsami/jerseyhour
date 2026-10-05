<?php
/**
 * Emergency password reset.
 * Only works while an empty file named "reset.allow" exists in the data/ folder
 * (create it with your hosting File Manager). The file is deleted after use.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

$flag = APP_ROOT . '/data/reset.allow';
$allowed = is_file($flag);
$done = false;
$error = '';

if ($allowed && is_post()) {
    csrf_check();
    $u = trim((string)($_POST['username'] ?? ''));
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (!preg_match('~^[A-Za-z0-9_.-]{3,40}$~', $u)) $error = 'Username must be 3–40 characters.';
    elseif (strlen($p1) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($p1 !== $p2) $error = 'Passwords do not match.';
    else {
        $id = (int)val('SELECT id FROM admins ORDER BY id LIMIT 1');
        if ($id) q('UPDATE admins SET username = ?, password_hash = ? WHERE id = ?', [$u, password_hash($p1, PASSWORD_DEFAULT), $id]);
        else q('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)', [$u, password_hash($p1, PASSWORD_DEFAULT), now()]);
        q('DELETE FROM login_attempts');
        @unlink($flag);
        $done = true;
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Reset admin password</title><?= brand_head() ?><link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>"></head>
<body class="auth-body"><main class="auth-card">
  <div class="auth-brand"><?= logo_html() ?></div>
  <?php if ($done): ?>
    <h1>Password updated</h1>
    <p class="muted">The reset file was removed<?= is_file($flag) ? ' — <strong>please delete data/reset.allow manually</strong>' : '' ?>.</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('admin/login.php')) ?>">Sign in</a>
  <?php elseif (!$allowed): ?>
    <h1>Reset locked</h1>
    <p class="muted">To reset the admin password, use your hosting File Manager to create an empty file named <code>reset.allow</code> inside the <code>data</code> folder, then reload this page.</p>
  <?php else: ?>
    <h1>Set a new admin login</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="field"><label for="u">Username</label><input id="u" name="username" value="admin" required></div>
      <div class="field"><label for="p">New password</label><input id="p" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
      <div class="field"><label for="p2">Confirm password</label><input id="p2" type="password" name="password2" minlength="8" required autocomplete="new-password"></div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Save new login</button>
    </form>
  <?php endif; ?>
</main></body></html>
