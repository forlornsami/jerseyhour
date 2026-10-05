<?php
/**
 * Cart endpoint — JSON for fetch() requests, redirects for plain form posts.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

function respond(bool $ok, string $msg = '', int $code = 200): void
{
    global $isAjax;
    if ($isAjax) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $s = cart_summary();
        $s['subtotal_fmt'] = money($s['subtotal']);
        $s['delivery_fmt'] = $s['delivery'] > 0 ? money($s['delivery']) : 'Free';
        $s['total_fmt'] = money($s['total']);
        $s['remaining_fmt'] = money($s['remaining']);
        foreach ($s['lines'] as &$l) { $l['price_fmt'] = money($l['price']); $l['line_fmt'] = money($l['line']); unset($l['raw_image']); }
        echo json_encode(['ok' => $ok, 'message' => $msg, 'cart' => $s]);
        exit;
    }
    if ($msg) flash($ok ? 'success' : 'error', $msg);
    redirect(!empty($_POST['buy_now']) && $ok ? 'checkout.php' : 'cart.php');
}

if (!is_post()) respond(true);
if (!csrf_valid()) respond(false, 'Your session expired. Please refresh the page.', 419);

$action = (string)($_POST['action'] ?? '');
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

if ($action === 'add') {
    $pid  = (int)($_POST['product_id'] ?? 0);
    $size = trim((string)($_POST['size'] ?? ''));
    $qty  = max(1, min(20, (int)($_POST['qty'] ?? 1)));
    $p = row('SELECT * FROM products WHERE id = ? AND active = 1', [$pid]);
    if (!$p) respond(false, 'This product is no longer available.', 404);
    $sizes = product_sizes($p);
    if ($sizes && ($size === '' || !array_key_exists($size, $sizes))) respond(false, 'Please choose a size.', 422);
    $avail = $sizes ? (int)$sizes[$size] : product_stock($p);
    $key = cart_key($pid, $size);
    $current = (int)($_SESSION['cart'][$key] ?? 0);
    if ($avail <= 0) respond(false, 'Sorry, this size is sold out.', 409);
    if ($current + $qty > $avail) {
        $_SESSION['cart'][$key] = $avail;
        respond(true, 'Only ' . $avail . ' available in ' . ($size ?: 'stock') . ' — your cart has the maximum.');
    }
    $_SESSION['cart'][$key] = $current + $qty;
    respond(true, 'Added to cart');
}

if ($action === 'update') {
    $key = (string)($_POST['key'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    if (isset($_SESSION['cart'][$key])) {
        if ($qty <= 0) unset($_SESSION['cart'][$key]);
        else $_SESSION['cart'][$key] = min(20, $qty);
    }
    respond(true);
}

if ($action === 'remove') {
    unset($_SESSION['cart'][(string)($_POST['key'] ?? '')]);
    respond(true, 'Removed from cart');
}

if ($action === 'get') respond(true);

respond(false, 'Unknown action', 400);
