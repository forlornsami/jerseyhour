<?php
require __DIR__ . '/partials/init.php';

$status = (string)($_GET['status'] ?? '');
$qStr = trim((string)($_GET['q'] ?? ''));
$statuses = order_statuses();

$where = ['1=1']; $args = [];
if (isset($statuses[$status])) { $where[] = 'status = ?'; $args[] = $status; }
if ($qStr !== '') {
    $where[] = '(order_no LIKE ? OR customer_name LIKE ? OR phone LIKE ? OR city LIKE ?)';
    $like = '%' . $qStr . '%';
    $phoneLike = '%' . preg_replace('~\D~', '', $qStr) . '%';
    array_push($args, $like, $like, preg_replace('~\D~', '', $qStr) !== '' ? $phoneLike : $like, $like);
}
$w = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM orders WHERE $w", $args);
$pg = paginate($total, 25, (int)($_GET['page'] ?? 1));
$list = rows("SELECT o.*, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) AS items FROM orders o WHERE $w ORDER BY o.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $args);

$tabCounts = ['' => (int)val('SELECT COUNT(*) FROM orders')];
foreach (rows('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) $tabCounts[$r['status']] = (int)$r['n'];

$tabUrl = function ($s) use ($qStr) { $qs = array_filter(['status' => $s, 'q' => $qStr]); return admin_url('orders.php' . ($qs ? '?' . http_build_query($qs) : '')); };

$pageTitle = 'Orders';
$active = 'orders';
require __DIR__ . '/partials/top.php';
?>
<nav class="tabs" aria-label="Filter by status">
  <a href="<?= e($tabUrl('')) ?>" class="<?= $status === '' ? 'active' : '' ?>">All <em><?= $tabCounts[''] ?></em></a>
  <?php foreach ($statuses as $k => $label): ?>
    <a href="<?= e($tabUrl($k)) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($label) ?> <em><?= $tabCounts[$k] ?? 0 ?></em></a>
  <?php endforeach; ?>
</nav>

<form class="toolbar" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div class="search-box grow"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($qStr) ?>" placeholder="Search order #, name, phone or city…"></div>
  <button class="btn" type="submit">Search</button>
</form>

<?php if (!$list): ?>
  <div class="card empty">
    <h2><?= $qStr || $status ? 'No orders match' : 'No orders yet' ?></h2>
    <p class="muted"><?= $qStr || $status ? 'Try a different search or status.' : 'New orders from your store will appear here instantly.' ?></p>
  </div>
<?php else: ?>
<div class="card flush">
  <div class="table-wrap">
    <table class="table orders-table">
      <thead><tr><th>Order</th><th>Customer</th><th>City</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
      <tbody>
        <?php foreach ($list as $o): $href = admin_url('order.php?id=' . $o['id']); ?>
          <tr class="row-link <?= $o['status'] === 'pending' ? 'is-new' : '' ?>" data-href="<?= e($href) ?>">
            <td><a href="<?= e($href) ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
            <td><?= e($o['customer_name']) ?><br><span class="muted small"><?= e($o['phone']) ?></span></td>
            <td><?= e($o['city']) ?></td>
            <td><?= (int)$o['items'] ?></td>
            <td><strong><?= e(money($o['total'])) ?></strong></td>
            <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td class="muted small nowrap"><?= e(nice_date($o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?= render_pagination($pg) ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/bottom.php'; ?>
