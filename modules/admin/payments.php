<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];
$msg = $err = '';

// Add Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $eventId = intval($_POST['event_id'] ?? 0);
    $amount  = floatval($_POST['amount'] ?? 0);
    $method  = sanitize($_POST['payment_method'] ?? 'cash');
    $date    = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $ref     = sanitize($_POST['reference_no'] ?? '');
    $notes   = sanitize($_POST['notes'] ?? '');

    if (!$eventId || $amount <= 0) {
        $err = 'Please select an event and enter a valid amount.';
    } else {
        $db->prepare("INSERT INTO payments (organization_id,event_id,amount,payment_method,payment_date,reference_no,notes,received_by) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$org,$eventId,$amount,$method,$date,$ref,$notes,$_SESSION['admin_id']]);
        // Update event advance_paid and remaining
        $db->prepare("UPDATE events SET advance_paid = advance_paid + ?, remaining_amount = GREATEST(0, remaining_amount - ?) WHERE id = ? AND organization_id = ?")
           ->execute([$amount,$amount,$eventId,$org]);
        logActivity('admin',$_SESSION['admin_id'],'PAYMENT_ADD',"Payment PKR $amount for event #$eventId",$org);
        $msg = 'Payment recorded successfully!';
    }
    }
}

// Delete payment
if (isset($_GET['delete'])) {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
    $pid = intval($_GET['delete']);
    $p = $db->prepare("SELECT * FROM payments WHERE id=? AND organization_id=?");
    $p->execute([$pid,$org]); $p=$p->fetch();
    if ($p) {
        $db->prepare("DELETE FROM payments WHERE id=?")->execute([$pid]);
        $db->prepare("UPDATE events SET advance_paid = GREATEST(0,advance_paid - ?), remaining_amount = remaining_amount + ? WHERE id=?")->execute([$p['amount'],$p['amount'],$p['event_id']]);
        $msg = 'Payment deleted and event balance restored.';
    }
    }
}

$eventFilter = intval($_GET['event_id'] ?? 0);

$events = $db->prepare("SELECT id, event_title, event_date, total_amount, advance_paid, remaining_amount FROM events WHERE organization_id=? AND status NOT IN('cancelled') ORDER BY event_date DESC");
$events->execute([$org]); $events=$events->fetchAll();

$params = [$org]; $where = "WHERE p.organization_id=?";
if ($eventFilter) { $where .= " AND p.event_id=?"; $params[]=$eventFilter; }

