<?php $store = setting('store_name', 'JerseyHour'); ?>
</main>

<section class="trust-strip" aria-label="Why shop with us">
  <div class="container trust-grid">
    <div class="trust-item"><?= icon('cash', 26) ?><div><strong>Cash on Delivery</strong><span>Pay when it arrives</span></div></div>
    <div class="trust-item"><?= icon('truck', 26) ?><div><strong>Nationwide delivery</strong><span><?= e(setting('delivery_time', '2 – 5 working days')) ?></span></div></div>
    <div class="trust-item"><?= icon('refresh', 26) ?><div><strong>7-day size exchange</strong><span>Hassle-free swaps</span></div></div>
    <div class="trust-item"><?= icon('shield', 26) ?><div><strong>Quality checked</strong><span>Every jersey inspected</span></div></div>
  </div>
</section>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <?= logo_html('dark') ?>
      <p><?= e(setting('tagline')) ?></p>
      <div class="socials">
        <?php foreach (['instagram', 'facebook', 'tiktok'] as $s): if ($link = setting($s)): ?>
          <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= ucfirst($s) ?>"><?= icon($s, 20) ?></a>
        <?php endif; endforeach; ?>
        <a href="<?= e(wa_link()) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('whatsapp', 20) ?></a>
      </div>
    </div>
    <div>
      <h4>Shop</h4>
      <ul>
        <li><a href="<?= e(url('shop.php')) ?>">All jerseys</a></li>
        <?php foreach (categories() as $c): ?><li><a href="<?= e(url('shop.php?category=' . rawurlencode($c['slug']))) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(url('shop.php?sort=sale')) ?>">On sale</a></li>
      </ul>
    </div>
    <div>
      <h4>Help</h4>
      <ul>
        <li><a href="<?= e(url('track.php')) ?>">Track your order</a></li>
        <li><a href="<?= e(url('page.php?p=size-guide')) ?>">Size guide</a></li>
        <li><a href="<?= e(url('page.php?p=shipping-policy')) ?>">Shipping policy</a></li>
        <li><a href="<?= e(url('page.php?p=returns-exchange')) ?>">Returns &amp; exchange</a></li>
        <li><a href="<?= e(url('page.php?p=about')) ?>">About us</a></li>
        <li><a href="<?= e(url('contact.php')) ?>">Contact</a></li>
      </ul>
    </div>
    <div>
      <h4>Visit or call</h4>
      <ul class="contact-list">
        <li><?= icon('pin', 18) ?><span><?= e(setting('address')) ?></span></li>
        <li><?= icon('phone', 18) ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
        <li><?= icon('mail', 18) ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
        <li><?= icon('clock', 18) ?><span><?= e(setting('hours')) ?></span></li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e($store) ?> · jerseyhour.com</span>
    <span class="footer-links"><a href="<?= e(url('page.php?p=privacy-policy')) ?>">Privacy</a><a href="<?= e(url('page.php?p=returns-exchange')) ?>">Returns</a></span>
  </div>
</footer>

<a class="wa-float" href="<?= e(wa_link('Hi ' . $store . '! ')) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><?= icon('whatsapp', 26) ?></a>
</body>
</html>
