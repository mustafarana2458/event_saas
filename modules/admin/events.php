<?php
require_once '../../includes/config.php';
requireAdmin();
$db   = getDB();
$org  = $_SESSION['org_id'];
$role = $_SESSION['admin_role'];

$msg = $err = '';

// Save Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id        = intval($_POST['id'] ?? 0);
    $hallId    = intval($_POST['hall_id'] ?? 0);
    $clientId  = intval($_POST['client_id'] ?? 0);
    $title     = sanitize($_POST['event_title'] ?? '');
    $type      = sanitize($_POST['event_type'] ?? 'wedding');
    $date      = sanitize($_POST['event_date'] ?? '');
    $slot      = sanitize($_POST['slot'] ?? 'full_day');
    $timeStart = sanitize($_POST['event_time_start'] ?? '');
    $timeEnd   = sanitize($_POST['event_time_end'] ?? '');
    $guests    = intval($_POST['guests_count'] ?? 0);
    $total     = floatval($_POST['total_amount'] ?? 0);
    $advance   = floatval($_POST['advance_paid'] ?? 0);
    $remaining = $total - $advance;
    $status    = sanitize($_POST['status'] ?? 'tentative');
    $notes     = sanitize($_POST['notes'] ?? '');

    // Branch is derived from the chosen hall's own branch, not trusted from POST directly
    $hallBranch = $db->prepare("SELECT branch_id FROM halls WHERE id=? AND organization_id=?");
    $hallBranch->execute([$hallId, $org]);
    $branchId = $hallBranch->fetchColumn();
    $branchId = $branchId !== false ? $branchId : null;

    // Check for conflicting bookings (same hall, same date, overlapping slot)
    $conflict = $db->prepare("SELECT id FROM events WHERE hall_id=? AND event_date=? AND status NOT IN('cancelled') AND id!=? AND (slot='full_day' OR ? = 'full_day' OR slot=?)");
    $conflict->execute([$hallId, $date, $id, $slot, $slot]);
    if ($conflict->fetch()) {
        $err = 'This hall is already booked for the selected date and slot.';
    } else {
        if ($id) {
            $db->prepare("UPDATE events SET hall_id=?,branch_id=?,client_id=?,event_title=?,event_type=?,event_date=?,slot=?,event_time_start=?,event_time_end=?,guests_count=?,total_amount=?,advance_paid=?,remaining_amount=?,status=?,notes=? WHERE id=? AND organization_id=?")
               ->execute([$hallId,$branchId,$clientId,$title,$type,$date,$slot,$timeStart,$timeEnd,$guests,$total,$advance,$remaining,$status,$notes,$id,$org]);
            $msg = 'Event updated successfully.';
        } else {
            $db->prepare("INSERT INTO events (organization_id,hall_id,branch_id,client_id,event_title,event_type,event_date,slot,event_time_start,event_time_end,guests_count,total_amount,advance_paid,remaining_amount,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$org,$hallId,$branchId,$clientId,$title,$type,$date,$slot,$timeStart,$timeEnd,$guests,$total,$advance,$remaining,$status,$notes,$_SESSION['admin_id']]);
            logActivity('admin', $_SESSION['admin_id'], 'EVENT_CREATE', "Created event: $title", $org);
            $msg = 'Event booked successfully!';
        }
    }
    }
}

// Delete event
if (isset($_GET['delete'])) {
    if (!in_array($role, ['org_admin','manager'])) {
        $err = 'You do not have permission to delete events.';
    } elseif (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
        $db->prepare("DELETE FROM events WHERE id=? AND organization_id=?")->execute([intval($_GET['delete']),$org]);
        $msg = 'Event deleted.';
    }
}

// View single event
$viewEvent = null;
if (isset($_GET['view'])) {
    $s=$db->prepare("SELECT e.*,c.name as client_name,c.phone as client_phone,c.email as client_email,c.cnic,c.address as client_addr,h.name as hall_name,br.name as branch_name FROM events e JOIN clients c ON e.client_id=c.id JOIN halls h ON e.hall_id=h.id LEFT JOIN branches br ON e.branch_id=br.id WHERE e.id=? AND e.organization_id=?");
    $s->execute([intval($_GET['view']),$org]); $viewEvent=$s->fetch();
}