$payments = $db->prepare("
    SELECT p.*, e.event_title, e.event_date, c.name as client_name
    FROM payments p
    JOIN events e ON p.event_id=e.id
    JOIN clients c ON e.client_id=c.id
    $where ORDER BY p.created_at DESC
");
$payments->execute($params); $payments=$payments->fetchAll();

$totalCollected = array_sum(array_column($payments,'amount'));

$selEvent = null;
if ($eventFilter) {
    foreach($events as $ev) { if($ev['id']==$eventFilter){$selEvent=$ev;break;} }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Payments — <?= sanitize($_SESSION['org_name']) ?></title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Payments</h2><p class="text-muted-sm">Track all payments and transactions</p></div>
            <button class="btn-gold" onclick="openPayModal()"><i class="fas fa-plus me-2"></i>Record Payment</button>
        </div>

        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?=$err?></div><?php endif; ?>

        <?php if($selEvent): ?>
        <div class="card-section" style="margin-bottom:20px">
            <div style="padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
                <div>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.06em">Filtered by Event</p>
                    <h4 style="font-size:1rem;font-weight:600"><?=sanitize($selEvent['event_title'])?></h4>
                </div>
                <div style="display:flex;gap:24px;flex-wrap:wrap">
                    <div style="text-align:center"><div style="font-size:0.75rem;color:var(--text-muted)">Total</div><div style="font-size:1.1rem;font-weight:700;color:var(--gold)"><?=formatPKR($selEvent['total_amount'])?></div></div>
                    <div style="text-align:center"><div style="font-size:0.75rem;color:var(--text-muted)">Paid</div><div style="font-size:1.1rem;font-weight:700;color:var(--success)"><?=formatPKR($selEvent['advance_paid'])?></div></div>
                    <div style="text-align:center"><div style="font-size:0.75rem;color:var(--text-muted)">Remaining</div><div style="font-size:1.1rem;font-weight:700;color:var(--danger)"><?=formatPKR($selEvent['remaining_amount'])?></div></div>
                </div>
                <?php if($selEvent['total_amount']>0): $pct=min(100,$selEvent['advance_paid']/$selEvent['total_amount']*100); ?>
                <div style="width:100%;"><div class="progress-custom" style="height:10px"><div class="progress-bar-custom" style="width:<?=$pct?>%"></div></div><div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px"><?=round($pct)?>% collected</div></div>
                <?php endif; ?>
                <a href="payments.php" class="btn-outline-gold" style="padding:7px 14px;font-size:0.82rem">Clear Filter</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info"><span class="stat-label"><?=$eventFilter?'Event Total':'All Collected'?></span><span class="stat-value" style="font-size:1.2rem"><?=formatPKR($totalCollected)?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#C9A84C,#E8C97A)"><i class="fas fa-receipt"></i></div>
                <div class="stat-info"><span class="stat-label">Transactions</span><span class="stat-value"><?=count($payments)?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f857a6,#ff5858)"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info"><span class="stat-label">This Month</span><span class="stat-value" style="font-size:1.1rem"><?php
                    $mth=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=? AND MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())");
                    $mth->execute([$org]); echo formatPKR($mth->fetchColumn());
                ?></span></div>
            </div>
        </div>

        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-list me-2"></i>Payment History (<?=count($payments)?>)</h3>
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search payments..." oninput="filterTable(this.value,'payTable')"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="payTable">
                    <thead><tr><th>Event</th><th>Client</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($payments as $p): ?>
                    <tr>
                        <td>
                            <strong><?=sanitize($p['event_title'])?></strong>
                            <small style="display:block;color:var(--text-muted)"><?=date('d M Y',strtotime($p['event_date']))?></small>
                        </td>
                        <td><?=sanitize($p['client_name'])?></td>
                        <td><?=date('d M Y',strtotime($p['payment_date']))?></td>
                        <td style="color:var(--success);font-weight:700;font-size:1rem"><?=formatPKR($p['amount'])?></td>
                        <td><span class="role-badge manager" style="text-transform:capitalize"><?=str_replace('_',' ',$p['payment_method'])?></span></td>
                        <td style="font-size:0.8rem;color:var(--text-muted)"><?=sanitize($p['reference_no']??'-')?></td>
                        <td>
                            <div class="action-btns">
                                <a href="events.php?view=<?=$p['event_id']?>" class="btn-action view" title="View Event"><i class="fas fa-eye"></i></a>
                                <a href="payments.php?delete=<?=$p['id']?>&csrf_token=<?= urlencode(csrfToken()) ?>" class="btn-action danger" title="Delete" onclick="return confirm('Delete this payment? Event balance will be restored.')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($payments)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px"><i class="fas fa-money-bill-wave" style="font-size:2rem;display:block;margin-bottom:10px"></i>No payments found</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="save_payment" value="1">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Event *</label>
              <select name="event_id" id="payEvent" class="form-select" required onchange="fillEventBalance(this)">
                <option value="">Select Event</option>
                <?php foreach($events as $ev): ?>
                <option value="<?=$ev['id']?>" data-remaining="<?=$ev['remaining_amount']?>" <?=$eventFilter==$ev['id']?'selected':''?>><?=sanitize($ev['event_title'])?> — <?=date('d M Y',strtotime($ev['event_date']))?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12" id="balanceInfo" style="display:none">
              <div style="background:var(--dark-surface);border:1px solid var(--dark-border);border-radius:10px;padding:12px 16px;font-size:0.85rem">
                <span style="color:var(--text-muted)">Remaining Balance: </span>
                <strong id="balanceAmount" style="color:var(--danger)"></strong>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Amount (PKR) *</label>
              <input type="number" step="0.01" name="amount" id="payAmount" class="form-control" required placeholder="0.00">
            </div>
            <div class="col-md-6">
              <label class="form-label">Payment Date *</label>
              <input type="date" name="payment_date" class="form-control" value="<?=date('Y-m-d')?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-select">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Reference No.</label>
              <input type="text" name="reference_no" class="form-control" placeholder="Cheque/TXN no.">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Record Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const payModal = new bootstrap.Modal(document.getElementById('payModal'));
function openPayModal(eventId) {
    if(eventId) document.getElementById('payEvent').value = eventId;
    fillEventBalance(document.getElementById('payEvent'));
    payModal.show();
}
function fillEventBalance(sel) {
    const opt = sel.options[sel.selectedIndex];
    const rem = parseFloat(opt.dataset.remaining||0);
    const info = document.getElementById('balanceInfo');
    if(sel.value) {
        info.style.display='block';
        document.getElementById('balanceAmount').textContent = 'PKR '+rem.toLocaleString('en-PK',{minimumFractionDigits:2});
        document.getElementById('payAmount').value = rem > 0 ? rem : '';
    } else { info.style.display='none'; }
}
function filterTable(q,tid){q=q.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none');}
<?php if($eventFilter): ?>window.addEventListener('DOMContentLoaded',()=>openPayModal(<?=$eventFilter?>));<?php endif; ?>
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
