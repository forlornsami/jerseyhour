<?php
/**
 * Storefront view helpers.
 */

function product_card(array $p, bool $eager = false): string
{
    $imgs  = product_images($p);
    $cover = img_url($imgs[0] ?? null);
    $alt   = isset($imgs[1]) ? img_url($imgs[1]) : '';
    $stock = product_stock($p);
    $off   = discount_pct($p);
    $url   = product_url($p);
    $sizes = array_keys(array_filter(product_sizes($p), fn($q) => (int)$q > 0));

    $badge = '';
    if ($stock <= 0) {
        $badge = '<span class="badge badge-muted">Sold out</span>';
    } elseif ($off > 0) {
        $badge = '<span class="badge badge-sale">-' . $off . '%</span>';
    } elseif (!empty($p['badge'])) {
        $badge = '<span class="badge">' . e($p['badge']) . '</span>';
    }

    $h  = '<article class="card' . ($stock <= 0 ? ' is-soldout' : '') . '">';
    $h .= '<a class="card-media" href="' . e($url) . '" tabindex="-1" aria-hidden="true">';
    $h .= '<img src="' . e($cover) . '" alt="" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async" width="800" height="1000">';
    if ($alt) $h .= '<img class="card-alt" src="' . e($alt) . '" alt="" loading="lazy" decoding="async" width="800" height="1000">';
    $h .= $badge . '</a>';
    $h .= '<div class="card-body">';
    if (!empty($p['category_name'])) $h .= '<span class="card-cat">' . e($p['category_name']) . '</span>';
    $h .= '<h3 class="card-title"><a href="' . e($url) . '">' . e($p['name']) . '</a></h3>';
    $h .= '<div class="price-row"><span class="price">' . e(money($p['price'])) . '</span>';
    if ($off > 0) $h .= '<s class="price-old">' . e(money($p['compare_price'])) . '</s>';
    $h .= '</div>';
    if ($sizes) {
        $h .= '<div class="card-sizes" aria-label="Available sizes">';
        foreach (array_slice($sizes, 0, 6) as $s) $h .= '<span>' . e($s) . '</span>';
        $h .= '</div>';
    }
    $h .= '</div></article>';
    return $h;
}

function product_query_base(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p LEFT JOIN categories c ON c.id = p.category_id';
}

function breadcrumbs(array $items): string
{
    $h = '<nav class="crumbs" aria-label="Breadcrumb"><ol>';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $href]) {
        $h .= $i === $last || !$href
            ? '<li aria-current="page">' . e($label) . '</li>'
            : '<li><a href="' . e($href) . '">' . e($label) . '</a></li>';
    }
    return $h . '</ol></nav>';
}

function order_timeline(array $o): string
{
    if ($o['status'] === 'cancelled') {
        return '<div class="alert alert-error">This order was cancelled. Contact us on WhatsApp if you think this is a mistake.</div>';
    }
    $steps = [
        'pending'   => ['Order placed', 'We received your order'],
        'confirmed' => ['Confirmed', 'We verified your order by phone'],
        'shipped'   => ['Shipped', 'Your parcel is with the courier'],
        'delivered' => ['Delivered', 'Enjoy your new jersey!'],
    ];
    $keys = array_keys($steps);
    $at = array_search($o['status'], $keys, true);
    $h = '<ol class="timeline">';
    foreach ($steps as $k => [$t, $d]) {
        $i = array_search($k, $keys, true);
        $cls = $i < $at ? 'done' : ($i === $at ? 'current' : '');
        $h .= '<li class="' . $cls . '"><span class="tl-dot">' . ($i <= $at ? icon('check', 14) : '') . '</span><div><strong>' . e($t) . '</strong><span>' . e($d) . '</span></div></li>';
    }
    return $h . '</ol>';
}

function page_title(string $t = ''): string
{
    $store = setting('store_name', 'JerseyHour');
    return $t ? $t . ' — ' . $store : $store . ' — ' . setting('tagline', 'Football & Cricket Jerseys in Pakistan');
}
