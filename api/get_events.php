<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
if (!isLoggedIn('admin')) { echo json_encode(['error'=>'unauthorized']); exit; }

$org   = $_SESSION['org_id'];
$db    = getDB();
$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));

$events = $db->prepare("
    SELECT e.id, e.event_title, e.event_date, e.event_type, e.slot, e.status,
           e.total_amount, e.advance_paid, e.remaining_amount,
           c.name as client_name, h.name as hall_name
    FROM events e JOIN clients c ON e.client_id=c.id JOIN halls h ON e.hall_id=h.id
    WHERE e.organization_id=? AND MONTH(e.event_date)=? AND YEAR(e.event_date)=?
    ORDER BY e.event_date
");
$events->execute([$org,$month,$year]);
echo json_encode($events->fetchAll());
