<?php
require __DIR__ . '/partials/init.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$o = row('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }
$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$id]);

/** Adjust stock for all items: $dir = +1 restock, -1 deduct */
function adjust_stock(array $items, int $dir): array
{
    $problems = [];
    foreach ($items as $it) {
        if (!$it['product_id']) continue;
        $p = row('SELECT id, name, sizes FROM products WHERE id = ?', [$it['product_id']]);
        if (!$p) continue;
        $sizes = product_sizes($p);
        if ($it['size'] === '' || !array_key_exists($it['size'], $sizes)) continue;
        $new = (int)$sizes[$it['size']] + $dir * (int)$it['qty'];
        if ($new < 0) { $problems[] = $p['name'] . ' (' . $it['size'] . ')'; $new = 0; }
        $sizes[$it['size']] = $new;
        q('UPDATE products SET sizes = ?, updated_at = ? WHERE id = ?', [json_encode($sizes), now(), $p['id']]);
    }
    return $problems;
}

if (is_post()) {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'status') {
        $new = (string)($_POST['status'] ?? '');
        if (isset(order_statuses()[$new]) && $new !== $o['status']) {
            $pdo = db();
            $pdo->beginTransaction();
            $problems = [];
            if ($new === 'cancelled') adjust_stock($items, +1);
            if ($o['status'] === 'cancelled') $problems = adjust_stock($items, -1);
            q('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?', [$new, now(), $id]);
            $pdo->commit();
            $msg = 'Order marked as ' . status_label($new) . '.';
            if ($new === 'cancelled') $msg .= ' Stock was returned to inventory.';
            if ($o['status'] === 'cancelled') $msg .= ' Stock was deducted again.';
            if ($problems) $msg .= ' Note: not enough stock for ' . implode(', ', $problems) . '.';
            flash('success', $msg);
        }
    } elseif ($act === 'note') {
        q('UPDATE orders SET admin_note = ?, updated_at = ? WHERE id = ?', [mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 2000), now(), $id]);
        flash('success', 'Note saved.');
    } elseif ($act === 'delete') {
        if ($o['status'] !== 'cancelled' && $o['status'] !== 'delivered') adjust_stock($items, +1);
        q('DELETE FROM order_items WHERE order_id = ?', [$id]);
        q('DELETE FROM orders WHERE id = ?', [$id]);
        flash('success', 'Order ' . $o['order_no'] . ' deleted.');
        redirect('admin/orders.php');
    }
    redirect('admin/order.php?id=' . $id);
}

$store = setting('store_name', 'JerseyHour');
$first = explode(' ', trim($o['customer_name']))[0];
$lines = implode("\n", array_map(fn($i) => '• ' . $i['product_name'] . ($i['size'] ? ' (' . $i['size'] . ')' : '') . ' × ' . $i['qty'], $items));
$waTemplates = [
    'Confirm order' => "Assalam o Alaikum $first! 👋\nThank you for ordering from $store.\n\nOrder: {$o['order_no']}\n$lines\nTotal (COD): " . money($o['total']) . "\n\nDelivery address: {$o['address']}, {$o['city']}\n\nPlease reply YES to confirm your order.",
    'Shipped' => "Hi $first! Your $store order {$o['order_no']} has been shipped 🚚 It should reach you in " . setting('delivery_time', '2–5 working days') . ". Please keep " . money($o['total']) . " ready for the courier.",
    'Delivered — thanks' => "Hi $first! We hope you love your new jersey 🙌 Thank you for shopping with $store. Tag us when you wear it!",
];
$waPhone = '92' . substr($o['phone'], 1);
$itemCount = array_sum(array_column($items, 'qty'));

$pageTitle = 'Order ' . $o['order_no'];
$active = 'orders';
$topActions = '<button class="btn btn-ghost" type="button" onclick="window.print()">Print packing slip</button>';
require __DIR__ . '/partials/top.php';
?>
<a class="back-link no-print" href="<?= e(admin_url('orders.php')) ?>">← All orders</a>

<div class="order-top">
  <div>
    <span class="pill pill-<?= e($o['status']) ?> pill-lg"><?= e(status_label($o['status'])) ?></span>
    <span class="muted">Placed <?= e(nice_date($o['created_at'])) ?> · <?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?> · <?= e($o['payment_method']) ?></span>
  </div>
  <form method="post" class="status-form no-print">
    <?= csrf_field() ?><input type="hidden" name="action" value="status">
    <label for="st" class="visually-hidden">Change status</label>
    <select id="st" name="status">
      <?php foreach (order_statuses() as $k => $l): ?><option value="<?= e($k) ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Update status</button>
  </form>
</div>

