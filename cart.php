<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$cart = cart_summary();
$title = 'Your cart';
$bodyClass = 'cart-page';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([['Home', url('')], ['Cart', null]]) ?>
    <h1>Your cart</h1>
  </div>
</section>

<div class="container">
  <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

  <?php if (!$cart['lines']): ?>
    <div class="empty">
      <div class="empty-icon"><?= icon('cart', 30) ?></div>
      <h2>Your cart is empty</h2>
      <p class="muted">Find your next matchday jersey — new drops every week.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('shop.php')) ?>">Start shopping <?= icon('arrow', 18) ?></a>
    </div>
  <?php else: ?>
    <div class="cart-layout" data-cart-page>
      <div class="cart-lines">
        <?php foreach ($cart['lines'] as $l): ?>
          <div class="cart-line">
            <a href="<?= e($l['url']) ?>" class="cart-thumb"><img src="<?= e($l['image']) ?>" alt="" width="96" height="120"></a>
            <div class="cart-line-info">
              <a href="<?= e($l['url']) ?>" class="cart-line-name"><?= e($l['name']) ?></a>
              <?php if ($l['size']): ?><span class="muted small">Size: <strong><?= e($l['size']) ?></strong></span><?php endif; ?>
              <span class="small"><?= e(money($l['price'])) ?> each</span>
              <form method="post" action="<?= e(url('api/cart.php')) ?>" class="cart-qty-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="key" value="<?= e($l['key']) ?>">
                <div class="qty qty-sm" data-qty>
                  <button type="button" data-step="-1" aria-label="Decrease"><?= icon('minus', 14) ?></button>
                  <input type="number" name="qty" value="<?= (int)$l['qty'] ?>" min="0" max="<?= (int)$l['max'] ?>" aria-label="Quantity" data-autosave>
                  <button type="button" data-step="1" aria-label="Increase"><?= icon('plus', 14) ?></button>
                </div>
                <noscript><button class="btn btn-sm">Update</button></noscript>
              </form>
            </div>
            <div class="cart-line-end">
              <strong><?= e(money($l['line'])) ?></strong>
              <form method="post" action="<?= e(url('api/cart.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="key" value="<?= e($l['key']) ?>">
                <button class="link-btn danger" type="submit"><?= icon('trash', 16) ?> Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <a class="link-arrow" href="<?= e(url('shop.php')) ?>">← Continue shopping</a>
      </div>

      <aside class="summary-card">
        <h2>Order summary</h2>
        <?php if ($cart['threshold'] > 0): $pct = min(100, (int)round($cart['subtotal'] / $cart['threshold'] * 100)); ?>
          <div class="ship-meter">
            <p><?= $cart['remaining'] > 0 ? 'Add <strong>' . e(money($cart['remaining'])) . '</strong> more for free delivery' : '<strong>You unlocked free delivery!</strong>' ?></p>
            <div class="meter"><span style="width: <?= $pct ?>%"></span></div>
          </div>
        <?php endif; ?>
        <div class="sum-row"><span>Subtotal (<?= (int)$cart['count'] ?> items)</span><span><?= e(money($cart['subtotal'])) ?></span></div>
        <div class="sum-row"><span>Delivery</span><span><?= $cart['delivery'] > 0 ? e(money($cart['delivery'])) : 'Free' ?></span></div>
        <div class="sum-row sum-total"><span>Total</span><strong><?= e(money($cart['total'])) ?></strong></div>
        <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('checkout.php')) ?>">Proceed to checkout <?= icon('arrow', 18) ?></a>
        <p class="secure-note"><?= icon('shield', 16) ?> Cash on Delivery · No advance payment</p>
      </aside>
    </div>
  <?php endif; ?>
</div>
<div class="spacer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
