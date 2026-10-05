<?php
/** @var string $pageTitle  @var string $active */
$_cnt = admin_counts();
$_nav = [
    ['index.php', 'dashboard', 'Dashboard', 'grid'],
    ['orders.php', 'orders', 'Orders', 'box', $_cnt['pending']],
    ['products.php', 'products', 'Products', 'shirt'],
    ['categories.php', 'categories', 'Categories', 'tag'],
    ['messages.php', 'messages', 'Messages', 'mail', $_cnt['messages']],
    ['pages.php', 'pages', 'Pages', 'doc'],
    ['settings.php', 'settings', 'Settings', 'cog'],
];
$aIcons = [
    'grid'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'box'   => '<path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/>',
    'shirt' => '<path d="M8 3 3 6l2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/>',
    'tag'   => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
    'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'doc'   => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/>',
    'cog'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'ext'   => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    'out'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
];
$ai = fn($_n, $s = 20) => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($aIcons[$_n] ?? '') . '</svg>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> · <?= e(setting('store_name', 'JerseyHour')) ?> Admin</title>
<?= brand_head() ?>
<link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>?v=<?= @filemtime(APP_ROOT . '/admin/assets/admin.css') ?>">
<script>window.JHA = <?= json_encode(['base' => BASE, 'csrf' => csrf_token()]) ?>;</script>
<script src="<?= e(url('admin/assets/admin.js')) ?>?v=<?= @filemtime(APP_ROOT . '/admin/assets/admin.js') ?>" defer></script>
</head>
<body class="admin">
<aside class="sidebar" id="sidebar">
  <a class="sb-brand" href="<?= e(admin_url()) ?>"><?= logo_html('dark') ?></a>
  <nav class="sb-nav" aria-label="Admin">
    <?php foreach ($_nav as $_n): ?>
      <a href="<?= e(admin_url($_n[0])) ?>" class="<?= ($active ?? '') === $_n[1] ? 'active' : '' ?>" <?= ($active ?? '') === $_n[1] ? 'aria-current="page"' : '' ?>>
        <?= $ai($_n[3]) ?><span><?= e($_n[2]) ?></span>
        <?php if (!empty($_n[4])): ?><em class="sb-count"><?= (int)$_n[4] ?></em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sb-foot">
    <a href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= $ai('ext', 18) ?><span>View store</span></a>
    <a href="<?= e(admin_url('account.php')) ?>" class="<?= ($active ?? '') === 'account' ? 'active' : '' ?>"><?= $ai('user', 18) ?><span><?= e($ADMIN['username']) ?></span></a>
    <form method="post" action="<?= e(admin_url('logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= $ai('out', 18) ?><span>Sign out</span></button></form>
  </div>
</aside>
<div class="sb-scrim" data-sb-close></div>

<div class="main">
  <header class="topbar">
    <button class="icon-btn sb-toggle" type="button" data-sb-open aria-label="Open menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
    <h1 class="topbar-title"><?= e($pageTitle ?? '') ?></h1>
    <div class="topbar-actions"><?= $topActions ?? '' ?></div>
  </header>
  <div class="content">
    <?php foreach (flashes() as $_fl): ?>
      <div class="alert alert-<?= e($_fl["type"] === "success" ? "ok" : ($_fl["type"] === "error" ? "error" : "warn")) ?>" data-autodismiss><?= e($_fl["msg"]) ?></div>
    <?php endforeach; ?>
