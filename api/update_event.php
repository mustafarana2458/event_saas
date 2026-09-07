<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
requireAdmin();

$db  = getDB();
$org = $_SESSION['org_id'];
$d   = json_decode(file_get_contents('php://input'), true);

$eventId = intval($d['event_id'] ?? 0);
$field   = $d['field'] ?? '';
$value   = sanitize($d['value'] ?? '');

if (!verifyCsrf($d['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'Invalid security token']); exit;
}

$allowed = ['status','notes','slot'];
if (!$eventId || !in_array($field, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Invalid request']); exit;
}

$db->prepare("UPDATE events SET $field = ? WHERE id = ? AND organization_id = ?")
   ->execute([$value, $eventId, $org]);

logActivity('admin', $_SESSION['admin_id'], 'EVENT_UPDATE', "Updated $field for event #$eventId", $org);
echo json_encode(['success' => true]);
