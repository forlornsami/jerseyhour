<?php
require __DIR__ . '/partials/init.php';

// ---- Actions ----
if (is_post()) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    $p = row('SELECT * FROM products WHERE id = ?', [$id]);
    if ($p) {
        if ($act === 'toggle') {
            q('UPDATE products SET active = ?, updated_at = ? WHERE id = ?', [$p['active'] ? 0 : 1, now(), $id]);
            flash('success', '“' . $p['name'] . '” is now ' . ($p['active'] ? 'hidden from' : 'visible in') . ' the store.');
        } elseif ($act === 'feature') {
            q('UPDATE products SET featured = ?, updated_at = ? WHERE id = ?', [$p['featured'] ? 0 : 1, now(), $id]);
            flash('success', '“' . $p['name'] . '” ' . ($p['featured'] ? 'removed from' : 'added to') . ' Fan favourites.');
        } elseif ($act === 'duplicate') {
            $slug = unique_slug('products', $p['slug'] . '-copy');
            q('INSERT INTO products (category_id, name, slug, description, price, compare_price, sizes, images, badge, featured, active, views, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?)',
              [$p['category_id'], $p['name'] . ' (copy)', $slug, $p['description'], $p['price'], $p['compare_price'], $p['sizes'], '[]', $p['badge'], now(), now()]);
            $newId = (int)db()->lastInsertId();
            flash('success', 'Duplicated. Add photos and publish when ready.');
            redirect('admin/product-edit.php?id=' . $newId);
        } elseif ($act === 'delete') {
            $used = (int)val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]);
            foreach (product_images($p) as $img) {
                // keep images referenced by existing orders
                if (!val('SELECT COUNT(*) FROM order_items WHERE image = ?', [$img])) delete_upload($img);
            }
            q('UPDATE order_items SET product_id = NULL WHERE product_id = ?', [$id]);
            q('DELETE FROM products WHERE id = ?', [$id]);
            flash('success', '“' . $p['name'] . '” was deleted.' . ($used ? ' Past orders still show it.' : ''));
        }
    }
    $back = $_POST['back'] ?? '';
    redirect($back && strpos($back, BASE . '/admin/') === 0 ? $back : 'admin/products.php');
}

// ---- Listing ----
$qStr   = trim((string)($_GET['q'] ?? ''));
$cat    = (int)($_GET['cat'] ?? 0);
$status = (string)($_GET['status'] ?? '');
$stockF = (string)($_GET['stock'] ?? '');

$where = ['1=1']; $args = [];
if ($qStr !== '') { $where[] = '(p.name LIKE ? OR p.badge LIKE ?)'; $args[] = "%$qStr%"; $args[] = "%$qStr%"; }
if ($cat) { $where[] = 'p.category_id = ?'; $args[] = $cat; }
if ($status === 'active') $where[] = 'p.active = 1';
if ($status === 'hidden') $where[] = 'p.active = 0';
if ($status === 'featured') $where[] = 'p.featured = 1';

$list = rows('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC', $args);
if ($stockF === 'low') $list = array_values(array_filter($list, fn($p) => product_stock($p) <= 5));
if ($stockF === 'out') $list = array_values(array_filter($list, fn($p) => product_stock($p) <= 0));
$pg = paginate(count($list), 20, (int)($_GET['page'] ?? 1));
$items = array_slice($list, $pg['offset'], $pg['per']);
$cats = rows('SELECT id, name FROM categories ORDER BY sort_order, name');
$total = (int)val('SELECT COUNT(*) FROM products');
$selfUrl = $_SERVER['REQUEST_URI'] ?? admin_url('products.php');

$pageTitle = 'Products';
$active = 'products';
$topActions = '<a class="btn btn-primary" href="' . e(admin_url('product-edit.php')) . '">+ Add product</a>';
require __DIR__ . '/partials/top.php';
?>

