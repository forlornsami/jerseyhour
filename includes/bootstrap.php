<?php
/**
 * JerseyHour — application bootstrap
 * Loaded at the top of every page.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ---- Session (hardened) ----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('jh_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Base URL detection (works in root or a sub-folder) -------------------
(function () {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
    $root      = str_replace('\\', '/', APP_ROOT);
    $real      = realpath($scriptDir) ?: $scriptDir;
    $realRoot  = realpath($root) ?: $root;
    $rel       = trim(substr(str_replace('\\', '/', $real), strlen(str_replace('\\', '/', $realRoot))), '/');
    $depth     = $rel === '' ? 0 : substr_count($rel, '/') + 1;
    $urlDir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    for ($i = 0; $i < $depth; $i++) {
        $urlDir = dirname($urlDir);
    }
    $urlDir = rtrim(str_replace('\\', '/', $urlDir), '/');
    define('BASE', $urlDir); // '' when installed at the domain root
})();

// ---- Config ----------------------------------------------------------------
$configFile = APP_ROOT . '/includes/config.php';
if (!is_file($configFile)) {
    if (!defined('INSTALLER')) {
        header('Location: ' . BASE . '/install.php');
        exit;
    }
} else {
    require $configFile;
}

require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';

if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
} else {
    date_default_timezone_set('Asia/Karachi');
}
