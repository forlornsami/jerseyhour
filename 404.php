<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/partials.php';
http_response_code(404);
$title = 'Page not found';
$bodyClass = 'notfound';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="empty empty-404">
    <div class="big-404">4<span>0</span>4</div>
    <h1>Offside! This page doesn't exist.</h1>
    <p class="muted">The link may be broken or the jersey may have sold out. Let's get you back in the game.</p>
    <div class="row-gap center">
      <a class="btn btn-primary btn-lg" href="<?= e(url('shop.php')) ?>">Browse jerseys</a>
      <a class="btn btn-ghost btn-lg" href="<?= e(url('')) ?>">Go home</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
