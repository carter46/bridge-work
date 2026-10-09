<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

hj_session_start();
if (hj_is_post()) {
    hj_csrf_verify();
    if (!empty($_SESSION['admin_id'])) {
        hj_audit('logout', 'admin', (int)$_SESSION['admin_id']);
    }
    hj_logout_session();
    hj_session_start();
    hj_flash('info', 'You have been signed out.');
}
hj_redirect('login.php');