<?php $flow = ['pending' => 'confirmed', 'confirmed' => 'shipped', 'shipped' => 'delivered']; if (isset($flow[$o['status']])): ?>
<form method="post" class="next-step no-print">
  <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($flow[$o['status']]) ?>">
  <span>Next step:</span>
  <button class="btn btn-accent" type="submit">Mark as <?= e(status_label($flow[$o['status']])) ?> →</button>
</form>
<?php endif; ?>

<div class="edit-grid">
  <div class="edit-main">
    <section class="card print-card">
      <div class="print-only print-head"><?= logo_html() ?><div><strong>Packing slip</strong><br><?= e($o['order_no']) ?> · <?= e(nice_date($o['created_at'], false)) ?></div></div>
      <h2 class="card-title">Items</h2>
      <ul class="order-items">
        <?php foreach ($items as $it): ?>
          <li>
            <img src="<?= e(img_url($it['image'])) ?>" alt="" width="52" height="65">
            <div class="grow">
              <?php if ($it['product_id']): ?><a href="<?= e(admin_url('product-edit.php?id=' . $it['product_id'])) ?>"><strong><?= e($it['product_name']) ?></strong></a><?php else: ?><strong><?= e($it['product_name']) ?></strong><?php endif; ?>
              <span class="muted small"><?= $it['size'] ? 'Size ' . e($it['size']) . ' · ' : '' ?><?= e(money($it['price'])) ?> × <?= (int)$it['qty'] ?></span>
            </div>
            <strong><?= e(money($it['price'] * $it['qty'])) ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="totals">
        <div><span>Subtotal</span><span><?= e(money($o['subtotal'])) ?></span></div>
        <div><span>Delivery</span><span><?= (float)$o['delivery_fee'] > 0 ? e(money($o['delivery_fee'])) : 'Free' ?></span></div>
        <div class="grand"><span>Collect on delivery</span><strong><?= e(money($o['total'])) ?></strong></div>
      </div>
      <div class="print-only print-ship">
        <strong>Ship to:</strong><br><?= e($o['customer_name']) ?> · <?= e($o['phone']) ?><br><?= nl2br(e($o['address'])) ?><br><?= e($o['city']) ?>
        <?php if ($o['notes']): ?><br><br><strong>Notes:</strong> <?= e($o['notes']) ?><?php endif; ?>
      </div>
    </section>

    <section class="card no-print">
      <h2 class="card-title">Message the customer</h2>
      <div class="wa-templates">
        <?php foreach ($waTemplates as $label => $text): ?>
          <a class="btn btn-wa" href="<?= e(wa_link($text, $waPhone)) ?>" target="_blank" rel="noopener">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.6z"/></svg><?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="hint">Opens WhatsApp with a ready-to-send message.</p>
    </section>

    <section class="card no-print">
      <h2 class="card-title">Internal note</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="admin_note" rows="3" placeholder="Courier tracking #, call notes… (only visible to you)"><?= e($o['admin_note']) ?></textarea>
        <button class="btn btn-sm" type="submit" style="margin-top:10px">Save note</button>
      </form>
    </section>
  </div>

  <aside class="edit-side no-print">
    <section class="card">
      <h2 class="card-title">Customer</h2>
      <p class="cust-name"><?= e($o['customer_name']) ?></p>
      <div class="cust-actions">
        <a class="btn btn-sm" href="tel:<?= e($o['phone']) ?>">Call <?= e($o['phone']) ?></a>
        <a class="btn btn-sm btn-wa" href="<?= e(wa_link('', $waPhone)) ?>" target="_blank" rel="noopener">WhatsApp</a>
      </div>
      <?php if ($o['email']): ?><p><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></p><?php endif; ?>
      <?php $prev = (int)val('SELECT COUNT(*) FROM orders WHERE phone = ? AND id <> ?', [$o['phone'], $o['id']]); if ($prev): ?>
        <p><a class="small" href="<?= e(admin_url('orders.php?q=' . rawurlencode($o['phone']))) ?>">↺ Returning customer · <?= $prev ?> other order<?= $prev === 1 ? '' : 's' ?></a></p>
      <?php endif; ?>
    </section>
    <section class="card">
      <div class="card-head"><h2 class="card-title">Delivery address</h2><button type="button" class="link" data-copy="<?= e($o['customer_name'] . "\n" . $o['phone'] . "\n" . $o['address'] . "\n" . $o['city']) ?>">Copy</button></div>
      <address><?= nl2br(e($o['address'])) ?><br><strong><?= e($o['city']) ?></strong></address>
      <?php if ($o['notes']): ?><div class="note-box"><span class="muted small">Customer note</span><p><?= nl2br(e($o['notes'])) ?></p></div><?php endif; ?>
    </section>
    <form method="post" class="danger-inline" data-confirm="Delete order <?= e($o['order_no']) ?> permanently? Consider marking it Cancelled instead.">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="link-danger" type="submit">Delete order</button>
    </form>
  </aside>
</div>
<?php require __DIR__ . '/partials/bottom.php'; ?>
