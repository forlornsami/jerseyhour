<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$cart = cart_summary();
if (!$cart['lines'] && !is_post()) redirect('cart.php');

$errors = [];
$in = [
    'name'    => trim((string)($_POST['name'] ?? ($_SESSION['checkout']['name'] ?? ''))),
    'phone'   => trim((string)($_POST['phone'] ?? ($_SESSION['checkout']['phone'] ?? ''))),
    'email'   => trim((string)($_POST['email'] ?? ($_SESSION['checkout']['email'] ?? ''))),
    'city'    => trim((string)($_POST['city'] ?? ($_SESSION['checkout']['city'] ?? ''))),
    'address' => trim((string)($_POST['address'] ?? ($_SESSION['checkout']['address'] ?? ''))),
    'notes'   => trim((string)($_POST['notes'] ?? '')),
];

if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) { redirect(''); } // honeypot

    if (mb_strlen($in['name']) < 3) $errors['name'] = 'Please enter your full name.';
    $phone = normalize_phone($in['phone']);
    if (!preg_match('~^03\d{9}$~', $phone)) $errors['phone'] = 'Enter a valid mobile number, e.g. 0342 0052034.';
    if ($in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'That email doesn\'t look right.';
    if (mb_strlen($in['city']) < 2) $errors['city'] = 'Please enter your city.';
    if (mb_strlen($in['address']) < 10) $errors['address'] = 'Please enter your complete address (house, street, area).';
    foreach (['name' => 120, 'email' => 150, 'city' => 80, 'address' => 500, 'notes' => 500] as $k => $max) {
        if (mb_strlen($in[$k]) > $max) $errors[$k] = 'This field is too long.';
    }

    $recent = $_SESSION['order_times'] ?? [];
    $recent = array_filter($recent, fn($t) => $t > time() - 3600);
    if (count($recent) >= 5) $errors['form'] = 'Too many orders from this device in the last hour. Please contact us on WhatsApp.';

    if (!$cart['lines']) $errors['form'] = 'Your cart is empty.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $subtotal = 0.0;
            $items = [];
            foreach (cart_raw() as $key => $qty) {
                [$pid, $size] = array_pad(explode('|', $key, 2), 2, '');
                $p = row('SELECT * FROM products WHERE id = ? AND active = 1', [(int)$pid]);
                if (!$p) throw new RuntimeException('An item in your cart is no longer available. Please review your cart.');
                $sizes = product_sizes($p);
                $qty = (int)$qty;
                if ($sizes) {
                    $have = (int)($sizes[$size] ?? 0);
                    if ($have < $qty) throw new RuntimeException('Sorry — only ' . $have . ' left of ' . $p['name'] . ' in size ' . $size . '. Please update your cart.');
                    $sizes[$size] = $have - $qty;
                    q('UPDATE products SET sizes = ?, updated_at = ? WHERE id = ?', [json_encode($sizes), now(), $p['id']]);
                }
                $subtotal += (float)$p['price'] * $qty;
                $items[] = [$p, $size, $qty];
            }
            $fee = delivery_fee($subtotal);
            $orderNo = generate_order_no();
            q('INSERT INTO orders (order_no, customer_name, phone, email, city, address, notes, subtotal, delivery_fee, total, payment_method, status, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
              [$orderNo, $in['name'], $phone, $in['email'], $in['city'], $in['address'], $in['notes'], $subtotal, $fee, $subtotal + $fee, 'COD', 'pending', now(), now()]);
            $orderId = (int)$pdo->lastInsertId();
            foreach ($items as [$p, $size, $qty]) {
                q('INSERT INTO order_items (order_id, product_id, product_name, size, price, qty, image) VALUES (?, ?, ?, ?, ?, ?, ?)',
                  [$orderId, $p['id'], $p['name'], $size, $p['price'], $qty, product_images($p)[0] ?? '']);
            }
            $pdo->commit();

            $_SESSION['cart'] = [];
            $_SESSION['order_times'] = array_merge($recent, [time()]);
            $_SESSION['my_orders'][] = $orderNo;
            $_SESSION['checkout'] = array_intersect_key($in, array_flip(['name', 'phone', 'email', 'city', 'address']));

            // Best-effort email notification to the store owner
            $to = setting('order_notify_email');
            if ($to && function_exists('mail')) {
                $lines = array_map(fn($i) => '- ' . $i[0]['name'] . ($i[1] ? ' (' . $i[1] . ')' : '') . ' x' . $i[2], $items);
                $body = "New order $orderNo\n\n" . implode("\n", $lines) . "\n\nTotal: " . money($subtotal + $fee)
                    . "\n\nCustomer: {$in['name']}\nPhone: $phone\nCity: {$in['city']}\nAddress: {$in['address']}\nNotes: {$in['notes']}";
                $host = preg_replace('~[^a-z0-9.-]~i', '', $_SERVER['HTTP_HOST'] ?? 'jerseyhour.com');
                @mail($to, 'New order ' . $orderNo . ' — ' . money($subtotal + $fee), $body, 'From: ' . setting('store_name', 'JerseyHour') . ' <no-reply@' . preg_replace('~^www\.~', '', $host) . '>');
            }
            redirect('order-success.php?o=' . rawurlencode($orderNo));
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['form'] = $t instanceof RuntimeException ? $t->getMessage() : 'Something went wrong while placing your order. Please try again.';
            if (!($t instanceof RuntimeException)) error_log('Checkout error: ' . $t->getMessage());
            $cart = cart_summary();
        }
    }
}

