<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

requireSuperAdmin();

$data   = json_decode(file_get_contents('php://input'), true);
$adminId = intval($data['admin_id'] ?? 0);
$status  = intval($data['status'] ?? 0);

if (!verifyCsrf($data['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Invalid security token']); exit; }
if (!$adminId) { echo json_encode(['success'=>false,'message'=>'Invalid admin']); exit; }

$db = getDB();
$db->prepare("UPDATE admins SET is_active = ? WHERE id = ?")->execute([$status, $adminId]);
logActivity('super_admin', $_SESSION['super_admin_id'], 'ADMIN_TOGGLE', "Admin ID $adminId set to ".($status?'active':'inactive'));

echo json_encode(['success' => true]);
