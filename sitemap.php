<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$root = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'jerseyhour.com');
$urls = [[url(''), date('Y-m-d')], [url('shop.php'), date('Y-m-d')], [url('contact.php'), null]];
foreach (rows('SELECT slug FROM categories') as $c) $urls[] = [url('shop.php?category=' . rawurlencode($c['slug'])), null];
foreach (rows('SELECT slug, updated_at FROM products WHERE active = 1') as $p) $urls[] = [url('product.php?s=' . rawurlencode($p['slug'])), substr($p['updated_at'], 0, 10)];
foreach (rows('SELECT slug, updated_at FROM pages') as $p) $urls[] = [url('page.php?p=' . rawurlencode($p['slug'])), substr($p['updated_at'], 0, 10)];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$u, $d]) {
    echo '  <url><loc>' . htmlspecialchars($root . $u, ENT_XML1) . '</loc>' . ($d ? '<lastmod>' . $d . '</lastmod>' : '') . "</url>\n";
}
echo '</urlset>';
