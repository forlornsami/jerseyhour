<?php
require __DIR__ . '/partials/init.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('error', 'Product not found.'); redirect('admin/products.php'); }

$isNew = !$p;
$errors = [];
$form = $p ?: [
    'name' => '', 'slug' => '', 'category_id' => (int)($_GET['cat'] ?? 0), 'description' => '', 'price' => '', 'compare_price' => '',
    'badge' => '', 'featured' => 0, 'active' => 1, 'sizes' => json_encode(array_fill_keys(default_sizes(), 0)), 'images' => '[]',
];
$sizes = product_sizes($form);
$images = product_images($form);

if (is_post()) {
    csrf_check();
    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['slug'] = trim((string)($_POST['slug'] ?? ''));
    $form['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['price'] = trim((string)($_POST['price'] ?? ''));
    $form['compare_price'] = trim((string)($_POST['compare_price'] ?? ''));
    $form['badge'] = mb_substr(trim((string)($_POST['badge'] ?? '')), 0, 30);
    $form['featured'] = !empty($_POST['featured']) ? 1 : 0;
    $form['active'] = !empty($_POST['active']) ? 1 : 0;

    // sizes
    $sizes = [];
    foreach ((array)($_POST['size_name'] ?? []) as $i => $sn) {
        $sn = mb_strtoupper(trim((string)$sn));
        if ($sn === '') continue;
        $sizes[mb_substr($sn, 0, 12)] = max(0, (int)($_POST['size_stock'][$i] ?? 0));
    }

    if (mb_strlen($form['name']) < 2) $errors['name'] = 'Enter a product name.';
    if (!is_numeric($form['price']) || (float)$form['price'] <= 0) $errors['price'] = 'Enter a price greater than 0.';
    if ($form['compare_price'] !== '' && (!is_numeric($form['compare_price']) || (float)$form['compare_price'] < 0)) $errors['compare_price'] = 'Enter a valid number or leave empty.';
    if (!$sizes) $errors['sizes'] = 'Add at least one size (use "One size" for free-size items).';

    // images: keep (ordered) + new uploads
    $keep = array_values(array_filter((array)($_POST['keep_images'] ?? []), fn($x) => in_array($x, $images, true)));
    $uploaded = [];
    foreach (files_list('new_images') as $f) {
        try { $uploaded[] = store_upload($f, 'products'); }
        catch (Throwable $t) { $errors['images'] = $f['name'] . ': ' . $t->getMessage(); }
    }
    $newImages = array_slice(array_merge($keep, $uploaded), 0, 10);

    if ($errors) {
        foreach ($uploaded as $u) delete_upload($u); // roll back uploads
    } else {
        $slug = unique_slug('products', $form['slug'] !== '' ? $form['slug'] : $form['name'], (int)$id);
        $cmp = $form['compare_price'] !== '' && (float)$form['compare_price'] > 0 ? (float)$form['compare_price'] : null;
        $vals = [$form['category_id'], $form['name'], $slug, $form['description'], (float)$form['price'], $cmp, json_encode($sizes), json_encode($newImages), $form['badge'] ?: null, $form['featured'], $form['active'], now()];
        if ($isNew) {
            q('INSERT INTO products (category_id, name, slug, description, price, compare_price, sizes, images, badge, featured, active, updated_at, views, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)', array_merge($vals, [now()]));
            $id = (int)db()->lastInsertId();
            flash('success', 'Product created' . ($form['active'] ? ' and live in your store.' : ' (hidden).'));
        } else {
            q('UPDATE products SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, compare_price = ?, sizes = ?, images = ?, badge = ?, featured = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($vals, [$id]));
            foreach (array_diff($images, $newImages) as $gone) {
                if (!val('SELECT COUNT(*) FROM order_items WHERE image = ?', [$gone])) delete_upload($gone);
            }
            flash('success', 'Changes saved.');
        }
        redirect('admin/product-edit.php?id=' . $id);
    }
    $form['sizes'] = json_encode($sizes);
}

$cats = rows('SELECT id, name FROM categories ORDER BY sort_order, name');
$totalStock = array_sum($sizes);
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

$pageTitle = $isNew ? 'Add product' : 'Edit product';
$active = 'products';
$topActions = !$isNew ? '<a class="btn btn-ghost" href="' . e(product_url($p)) . '" target="_blank" rel="noopener">View in store ↗</a>' : '';
require __DIR__ . '/partials/top.php';
?>
<a class="back-link" href="<?= e(admin_url('products.php')) ?>">← All products</a>

<?php if ($errors): ?><div class="alert alert-error">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="edit-grid" data-dirty-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="edit-main">
    <section class="card">
      <h2 class="card-title">Details</h2>
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name">Product name</label>
        <input id="name" name="name" value="<?= e($form['name']) ?>" required placeholder="e.g. Royal Stripe Home Jersey 25/26" data-slug-source>
        <?= $err('name') ?>
      </div>
      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="8" placeholder="Fabric, fit, features…"><?= e($form['description']) ?></textarea>
        <p class="hint">Tips: leave a blank line between paragraphs · start lines with <code>-</code> for bullet points · wrap text in <code>**double stars**</code> for bold.</p>
      </div>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">Photos</h2><span class="muted small">Up to 10 · first photo is the cover · drag to reorder</span></div>
      <?php if (isset($errors['images'])): ?><div class="alert alert-error"><?= e($errors['images']) ?></div><?php endif; ?>
      <div class="img-grid" data-img-grid>
        <?php foreach ($images as $img): ?>
          <div class="img-item" draggable="true">
            <img src="<?= e(img_url($img)) ?>" alt="">
            <input type="hidden" name="keep_images[]" value="<?= e($img) ?>">
            <button type="button" class="img-remove" data-img-remove aria-label="Remove photo">×</button>
          </div>
        <?php endforeach; ?>
        <label class="img-drop" data-img-drop>
          <input type="file" name="new_images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-img-input>
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          <span>Add photos</span>
          <small>or drop files here</small>
        </label>
      </div>
      <p class="hint">JPG, PNG or WEBP up to 8 MB each. Large photos are resized automatically. Portrait photos (4:5) look best.</p>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">Sizes &amp; stock</h2><span class="muted small">Total: <strong data-stock-total><?= (int)$totalStock ?></strong></span></div>
      <?= $err('sizes') ?>
      <div class="size-table" data-size-table>
        <div class="size-row size-row-head"><span>Size</span><span>In stock</span><span></span></div>
        <?php foreach ($sizes as $sn => $qty): ?>
          <div class="size-row">
            <input name="size_name[]" value="<?= e($sn) ?>" aria-label="Size name" maxlength="12">
            <div class="stepper"><button type="button" data-st="-1" aria-label="Decrease">−</button><input type="number" name="size_stock[]" value="<?= (int)$qty ?>" min="0" inputmode="numeric" aria-label="Stock for <?= e($sn) ?>"><button type="button" data-st="1" aria-label="Increase">+</button></div>
            <button type="button" class="icon-btn" data-size-remove aria-label="Remove size">×</button>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="row-gap">
        <button type="button" class="btn btn-sm" data-size-add>+ Add size</button>
        <button type="button" class="btn btn-sm btn-ghost" data-size-fill>Set all to…</button>
      </div>
    </section>
  </div>

  <aside class="edit-side">
    <section class="card">
      <h2 class="card-title">Visibility</h2>
      <label class="switch-row"><input type="checkbox" name="active" value="1" <?= $form['active'] ? 'checked' : '' ?>><span><strong>Visible in store</strong><small>Customers can see and buy it</small></span></label>
      <label class="switch-row"><input type="checkbox" name="featured" value="1" <?= $form['featured'] ? 'checked' : '' ?>><span><strong>Fan favourite</strong><small>Show on the homepage</small></span></label>
    </section>

    <section class="card">
      <h2 class="card-title">Pricing</h2>
      <div class="field <?= isset($errors['price']) ? 'has-error' : '' ?>">
        <label for="price">Price (<?= e(setting('currency_symbol', 'Rs.')) ?>)</label>
        <input id="price" name="price" type="number" min="0" step="1" inputmode="numeric" value="<?= e($form['price'] !== '' ? (float)$form['price'] : '') ?>" required>
        <?= $err('price') ?>
      </div>
      <div class="field <?= isset($errors['compare_price']) ? 'has-error' : '' ?>">
        <label for="compare_price">Compare-at price <span class="hint">optional</span></label>
        <input id="compare_price" name="compare_price" type="number" min="0" step="1" inputmode="numeric" value="<?= e($form['compare_price'] !== '' && $form['compare_price'] !== null ? (float)$form['compare_price'] : '') ?>">
        <?= $err('compare_price') ?: '<p class="hint">Shows a strikethrough price and a “-%” sale badge.</p>' ?>
      </div>
    </section>

    <section class="card">
      <h2 class="card-title">Organise</h2>
      <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
          <option value="">— None —</option>
          <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$form['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="badge">Badge <span class="hint">optional</span></label>
        <input id="badge" name="badge" value="<?= e($form['badge']) ?>" list="badgeList" maxlength="30" placeholder="New, Hot, Player Edition…">
        <datalist id="badgeList"><option value="New"><option value="Hot"><option value="Player Edition"><option value="Retro"><option value="Limited"><option value="Sale"></datalist>
      </div>
      <div class="field">
        <label for="slug">URL handle</label>
        <input id="slug" name="slug" value="<?= e($form['slug']) ?>" placeholder="auto-generated" data-slug-target>
        <p class="hint">jerseyhour.com/product.php?s=<span data-slug-preview><?= e($form['slug'] ?: '…') ?></span></p>
      </div>
    </section>

    <div class="sticky-save">
      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= $isNew ? 'Create product' : 'Save changes' ?></button>
      <?php if (!$isNew): ?><p class="muted small center">Last updated <?= e(nice_date($p['updated_at'])) ?></p><?php endif; ?>
    </div>
  </aside>
</form>

<?php if (!$isNew): ?>
<form method="post" action="<?= e(admin_url('products.php')) ?>" class="danger-zone" data-confirm="Delete “<?= e($p['name']) ?>”? This can't be undone.">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="delete">
  <div><strong>Delete product</strong><p class="muted small">Removes it from the store. Past orders keep their details.</p></div>
  <button class="btn btn-danger" type="submit">Delete</button>
</form>
<?php endif; ?>

<template id="sizeRowTpl">
  <div class="size-row">
    <input name="size_name[]" value="" aria-label="Size name" maxlength="12" placeholder="e.g. 3XL">
    <div class="stepper"><button type="button" data-st="-1" aria-label="Decrease">−</button><input type="number" name="size_stock[]" value="0" min="0" inputmode="numeric" aria-label="Stock"><button type="button" data-st="1" aria-label="Increase">+</button></div>
    <button type="button" class="icon-btn" data-size-remove aria-label="Remove size">×</button>
  </div>
</template>

<?php require __DIR__ . '/partials/bottom.php'; ?>
