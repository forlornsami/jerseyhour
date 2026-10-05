<?php
/**
 * JerseyHour — shared helpers
 */

// ---------------------------------------------------------------- output ---
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function img_url(?string $path): string
{
    if (!$path) return url('assets/img/placeholder.svg');
    if (preg_match('~^https?://~i', $path)) return $path;
    return url($path);
}

function money($amount): string
{
    $sym = setting('currency_symbol', 'Rs.');
    return $sym . ' ' . number_format((float)$amount, 0);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function nice_date(?string $dt, bool $time = true): string
{
    if (!$dt) return '';
    $ts = strtotime($dt);
    return $time ? date('d M Y, g:i A', $ts) : date('d M Y', $ts);
}

function redirect(string $to): void
{
    if (!preg_match('~^https?://~', $to) && strpos($to, BASE . '/') !== 0 && $to !== BASE) {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

// -------------------------------------------------------------- settings ---
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        try {
            foreach (rows('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable $t) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function save_setting(string $key, $value): void
{
    $exists = val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key]);
    if ($exists) {
        q('UPDATE settings SET svalue = ? WHERE skey = ?', [(string)$value, $key]);
    } else {
        q('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, (string)$value]);
    }
}

// ------------------------------------------------------------------ CSRF ---
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ----------------------------------------------------------------- flash ---
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ------------------------------------------------------------------ text ---
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : 'item';
}

function unique_slug(string $table, string $base, int $ignoreId = 0): string
{
    $slug = slugify($base);
    $try = $slug;
    $i = 2;
    while (val("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?", [$try, $ignoreId])) {
        $try = $slug . '-' . $i++;
    }
    return $try;
}

function excerpt(?string $text, int $len = 140): string
{
    $t = trim(preg_replace('~\s+~', ' ', strip_tags((string)$text)));
    if (mb_strlen($t) <= $len) return $t;
    return rtrim(mb_substr($t, 0, $len - 1)) . '…';
}

/** Minimal, safe formatting for admin-written text: paragraphs, line breaks, **bold**, - bullet lists, ## headings. */
function rich_text(?string $text): string
{
    $text = str_replace("\r\n", "\n", trim((string)$text));
    if ($text === '') return '';
    $blocks = preg_split("~\n{2,}~", $text);
    $html = '';
    foreach ($blocks as $b) {
        $lines = explode("\n", $b);
        $isList = count(array_filter($lines, fn($l) => preg_match('~^\s*[-•*]\s+~', $l))) === count($lines);
        $fmt = function ($s) {
            $s = e($s);
            return preg_replace('~\*\*(.+?)\*\*~', '<strong>$1</strong>', $s);
        };
        $isTable = count(array_filter($lines, fn($l) => preg_match('~^\s*\|~', $l))) === count($lines);
        if ($isTable) {
            $html .= '<div class="table-wrap"><table class="rt-table">';
            foreach ($lines as $i => $l) {
                $cells = array_map('trim', explode('|', trim(trim($l), '|')));
                $tag = $i === 0 ? 'th' : 'td';
                $html .= '<tr>' . implode('', array_map(fn($c) => "<$tag>" . $fmt($c) . "</$tag>", $cells)) . '</tr>';
            }
            $html .= '</table></div>';
        } elseif ($isList) {
            $html .= '<ul>';
            foreach ($lines as $l) $html .= '<li>' . $fmt(preg_replace('~^\s*[-•*]\s+~', '', $l)) . '</li>';
            $html .= '</ul>';
        } elseif (preg_match('~^##\s+(.+)$~', $b, $m)) {
            $html .= '<h3>' . $fmt($m[1]) . '</h3>';
        } else {
            $html .= '<p>' . implode('<br>', array_map($fmt, $lines)) . '</p>';
        }
    }
    return $html;
}

// -------------------------------------------------------------- products ---
function product_sizes(array $p): array
{
    $s = json_decode((string)($p['sizes'] ?? ''), true);
    return is_array($s) ? $s : [];
}

function product_images(array $p): array
{
    $i = json_decode((string)($p['images'] ?? ''), true);
    return is_array($i) ? array_values(array_filter($i)) : [];
}

function product_cover(array $p): string
{
    $imgs = product_images($p);
    return img_url($imgs[0] ?? null);
}

function product_stock(array $p): int
{
    return (int)array_sum(array_map('intval', product_sizes($p)));
}

function product_url(array $p): string
{
    return url('product.php?s=' . rawurlencode($p['slug']));
}

function discount_pct(array $p): int
{
    $c = (float)($p['compare_price'] ?? 0);
    $pr = (float)$p['price'];
    if ($c > $pr && $c > 0) return (int)round((($c - $pr) / $c) * 100);
    return 0;
}

function default_sizes(): array
{
    $list = array_filter(array_map('trim', explode(',', setting('size_list', 'XS,S,M,L,XL,XXL'))));
    return $list ?: ['S', 'M', 'L', 'XL'];
}

function categories(): array
{
    static $c = null;
    if ($c === null) {
        $c = rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS product_count
                   FROM categories c ORDER BY c.sort_order, c.name');
    }
    return $c;
}

// ------------------------------------------------------------------ cart ---
function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_key(int $pid, string $size): string
{
    return $pid . '|' . $size;
}

/** Returns hydrated cart lines + totals, silently dropping unavailable items. */
function cart_summary(): array
{
    $lines = [];
    $subtotal = 0.0;
    $count = 0;
    $raw = cart_raw();
    if ($raw) {
        $ids = array_unique(array_map(fn($k) => (int)explode('|', $k)[0], array_keys($raw)));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $products = [];
        foreach (rows("SELECT * FROM products WHERE active = 1 AND id IN ($in)", $ids) as $p) {
            $products[(int)$p['id']] = $p;
        }
        foreach ($raw as $key => $qty) {
            [$pid, $size] = array_pad(explode('|', $key, 2), 2, '');
            $p = $products[(int)$pid] ?? null;
            if (!$p) { unset($_SESSION['cart'][$key]); continue; }
            $sizes = product_sizes($p);
            $avail = $size !== '' ? (int)($sizes[$size] ?? 0) : product_stock($p);
            $qty = max(0, min((int)$qty, $avail));
            if ($qty < 1) { unset($_SESSION['cart'][$key]); continue; }
            $_SESSION['cart'][$key] = $qty;
            $line = (float)$p['price'] * $qty;
            $subtotal += $line;
            $count += $qty;
            $lines[] = [
                'key'   => $key,
                'id'    => (int)$p['id'],
                'name'  => $p['name'],
                'size'  => $size,
                'price' => (float)$p['price'],
                'qty'   => $qty,
                'max'   => $avail,
                'line'  => $line,
                'image' => product_cover($p),
                'url'   => product_url($p),
                'raw_image' => product_images($p)[0] ?? '',
            ];
        }
    }
    $fee = delivery_fee($subtotal);
    $threshold = (float)setting('free_shipping_over', '0');
    return [
        'lines'     => $lines,
        'count'     => $count,
        'subtotal'  => $subtotal,
        'delivery'  => $count ? $fee : 0,
        'total'     => $subtotal + ($count ? $fee : 0),
        'threshold' => $threshold,
        'remaining' => $threshold > 0 ? max(0, $threshold - $subtotal) : 0,
    ];
}

function delivery_fee(float $subtotal): float
{
    $fee = (float)setting('delivery_fee', '250');
    $free = (float)setting('free_shipping_over', '0');
    if ($free > 0 && $subtotal >= $free) return 0.0;
    return $fee;
}

// ---------------------------------------------------------------- orders ---
function order_statuses(): array
{
    return [
        'pending'   => 'Pending',
        'confirmed' => 'Confirmed',
        'shipped'   => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
}

function status_label(string $s): string
{
    return order_statuses()[$s] ?? ucfirst($s);
}

function generate_order_no(): string
{
    do {
        $no = 'JH' . date('ymd') . '-' . random_int(1000, 9999);
    } while (val('SELECT COUNT(*) FROM orders WHERE order_no = ?', [$no]));
    return $no;
}

function normalize_phone(string $p): string
{
    $d = preg_replace('~\D+~', '', $p);
    if (strpos($d, '92') === 0) $d = '0' . substr($d, 2);
    if ($d !== '' && $d[0] !== '0') $d = '0' . $d;
    return $d;
}

function wa_number(): string
{
    $n = preg_replace('~\D+~', '', setting('whatsapp', setting('phone', '')));
    if (strpos($n, '0') === 0) $n = '92' . substr($n, 1);
    return $n;
}

function wa_link(string $text = '', ?string $number = null): string
{
    $n = $number !== null ? preg_replace('~\D+~', '', $number) : wa_number();
    if (strpos($n, '0') === 0) $n = '92' . substr($n, 1);
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

// --------------------------------------------------------------- uploads ---
/**
 * Validates and stores an uploaded image. Returns relative path or throws.
 * Images are resized to $maxW if GD is available.
 */
function store_upload(array $file, string $folder = 'products', int $maxW = 1400): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . (int)($file['error'] ?? -1) . ').');
    }
    $maxBytes = 8 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image is larger than 8 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }
    $ext = $allowed[$info[2]];
    $dir = APP_ROOT . '/uploads/' . $folder;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;

    $resized = false;
    if (function_exists('imagecreatetruecolor') && $info[0] > $maxW && $ext !== 'gif') {
        $src = null;
        if ($ext === 'jpg' && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($file['tmp_name']);
        if ($ext === 'png' && function_exists('imagecreatefrompng'))  $src = @imagecreatefrompng($file['tmp_name']);
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($file['tmp_name']);
        if ($src) {
            $w = $maxW;
            $h = (int)round($info[1] * ($maxW / $info[0]));
            $dst = imagecreatetruecolor($w, $h);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            if ($ext === 'jpg') $resized = imagejpeg($dst, $dest, 84);
            if ($ext === 'png') $resized = imagepng($dst, $dest, 7);
            if ($ext === 'webp') $resized = imagewebp($dst, $dest, 84);
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
    if (!$resized && !move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the image. Check that the uploads folder is writable.');
    }
    @chmod($dest, 0644);
    return 'uploads/' . $folder . '/' . $name;
}

/** Normalises $_FILES['x'] multi-upload into a list of single-file arrays. */
function files_list(string $field): array
{
    if (empty($_FILES[$field])) return [];
    $f = $_FILES[$field];
    if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function delete_upload(?string $path): void
{
    if (!$path || strpos($path, 'uploads/') !== 0 || strpos($path, '..') !== false) return;
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) @unlink($full);
}

// ------------------------------------------------------------------ auth ---
function admin(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;
    static $a = null;
    if ($a === null) $a = row('SELECT id, username FROM admins WHERE id = ?', [$_SESSION['admin_id']]);
    return $a;
}

function require_admin(): array
{
    $a = admin();
    if (!$a) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php');
    }
    // idle timeout: 4 hours
    if (!empty($_SESSION['admin_seen']) && time() - $_SESSION['admin_seen'] > 14400) {
        unset($_SESSION['admin_id']);
        flash('error', 'You were signed out after a period of inactivity.');
        redirect('admin/login.php');
    }
    $_SESSION['admin_seen'] = time();
    return $a;
}

// ------------------------------------------------------------ pagination ---
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

function page_link(int $p): string
{
    $qs = $_GET;
    $qs['page'] = $p;
    return '?' . http_build_query($qs);
}

function render_pagination(array $pg): string
{
    if ($pg['pages'] <= 1) return '';
    $h = '<nav class="pagination" aria-label="Pagination">';
    if ($pg['page'] > 1) $h .= '<a href="' . e(page_link($pg['page'] - 1)) . '" aria-label="Previous">&larr;</a>';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($i === 1 || $i === $pg['pages'] || abs($i - $pg['page']) <= 1) {
            $h .= $i === $pg['page']
                ? '<span class="current" aria-current="page">' . $i . '</span>'
                : '<a href="' . e(page_link($i)) . '">' . $i . '</a>';
        } elseif (abs($i - $pg['page']) === 2) {
            $h .= '<span class="gap">…</span>';
        }
    }
    if ($pg['page'] < $pg['pages']) $h .= '<a href="' . e(page_link($pg['page'] + 1)) . '" aria-label="Next">&rarr;</a>';
    return $h . '</nav>';
}

// ------------------------------------------------------------------ brand ---
/** Path of the current logo (admin upload → bundled default → none). */
function logo_src(bool $forDarkBg = false): string
{
    if ($forDarkBg && setting('logo_light')) return setting('logo_light');
    if (setting('logo_image')) return setting('logo_image');
    if ($forDarkBg && is_file(APP_ROOT . '/assets/img/logo-light.png')) return 'assets/img/logo-light.png';
    return is_file(APP_ROOT . '/assets/img/logo.png') ? 'assets/img/logo.png' : '';
}

/** True when a dedicated light logo is available for dark backgrounds. */
function has_light_logo(): bool
{
    return (bool)setting('logo_light') || (!setting('logo_image') && is_file(APP_ROOT . '/assets/img/logo-light.png'));
}

/**
 * Brand logo markup.
 * $variant: 'default' (light backgrounds) or 'dark' (dark backgrounds such as the footer / admin sidebar).
 * On dark backgrounds the "light version" logo is used if uploaded, otherwise the logo sits on a white chip.
 */
function logo_html(string $variant = 'default'): string
{
    $name = setting('store_name', 'JerseyHour');
    $src  = logo_src($variant === 'dark');
    $h    = max(20, min(120, (int)setting('logo_height', '50')));
    if ($src) {
        $chip = $variant === 'dark' && !has_light_logo();
        return '<span class="logo logo-img' . ($chip ? ' logo-chip' : '') . '" style="--logo-h:' . $h . 'px">'
            . '<img src="' . e(img_url($src)) . '" alt="' . e($name) . '" height="' . $h . '"></span>';
    }
    // Text fallback when no logo image exists
    if (preg_match('~^([A-Z]?[a-z]+)([A-Z].*)$~', $name, $m)) {
        $word = e($m[1]) . '<b>' . e($m[2]) . '</b>';
    } else {
        $word = e($name);
    }
    return '<span class="logo"><span class="logo-word">' . $word . '</span></span>';
}

function favicon_url(): string
{
    $f = setting('favicon');
    return $f ? img_url($f) : url('assets/img/favicon.png');
}

/** <link> tags for favicon + optional brand colour override (used on every page). */
function brand_head(): string
{
    $fav = favicon_url();
    $h = '<link rel="icon" href="' . e($fav) . '">' . "\n"
       . '<link rel="apple-touch-icon" href="' . e(setting('favicon') ? $fav : url('assets/img/apple-touch-icon.png')) . '">' . "\n";
    $c = setting('brand_color');
    if (preg_match('~^#([0-9a-f]{6})$~i', $c, $m)) {
        [$r, $g, $b] = array_map('hexdec', str_split($m[1], 2));
        $deep = sprintf('#%02x%02x%02x', (int)($r * .72), (int)($g * .72), (int)($b * .72));
        $soft = sprintf('#%02x%02x%02x', (int)(255 - (255 - $r) * .1), (int)(255 - (255 - $g) * .1), (int)(255 - (255 - $b) * .1));
        $h .= '<style>:root{--brand:' . $c . ';--brand-deep:' . $deep . ';--brand-soft:' . $soft . ';--brand-rgb:' . "$r,$g,$b" . '}</style>' . "\n";
    }
    return $h;
}

// ------------------------------------------------------------------ icons ---
function icon(string $name, int $size = 20): string
{
    $p = [
        'cart'    => '<path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'pin'     => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'truck'   => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'cash'    => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15.5-6.3L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.3L3 16"/><path d="M3 21v-5h5"/>',
        'shield'  => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'check'   => '<path d="m5 12 5 5L20 7"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'trash'   => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'whatsapp'=> '<path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.6z"/><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.6-2-1-1 1c-1-.4-2.5-1.9-2.9-2.9l1-1-1-2z"/>',
        'ruler'   => '<path d="M3 17 17 3l4 4L7 21z"/><path d="m7 13 2 2M10 10l2 2M13 7l2 2"/>',
        'filter'  => '<path d="M3 5h18M6 12h12M10 19h4"/>',
        'box'     => '<path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/>',
        'instagram'=> '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/>',
        'facebook'=> '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
        'tiktok'  => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.5 2.8 2.4 4.6 5 5"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'star'    => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    ];
    $inner = $p[$name] ?? '';
    return '<svg class="i i-' . $name . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
