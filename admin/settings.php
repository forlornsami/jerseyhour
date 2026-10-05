<?php
require __DIR__ . '/partials/init.php';

$groups = [
    'branding' => ['Logo & branding', 'Your logo appears in the header, footer, admin panel and browser tab.', [
        'logo_image'  => ['Logo', 'image', '', 'PNG with a transparent background works best (at least 500 px wide). Remove it to go back to the default JerseyHour logo.'],
        'logo_light'  => ['Logo for dark backgrounds', 'image', '', 'Optional white/light version for the footer. If empty: the default JerseyHour light logo is used, or your uploaded logo is shown on a white badge.'],
        'logo_height' => ['Logo height in header (px)', 'number', '50', 'Between 24 and 80. Mobile uses 80% of this.'],
        'favicon'     => ['Browser tab icon (favicon)', 'image', '', 'Square PNG, 64×64 px or larger. Default is the football from your logo.'],
        'brand_color' => ['Brand colour', 'color', '#7A2230', 'Used for buttons, badges, the announcement bar and highlights.'],
    ]],
    'store' => ['Store details', 'How customers reach you. Shown in the header, footer and contact page.', [
        'store_name' => ['Store name', 'text'],
        'tagline'    => ['Tagline', 'text'],
        'phone'      => ['Phone (display)', 'text', '+92 342 0052034'],
        'whatsapp'   => ['WhatsApp number', 'text', '+923420052034', 'Used for all WhatsApp buttons. Include country code.'],
        'email'      => ['Email', 'email'],
        'address'    => ['Store address', 'text'],
        'hours'      => ['Opening hours', 'text'],
    ]],
    'shipping' => ['Delivery & pricing', 'Applied at checkout.', [
        'delivery_fee'       => ['Delivery charge', 'number', '250'],
        'free_shipping_over' => ['Free delivery on orders over', 'number', '5000', 'Set 0 to disable free delivery.'],
        'delivery_time'      => ['Delivery time (shown to customers)', 'text', '2 – 5 working days'],
        'currency_symbol'    => ['Currency symbol', 'text', 'Rs.'],
        'size_list'          => ['Default sizes for new products', 'text', 'XS,S,M,L,XL,XXL,XXXL', 'Comma-separated. Also used by the size filter in the shop.'],
    ]],
    'home' => ['Homepage', 'The first thing visitors see.', [
        'announcement'  => ['Announcement bar', 'text', '', 'Leave empty to hide the top bar.'],
        'hero_eyebrow'  => ['Hero label', 'text'],
        'hero_title'    => ['Hero headline', 'text'],
        'hero_subtitle' => ['Hero text', 'textarea'],
        'hero_image'    => ['Hero image', 'image', '', 'Optional. If empty, your fan-favourite jerseys are shown as a stack.'],
    ]],
    'social' => ['Social & SEO', 'Links appear as icons in the footer.', [
        'instagram'        => ['Instagram URL', 'url', 'https://instagram.com/jerseyhour'],
        'facebook'         => ['Facebook URL', 'url', 'https://facebook.com/jerseyhour'],
        'tiktok'           => ['TikTok URL', 'url', 'https://tiktok.com/@jerseyhour'],
        'meta_description' => ['Search engine description', 'textarea', '', 'About 150 characters — shown on Google results.'],
    ]],
    'notify' => ['Notifications', 'Get told about new orders.', [
        'order_notify_email' => ['Email new orders to', 'email', '', 'Requires your hosting to support PHP mail(). Leave empty to disable.'],
    ]],
];

if (is_post()) {
    csrf_check();
    $errs = [];
    foreach ($groups as $g) {
        foreach ($g[2] as $key => $def) {
            $type = $def[1];
            if ($type === 'image') {
                if (!empty($_POST['remove_' . $key])) { delete_upload(setting($key)); save_setting($key, ''); }
                $files = files_list($key);
                if ($files) {
                    try { $path = store_upload($files[0], 'site', 1800); delete_upload(setting($key)); save_setting($key, $path); }
                    catch (Throwable $t) { $errs[] = $def[0] . ': ' . $t->getMessage(); }
                }
                continue;
            }
            $v = trim((string)($_POST[$key] ?? ''));
            if ($type === 'number') $v = (string)max(0, (float)$v);
            if ($key === 'logo_height') $v = (string)max(24, min(80, (int)$v ?: 50));
            if ($type === 'color' && !preg_match('~^#[0-9a-f]{6}$~i', $v)) { $errs[] = $def[0] . ' must be a colour like #7A2230.'; continue; }
            if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errs[] = $def[0] . ' is not a valid email.'; continue; }
            if ($type === 'url' && $v !== '' && !preg_match('~^https?://~i', $v)) $v = 'https://' . $v;
            if ($key === 'store_name' && $v === '') { $errs[] = 'Store name cannot be empty.'; continue; }
            save_setting($key, mb_substr($v, 0, 2000));
        }
    }
    settings_all(true);
    if ($errs) foreach ($errs as $e) flash('error', $e); else flash('success', 'Settings saved.');
    redirect('admin/settings.php' . (!empty($_POST['tab']) ? '#' . preg_replace('~\W~', '', $_POST['tab']) : ''));
}

