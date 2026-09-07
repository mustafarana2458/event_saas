<?php
require_once 'includes/config.php';
if (isLoggedIn('super_admin')) {
    redirect(APP_URL . '/modules/superadmin/dashboard.php');
} elseif (isLoggedIn('admin')) {
    redirect(APP_URL . '/modules/admin/dashboard.php');
} else {
    redirect(APP_URL . '/modules/auth/login.php');
}
