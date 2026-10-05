<?php
/**
 * Database layer — PDO wrapper supporting SQLite (zero-setup) and MySQL.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    if (!defined('DB_DRIVER')) {
        throw new RuntimeException('Database is not configured.');
    }
    $pdo = db_connect(DB_DRIVER, [
        'path' => defined('DB_PATH') ? DB_PATH : '',
        'host' => defined('DB_HOST') ? DB_HOST : '',
        'name' => defined('DB_NAME') ? DB_NAME : '',
        'user' => defined('DB_USER') ? DB_USER : '',
        'pass' => defined('DB_PASS') ? DB_PASS : '',
    ]);
    return $pdo;
}

function db_connect(string $driver, array $c): PDO
{
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if ($driver === 'mysql') {
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    } else {
        $path = $c['path'];
        if ($path !== '' && $path[0] !== '/' && !preg_match('~^[A-Za-z]:[\\\\/]~', $path)) {
            $path = APP_ROOT . '/' . $path;
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Portable schema. */
function db_schema(string $driver): array
{
    $pk   = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int  = $driver === 'mysql' ? 'INT' : 'INTEGER';
    $fk   = $driver === 'mysql' ? 'INT UNSIGNED' : 'INTEGER';
    $txt  = $driver === 'mysql' ? 'MEDIUMTEXT' : 'TEXT';
    $str  = 'VARCHAR(191)';
    $dec  = 'DECIMAL(12,2)';
    $tail = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id $pk,
            username $str NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS categories (
            id $pk,
            name $str NOT NULL,
            slug $str NOT NULL UNIQUE,
            description $txt,
            image VARCHAR(255),
            sort_order $int NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS products (
            id $pk,
            category_id $fk NULL,
            name $str NOT NULL,
            slug $str NOT NULL UNIQUE,
            description $txt,
            price $dec NOT NULL DEFAULT 0,
            compare_price $dec NULL,
            sizes $txt,
            images $txt,
            badge VARCHAR(40),
            featured $int NOT NULL DEFAULT 0,
            active $int NOT NULL DEFAULT 1,
            views $int NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS orders (
            id $pk,
            order_no VARCHAR(40) NOT NULL UNIQUE,
            customer_name $str NOT NULL,
            phone VARCHAR(40) NOT NULL,
            email $str,
            city VARCHAR(100) NOT NULL,
            address $txt NOT NULL,
            notes $txt,
            subtotal $dec NOT NULL DEFAULT 0,
            delivery_fee $dec NOT NULL DEFAULT 0,
            total $dec NOT NULL DEFAULT 0,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'COD',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note $txt,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS order_items (
            id $pk,
            order_id $fk NOT NULL,
            product_id $fk NULL,
            product_name $str NOT NULL,
            size VARCHAR(20),
            price $dec NOT NULL DEFAULT 0,
            qty $int NOT NULL DEFAULT 1,
            image VARCHAR(255)
        )$tail",
        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(100) NOT NULL PRIMARY KEY,
            svalue $txt
        )$tail",
        "CREATE TABLE IF NOT EXISTS pages (
            id $pk,
            slug $str NOT NULL UNIQUE,
            title $str NOT NULL,
            content $txt,
            updated_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS messages (
            id $pk,
            name $str NOT NULL,
            phone VARCHAR(40),
            email $str,
            message $txt NOT NULL,
            is_read $int NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id $pk,
            ip VARCHAR(64) NOT NULL,
            attempted_at DATETIME NOT NULL
        )$tail",
        "CREATE INDEX idx_products_cat ON products (category_id)",
        "CREATE INDEX idx_items_order ON order_items (order_id)",
        "CREATE INDEX idx_orders_status ON orders (status)",
    ];
}
