<?php
require_once __DIR__ . '/partials.php';
/** @var string $title  @var string $metaDesc  @var string $bodyClass  @var string $ogImage  @var string $canonical */
$title     = $title ?? '';
$metaDesc  = $metaDesc ?? setting('meta_description');
$bodyClass = $bodyClass ?? '';
$ogImage   = $ogImage ?? '';
$store     = setting('store_name', 'JerseyHour');
$cartCount = 0;
foreach (cart_raw() as $_cq) $cartCount += (int)$_cq;
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'jerseyhour.com';
$absBase   = $scheme . '://' . $host;
$current   = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navCats   = array_slice(categories(), 0, 5);
$activeCat = $_GET['category'] ?? '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(page_title($title)) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="<?= e(preg_match('~^#[0-9a-f]{6}$~i', setting('brand_color')) ? setting('brand_color') : '#7A2230') ?>">
<?= brand_head() ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($store) ?>">
<meta property="og:title" content="<?= e(page_title($title)) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e(preg_match('~^https?://~', $ogImage) ? $ogImage : $absBase . $ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="preload" href="<?= e(url('assets/fonts/barlow-condensed-latin-800-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/inter-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script>window.JH = <?= json_encode(['base' => BASE, 'csrf' => csrf_token(), 'currency' => setting('currency_symbol', 'Rs.')]) ?>;</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($ann = setting('announcement')): ?>
<div class="announce" role="note"><div class="container"><?= e($ann) ?></div></div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="container header-inner">
    <button class="icon-btn only-mobile" type="button" data-open="navDrawer" aria-label="Open menu"><?= icon('menu', 22) ?></button>

    <a class="brand" href="<?= e(url('')) ?>" aria-label="<?= e($store) ?> home"><?= logo_html() ?></a>

    <nav class="main-nav only-desktop" aria-label="Main">
      <a href="<?= e(url('shop.php')) ?>" class="<?= $current === 'shop.php' && !$activeCat ? 'active' : '' ?>">Shop all</a>
      <?php foreach ($navCats as $_nc): ?>
        <a href="<?= e(url('shop.php?category=' . rawurlencode($_nc['slug']))) ?>" class="<?= $activeCat === $_nc['slug'] ? 'active' : '' ?>"><?= e($_nc['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" type="button" data-open="searchPanel" aria-label="Search"><?= icon('search', 21) ?></button>
      <a class="icon-btn only-desktop" href="<?= e(url('track.php')) ?>" aria-label="Track order" title="Track order"><?= icon('box', 21) ?></a>
      <button class="icon-btn cart-btn" type="button" data-open="cartDrawer" aria-label="Open cart">
        <?= icon('cart', 22) ?>
        <span class="cart-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= (int)$cartCount ?></span>
      </button>
    </div>
  </div>
</header>

<!-- Search -->
<div class="search-panel" id="searchPanel" role="dialog" aria-modal="true" aria-label="Search" hidden>
  <div class="container">
    <form action="<?= e(url('shop.php')) ?>" method="get" class="search-form" role="search">
      <?= icon('search', 22) ?>
      <input type="search" name="q" placeholder="Search jerseys, teams, kits…" aria-label="Search products" autocomplete="off">
      <button type="button" class="icon-btn" data-close aria-label="Close search"><?= icon('close', 22) ?></button>
    </form>
    <div class="search-suggest">
      <span>Popular:</span>
      <?php foreach ($navCats as $_nc): ?><a href="<?= e(url('shop.php?category=' . rawurlencode($_nc['slug']))) ?>"><?= e($_nc['name']) ?></a><?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Mobile nav -->
<aside class="drawer drawer-left" id="navDrawer" aria-label="Menu" hidden>
  <div class="drawer-head">
    <?= logo_html() ?>
    <button class="icon-btn" type="button" data-close aria-label="Close menu"><?= icon('close', 22) ?></button>
  </div>
  <nav class="drawer-nav">
    <a href="<?= e(url('')) ?>">Home <?= icon('chevron', 18) ?></a>
    <a href="<?= e(url('shop.php')) ?>">Shop all <?= icon('chevron', 18) ?></a>
    <?php foreach (categories() as $_nc): ?>
      <a href="<?= e(url('shop.php?category=' . rawurlencode($_nc['slug']))) ?>"><?= e($_nc['name']) ?> <?= icon('chevron', 18) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('track.php')) ?>">Track your order <?= icon('chevron', 18) ?></a>
    <a href="<?= e(url('contact.php')) ?>">Contact <?= icon('chevron', 18) ?></a>
  </nav>
  <div class="drawer-foot">
    <a class="btn btn-whatsapp btn-block" href="<?= e(wa_link('Hi ' . $store . '! I have a question.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 20) ?> Chat on WhatsApp</a>
    <p class="muted small"><?= e(setting('phone')) ?> · <?= e(setting('hours')) ?></p>
  </div>
</aside>

<!-- Cart drawer -->
<aside class="drawer drawer-right" id="cartDrawer" aria-label="Shopping cart" hidden>
  <div class="drawer-head">
    <h2 class="drawer-title">Your cart <span class="muted" data-cart-count-text></span></h2>
    <button class="icon-btn" type="button" data-close aria-label="Close cart"><?= icon('close', 22) ?></button>
  </div>
  <div class="drawer-body" data-cart-body>
    <div class="cart-loading"><span class="spinner"></span></div>
  </div>
  <div class="drawer-foot" data-cart-foot hidden>
    <div class="ship-meter" data-ship-meter></div>
    <div class="sum-row"><span>Subtotal</span><strong data-cart-subtotal></strong></div>
    <p class="muted small">Delivery calculated at checkout. Pay cash when your jersey arrives.</p>
    <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('checkout.php')) ?>">Checkout <?= icon('arrow', 18) ?></a>
    <a class="btn btn-ghost btn-block" href="<?= e(url('cart.php')) ?>">View cart</a>
  </div>
</aside>
<div class="scrim" id="scrim" hidden></div>
<div class="toasts" id="toasts" aria-live="polite"></div>

<main id="main">
