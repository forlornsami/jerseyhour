<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$q        = trim((string)($_GET['q'] ?? ''));
$catSlug  = (string)($_GET['category'] ?? '');
$size     = (string)($_GET['size'] ?? '');
$min      = ($_GET['min'] ?? '') !== '' ? max(0, (int)$_GET['min']) : null;
$max      = ($_GET['max'] ?? '') !== '' ? max(0, (int)$_GET['max']) : null;
$inStock  = !empty($_GET['instock']);
$sort     = (string)($_GET['sort'] ?? 'new');
$page     = max(1, (int)($_GET['page'] ?? 1));

$sorts = [
    'new'        => ['Newest', 'p.created_at DESC'],
    'popular'    => ['Most popular', 'p.featured DESC, p.views DESC'],
    'price-asc'  => ['Price: low to high', 'p.price ASC'],
    'price-desc' => ['Price: high to low', 'p.price DESC'],
    'sale'       => ['On sale', 'p.created_at DESC'],
];
if (!isset($sorts[$sort])) $sort = 'new';

$where = ['p.active = 1'];
$args = [];
$category = null;
if ($catSlug !== '') {
    $category = row('SELECT * FROM categories WHERE slug = ?', [$catSlug]);
    if ($category) { $where[] = 'p.category_id = ?'; $args[] = $category['id']; }
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.badge LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like, $like);
}
if ($min !== null) { $where[] = 'p.price >= ?'; $args[] = $min; }
if ($max !== null && $max > 0) { $where[] = 'p.price <= ?'; $args[] = $max; }
if ($sort === 'sale') { $where[] = 'p.compare_price > p.price'; }

$all = rows(product_query_base() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sorts[$sort][1], $args);

// Size / stock filtering done in PHP (sizes are stored as JSON)
$all = array_values(array_filter($all, function ($p) use ($size, $inStock) {
    $sizes = product_sizes($p);
    if ($size !== '' && (int)($sizes[$size] ?? 0) <= 0) return false;
    if ($inStock && product_stock($p) <= 0) return false;
    return true;
}));

$pg = paginate(count($all), 12, $page);
$items = array_slice($all, $pg['offset'], $pg['per']);
$allSizes = default_sizes();
$priceRange = row('SELECT MIN(price) AS lo, MAX(price) AS hi FROM products WHERE active = 1') ?: ['lo' => 0, 'hi' => 0];

// Active filter chips
$chips = [];
$without = function (array $keys) { $qs = $_GET; foreach ($keys as $k) unset($qs[$k]); unset($qs['page']); return url('shop.php' . ($qs ? '?' . http_build_query($qs) : '')); };
if ($q !== '')       $chips[] = ['“' . $q . '”', $without(['q'])];
if ($category)       $chips[] = [$category['name'], $without(['category'])];
if ($size !== '')    $chips[] = ['Size ' . $size, $without(['size'])];
if ($min !== null || ($max !== null && $max > 0)) $chips[] = ['Price', $without(['min', 'max'])];
if ($inStock)        $chips[] = ['In stock', $without(['instock'])];

$heading = $category ? $category['name'] : ($q !== '' ? 'Search results' : ($sort === 'sale' ? 'On sale' : 'All jerseys'));
$title = $heading;
if ($category && $category['description']) $metaDesc = $category['description'];
$bodyClass = 'shop';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <?= breadcrumbs([['Home', url('')], ['Shop', $category || $q ? url('shop.php') : null], $category ? [$category['name'], null] : ($q !== '' ? ['Search', null] : ['All', null])]) ?>
    <h1><?= e($heading) ?></h1>
    <?php if ($category && $category['description']): ?><p class="muted"><?= e($category['description']) ?></p><?php endif; ?>
  </div>
</section>