$pageTitle = 'Settings';
$active = 'settings';
require __DIR__ . '/partials/top.php';
?>
<form method="post" enctype="multipart/form-data" class="settings" data-dirty-guard>
  <?= csrf_field() ?>
  <nav class="settings-nav" aria-label="Settings sections">
    <?php foreach ($groups as $gk => $g): ?><a href="#<?= e($gk) ?>"><?= e($g[0]) ?></a><?php endforeach; ?>
  </nav>
  <div class="settings-body">
    <?php foreach ($groups as $gk => $g): ?>
      <section class="card settings-group" id="<?= e($gk) ?>">
        <div class="sg-head"><h2 class="card-title"><?= e($g[0]) ?></h2><p class="muted small"><?= e($g[1]) ?></p></div>
        <div class="sg-fields">
          <?php foreach ($g[2] as $key => $def): $val = setting($key); $id = 'set_' . $key; ?>
            <div class="field">
              <label for="<?= e($id) ?>"><?= e($def[0]) ?></label>
              <?php if ($def[1] === 'textarea'): ?>
                <textarea id="<?= e($id) ?>" name="<?= e($key) ?>" rows="3" placeholder="<?= e($def[2] ?? '') ?>"><?= e($val) ?></textarea>
              <?php elseif ($def[1] === 'image'): ?>
                <?php if ($val): ?>
                  <div class="img-current <?= $key === 'logo_light' ? 'on-dark' : '' ?>"><img src="<?= e(img_url($val)) ?>" alt="" height="<?= $key === 'favicon' ? 48 : 70 ?>"><label class="check"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> Remove</label></div>
                <?php elseif ($key === 'logo_image'): ?>
                  <div class="img-current"><img src="<?= e(url('assets/img/logo.png')) ?>" alt="" height="70"><span class="muted small">Default logo</span></div>
                <?php elseif ($key === 'logo_light' && !setting('logo_image')): ?>
                  <div class="img-current on-dark"><img src="<?= e(url('assets/img/logo-light.png')) ?>" alt="" height="70"><span class="small" style="color:#fff">Default light logo</span></div>
                <?php elseif ($key === 'favicon'): ?>
                  <div class="img-current"><img src="<?= e(url('assets/img/favicon.png')) ?>" alt="" height="48"><span class="muted small">Default icon</span></div>
                <?php endif; ?>
                <input id="<?= e($id) ?>" type="file" name="<?= e($key) ?>" accept="image/*">
              <?php elseif ($def[1] === 'color'): $cv = preg_match('~^#[0-9a-f]{6}$~i', $val) ? $val : ($def[2] ?? '#7A2230'); ?>
                <div class="color-field">
                  <input type="color" value="<?= e($cv) ?>" aria-label="Pick colour" oninput="this.nextElementSibling.value=this.value;this.nextElementSibling.dispatchEvent(new Event('input',{bubbles:true}))">
                  <input id="<?= e($id) ?>" name="<?= e($key) ?>" value="<?= e($cv) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" oninput="if(/^#[0-9a-f]{6}$/i.test(this.value))this.previousElementSibling.value=this.value">
                  <button type="button" class="btn btn-sm btn-ghost" onclick="var t=this.previousElementSibling;t.value='<?= e($def[2]) ?>';t.previousElementSibling.value=t.value;t.dispatchEvent(new Event('input',{bubbles:true}))">Reset</button>
                </div>
              <?php else: ?>
                <input id="<?= e($id) ?>" type="<?= $def[1] === 'number' ? 'number' : ($def[1] === 'url' ? 'text' : e($def[1])) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" placeholder="<?= e($def[2] ?? '') ?>" <?= $def[1] === 'number' ? 'min="0" step="1" inputmode="numeric"' : '' ?>>
              <?php endif; ?>
              <?php if (!empty($def[3])): ?><p class="hint"><?= e($def[3]) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="save-bar"><span class="muted small" data-dirty-note>All changes saved</span><button class="btn btn-primary btn-lg" type="submit">Save settings</button></div>
  </div>
</form>
<?php require __DIR__ . '/partials/bottom.php'; ?>
