<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn('admin')) {
    echo json_encode(['active' => false, 'reason' => 'not_logged_in']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT a.is_active, o.is_active as org_active
    FROM admins a
    JOIN organizations o ON a.organization_id = o.id
    WHERE a.id = ?
");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin || !$admin['is_active'] || !$admin['org_active']) {
    session_destroy();
    echo json_encode(['active' => false, 'reason' => 'deactivated']);
    exit;
}

$_SESSION['last_activity'] = time();
echo json_encode(['active' => true]);
