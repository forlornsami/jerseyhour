<?php
require __DIR__ . '/partials/init.php';

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? row('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
$errors = [];

if (is_post()) {
    csrf_check();
    $act = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'delete') {
        $c = row('SELECT * FROM categories WHERE id = ?', [$id]);
        if ($c) {
            q('UPDATE products SET category_id = NULL WHERE category_id = ?', [$id]);
            q('DELETE FROM categories WHERE id = ?', [$id]);
            delete_upload($c['image']);
            flash('success', 'Category “' . $c['name'] . '” deleted. Its products are now uncategorised.');
        }
        redirect('admin/categories.php');
    }

    if ($act === 'move') {
        $dir = (int)($_POST['dir'] ?? 0);
        $all = rows('SELECT id FROM categories ORDER BY sort_order, name');
        $ids = array_map('intval', array_column($all, 'id'));
        $pos = array_search($id, $ids, true);
        if ($pos !== false && isset($ids[$pos + $dir])) {
            [$ids[$pos], $ids[$pos + $dir]] = [$ids[$pos + $dir], $ids[$pos]];
            foreach ($ids as $i => $cid) q('UPDATE categories SET sort_order = ? WHERE id = ?', [$i, $cid]);
        }
        redirect('admin/categories.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $slugIn = trim((string)($_POST['slug'] ?? ''));
    if (mb_strlen($name) < 2) $errors['name'] = 'Enter a category name.';
    $current = $id ? row('SELECT * FROM categories WHERE id = ?', [$id]) : null;
    $image = $current['image'] ?? null;
    if (!$errors) {
        $files = files_list('image');
        if ($files) {
            try { $new = store_upload($files[0], 'site', 1000); if ($image) delete_upload($image); $image = $new; }
            catch (Throwable $t) { $errors['image'] = $t->getMessage(); }
        }
    }
    if (!$errors) {
        $slug = unique_slug('categories', $slugIn ?: $name, $id);
        if ($current) {
            q('UPDATE categories SET name = ?, slug = ?, description = ?, image = ? WHERE id = ?', [$name, $slug, $desc, $image, $id]);
            flash('success', 'Category updated.');
        } else {
            $order = (int)val('SELECT COALESCE(MAX(sort_order), 0) FROM categories') + 1;
            q('INSERT INTO categories (name, slug, description, image, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$name, $slug, $desc, $image, $order, now()]);
            flash('success', 'Category “' . $name . '” added.');
        }
        redirect('admin/categories.php');
    }
    $edit = ['id' => $id, 'name' => $name, 'slug' => $slugIn, 'description' => $desc, 'image' => $image];
}

$cats = rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY c.sort_order, c.name');
$f = $edit ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'image' => null];

$pageTitle = 'Categories';
$active = 'categories';
require __DIR__ . '/partials/top.php';
?>
<div class="split">
  <section class="card flush">
    <?php if (!$cats): ?>
      <div class="empty"><h2>No categories yet</h2><p class="muted">Create categories like “Club Jerseys” or “Cricket Kits” to organise your store.</p></div>
    <?php else: ?>
    <ul class="cat-list">
      <?php foreach ($cats as $i => $c): ?>
        <li class="<?= $f['id'] == $c['id'] ? 'is-editing' : '' ?>">
          <img src="<?= e(img_url($c['image'])) ?>" alt="" width="48" height="60">
          <div class="grow">
            <strong><?= e($c['name']) ?></strong>
            <span class="muted small"><?= (int)$c['n'] ?> product<?= (int)$c['n'] === 1 ? '' : 's' ?> · /<?= e($c['slug']) ?></span>
          </div>
          <div class="nowrap cat-actions">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="-1"><button class="icon-btn" type="submit" aria-label="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button></form>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="1"><button class="icon-btn" type="submit" aria-label="Move down" <?= $i === count($cats) - 1 ? 'disabled' : '' ?>>↓</button></form>
            <a class="btn btn-sm" href="<?= e(admin_url('categories.php?edit=' . $c['id'])) ?>">Edit</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('products.php?cat=' . $c['id'])) ?>">Products</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card-title"><?= $f['id'] ? 'Edit category' : 'New category' ?></h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="cname">Name</label>
        <input id="cname" name="name" value="<?= e($f['name']) ?>" required placeholder="e.g. Club Jerseys">
        <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="cdesc">Short description <span class="hint">optional</span></label>
        <textarea id="cdesc" name="description" rows="2"><?= e($f['description']) ?></textarea>
      </div>
      <div class="field">
        <label for="cslug">URL handle <span class="hint">optional</span></label>
        <input id="cslug" name="slug" value="<?= e($f['slug']) ?>" placeholder="auto">
      </div>
      <div class="field <?= isset($errors['image']) ? 'has-error' : '' ?>">
        <label for="cimg">Tile image</label>
        <?php if ($f['image']): ?><img class="thumb-preview" src="<?= e(img_url($f['image'])) ?>" alt="" width="80" height="100"><?php endif; ?>
        <input id="cimg" type="file" name="image" accept="image/*">
        <?php if (isset($errors['image'])): ?><p class="field-error"><?= e($errors['image']) ?></p><?php endif; ?>
        <p class="hint">Portrait image, shown on the homepage.</p>
      </div>
      <div class="row-gap">
        <button class="btn btn-primary" type="submit"><?= $f['id'] ? 'Save category' : 'Add category' ?></button>
        <?php if ($f['id']): ?><a class="btn btn-ghost" href="<?= e(admin_url('categories.php')) ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
    <?php if ($f['id']): ?>
      <form method="post" class="danger-inline" data-confirm="Delete this category? Its products will stay, but become uncategorised.">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
        <button class="link-danger" type="submit">Delete category</button>
      </form>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/partials/bottom.php'; ?>
