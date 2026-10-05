<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$slug = (string)($_GET['s'] ?? '');
$p = $slug !== '' ? row(product_query_base() . ' WHERE p.slug = ? AND p.active = 1', [$slug]) : null;
if (!$p) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
// Count a view once per session
if (empty($_SESSION['viewed'][$p['id']])) {
    $_SESSION['viewed'][$p['id']] = 1;
    q('UPDATE products SET views = views + 1 WHERE id = ?', [$p['id']]);
}

$images = product_images($p) ?: [null];
$sizes  = product_sizes($p);
$stock  = product_stock($p);
$off    = discount_pct($p);
$store  = setting('store_name', 'JerseyHour');
$related = rows(product_query_base() . ' WHERE p.active = 1 AND p.id <> ? AND (p.category_id = ? OR p.featured = 1) ORDER BY CASE WHEN p.category_id = ? THEN 0 ELSE 1 END, p.created_at DESC LIMIT 4',
    [$p['id'], (int)$p['category_id'], (int)$p['category_id']]);
$sizeGuide = size_guide_page();
$firstAvail = '';
foreach ($sizes as $s => $qty) { if ((int)$qty > 0) { $firstAvail = $s; break; } }
$selfUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'jerseyhour.com') . product_url($p);

$title = $p['name'];
$metaDesc = excerpt($p['description'], 155) ?: setting('meta_description');
$ogImage = product_cover($p);
$jsonLd = [
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $p['name'],
    'image' => array_map(fn($i) => img_url($i), array_filter($images)),
    'description' => excerpt($p['description'], 300),
    'sku' => 'JH-' . $p['id'],
    'brand' => ['@type' => 'Brand', 'name' => $store],
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'PKR',
        'price' => (float)$p['price'],
        'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => $selfUrl,
    ],
];
$bodyClass = 'product-page';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <?= breadcrumbs(array_filter([['Home', url('')], ['Shop', url('shop.php')], $p['category_name'] ? [$p['category_name'], url('shop.php?category=' . rawurlencode($p['category_slug']))] : null, [$p['name'], null]])) ?>

  <div class="pdp">
    <div class="gallery" data-gallery>
      <div class="gallery-main" data-gallery-track>
        <?php foreach ($images as $i => $img): ?>
          <figure class="gallery-slide" id="slide-<?= $i ?>">
            <img src="<?= e(img_url($img)) ?>" alt="<?= e($p['name']) ?><?= $i ? ' — view ' . ($i + 1) : '' ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="800" height="1000">
          </figure>
        <?php endforeach; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs" role="tablist" aria-label="Product images">
          <?php foreach ($images as $i => $img): ?>
            <button type="button" class="thumb <?= $i === 0 ? 'active' : '' ?>" data-thumb="<?= $i ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Image <?= $i + 1 ?>">
              <img src="<?= e(img_url($img)) ?>" alt="" loading="lazy" width="160" height="200">
            </button>
          <?php endforeach; ?>
        </div>
        <div class="gallery-dots only-mobile" aria-hidden="true"><?php foreach ($images as $i => $img): ?><span class="<?= $i === 0 ? 'active' : '' ?>"></span><?php endforeach; ?></div>
      <?php endif; ?>
      <?php if ($off > 0): ?><span class="badge badge-sale badge-lg">-<?= $off ?>%</span><?php elseif ($p['badge']): ?><span class="badge badge-lg"><?= e($p['badge']) ?></span><?php endif; ?>
    </div>

    <div class="pdp-info">
      <?php if ($p['category_name']): ?><a class="card-cat" href="<?= e(url('shop.php?category=' . rawurlencode($p['category_slug']))) ?>"><?= e($p['category_name']) ?></a><?php endif; ?>
      <h1 class="pdp-title"><?= e($p['name']) ?></h1>
      <div class="price-row price-lg">
        <span class="price"><?= e(money($p['price'])) ?></span>
        <?php if ($off > 0): ?>
          <s class="price-old"><?= e(money($p['compare_price'])) ?></s>
          <span class="save">Save <?= e(money($p['compare_price'] - $p['price'])) ?></span>
        <?php endif; ?>
      </div>

      <form class="buy-form" method="post" action="<?= e(url('api/cart.php')) ?>" data-add-to-cart novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">

        <?php if ($sizes): ?>
        <fieldset class="size-select">
          <div class="size-head">
            <legend>Size <span class="muted" data-size-label><?= $firstAvail ? '' : '— sold out' ?></span></legend>
            <?php if ($sizeGuide): ?><button type="button" class="link-btn" data-open="sizeGuide"><?= icon('ruler', 16) ?> Size guide</button><?php endif; ?>
          </div>
          <div class="size-options">
            <?php foreach ($sizes as $s => $qty): $qty = (int)$qty; ?>
              <label class="size-opt <?= $qty <= 0 ? 'is-out' : '' ?>">
                <input type="radio" name="size" value="<?= e($s) ?>" data-stock="<?= $qty ?>" <?= $qty <= 0 ? 'disabled' : '' ?> required>
                <span><?= e($s) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="stock-note" data-stock-note aria-live="polite"><?= $stock > 0 ? 'Select a size to see availability' : '' ?></p>
        </fieldset>
        <?php endif; ?>

        <?php if ($stock > 0): ?>
          <div class="buy-row">
            <div class="qty" data-qty>
              <button type="button" data-step="-1" aria-label="Decrease quantity"><?= icon('minus', 16) ?></button>
              <input type="number" name="qty" value="1" min="1" max="<?= (int)$stock ?>" inputmode="numeric" aria-label="Quantity">
              <button type="button" data-step="1" aria-label="Increase quantity"><?= icon('plus', 16) ?></button>
            </div>
            <button class="btn btn-primary btn-lg grow" type="submit" data-add-btn><?= icon('cart', 20) ?> Add to cart</button>
          </div>
          <button class="btn btn-accent btn-lg btn-block" type="submit" name="buy_now" value="1" data-buy-now>Buy now — Cash on Delivery</button>
        <?php else: ?>
          <button class="btn btn-lg btn-block" type="button" disabled>Sold out</button>
        <?php endif; ?>
        <a class="btn btn-whatsapp btn-block" data-wa-order data-wa-base="<?= e('Hi ' . $store . "! I'd like to order:\n" . $p['name'] . "\nPrice: " . money($p['price'])) ?>" data-wa-link="<?= e($selfUrl) ?>" href="<?= e(wa_link('Hi ' . $store . "! I'd like to order: " . $p['name'] . ' — ' . $selfUrl)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 20) ?> Order on WhatsApp</a>
      </form>

      <ul class="pdp-perks">
        <li><?= icon('cash', 20) ?><span><strong>Cash on Delivery</strong> — pay when it arrives</span></li>
        <li><?= icon('truck', 20) ?><span><strong>Delivery in <?= e(setting('delivery_time', '2 – 5 working days')) ?></strong><?php if ((float)setting('free_shipping_over') > 0): ?> · free over <?= e(money(setting('free_shipping_over'))) ?><?php endif; ?></span></li>
        <li><?= icon('refresh', 20) ?><span><strong>7-day size exchange</strong> — unworn with tags</span></li>
      </ul>

      <div class="accordion">
        <details open>
          <summary>Description</summary>
          <div class="rich"><?= rich_text($p['description']) ?: '<p class="muted">No description yet.</p>' ?></div>
        </details>
        <details>
          <summary>Delivery &amp; payment</summary>
          <div class="rich"><p>We deliver across Pakistan in <?= e(setting('delivery_time', '2 – 5 working days')) ?>. Delivery charge: <?= e(money(setting('delivery_fee', '0'))) ?><?php if ((float)setting('free_shipping_over') > 0): ?>, free on orders over <?= e(money(setting('free_shipping_over'))) ?><?php endif; ?>. Pay in cash to the courier on delivery.</p></div>
        </details>
        <details>
          <summary>Exchange policy</summary>
          <div class="rich"><p>Wrong size? Exchange within 7 days of delivery — the jersey must be unworn with original tags. <a href="<?= e(url('page.php?p=returns-exchange')) ?>">Read the full policy</a>.</p></div>
        </details>
      </div>
    </div>
  </div>
