<?php
require __DIR__ . '/partials/init.php';

$errors = [];
if (is_post()) {
    csrf_check();
    $me = row('SELECT * FROM admins WHERE id = ?', [$ADMIN['id']]);
    $current = (string)($_POST['current'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $new = (string)($_POST['new'] ?? '');
    $new2 = (string)($_POST['new2'] ?? '');
    if (!password_verify($current, $me['password_hash'])) $errors['current'] = 'Your current password is incorrect.';
    if (!preg_match('~^[A-Za-z0-9_.-]{3,40}$~', $username)) $errors['username'] = '3–40 characters: letters, numbers, dot, dash, underscore.';
    if ($new !== '' && strlen($new) < 8) $errors['new'] = 'New password must be at least 8 characters.';
    if ($new !== $new2) $errors['new2'] = 'Passwords do not match.';
    if (!$errors && val('SELECT COUNT(*) FROM admins WHERE username = ? AND id <> ?', [$username, $me['id']])) $errors['username'] = 'That username is taken.';
    if (!$errors) {
        q('UPDATE admins SET username = ? WHERE id = ?', [$username, $me['id']]);
        if ($new !== '') q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        session_regenerate_id(true);
        flash('success', $new !== '' ? 'Account updated and password changed.' : 'Account updated.');
        redirect('admin/account.php');
    }
}
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

$pageTitle = 'Your account';
$active = 'account';
require __DIR__ . '/partials/top.php';
?>
<form method="post" class="card narrow-card" autocomplete="off">
  <?= csrf_field() ?>
  <h2 class="card-title">Sign-in details</h2>
  <div class="field <?= isset($errors['username']) ? 'has-error' : '' ?>"><label for="u">Username</label><input id="u" name="username" value="<?= e($_POST['username'] ?? $ADMIN['username']) ?>" autocomplete="username"><?= $err('username') ?></div>
  <div class="grid-2">
    <div class="field <?= isset($errors['new']) ? 'has-error' : '' ?>"><label for="n">New password <span class="hint">leave blank to keep</span></label><input id="n" type="password" name="new" autocomplete="new-password"><?= $err('new') ?></div>
    <div class="field <?= isset($errors['new2']) ? 'has-error' : '' ?>"><label for="n2">Confirm new password</label><input id="n2" type="password" name="new2" autocomplete="new-password"><?= $err('new2') ?></div>
  </div>
  <hr>
  <div class="field <?= isset($errors['current']) ? 'has-error' : '' ?>"><label for="c">Current password <span class="hint">required to save changes</span></label><input id="c" type="password" name="current" required autocomplete="current-password"><?= $err('current') ?></div>
  <button class="btn btn-primary" type="submit">Save account</button>
</form>
<?php require __DIR__ . '/partials/bottom.php'; ?>
