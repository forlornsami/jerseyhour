<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$featured = rows(product_query_base() . ' WHERE p.active = 1 AND p.featured = 1 ORDER BY p.updated_at DESC LIMIT 4');
$latest   = rows(product_query_base() . ' WHERE p.active = 1 ORDER BY p.created_at DESC LIMIT 8');
$cats     = categories();
$heroImg  = setting('hero_image');
$heroPicks = array_slice($featured ?: $latest, 0, 3);
$store    = setting('store_name', 'JerseyHour');
$mapsUrl  = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(setting('address'));

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'ClothingStore',
    'name' => $store,
    'url' => 'https://jerseyhour.com',
    'telephone' => setting('phone'),
    'email' => setting('email'),
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => setting('address'), 'addressLocality' => 'Peshawar', 'addressCountry' => 'PK'],
];

$bodyClass = 'home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="eyebrow"><span class="dot"></span><?= e(setting('hero_eyebrow')) ?></span>
      <h1 class="hero-title"><?= e(setting('hero_title', 'Wear the game you love.')) ?></h1>
      <p class="hero-sub"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-cta">
        <a class="btn btn-light btn-lg" href="<?= e(url('shop.php')) ?>">Shop jerseys <?= icon('arrow', 18) ?></a>
        <a class="btn btn-outline-light btn-lg" href="<?= e(wa_link('Hi ' . $store . '! I want to order a jersey.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 18) ?> Order on WhatsApp</a>
      </div>
      <ul class="hero-points">
        <li><?= icon('check', 16) ?> Cash on Delivery</li>
        <li><?= icon('check', 16) ?> All-Pakistan shipping</li>
        <li><?= icon('check', 16) ?> 7-day size exchange</li>
      </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <?php if ($heroImg): ?>
        <img class="hero-photo" src="<?= e(img_url($heroImg)) ?>" alt="" fetchpriority="high">
      <?php else: ?>
        <div class="hero-stack">
          <?php foreach ($heroPicks as $i => $p): ?>
            <img class="hs hs-<?= $i ?>" src="<?= e(product_cover($p)) ?>" alt="" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="800" height="1000">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($heroPicks): $hp = $heroPicks[0]; ?>
        <a class="hero-tag" href="<?= e(product_url($hp)) ?>" tabindex="-1">
          <span class="muted small">Trending</span>
          <strong><?= e($hp['name']) ?></strong>
          <span class="price"><?= e(money($hp['price'])) ?></span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>Football</span><i>✦</i><span>Cricket</span><i>✦</i><span>Retro classics</span><i>✦</i><span>National teams</span><i>✦</i><span>Cash on delivery</span><i>✦</i><span>Peshawar to Karachi</span><i>✦</i>
    <?php endfor; ?>
  </div>
</div>

<?php if ($cats): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div><span class="kicker">Browse</span><h2>Shop by category</h2></div>
      <a class="link-arrow" href="<?= e(url('shop.php')) ?>">All jerseys <?= icon('arrow', 16) ?></a>
    </div>
    <div class="cat-grid">
      <?php foreach ($cats as $c): ?>
        <a class="cat-tile" href="<?= e(url('shop.php?category=' . rawurlencode($c['slug']))) ?>">
          <img src="<?= e(img_url($c['image'])) ?>" alt="" loading="lazy" width="800" height="1000">
          <span class="cat-info">
            <strong><?= e($c['name']) ?></strong>
            <span><?= (int)$c['product_count'] ?> <?= (int)$c['product_count'] === 1 ? 'style' : 'styles' ?> <?= icon('arrow', 14) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section section-tint">
  <div class="container">
    <div class="section-head">
      <div><span class="kicker">Best sellers</span><h2>Fan favourites</h2></div>
      <a class="link-arrow" href="<?= e(url('shop.php?sort=popular')) ?>">View all <?= icon('arrow', 16) ?></a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $i => $p) echo product_card($p, $i < 4); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="promo">
      <div>
        <span class="kicker kicker-dark">Make it yours</span>
        <h2>Your name. Your number.</h2>
        <p>Custom name &amp; number printing available on selected jerseys. Message us your design and we'll handle the rest.</p>
      </div>
      <a class="btn btn-light btn-lg" href="<?= e(wa_link('Hi! I want custom name & number printing on a jersey.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 18) ?> Ask on WhatsApp</a>
    </div>
  </div>
</section>

<?php if ($latest): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div><span class="kicker">Just landed</span><h2>New arrivals</h2></div>
      <a class="link-arrow" href="<?= e(url('shop.php?sort=new')) ?>">Shop new <?= icon('arrow', 16) ?></a>
    </div>
    <div class="product-grid">
      <?php foreach ($latest as $p) echo product_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section-tint">
  <div class="container visit-grid">
    <div class="visit-card">
      <span class="kicker">Visit the store</span>
      <h2>Try it on in Peshawar</h2>
      <ul class="visit-list">
        <li><?= icon('pin', 20) ?><span><?= e(setting('address')) ?></span></li>
        <li><?= icon('clock', 20) ?><span><?= e(setting('hours')) ?></span></li>
        <li><?= icon('phone', 20) ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
      </ul>
      <div class="row-gap">
        <a class="btn btn-primary" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener"><?= icon('pin', 18) ?> Get directions</a>
        <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>">Contact us</a>
      </div>
    </div>
    <div class="faq">
      <span class="kicker">Good to know</span>
      <h2>Questions, answered</h2>
      <details open><summary>How does Cash on Delivery work?</summary><p>Place your order online — no card needed. Our team confirms it by call or WhatsApp, and you pay the courier in cash when your jersey arrives.</p></details>
      <details><summary>How long does delivery take?</summary><p><?= e(setting('delivery_time', '2 – 5 working days')) ?> across Pakistan. Peshawar orders usually arrive within 1–2 days.</p></details>
      <details><summary>What if the size doesn't fit?</summary><p>You can exchange for another size within 7 days of delivery as long as the jersey is unworn with tags attached. See our <a href="<?= e(url('page.php?p=returns-exchange')) ?>">exchange policy</a>.</p></details>
      <details><summary>How do I choose my size?</summary><p>Every product has a size guide. Player-edition jerseys fit slimmer — go one size up if you're between sizes. <a href="<?= e(url('page.php?p=size-guide')) ?>">Open size guide</a>.</p></details>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
