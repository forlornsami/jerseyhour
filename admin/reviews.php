<?php
require __DIR__ . '/partials/init.php';
reviews_ready();

if (is_post()) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'hide') { q('UPDATE reviews SET approved = 0 WHERE id = ?', [$id]); flash('success', 'Review hidden from the store.'); }
    if ($act === 'show') { q('UPDATE reviews SET approved = 1 WHERE id = ?', [$id]); flash('success', 'Review is now visible in the store.'); }
    if ($act === 'delete') { q('DELETE FROM reviews WHERE id = ?', [$id]); flash('success', 'Review deleted.'); }
    $back = $_POST['back'] ?? '';
    redirect($back && strpos($back, BASE . '/admin/') === 0 ? $back : 'admin/reviews.php');
}

// Opening this page marks all reviews as seen (clears the sidebar counter).
$_newSince = setting('reviews_seen_at', '1970-01-01 00:00:00');
save_setting('reviews_seen_at', now());

$status = (string)($_GET['status'] ?? '');
$stars  = (int)($_GET['stars'] ?? 0);
$qStr   = trim((string)($_GET['q'] ?? ''));

$where = ['1=1']; $args = [];
if ($status === 'visible') $where[] = 'r.approved = 1';
if ($status === 'hidden')  $where[] = 'r.approved = 0';
if ($stars >= 1 && $stars <= 5) { $where[] = 'r.rating = ?'; $args[] = $stars; }
if ($qStr !== '') { $where[] = '(p.name LIKE ? OR r.name LIKE ? OR r.comment LIKE ?)'; array_push($args, "%$qStr%", "%$qStr%", "%$qStr%"); }
$w = implode(' AND ', $where);

$pg = paginate((int)val("SELECT COUNT(*) FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE $w", $args), 25, (int)($_GET['page'] ?? 1));
$list = rows("SELECT r.*, p.name AS product_name, p.slug AS product_slug, p.images AS product_images
              FROM reviews r LEFT JOIN products p ON p.id = r.product_id
              WHERE $w ORDER BY r.created_at DESC, r.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $args);
$tot = row('SELECT COUNT(*) AS n, AVG(rating) AS a, SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS v FROM reviews');
$counts = ['' => (int)$tot['n'], 'visible' => (int)$tot['v'], 'hidden' => (int)$tot['n'] - (int)$tot['v']];
$selfUrl = $_SERVER['REQUEST_URI'] ?? admin_url('reviews.php');
$tabUrl = fn($s) => admin_url('reviews.php' . ($s ? '?status=' . $s : ''));

$pageTitle = 'Reviews';
$active = 'reviews';
require __DIR__ . '/partials/top.php';
?>
<div class="stats">
  <div class="stat stat-accent"><span>Average rating</span><strong><?= $tot['n'] ? e(number_format((float)$tot['a'], 1)) : '—' ?></strong><em><?= $tot['n'] ? stars_html((float)$tot['a'], 15) : 'No reviews yet' ?></em></div>
  <div class="stat"><span>Total reviews</span><strong><?= (int)$tot['n'] ?></strong><em>All products</em></div>
  <div class="stat"><span>Visible</span><strong><?= $counts['visible'] ?></strong><em>Shown in the store</em></div>
  <div class="stat"><span>Hidden</span><strong><?= $counts['hidden'] ?></strong><em>Only you can see these</em></div>
</div>

<nav class="tabs" aria-label="Filter reviews">
  <?php foreach (['' => 'All', 'visible' => 'Visible', 'hidden' => 'Hidden'] as $k => $label): ?>
    <a href="<?= e($tabUrl($k)) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($label) ?> <em><?= $counts[$k] ?></em></a>
  <?php endforeach; ?>
</nav>

<form class="toolbar" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div class="search-box"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($qStr) ?>" placeholder="Search product, name or text…"></div>
  <select name="stars" onchange="this.form.submit()">
    <option value="">Any rating</option>
    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $stars === $i ? 'selected' : '' ?>><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
  </select>
  <?php if ($qStr || $stars): ?><a class="btn btn-ghost" href="<?= e($tabUrl($status)) ?>">Reset</a><?php endif; ?>
</form>

<?php if (!$tot['n']): ?>
  <div class="card empty"><h2>No reviews yet</h2><p class="muted">When customers rate a jersey on its product page, the review shows up here.</p></div>
<?php elseif (!$list): ?>
  <div class="card empty"><h2>No matches</h2><p class="muted">Try another filter.</p></div>
<?php else: ?>
<div class="card flush">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Product</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($list as $r): $imgs = json_decode((string)$r['product_images'], true) ?: []; ?>
        <tr class="<?= $r['created_at'] > $_newSince ? 'is-new' : '' ?>">
          <td>
            <?php if ($r['product_name'] !== null): ?>
              <a class="prod-cell" href="<?= e(url('product.php?s=' . rawurlencode($r['product_slug']))) ?>#reviews" target="_blank" rel="noopener">
                <img src="<?= e(img_url($imgs[0] ?? null)) ?>" alt="" width="44" height="55" loading="lazy">
                <span><strong><?= e($r['product_name']) ?></strong></span>
              </a>
            <?php else: ?><span class="muted">Deleted product</span><?php endif; ?>
          </td>
          <td class="nowrap"><?= stars_html((float)$r['rating'], 16) ?></td>
          <td><strong><?= e($r['name']) ?></strong><?php if ($r['created_at'] > $_newSince): ?> <span class="dot-new">New</span><?php endif; ?>
            <?php if (trim((string)$r['comment']) !== ''): ?><div class="rv-comment muted"><?= e($r['comment']) ?></div><?php endif; ?></td>
          <td class="nowrap muted small"><?= e(nice_date($r['created_at'])) ?></td>
          <td><span class="pill <?= $r['approved'] ? 'pill-visible' : 'pill-hidden' ?>"><?= $r['approved'] ? 'Visible' : 'Hidden' ?></span></td>
          <td class="right nowrap">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($selfUrl) ?>"><input type="hidden" name="action" value="<?= $r['approved'] ? 'hide' : 'show' ?>"><button class="btn btn-sm" type="submit"><?= $r['approved'] ? 'Hide' : 'Show' ?></button></form>
            <form method="post" class="inline" data-confirm="Delete this review? This can't be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($selfUrl) ?>"><input type="hidden" name="action" value="delete"><button class="link-danger" type="submit">Delete</button></form>
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
