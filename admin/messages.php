<?php
require __DIR__ . '/partials/init.php';

if (is_post()) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'read') q('UPDATE messages SET is_read = 1 WHERE id = ?', [$id]);
    if ($act === 'unread') q('UPDATE messages SET is_read = 0 WHERE id = ?', [$id]);
    if ($act === 'delete') { q('DELETE FROM messages WHERE id = ?', [$id]); flash('success', 'Message deleted.'); }
    if ($act === 'readall') { q('UPDATE messages SET is_read = 1'); flash('success', 'All messages marked as read.'); }
    redirect('admin/messages.php');
}

$pg = paginate((int)val('SELECT COUNT(*) FROM messages'), 20, (int)($_GET['page'] ?? 1));
$msgs = rows("SELECT * FROM messages ORDER BY is_read ASC, created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");

$pageTitle = 'Messages';
$active = 'messages';
$topActions = admin_counts()['messages'] ? '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="readall"><button class="btn btn-ghost" type="submit">Mark all read</button></form>' : '';
require __DIR__ . '/partials/top.php';
?>
<?php if (!$msgs): ?>
  <div class="card empty"><h2>Inbox zero</h2><p class="muted">Messages from the Contact page will show up here.</p></div>
<?php else: ?>
  <div class="msg-list">
    <?php foreach ($msgs as $m): $waNum = $m['phone'] ? normalize_phone($m['phone']) : ''; ?>
      <article class="card msg <?= $m['is_read'] ? '' : 'unread' ?>" id="m<?= (int)$m['id'] ?>">
        <header>
          <span class="avatar"><?= e(mb_strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
          <div class="grow">
            <strong><?= e($m['name']) ?></strong><?php if (!$m['is_read']): ?> <span class="dot-new">New</span><?php endif; ?>
            <div class="muted small"><?= e(implode(' · ', array_filter([$m['phone'], $m['email']]))) ?></div>
          </div>
          <span class="muted small nowrap"><?= e(nice_date($m['created_at'])) ?></span>
        </header>
        <p class="msg-body"><?= nl2br(e($m['message'])) ?></p>
        <footer class="row-gap">
          <?php if (preg_match('~^03\d{9}$~', $waNum)): ?><a class="btn btn-sm btn-wa" href="<?= e(wa_link('Hi ' . $m['name'] . '! Thanks for contacting ' . setting('store_name', 'JerseyHour') . '. ', '92' . substr($waNum, 1))) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a><?php endif; ?>
          <?php if ($m['email']): ?><a class="btn btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: your message to ' . setting('store_name', 'JerseyHour')) ?>">Reply by email</a><?php endif; ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>"><button class="btn btn-sm btn-ghost" type="submit">Mark <?= $m['is_read'] ? 'unread' : 'read' ?></button></form>
          <form method="post" class="inline" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="link-danger" type="submit">Delete</button></form>
        </footer>
      </article>
    <?php endforeach; ?>
  </div>
  <?= render_pagination($pg) ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/bottom.php'; ?>
