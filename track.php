<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$no = strtoupper(trim((string)($_REQUEST['o'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$order = null;
$error = '';

if (is_post()) {
    csrf_check();
    $tries = $_SESSION['track_tries'] ?? [];
    $tries = array_filter($tries, fn($t) => $t > time() - 600);
    if (count($tries) >= 10) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $_SESSION['track_tries'] = array_merge($tries, [time()]);
        $o = row('SELECT * FROM orders WHERE order_no = ?', [$no]);
        if ($o && normalize_phone($phone) === $o['phone']) {
            $order = $o;
        } else {
            $error = 'We couldn\'t find an order with that number and phone. Check both and try again.';
        }
    }
}
$items = $order ? rows('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]) : [];

$title = 'Track your order';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container narrow">
    <?= breadcrumbs([['Home', url('')], ['Track order', null]]) ?>
    <h1>Track your order</h1>
    <p class="muted">Enter the order number from your confirmation and the phone number you used at checkout.</p>
  </div>
</section>

<div class="container narrow">
  <form method="post" class="panel track-form">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="grid-2">
      <div class="field"><label for="o">Order number</label><input id="o" name="o" value="<?= e($no) ?>" placeholder="JH260924-1234" required autocapitalize="characters"></div>
      <div class="field"><label for="tphone">Phone number</label><input id="tphone" name="phone" type="tel" value="<?= e($phone) ?>" placeholder="03XX XXXXXXX" required inputmode="tel"></div>
    </div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Track order</button>
  </form>

  <?php if ($order): ?>
    <section class="panel">
      <div class="order-head">
        <div><span class="muted small">Order</span><h2><?= e($order['order_no']) ?></h2><span class="muted small">Placed <?= e(nice_date($order['created_at'])) ?></span></div>
        <span class="pill pill-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span>
      </div>
      <?= order_timeline($order) ?>
      <ul class="mini-lines">
        <?php foreach ($items as $it): ?>
          <li>
            <span class="mini-thumb"><img src="<?= e(img_url($it['image'])) ?>" alt="" width="56" height="70"><span class="mini-qty"><?= (int)$it['qty'] ?></span></span>
            <span class="mini-name"><?= e($it['product_name']) ?><?php if ($it['size']): ?><small>Size <?= e($it['size']) ?></small><?php endif; ?></span>
            <span><?= e(money($it['price'] * $it['qty'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-row sum-total"><span>Total (COD)</span><strong><?= e(money($order['total'])) ?></strong></div>
    </section>
  <?php endif; ?>

  <p class="center muted">Need help? <a href="<?= e(wa_link('Hi! I need help with my order ' . $no)) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></p>
</div>
<div class="spacer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