</div>

<?php if ($related): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><div><span class="kicker">You may also like</span><h2>Complete the kit</h2></div></div>
    <div class="product-grid"><?php foreach ($related as $r) echo product_card($r); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($stock > 0): ?>
<div class="sticky-buy" data-sticky-buy aria-hidden="true">
  <img src="<?= e(product_cover($p)) ?>" alt="" width="44" height="55">
  <div class="sticky-meta"><strong><?= e($p['name']) ?></strong><span><?= e(money($p['price'])) ?></span></div>
  <button class="btn btn-primary" type="button" data-sticky-add tabindex="-1">Add to cart</button>
</div>
<?php endif; ?>

<?php if ($sizeGuide): ?>
<div class="modal" id="sizeGuide" role="dialog" aria-modal="true" aria-labelledby="sgTitle" hidden>
  <div class="modal-card">
    <div class="modal-head"><h2 id="sgTitle"><?= e($sizeGuide['title']) ?></h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('close', 22) ?></button></div>
    <a class="size-guide-img" href="<?= e(asset(SIZE_GUIDE_IMAGE)) ?>" target="_blank" rel="noopener"><img src="<?= e(asset(SIZE_GUIDE_IMAGE)) ?>" alt="JerseyHour size chart: chest and length in inches for sizes XS to XXXL" loading="lazy" width="1312" height="1200"></a>
    <div class="rich"><?= rich_text($sizeGuide['content']) ?></div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
