<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

requireSuperAdmin();

$data = json_decode(file_get_contents('php://input'), true);
$orgId  = intval($data['org_id'] ?? 0);
$status = intval($data['status'] ?? 0);

if (!verifyCsrf($data['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Invalid security token']); exit; }
if (!$orgId) { echo json_encode(['success'=>false,'message'=>'Invalid organization']); exit; }

$db = getDB();
$db->prepare("UPDATE organizations SET is_active = ? WHERE id = ?")->execute([$status, $orgId]);

// Log activity
logActivity('super_admin', $_SESSION['super_admin_id'], 'ORG_TOGGLE', "Organization ID $orgId set to ".($status?'active':'inactive'));

echo json_encode(['success' => true]);