<div class="container">
  <div class="cat-chips" role="list">
    <a role="listitem" class="chip <?= !$category ? 'active' : '' ?>" href="<?= e($without(['category'])) ?>">All</a>
    <?php foreach (categories() as $c): $qs = $_GET; $qs['category'] = $c['slug']; unset($qs['page']); ?>
      <a role="listitem" class="chip <?= $category && $category['id'] == $c['id'] ? 'active' : '' ?>" href="<?= e(url('shop.php?' . http_build_query($qs))) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="shop-layout">
    <aside class="filters" id="filterDrawer" aria-label="Filters">
      <div class="filters-head only-mobile">
        <h2>Filters</h2>
        <button class="icon-btn" type="button" data-close aria-label="Close filters"><?= icon('close', 22) ?></button>
      </div>
      <form method="get" action="<?= e(url('shop.php')) ?>" class="filter-form" data-autosubmit>
        <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category['slug']) ?>"><?php endif; ?>
        <?php if ($sort !== 'new'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>

        <div class="filter-group">
          <label class="filter-label" for="fq">Search</label>
          <div class="input-icon"><?= icon('search', 18) ?><input id="fq" type="search" name="q" value="<?= e($q) ?>" placeholder="Team, colour, style…"></div>
        </div>

        <fieldset class="filter-group">
          <legend class="filter-label">Size</legend>
          <div class="size-pills">
            <?php foreach ($allSizes as $s): ?>
              <label class="size-pill"><input type="radio" name="size" value="<?= e($s) ?>" <?= $size === $s ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="filter-group">
          <legend class="filter-label">Price (<?= e(setting('currency_symbol', 'Rs.')) ?>)</legend>
          <div class="price-inputs">
            <input type="number" name="min" inputmode="numeric" min="0" placeholder="<?= (int)$priceRange['lo'] ?>" value="<?= $min !== null ? (int)$min : '' ?>" aria-label="Minimum price">
            <span>–</span>
            <input type="number" name="max" inputmode="numeric" min="0" placeholder="<?= (int)$priceRange['hi'] ?>" value="<?= $max ? (int)$max : '' ?>" aria-label="Maximum price">
          </div>
        </fieldset>

        <label class="toggle">
          <input type="checkbox" name="instock" value="1" <?= $inStock ? 'checked' : '' ?>>
          <span class="toggle-ui"></span> In stock only
        </label>

        <div class="filter-actions">
          <button class="btn btn-primary btn-block" type="submit">Apply filters</button>
          <?php if ($chips): ?><a class="btn btn-ghost btn-block" href="<?= e(url('shop.php')) ?>">Clear all</a><?php endif; ?>
        </div>
      </form>
    </aside>

    <section class="shop-main">
      <div class="shop-toolbar">
        <button class="btn btn-ghost btn-sm only-mobile" type="button" data-open="filterDrawer"><?= icon('filter', 18) ?> Filters<?= $chips ? ' (' . count($chips) . ')' : '' ?></button>
        <p class="result-count"><strong><?= (int)$pg['total'] ?></strong> <?= $pg['total'] === 1 ? 'jersey' : 'jerseys' ?></p>
        <form method="get" class="sort-form">
          <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page'], true) || is_array($v)) continue; ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
          <?php endforeach; ?>
          <label class="visually-hidden" for="sort">Sort by</label>
          <select id="sort" name="sort" onchange="this.form.submit()">
            <?php foreach ($sorts as $k => [$label]): ?><option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>

      <?php if ($chips): ?>
        <div class="active-chips">
          <?php foreach ($chips as [$label, $href]): ?>
            <a class="chip chip-x" href="<?= e($href) ?>" aria-label="Remove filter <?= e($label) ?>"><?= e($label) ?> <?= icon('close', 14) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($items): ?>
        <div class="product-grid grid-3">
          <?php foreach ($items as $i => $p) echo product_card($p, $i < 3); ?>
        </div>
        <?= render_pagination($pg) ?>
      <?php else: ?>
        <div class="empty">
          <div class="empty-icon"><?= icon('search', 30) ?></div>
          <h2>No jerseys found</h2>
          <p class="muted">Try a different size or search term — or ask us on WhatsApp, we might have it in store.</p>
          <div class="row-gap center">
            <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">See all jerseys</a>
            <a class="btn btn-ghost" href="<?= e(wa_link('Hi! Do you have ' . ($q ?: 'this jersey') . '?')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 18) ?> Ask us</a>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
