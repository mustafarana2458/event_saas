<?php
require_once '../../includes/config.php';

$type = $_SESSION['user_type'] ?? 'admin';
$userId = $_SESSION['admin_id'] ?? $_SESSION['super_admin_id'] ?? null;
$orgId = $_SESSION['org_id'] ?? null;

if ($userId) {
    logActivity($type, $userId, 'LOGOUT', 'User logged out', $orgId);
}

session_destroy();

if ($type === 'super_admin') {
    redirect(APP_URL . '/modules/auth/login.php?type=superadmin');
} else {
    redirect(APP_URL . '/modules/auth/login.php');
}