$halls    = $db->prepare("SELECT * FROM halls WHERE organization_id=? AND is_active=1 ORDER BY name"); $halls->execute([$org]); $halls=$halls->fetchAll();
$clients  = $db->prepare("SELECT * FROM clients WHERE organization_id=? ORDER BY name"); $clients->execute([$org]); $clients=$clients->fetchAll();
$branchesActive = $db->prepare("SELECT * FROM branches WHERE organization_id=? AND status='active' ORDER BY name"); $branchesActive->execute([$org]); $branchesActive=$branchesActive->fetchAll();
$branchesAll    = $db->prepare("SELECT * FROM branches WHERE organization_id=? ORDER BY name"); $branchesAll->execute([$org]); $branchesAll=$branchesAll->fetchAll();

// Filter
$statusFilter = $_GET['status'] ?? '';
$branchFilter = intval($_GET['branch'] ?? 0);
$search       = sanitize($_GET['q'] ?? '');
$params       = [$org];
$where        = "WHERE e.organization_id=?";
if ($statusFilter) { $where .= " AND e.status=?"; $params[] = $statusFilter; }
if ($branchFilter) { $where .= " AND e.branch_id=?"; $params[] = $branchFilter; }
if ($search) { $where .= " AND (e.event_title LIKE ? OR c.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$events = $db->prepare("
    SELECT e.*, c.name as client_name, h.name as hall_name, br.name as branch_name
    FROM events e JOIN clients c ON e.client_id=c.id JOIN halls h ON e.hall_id=h.id
    LEFT JOIN branches br ON e.branch_id=br.id
    $where ORDER BY e.event_date DESC
");
$events->execute($params); $events=$events->fetchAll();

$showModal = isset($_GET['action']) && $_GET['action']==='new';
$editEvent = null;
if (isset($_GET['edit'])) {
    $s=$db->prepare("SELECT * FROM events WHERE id=? AND organization_id=?"); $s->execute([intval($_GET['edit']),$org]); $editEvent=$s->fetch();
    $showModal = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Events — <?= sanitize($_SESSION['org_name']) ?></title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Events & Bookings</h2><p class="text-muted-sm">Manage all hall bookings</p></div>
            <button class="btn-gold" onclick="openEventModal()"><i class="fas fa-plus me-2"></i>New Booking</button>
        </div>

        <?php if ($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></div><?php endif; ?>

        <!-- Filters -->
        <div class="card-section" style="margin-bottom:20px">
            <div style="padding:16px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;flex:1">
                    <div class="search-bar"><i class="fas fa-search"></i><input type="text" name="q" value="<?= sanitize($_GET['q']??'') ?>" placeholder="Search events or clients..."></div>
                    <select name="status" class="form-select" style="width:160px">
                        <option value="">All Status</option>
                        <?php foreach(['tentative','confirmed','in_progress','completed','cancelled'] as $s): ?>
                        <option value="<?=$s?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(count($branchesAll) > 1): ?>
                    <select name="branch" class="form-select" style="width:170px">
                        <option value="">All Branches</option>
                        <?php foreach($branchesAll as $br): ?>
                        <option value="<?=$br['id']?>" <?= $branchFilter===(int)$br['id']?'selected':'' ?>><?= sanitize($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <button type="submit" class="btn-gold" style="padding:9px 16px"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="events.php" class="btn-outline-gold" style="padding:9px 14px">Clear</a>
                </form>
            </div>
        </div>

        <?php if ($viewEvent): ?>
        <!-- Event Detail View -->
        <div class="card-section" style="margin-bottom:20px">
            <div class="section-header">
                <h3><i class="fas fa-info-circle me-2"></i><?= sanitize($viewEvent['event_title']) ?></h3>
                <div style="display:flex;gap:8px">
                    <a href="events.php?edit=<?= $viewEvent['id'] ?>" class="btn-outline-gold" style="padding:7px 14px;font-size:0.82rem"><i class="fas fa-edit me-1"></i>Edit</a>
                    <a href="invoices.php?event_id=<?= $viewEvent['id'] ?>" class="btn-gold" style="padding:7px 14px;font-size:0.82rem"><i class="fas fa-file-invoice me-1"></i>Invoice</a>
                    <a href="events.php" class="btn-outline-gold" style="padding:7px 14px;font-size:0.82rem"><i class="fas fa-times"></i></a>
                </div>
            </div>
            <div style="padding:24px">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h5 style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Event Details</h5>
                        <?php $details=[['Type',ucfirst(str_replace('_',' ',$viewEvent['event_type']))],['Date',date('l, d F Y',strtotime($viewEvent['event_date']))],['Slot',ucfirst(str_replace('_',' ',$viewEvent['slot']))],['Branch',$viewEvent['branch_name']??'—'],['Hall',$viewEvent['hall_name']],['Guests',$viewEvent['guests_count'].' guests'],['Status',ucfirst(str_replace('_',' ',$viewEvent['status']))]]; ?>
                        <?php foreach($details as $d): ?>
                        <div style="display:flex;margin-bottom:10px">
                            <span style="width:100px;font-size:0.82rem;color:var(--text-muted)"><?= $d[0] ?></span>
                            <span style="font-size:0.875rem;font-weight:500"><?= sanitize($d[1]) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-3">
                        <h5 style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Client</h5>
                        <div style="font-weight:600;margin-bottom:4px"><?= sanitize($viewEvent['client_name']) ?></div>
                        <div style="font-size:0.82rem;color:var(--text-muted)"><?= sanitize($viewEvent['client_phone']) ?></div>
                        <div style="font-size:0.82rem;color:var(--text-muted)"><?= sanitize($viewEvent['client_email']??'') ?></div>
                        <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px"><?= sanitize($viewEvent['client_addr']??'') ?></div>
                    </div>
                    <div class="col-md-3">
                        <h5 style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Payment</h5>
                        <?php $pct=$viewEvent['total_amount']>0?($viewEvent['advance_paid']/$viewEvent['total_amount']*100):0; ?>
                        <div style="margin-bottom:8px"><span style="font-size:0.82rem;color:var(--text-muted)">Total</span><div style="font-size:1.1rem;font-weight:700;color:var(--gold)"><?= formatPKR($viewEvent['total_amount']) ?></div></div>
                        <div style="margin-bottom:8px"><span style="font-size:0.82rem;color:var(--text-muted)">Paid</span><div style="font-size:1rem;font-weight:600;color:var(--success)"><?= formatPKR($viewEvent['advance_paid']) ?></div></div>
                        <div><span style="font-size:0.82rem;color:var(--text-muted)">Remaining</span><div style="font-size:1rem;font-weight:600;color:var(--danger)"><?= formatPKR($viewEvent['remaining_amount']) ?></div></div>
                        <div class="progress-custom" style="margin-top:10px"><div class="progress-bar-custom" style="width:<?= $pct ?>%"></div></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px"><?= round($pct) ?>% paid</div>
                    </div>
                </div>
                <?php if ($viewEvent['notes']): ?>
                <div style="margin-top:16px;padding:12px 16px;background:var(--dark-surface);border-radius:10px;font-size:0.875rem;color:var(--text-muted)"><i class="fas fa-sticky-note me-2"></i><?= sanitize($viewEvent['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Events Table -->
        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-list me-2"></i>Events (<?= count($events) ?>)</h3>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Event</th><th>Date</th><th>Hall</th><th>Client</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($events as $ev): ?>
                    <tr>
                        <td>
                            <strong><?= sanitize($ev['event_title']) ?></strong>
                            <small style="display:block;color:var(--text-muted);text-transform:capitalize"><?= str_replace('_',' ',$ev['event_type']) ?></small>
                        </td>
                        <td>
                            <?= date('d M Y', strtotime($ev['event_date'])) ?>
                            <small style="display:block;color:var(--text-muted);text-transform:capitalize"><?= str_replace('_',' ',$ev['slot']) ?></small>
                        </td>
                        <td style="font-size:0.82rem"><?= sanitize($ev['hall_name']) ?><?php if($ev['branch_name']): ?><small style="display:block;color:var(--text-muted)"><?= sanitize($ev['branch_name']) ?></small><?php endif; ?></td>
                        <td style="font-size:0.82rem"><?= sanitize($ev['client_name']) ?></td>
                        <td style="color:var(--text-primary);font-weight:600"><?= formatPKR($ev['total_amount']) ?></td>
                        <td style="color:var(--success)"><?= formatPKR($ev['advance_paid']) ?></td>
                        <td style="color:<?= $ev['remaining_amount']>0?'var(--danger)':'var(--success)' ?>;font-weight:600"><?= formatPKR($ev['remaining_amount']) ?></td>
                        <td><span class="status-badge <?= $ev['status'] ?>"><?= ucfirst(str_replace('_',' ',$ev['status'])) ?></span></td>
                        <td>
                            <div class="action-btns">
                                <a href="events.php?view=<?= $ev['id'] ?>" class="btn-action view" title="View"><i class="fas fa-eye"></i></a>
                                <button class="btn-action edit" onclick="editEvent(<?= htmlspecialchars(json_encode($ev)) ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                <a href="payments.php?event_id=<?= $ev['id'] ?>" class="btn-action success" title="Add Payment"><i class="fas fa-money-bill"></i></a>
                                <a href="invoices.php?event_id=<?= $ev['id'] ?>" class="btn-action print" title="Invoice"><i class="fas fa-file-invoice"></i></a>
                                <?php if (in_array($role, ['org_admin','manager'])): ?>
                                <a href="events.php?delete=<?= $ev['id'] ?>&csrf_token=<?= urlencode(csrfToken()) ?>" class="btn-action danger" title="Delete" onclick="return confirm('Delete this event?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($events)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:40px"><i class="fas fa-calendar-times" style="font-size:2rem;margin-bottom:10px;display:block"></i>No events found</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventModalTitle">New Booking</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="save_event" value="1">
        <input type="hidden" name="id" id="evId" value="0">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Event Title *</label><input name="event_title" id="evTitle" class="form-control" required placeholder="e.g. Ali & Sara Wedding"></div>
            <div class="col-md-4">
              <label class="form-label">Event Type *</label>
              <select name="event_type" id="evType" class="form-select">
                <?php foreach(['wedding','engagement','mehendi','valima','birthday','corporate','other'] as $t): ?>
                <option value="<?=$t?>"><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if(count($branchesActive) > 1): ?>
            <div class="col-md-6">
              <label class="form-label">Branch *</label>
              <select id="evBranch" class="form-select" required>
                <?php foreach($branchesActive as $br): ?><option value="<?=$br['id']?>"><?= sanitize($br['name']) ?></option><?php endforeach; ?>
              </select>
              <small style="color:var(--text-muted)">Filters the hall list below. The event's branch is saved automatically based on the hall you pick.</small>
            </div>
            <?php elseif(count($branchesActive) === 1): ?>
            <input type="hidden" id="evBranch" value="<?= $branchesActive[0]['id'] ?>">
            <?php endif; ?>
            <div class="col-md-6">
              <label class="form-label">Hall *</label>
              <select name="hall_id" id="evHall" class="form-select" required>
                <option value="">Select Hall</option>
                <?php foreach($halls as $h): ?><option value="<?=$h['id']?>" data-price="<?=$h['price_per_event']?>" data-branch="<?=$h['branch_id']?>"><?= sanitize($h['name']) ?> (Cap: <?= $h['capacity'] ?>)</option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Client *</label>
              <select name="client_id" id="evClient" class="form-select" required>
                <option value="">Select Client</option>
                <?php foreach($clients as $c): ?><option value="<?=$c['id']?>"><?= sanitize($c['name']) ?> — <?= sanitize($c['phone']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Event Date *</label><input type="date" name="event_date" id="evDate" class="form-control" required></div>
            <div class="col-md-4">
              <label class="form-label">Slot</label>
              <select name="slot" id="evSlot" class="form-select">
                <option value="full_day">Full Day</option>
                <option value="morning">Morning</option>
                <option value="afternoon">Afternoon</option>
                <option value="evening">Evening</option>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Guests Count</label><input type="number" name="guests_count" id="evGuests" class="form-control" value="0"></div>
            <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="event_time_start" id="evTStart" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">End Time</label><input type="time" name="event_time_end" id="evTEnd" class="form-control"></div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" id="evStatus" class="form-select">
                <option value="tentative">Tentative</option>
                <option value="confirmed">Confirmed</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Total Amount (PKR) *</label><input type="number" step="0.01" name="total_amount" id="evTotal" class="form-control" required value="0"></div>
            <div class="col-md-4"><label class="form-label">Advance Paid (PKR)</label><input type="number" step="0.01" name="advance_paid" id="evAdvance" class="form-control" value="0" oninput="calcRemaining()"></div>
            <div class="col-md-4">
              <label class="form-label">Remaining</label>
              <input type="text" id="evRemaining" class="form-control" readonly style="color:var(--danger)" value="PKR 0.00">
            </div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="evNotes" class="form-control" rows="2" placeholder="Any special instructions..."></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Save Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const modal = new bootstrap.Modal(document.getElementById('eventModal'));
<?php if($showModal) echo "window.addEventListener('DOMContentLoaded',()=>modal.show());"; ?>
<?php if($editEvent) echo "window.addEventListener('DOMContentLoaded',()=>editEvent(".json_encode($editEvent)."));"; ?>

function openEventModal() {
    document.getElementById('eventModal').querySelector('form').reset();
    document.getElementById('evId').value=0;
    document.getElementById('eventModalTitle').textContent='New Booking';
    filterHallsByBranch();
    modal.show();
}

function editEvent(ev) {
    document.getElementById('eventModalTitle').textContent='Edit Booking';
    document.getElementById('evId').value=ev.id;
    document.getElementById('evTitle').value=ev.event_title;
    document.getElementById('evType').value=ev.event_type;
    const branchSel = document.getElementById('evBranch');
    if (branchSel && ev.branch_id) branchSel.value = ev.branch_id;
    filterHallsByBranch(ev.hall_id);
    document.getElementById('evHall').value=ev.hall_id;
    document.getElementById('evClient').value=ev.client_id;
    document.getElementById('evDate').value=ev.event_date;
    document.getElementById('evSlot').value=ev.slot;
    document.getElementById('evGuests').value=ev.guests_count;
    document.getElementById('evTStart').value=ev.event_time_start||'';
    document.getElementById('evTEnd').value=ev.event_time_end||'';
    document.getElementById('evStatus').value=ev.status;
    document.getElementById('evTotal').value=ev.total_amount;
    document.getElementById('evAdvance').value=ev.advance_paid;
    document.getElementById('evNotes').value=ev.notes||'';
    calcRemaining();
    modal.show();
}

// Branch -> hall filtering
function filterHallsByBranch(keepHallId) {
    const branchSel = document.getElementById('evBranch');
    const hallSelect = document.getElementById('evHall');
    if (!branchSel || !hallSelect) return;
    const branchId = branchSel.value;
    Array.from(hallSelect.options).forEach(opt => {
        if (!opt.value) return; // keep the placeholder
        const matches = !branchId || opt.dataset.branch === branchId;
        opt.hidden = !matches;
        opt.disabled = !matches;
    });
    const keep = keepHallId != null ? String(keepHallId) : hallSelect.value;
    const keepOpt = Array.from(hallSelect.options).find(o => o.value === keep);
    if (!keepOpt || keepOpt.disabled) {
        hallSelect.value = '';
        document.getElementById('evTotal').value = 0;
        calcRemaining();
    }
}
(function() {
    const branchSel = document.getElementById('evBranch');
    if (branchSel && branchSel.tagName === 'SELECT') {
        branchSel.addEventListener('change', () => filterHallsByBranch());
    }
})();

function calcRemaining() {
    const t=parseFloat(document.getElementById('evTotal').value)||0;
    const a=parseFloat(document.getElementById('evAdvance').value)||0;
    document.getElementById('evRemaining').value='PKR '+Math.max(0,t-a).toLocaleString('en-PK',{minimumFractionDigits:2});
}

// Auto-fill hall price
document.getElementById('evHall').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const price = opt.dataset.price;
    if (price) { document.getElementById('evTotal').value=price; calcRemaining(); }
});
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
