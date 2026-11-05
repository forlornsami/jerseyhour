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

// ---- Customer reviews ----
reviews_ready();
$rvErrors = [];
$rvIn = ['name' => trim((string)($_POST['rv_name'] ?? '')), 'rating' => (int)($_POST['rv_rating'] ?? 0), 'comment' => trim((string)($_POST['rv_comment'] ?? ''))];
if (is_post() && ($_POST['action'] ?? '') === 'review') {
    csrf_check();
    if (!empty($_POST['website'])) redirect(product_url($p));
    if (mb_strlen($rvIn['name']) < 2) $rvErrors['name'] = 'Please enter your name.';
    if ($rvIn['rating'] < 1 || $rvIn['rating'] > 5) $rvErrors['rating'] = 'Please choose a star rating.';
    if (mb_strlen($rvIn['comment']) > 1500) $rvErrors['comment'] = 'Review is too long (1500 characters max).';
    if (!empty($_SESSION['reviewed'][$p['id']])) $rvErrors['form'] = 'You have already reviewed this product. Thank you!';
    elseif (time() - ($_SESSION['last_review'] ?? 0) < 30) $rvErrors['form'] = 'Please wait a moment before posting another review.';
    if (!$rvErrors) {
        q('INSERT INTO reviews (product_id, name, rating, comment, approved, ip, created_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
          [$p['id'], mb_substr($rvIn['name'], 0, 80), $rvIn['rating'], $rvIn['comment'], client_ip(), now()]);
        $_SESSION['last_review'] = time();
        $_SESSION['reviewed'][$p['id']] = 1;
        $_SESSION['review_thanks'] = 1;
        redirect(product_url($p) . '#reviews');
    }
}
$rvThanks = !empty($_SESSION['review_thanks']);
unset($_SESSION['review_thanks']);
$rvStats = review_stats((int)$p['id']);
$reviews = rows('SELECT name, rating, comment, created_at FROM reviews WHERE product_id = ? AND approved = 1 ORDER BY created_at DESC, id DESC LIMIT 50', [$p['id']]);
$rvDist = array_fill(1, 5, 0);
foreach (rows('SELECT rating, COUNT(*) AS n FROM reviews WHERE product_id = ? AND approved = 1 GROUP BY rating', [$p['id']]) as $r) {
    $k = (int)$r['rating'];
    if (isset($rvDist[$k])) $rvDist[$k] = (int)$r['n'];
}

$images = product_images($p) ?: [null];
$sizes  = product_sizes($p);
$stock  = product_stock($p);
$off    = discount_pct($p);
$store  = setting('store_name', 'JerseyHour');
$related = rows(product_query_base() . ' WHERE p.active = 1 AND p.id <> ? AND (p.category_id = ? OR p.featured = 1) ORDER BY CASE WHEN p.category_id = ? THEN 0 ELSE 1 END, p.created_at DESC LIMIT 4',
    [$p['id'], (int)$p['category_id'], (int)$p['category_id']]);
