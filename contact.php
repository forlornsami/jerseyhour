<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$errors = [];
$sent = false;
$in = ['name' => trim((string)($_POST['name'] ?? '')), 'phone' => trim((string)($_POST['phone'] ?? '')), 'email' => trim((string)($_POST['email'] ?? '')), 'message' => trim((string)($_POST['message'] ?? ''))];

if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) redirect('');
    if (mb_strlen($in['name']) < 2) $errors['name'] = 'Please enter your name.';
    if ($in['phone'] === '' && $in['email'] === '') $errors['phone'] = 'Add a phone number or email so we can reply.';
    if ($in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'That email doesn\'t look right.';
    if (mb_strlen($in['message']) < 5) $errors['message'] = 'Please write a short message.';
    if (mb_strlen($in['message']) > 2000) $errors['message'] = 'Message is too long (2000 characters max).';
    $last = $_SESSION['last_contact'] ?? 0;
    if (time() - $last < 30) $errors['form'] = 'Please wait a moment before sending another message.';
    if (!$errors) {
        q('INSERT INTO messages (name, phone, email, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)',
          [mb_substr($in['name'], 0, 120), mb_substr($in['phone'], 0, 40), mb_substr($in['email'], 0, 150), $in['message'], now()]);
        $_SESSION['last_contact'] = time();
        $sent = true;
        $in = ['name' => '', 'phone' => '', 'email' => '', 'message' => ''];
    }
}
$mapQ = rawurlencode(setting('address'));
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

$title = 'Contact us';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([['Home', url('')], ['Contact', null]]) ?>
    <h1>Get in touch</h1>
    <p class="muted">Questions about sizes, stock or an order? We usually reply within an hour during store hours.</p>
  </div>
</section>

<div class="container">
  <div class="contact-cards">
    <a class="contact-card" href="<?= e(wa_link('Hi ' . setting('store_name', 'JerseyHour') . '!')) ?>" target="_blank" rel="noopener">
      <span class="cc-icon cc-wa"><?= icon('whatsapp', 24) ?></span><strong>WhatsApp</strong><span><?= e(setting('phone')) ?></span><em>Fastest reply →</em>
    </a>
    <a class="contact-card" href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>">
      <span class="cc-icon"><?= icon('phone', 24) ?></span><strong>Call us</strong><span><?= e(setting('phone')) ?></span><em><?= e(setting('hours')) ?></em>
    </a>
    <a class="contact-card" href="mailto:<?= e(setting('email')) ?>">
      <span class="cc-icon"><?= icon('mail', 24) ?></span><strong>Email</strong><span><?= e(setting('email')) ?></span><em>Reply within 24 hours</em>
    </a>
    <a class="contact-card" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e($mapQ) ?>" target="_blank" rel="noopener">
      <span class="cc-icon"><?= icon('pin', 24) ?></span><strong>Visit</strong><span><?= e(setting('address')) ?></span><em>Get directions →</em>
    </a>
  </div>

  <div class="contact-grid">
    <form method="post" class="panel" novalidate>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <h2 class="panel-title">Send a message</h2>
      <?php if ($sent): ?><div class="alert alert-success" role="status">Thanks! Your message has been sent — we'll get back to you soon.</div><?php endif; ?>
      <?php if (!empty($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label for="cname">Your name</label><input id="cname" name="name" value="<?= e($in['name']) ?>" autocomplete="name" required><?= $err('name') ?></div>
      <div class="grid-2">
        <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>"><label for="cphone">Phone</label><input id="cphone" name="phone" type="tel" value="<?= e($in['phone']) ?>" autocomplete="tel"><?= $err('phone') ?></div>
        <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>"><label for="cemail">Email</label><input id="cemail" name="email" type="email" value="<?= e($in['email']) ?>" autocomplete="email"><?= $err('email') ?></div>
      </div>
      <div class="field <?= isset($errors['message']) ? 'has-error' : '' ?>"><label for="cmsg">Message</label><textarea id="cmsg" name="message" rows="5" required><?= e($in['message']) ?></textarea><?= $err('message') ?></div>
      <button class="btn btn-primary btn-lg" type="submit" data-submit-once>Send message</button>
    </form>
    <div class="map-wrap">
      <iframe title="Store location map" src="https://www.google.com/maps?q=<?= e($mapQ) ?>&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</div>
<div class="spacer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
