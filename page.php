<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$slug = (string)($_GET['p'] ?? '');
$page = $slug === 'size-guide' ? size_guide_page() : ($slug !== '' ? row('SELECT * FROM pages WHERE slug = ?', [$slug]) : null);
if (!$page) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$others = rows('SELECT slug, title FROM pages WHERE slug <> ? ORDER BY title', [$slug]);
$title = $page['title'];
$metaDesc = excerpt($page['content'], 155);
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container narrow">
    <?= breadcrumbs([['Home', url('')], [$page['title'], null]]) ?>
    <h1><?= e($page['title']) ?></h1>
    <p class="muted small">Last updated <?= e(nice_date($page['updated_at'], false)) ?></p>
  </div>
</section>
<div class="container narrow">
  <article class="rich rich-page">
    <?php if ($slug === 'size-guide'): ?>
      <figure class="size-guide-img"><img src="<?= e(asset(SIZE_GUIDE_IMAGE)) ?>" alt="JerseyHour size chart: chest and length in inches for sizes XS to XXXL" width="1312" height="1200"></figure>
    <?php endif; ?>
    <?= rich_text($page['content']) ?>
  </article>
  <?php if ($others): ?>
    <nav class="page-links" aria-label="More information">
      <?php foreach ($others as $o): ?><a class="chip" href="<?= e(url('page.php?p=' . rawurlencode($o['slug']))) ?>"><?= e($o['title']) ?></a><?php endforeach; ?>
    </nav>
  <?php endif; ?>
</div>
<div class="spacer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
