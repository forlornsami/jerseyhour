<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$ADMIN = require_admin();

function admin_url(string $p = ''): string { return url('admin/' . ltrim($p, '/')); }

function admin_counts(): array
{
    static $c = null;
    if ($c === null) {
        $c = [
            'pending'  => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
            'messages' => (int)val('SELECT COUNT(*) FROM messages WHERE is_read = 0'),
        ];
    }
    return $c;
}

function stock_class(int $n): string
{
    return $n <= 0 ? 'stock-out' : ($n <= 5 ? 'stock-low' : 'stock-ok');
}