<form class="toolbar" method="get">
  <div class="search-box"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($qStr) ?>" placeholder="Search products…"></div>
  <select name="cat" onchange="this.form.submit()">
    <option value="">All categories</option>
    <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="status" onchange="this.form.submit()">
    <option value="">Any status</option>
    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Visible</option>
    <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Hidden</option>
    <option value="featured" <?= $status === 'featured' ? 'selected' : '' ?>>Fan favourites</option>
  </select>
  <select name="stock" onchange="this.form.submit()">
    <option value="">Any stock</option>
    <option value="low" <?= $stockF === 'low' ? 'selected' : '' ?>>Low (≤ 5)</option>
    <option value="out" <?= $stockF === 'out' ? 'selected' : '' ?>>Out of stock</option>
  </select>
  <?php if ($qStr || $cat || $status || $stockF): ?><a class="btn btn-ghost" href="<?= e(admin_url('products.php')) ?>">Reset</a><?php endif; ?>
</form>

<?php if (!$total): ?>
  <div class="card empty">
    <div class="empty-art"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3 3 6l2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/></svg></div>
    <h2>Add your first jersey</h2>
    <p class="muted">Upload photos, set sizes and stock, and it goes live instantly.</p>
    <a class="btn btn-primary btn-lg" href="<?= e(admin_url('product-edit.php')) ?>">+ Add product</a>
  </div>
<?php elseif (!$items): ?>
  <div class="card empty"><h2>No matches</h2><p class="muted">Try another search or reset the filters.</p></div>
<?php else: ?>
<div class="card flush">
  <div class="table-wrap">
    <table class="table products-table">
      <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $p): $s = product_stock($p); $sizes = product_sizes($p); ?>
        <tr>
          <td>
            <a class="prod-cell" href="<?= e(admin_url('product-edit.php?id=' . $p['id'])) ?>">
              <img src="<?= e(product_cover($p)) ?>" alt="" width="44" height="55" loading="lazy">
              <span><strong><?= e($p['name']) ?></strong>
                <span class="muted small"><?php if ($p['badge']): ?><?= e($p['badge']) ?> · <?php endif; ?><?= (int)$p['views'] ?> views<?php if ($p['featured']): ?> · ★ Favourite<?php endif; ?></span></span>
            </a>
          </td>
          <td><?= e($p['category_name'] ?: '—') ?></td>
          <td><strong><?= e(money($p['price'])) ?></strong><?php if ((float)$p['compare_price'] > (float)$p['price']): ?><br><s class="muted small"><?= e(money($p['compare_price'])) ?></s><?php endif; ?></td>
          <td>
            <span class="stock <?= stock_class($s) ?>"><?= $s <= 0 ? 'Out of stock' : $s . ' in stock' ?></span>
            <?php if ($sizes): ?><div class="size-mini"><?php foreach ($sizes as $k => $v): ?><span class="<?= (int)$v <= 0 ? 'zero' : '' ?>" title="<?= e($k) ?>: <?= (int)$v ?>"><?= e($k) ?> <b><?= (int)$v ?></b></span><?php endforeach; ?></div><?php endif; ?>
          </td>
          <td>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="back" value="<?= e($selfUrl) ?>">
              <button type="submit" class="switch <?= $p['active'] ? 'on' : '' ?>" aria-label="<?= $p['active'] ? 'Hide from store' : 'Show in store' ?>" title="<?= $p['active'] ? 'Visible — click to hide' : 'Hidden — click to show' ?>"><span></span></button>
            </form>
          </td>
          <td class="right nowrap">
            <a class="btn btn-sm" href="<?= e(admin_url('product-edit.php?id=' . $p['id'])) ?>">Edit</a>
            <details class="menu">
              <summary class="btn btn-sm btn-ghost" aria-label="More actions">•••</summary>
              <div class="menu-pop">
                <a href="<?= e(product_url($p)) ?>" target="_blank" rel="noopener">View in store</a>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="feature"><input type="hidden" name="back" value="<?= e($selfUrl) ?>"><button type="submit"><?= $p['featured'] ? 'Remove from favourites' : 'Add to favourites' ?></button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="duplicate"><button type="submit">Duplicate</button></form>
                <form method="post" data-confirm="Delete “<?= e($p['name']) ?>”? This can't be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="danger">Delete</button></form>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?= render_pagination($pg) ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/bottom.php'; ?>
