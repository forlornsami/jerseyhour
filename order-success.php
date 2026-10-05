<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$no = (string)($_GET['o'] ?? '');
$mine = in_array($no, $_SESSION['my_orders'] ?? [], true);
$o = $mine ? row('SELECT * FROM orders WHERE order_no = ?', [$no]) : null;
if (!$o) redirect('track.php');
$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
$store = setting('store_name', 'JerseyHour');
$firstName = explode(' ', trim($o['customer_name']))[0];

$title = 'Order placed';
$bodyClass = 'success-page';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head page-head-sm">
  <div class="container">
    <ol class="steps" aria-label="Checkout progress">
      <li class="done">Cart</li><li class="done">Details</li><li class="current" aria-current="step">Confirmation</li>
    </ol>
  </div>
</section>

<div class="container narrow">
  <div class="success-hero">
    <div class="success-mark"><?= icon('check', 34) ?></div>
    <h1>Thank you, <?= e($firstName) ?>!</h1>
    <p class="muted">Your order has been placed. We'll call or WhatsApp you on <strong><?= e($o['phone']) ?></strong> to confirm it before dispatch.</p>
    <div class="order-no">
      <span>Order number</span>
      <strong data-copy-text><?= e($o['order_no']) ?></strong>
      <button type="button" class="link-btn" data-copy="<?= e($o['order_no']) ?>">Copy</button>
    </div>
  </div>

  <div class="success-grid">
    <section class="panel">
      <h2 class="panel-title">What happens next</h2>
      <?= order_timeline($o) ?>
      <a class="btn btn-whatsapp btn-block" href="<?= e(wa_link('Hi ' . $store . '! I just placed order ' . $o['order_no'] . '. Please confirm.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 20) ?> Confirm faster on WhatsApp</a>
    </section>

    <section class="panel">
      <h2 class="panel-title">Order summary</h2>
      <ul class="mini-lines">
        <?php foreach ($items as $it): ?>
          <li>
            <span class="mini-thumb"><img src="<?= e(img_url($it['image'])) ?>" alt="" width="56" height="70"><span class="mini-qty"><?= (int)$it['qty'] ?></span></span>
            <span class="mini-name"><?= e($it['product_name']) ?><?php if ($it['size']): ?><small>Size <?= e($it['size']) ?></small><?php endif; ?></span>
            <span><?= e(money($it['price'] * $it['qty'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-row"><span>Subtotal</span><span><?= e(money($o['subtotal'])) ?></span></div>
      <div class="sum-row"><span>Delivery</span><span><?= (float)$o['delivery_fee'] > 0 ? e(money($o['delivery_fee'])) : 'Free' ?></span></div>
      <div class="sum-row sum-total"><span>Pay on delivery</span><strong><?= e(money($o['total'])) ?></strong></div>
      <div class="ship-to">
        <span class="muted small">Delivering to</span>
        <p><strong><?= e($o['customer_name']) ?></strong><br><?= nl2br(e($o['address'])) ?><br><?= e($o['city']) ?></p>
      </div>
    </section>
  </div>
  <div class="center row-gap">
    <a class="btn btn-ghost" href="<?= e(url('track.php?o=' . rawurlencode($o['order_no']))) ?>">Track this order</a>
    <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
  </div>
</div>
<div class="spacer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
