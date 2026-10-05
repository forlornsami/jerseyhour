<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

if (admin()) redirect('admin/index.php');

$error = '';
$username = trim((string)($_POST['username'] ?? ''));
$ip = client_ip();

if (is_post()) {
    csrf_check();
    $since = date('Y-m-d H:i:s', time() - 900);
    q('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $fails = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$ip, $since]);
    if ($fails >= 6) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $a = row('SELECT * FROM admins WHERE username = ?', [$username]);
        if ($a && password_verify((string)($_POST['password'] ?? ''), $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['admin_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
            if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $a['id']]);
            }
            $to = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            if ($to && strpos($to, BASE . '/admin/') === 0 && strpos($to, 'login.php') === false) redirect($to);
            redirect('admin/index.php');
        }
        q('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)', [$ip, now()]);
        usleep(400000);
        $left = 5 - $fails;
        $error = 'Wrong username or password.' . ($left <= 2 && $left > 0 ? " $left attempt" . ($left === 1 ? '' : 's') . ' left.' : '');
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · <?= e(setting('store_name', 'JerseyHour')) ?> Admin</title>
<?= brand_head() ?>
<link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-card">
  <div class="auth-brand"><?= logo_html() ?></div>
  <h1>Welcome back</h1>
  <p class="muted">Sign in to manage products and orders.</p>
  <?php foreach (flashes() as $f): ?><div class="alert alert-warn"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="pw-wrap">
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button type="button" class="pw-toggle" onclick="var i=document.getElementById('password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'Show':'Hide'">Show</button>
      </div>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
  </form>
  <p class="auth-foot"><a href="<?= e(url('')) ?>">← Back to store</a></p>
</main>
</body>
</html>
