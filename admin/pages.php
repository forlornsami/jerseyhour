<?php
require __DIR__ . '/partials/init.php';

$slug = (string)($_GET['p'] ?? '');
$page = $slug !== '' ? row('SELECT * FROM pages WHERE slug = ?', [$slug]) : null;

if (is_post()) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    if ($id && mb_strlen($title) >= 2) {
        q('UPDATE pages SET title = ?, content = ?, updated_at = ? WHERE id = ?', [$title, $content, now(), $id]);
        flash('success', 'Page saved.');
    } else {
        flash('error', 'Please enter a page title.');
    }
    $s = val('SELECT slug FROM pages WHERE id = ?', [$id]);
    redirect('admin/pages.php?p=' . rawurlencode((string)$s));
}

$pages = rows('SELECT * FROM pages ORDER BY title');
$pageTitle = $page ? 'Edit page' : 'Pages';
$active = 'pages';
$topActions = $page ? '<a class="btn btn-ghost" href="' . e(url('page.php?p=' . rawurlencode($page['slug']))) . '" target="_blank" rel="noopener">View page ↗</a>' : '';
require __DIR__ . '/partials/top.php';
?>
<?php if (!$page): ?>
  <p class="muted">These information pages are linked in your store's footer.</p>
  <div class="card flush">
    <ul class="cat-list">
      <?php foreach ($pages as $pg): ?>
        <li>
          <div class="grow"><strong><?= e($pg['title']) ?></strong><span class="muted small">/page.php?p=<?= e($pg['slug']) ?> · updated <?= e(nice_date($pg['updated_at'], false)) ?></span></div>
          <a class="btn btn-sm" href="<?= e(admin_url('pages.php?p=' . rawurlencode($pg['slug']))) ?>">Edit</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php else: ?>
  <a class="back-link" href="<?= e(admin_url('pages.php')) ?>">← All pages</a>
  <form method="post" class="card" data-dirty-guard>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
    <div class="field"><label for="ptitle">Title</label><input id="ptitle" name="title" value="<?= e($page['title']) ?>" required></div>
    <div class="field">
      <label for="pcontent">Content</label>
      <textarea id="pcontent" name="content" rows="18" class="mono"><?= e($page['content']) ?></textarea>
      <div class="format-help">
        <strong>Formatting:</strong>
        <span>Blank line = new paragraph</span>
        <span><code>## Heading</code></span>
        <span><code>- item</code> bullet list</span>
        <span><code>**bold**</code></span>
        <span><code>| A | B |</code> table rows</span>
      </div>
    </div>
    <button class="btn btn-primary btn-lg" type="submit">Save page</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/partials/bottom.php'; ?>