$sizeGuide = row("SELECT * FROM pages WHERE slug = 'size-guide'");
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
if ($rvStats['count'] > 0) {
    $jsonLd['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $rvStats['avg'], 'reviewCount' => $rvStats['count'], 'bestRating' => 5, 'worstRating' => 1];
    $jsonLd['review'] = array_map(fn($r) => [
        '@type' => 'Review',
        'author' => ['@type' => 'Person', 'name' => $r['name']],
        'datePublished' => substr($r['created_at'], 0, 10),
        'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int)$r['rating'], 'bestRating' => 5],
        'reviewBody' => (string)$r['comment'],
    ], array_slice($reviews, 0, 5));
}
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
      <a class="pdp-rating" href="#reviews">
        <?php if ($rvStats['count']): ?>
          <?= stars_html($rvStats['avg'], 18) ?> <strong><?= e(number_format($rvStats['avg'], 1)) ?></strong> <span class="muted">(<?= $rvStats['count'] ?> review<?= $rvStats['count'] === 1 ? '' : 's' ?>)</span>
        <?php else: ?>
          <?= stars_html(0, 18) ?> <span class="muted">Be the first to review</span>
        <?php endif; ?>
      </a>
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

<section class="section reviews-section" id="reviews">
  <div class="container">
    <div class="section-head"><div><span class="kicker">Customer reviews</span><h2>What fans say</h2></div></div>
    <div class="reviews-grid">
      <div class="reviews-side">
        <div class="panel rv-summary">
          <?php if ($rvStats['count']): ?>
            <div class="rv-score"><strong><?= e(number_format($rvStats['avg'], 1)) ?></strong><div><?= stars_html($rvStats['avg'], 20) ?><span class="muted small">Based on <?= $rvStats['count'] ?> review<?= $rvStats['count'] === 1 ? '' : 's' ?></span></div></div>
            <ul class="rv-bars">
              <?php for ($i = 5; $i >= 1; $i--): $pct = $rvStats['count'] ? round($rvDist[$i] / $rvStats['count'] * 100) : 0; ?>
                <li><span><?= $i ?>★</span><span class="rv-bar"><span style="width:<?= $pct ?>%"></span></span><span class="muted"><?= $rvDist[$i] ?></span></li>
              <?php endfor; ?>
            </ul>
          <?php else: ?>
            <p class="muted">No reviews yet. Bought this jersey? Tell other fans what you think.</p>
          <?php endif; ?>
        </div>

        <form method="post" action="<?= e(product_url($p)) ?>#reviews" class="panel rv-form" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="review">
          <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
          <h3 class="panel-title">Write a review</h3>
          <?php if ($rvThanks): ?><div class="alert alert-success" role="status">Thanks for your review!</div><?php endif; ?>
          <?php if (!empty($rvErrors['form'])): ?><div class="alert alert-error"><?= e($rvErrors['form']) ?></div><?php endif; ?>
          <fieldset class="field <?= isset($rvErrors['rating']) ? 'has-error' : '' ?>">
            <legend>Your rating</legend>
            <div class="star-input">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="rv-star-<?= $i ?>" name="rv_rating" value="<?= $i ?>" <?= $rvIn['rating'] === $i ? 'checked' : '' ?> required>
                <label for="rv-star-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><span class="visually-hidden"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span></label>
              <?php endfor; ?>
            </div>
            <?php if (isset($rvErrors['rating'])): ?><p class="field-error"><?= e($rvErrors['rating']) ?></p><?php endif; ?>
          </fieldset>
          <div class="field <?= isset($rvErrors['name']) ? 'has-error' : '' ?>"><label for="rv-name">Your name</label><input id="rv-name" name="rv_name" value="<?= e($rvIn['name']) ?>" maxlength="80" autocomplete="name" required><?php if (isset($rvErrors['name'])): ?><p class="field-error"><?= e($rvErrors['name']) ?></p><?php endif; ?></div>
          <div class="field <?= isset($rvErrors['comment']) ? 'has-error' : '' ?>"><label for="rv-comment">Review <span class="muted">(optional)</span></label><textarea id="rv-comment" name="rv_comment" rows="4" maxlength="1500" placeholder="Fit, quality, delivery…"><?= e($rvIn['comment']) ?></textarea><?php if (isset($rvErrors['comment'])): ?><p class="field-error"><?= e($rvErrors['comment']) ?></p><?php endif; ?></div>
          <button class="btn btn-primary" type="submit" data-submit-once>Post review</button>
        </form>
      </div>

      <div class="rv-list">
        <?php if (!$reviews): ?>
          <p class="muted">Reviews from customers will appear here.</p>
        <?php endif; ?>
        <?php foreach ($reviews as $r): ?>
          <article class="rv-item">
            <header>
              <span class="rv-avatar"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
              <div><strong><?= e($r['name']) ?></strong><span class="muted small"><?= e(nice_date($r['created_at'], false)) ?></span></div>
              <?= stars_html((float)$r['rating'], 16) ?>
            </header>
            <?php if (trim((string)$r['comment']) !== ''): ?><p><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

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
    <div class="rich"><?= rich_text($sizeGuide['content']) ?></div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
