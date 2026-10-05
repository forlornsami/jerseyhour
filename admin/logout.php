<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (is_post() && csrf_valid()) {
    unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
    session_regenerate_id(true);
    flash('info', 'You have been signed out.');
}
redirect('admin/login.php');
