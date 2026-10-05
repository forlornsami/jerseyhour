<?php
require __DIR__ . '/partials/init.php';

$monthStart = date('Y-m-01 00:00:00');
$todayStart = date('Y-m-d 00:00:00');
$revMonth   = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled' AND created_at >= ?", [$monthStart]);
$ordMonth   = (int)val("SELECT COUNT(*) FROM orders WHERE status <> 'cancelled' AND created_at >= ?", [$monthStart]);
$ordToday   = (int)val('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$todayStart]);
$pending    = admin_counts()['pending'];
$delivered  = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'delivered'");
$products   = rows('SELECT id, name, slug, sizes, images, active FROM products ORDER BY name');
$lowStock   = array_values(array_filter($products, fn($p) => $p['active'] && product_stock($p) <= 5));
usort($lowStock, fn($a, $b) => product_stock($a) <=> product_stock($b));
$activeCount = count(array_filter($products, fn($p) => $p['active']));

// 14-day chart (aggregate in PHP → works on SQLite and MySQL)
$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = ['total' => 0.0, 'count' => 0];
foreach (rows("SELECT total, created_at FROM orders WHERE status <> 'cancelled' AND created_at >= ?", [date('Y-m-d 00:00:00', strtotime('-13 days'))]) as $o) {
    $d = substr($o['created_at'], 0, 10);
    if (isset($days[$d])) { $days[$d]['total'] += (float)$o['total']; $days[$d]['count']++; }
}
$maxDay = max(1, max(array_column($days, 'total')));
$sum14 = array_sum(array_column($days, 'total'));

$recent = rows('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8');
$msgs = rows('SELECT * FROM messages WHERE is_read = 0 ORDER BY created_at DESC LIMIT 3');

$pageTitle = 'Dashboard';
$active = 'dashboard';
$topActions = '<a class="btn btn-primary" href="' . e(admin_url('product-edit.php')) . '">+ Add product</a>';
require __DIR__ . '/partials/top.php';
?>
<p class="greet">Assalam o Alaikum, <strong><?= e($ADMIN['username']) ?></strong> — here's how the store is doing.</p>

<div class="stats">
  <div class="stat stat-accent">
    <span>Sales this month</span>
    <strong><?= e(money($revMonth)) ?></strong>
    <em><?= $ordMonth ?> order<?= $ordMonth === 1 ? '' : 's' ?> · excl. cancelled</em>
  </div>
  <a class="stat" href="<?= e(admin_url('orders.php?status=pending')) ?>">
    <span>Pending orders</span>
    <strong><?= $pending ?></strong>
    <em><?= $pending ? 'Need confirmation →' : 'All caught up' ?></em>
  </a>
  <div class="stat">
    <span>Orders today</span>
    <strong><?= $ordToday ?></strong>
    <em>Delivered all-time: <?= e(money($delivered)) ?></em>
  </div>
  <a class="stat" href="<?= e(admin_url('products.php?stock=low')) ?>">
    <span>Active products</span>
    <strong><?= $activeCount ?></strong>
    <em class="<?= $lowStock ? 'warn' : '' ?>"><?= count($lowStock) ?> low on stock</em>
  </a>
</div>

<div class="dash-grid">
  <section class="card">
    <div class="card-head"><h2>Last 14 days</h2><span class="muted"><?= e(money($sum14)) ?></span></div>
    <div class="chart" role="img" aria-label="Sales over the last 14 days">
      <?php foreach ($days as $d => $v): $h = $v['total'] > 0 ? max(4, round($v['total'] / $maxDay * 100)) : 0; ?>
        <div class="bar-col" title="<?= e(date('D d M', strtotime($d))) ?>: <?= e(money($v['total'])) ?> (<?= $v['count'] ?> orders)">
          <div class="bar-track"><div class="bar" style="height: <?= $h ?>%"></div></div>
          <span><?= date('d', strtotime($d)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Low stock</h2><a href="<?= e(admin_url('products.php?stock=low')) ?>">View all</a></div>
    <?php if (!$lowStock): ?>
      <p class="muted empty-sm">All products are well stocked.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach (array_slice($lowStock, 0, 6) as $p): $s = product_stock($p); ?>
          <li><a href="<?= e(admin_url('product-edit.php?id=' . $p['id'])) ?>">
            <img src="<?= e(product_cover($p)) ?>" alt="" width="36" height="45">
            <span class="grow"><?= e($p['name']) ?></span>
            <span class="stock <?= stock_class($s) ?>"><?= $s <= 0 ? 'Out' : $s . ' left' ?></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="card-head"><h2>Recent orders</h2><a href="<?= e(admin_url('orders.php')) ?>">All orders</a></div>
  <?php if (!$recent): ?>
    <div class="empty-sm"><p class="muted">No orders yet. Share your store link on WhatsApp and Instagram to get your first sale!</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Order</th><th>Customer</th><th>City</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $o): ?>
          <tr class="row-link" data-href="<?= e(admin_url('order.php?id=' . $o['id'])) ?>">
            <td><a href="<?= e(admin_url('order.php?id=' . $o['id'])) ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
            <td><?= e($o['customer_name']) ?><br><span class="muted small"><?= e($o['phone']) ?></span></td>
            <td><?= e($o['city']) ?></td>
            <td><strong><?= e(money($o['total'])) ?></strong></td>
            <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td class="muted small"><?= e(nice_date($o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php if ($msgs): ?>
<section class="card">
  <div class="card-head"><h2>New messages</h2><a href="<?= e(admin_url('messages.php')) ?>">Inbox</a></div>
  <ul class="list">
    <?php foreach ($msgs as $m): ?>
      <li><a href="<?= e(admin_url('messages.php#m' . $m['id'])) ?>"><span class="avatar"><?= e(mb_strtoupper(mb_substr($m['name'], 0, 1))) ?></span><span class="grow"><strong><?= e($m['name']) ?></strong><br><span class="muted small"><?= e(excerpt($m['message'], 90)) ?></span></span><span class="muted small"><?= e(nice_date($m['created_at'], false)) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . '/partials/bottom.php'; ?>