$cities = ['Peshawar', 'Islamabad', 'Rawalpindi', 'Lahore', 'Karachi', 'Faisalabad', 'Multan', 'Quetta', 'Mardan', 'Abbottabad', 'Swat', 'Nowshera', 'Kohat', 'Dera Ismail Khan', 'Bannu', 'Charsadda', 'Mansehra', 'Sialkot', 'Gujranwala', 'Hyderabad', 'Sargodha', 'Bahawalpur', 'Sukkur', 'Gujrat'];
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';

$title = 'Checkout';
$bodyClass = 'checkout-page';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head page-head-sm">
  <div class="container">
    <ol class="steps" aria-label="Checkout progress">
      <li class="done"><a href="<?= e(url('cart.php')) ?>">Cart</a></li>
      <li class="current" aria-current="step">Details</li>
      <li>Confirmation</li>
    </ol>
    <h1>Checkout</h1>
  </div>
</section>

<div class="container">
  <?php if (!empty($errors['form'])): ?><div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
  <?php if ($errors && empty($errors['form'])): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields below.</div><?php endif; ?>

  <form method="post" class="checkout-layout" novalidate data-checkout>
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="checkout-main">
      <section class="panel">
        <h2 class="panel-title"><span class="step-num">1</span> Contact</h2>
        <div class="grid-2">
          <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
            <label for="name">Full name</label>
            <input id="name" name="name" value="<?= e($in['name']) ?>" autocomplete="name" required minlength="3"<?= $inv('name') ?>>
            <?= $err('name') ?>
          </div>
          <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
            <label for="phone">Mobile number</label>
            <input id="phone" name="phone" type="tel" value="<?= e($in['phone']) ?>" placeholder="03XX XXXXXXX" autocomplete="tel" inputmode="tel" required<?= $inv('phone') ?>>
            <?= $err('phone') ?: '<p class="hint">We\'ll call or WhatsApp to confirm your order.</p>' ?>
          </div>
        </div>
        <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
          <label for="email">Email <span class="hint">optional</span></label>
          <input id="email" name="email" type="email" value="<?= e($in['email']) ?>" autocomplete="email"<?= $inv('email') ?>>
          <?= $err('email') ?>
        </div>
      </section>

      <section class="panel">
        <h2 class="panel-title"><span class="step-num">2</span> Delivery address</h2>
        <div class="field <?= isset($errors['city']) ? 'has-error' : '' ?>">
          <label for="city">City</label>
          <input id="city" name="city" list="cityList" value="<?= e($in['city']) ?>" autocomplete="address-level2" required<?= $inv('city') ?>>
          <datalist id="cityList"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
          <?= $err('city') ?>
        </div>
        <div class="field <?= isset($errors['address']) ? 'has-error' : '' ?>">
          <label for="address">Complete address</label>
          <textarea id="address" name="address" rows="3" placeholder="House #, street, area, nearest landmark" autocomplete="street-address" required<?= $inv('address') ?>><?= e($in['address']) ?></textarea>
          <?= $err('address') ?>
        </div>
        <div class="field">
          <label for="notes">Order notes <span class="hint">optional</span></label>
          <textarea id="notes" name="notes" rows="2" placeholder="Name/number printing, delivery timing, etc."><?= e($in['notes']) ?></textarea>
        </div>
      </section>

      <section class="panel">
        <h2 class="panel-title"><span class="step-num">3</span> Payment</h2>
        <label class="pay-option is-selected">
          <input type="radio" name="payment" value="COD" checked>
          <span class="pay-icon"><?= icon('cash', 24) ?></span>
          <span><strong>Cash on Delivery</strong><small>Pay the courier in cash when your order arrives.</small></span>
          <span class="pay-check"><?= icon('check', 16) ?></span>
        </label>
      </section>
    </div>

    <aside class="summary-card summary-sticky">
      <h2>Your order</h2>
      <ul class="mini-lines">
        <?php foreach ($cart['lines'] as $l): ?>
          <li>
            <span class="mini-thumb"><img src="<?= e($l['image']) ?>" alt="" width="56" height="70"><span class="mini-qty"><?= (int)$l['qty'] ?></span></span>
            <span class="mini-name"><?= e($l['name']) ?><?php if ($l['size']): ?><small>Size <?= e($l['size']) ?></small><?php endif; ?></span>
            <span><?= e(money($l['line'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-row"><span>Subtotal</span><span><?= e(money($cart['subtotal'])) ?></span></div>
      <div class="sum-row"><span>Delivery</span><span><?= $cart['delivery'] > 0 ? e(money($cart['delivery'])) : 'Free' ?></span></div>
      <div class="sum-row sum-total"><span>Total</span><strong><?= e(money($cart['total'])) ?></strong></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit" data-submit-once>Place order · <?= e(money($cart['total'])) ?></button>
      <p class="secure-note"><?= icon('shield', 16) ?> Your details are only used to deliver your order.</p>
    </aside>
  </form>
</div>
<div class="spacer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
