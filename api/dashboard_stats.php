<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
requireAdmin();

$db  = getDB();
$org = $_SESSION['org_id'];

$totalEvents = $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=?");
$totalEvents->execute([$org]);

$todayEvents = $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=? AND event_date=CURDATE()");
$todayEvents->execute([$org]);

$revenue = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=?");
$revenue->execute([$org]);

$pendingAmount = $db->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM events WHERE organization_id=? AND status NOT IN('cancelled','completed')");
$pendingAmount->execute([$org]);

$stats = [
    'total_events'    => $totalEvents->fetchColumn(),
    'today_events'    => $todayEvents->fetchColumn(),
    'revenue'         => $revenue->fetchColumn(),
    'pending_amount'  => $pendingAmount->fetchColumn(),
];

echo json_encode($stats);
