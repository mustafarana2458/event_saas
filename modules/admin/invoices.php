<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];
$msg = $err = '';

// Generate Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_invoice'])) {
    if (!in_array($_SESSION['admin_role'], ['org_admin','manager'])) {
        $err = 'You do not have permission to generate invoices.';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh and try again.';
    } else {
    $eventId  = intval($_POST['event_id'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);
    $tax      = floatval($_POST['tax'] ?? 0);
    $notes    = sanitize($_POST['notes'] ?? '');
    $items    = $_POST['items'] ?? [];

    $ev = $db->prepare("SELECT e.*,c.name as client_name FROM events e JOIN clients c ON e.client_id=c.id WHERE e.id=? AND e.organization_id=?");
    $ev->execute([$eventId,$org]); $ev=$ev->fetch();

    if ($ev) {
        $subtotal = 0;
        foreach($items as $it) { $subtotal += floatval($it['qty']??1) * floatval($it['price']??0); }
        $total    = $subtotal - $discount + $tax;
        $amtPaid  = $ev['advance_paid'];
        $amtDue   = max(0, $total - $amtPaid);
        $invNum   = generateInvoiceNumber($org);

        $db->prepare("INSERT INTO invoices (organization_id,event_id,invoice_number,issue_date,due_date,subtotal,discount,tax,total_amount,amount_paid,amount_due,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$org,$eventId,$invNum,date('Y-m-d'),$_POST['due_date']??null,$subtotal,$discount,$tax,$total,$amtPaid,$amtDue,$amtDue<=0?'paid':'partial',$notes,$_SESSION['admin_id']]);
        $invId = $db->lastInsertId();

        foreach($items as $it) {
            $qty = floatval($it['qty']??1); $price = floatval($it['price']??0);
            $db->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total) VALUES (?,?,?,?,?)")
               ->execute([$invId,sanitize($it['desc']??'Item'),$qty,$price,$qty*$price]);
        }
        logActivity('admin',$_SESSION['admin_id'],'INVOICE_CREATE',"Invoice $invNum created",$org);
        header("Location: invoices.php?view=$invId"); exit;
    }
    }
}

// View invoice
$viewInv = null;
if (isset($_GET['view'])) {
    $s = $db->prepare("SELECT inv.*,e.event_title,e.event_date,e.slot,e.event_type,c.name as client_name,c.phone as client_phone,c.email as client_email,c.address as client_address,c.cnic,h.name as hall_name FROM invoices inv JOIN events e ON inv.event_id=e.id JOIN clients c ON e.client_id=c.id JOIN halls h ON e.hall_id=h.id WHERE inv.id=? AND inv.organization_id=?");
    $s->execute([intval($_GET['view']),$org]); $viewInv=$s->fetch();
    if($viewInv){
        $items=$db->prepare("SELECT * FROM invoice_items WHERE invoice_id=?"); $items->execute([$viewInv['id']]); $viewInv['items']=$items->fetchAll();
    }
}

$events = $db->prepare("SELECT id,event_title,event_date,total_amount,advance_paid,remaining_amount FROM events WHERE organization_id=? AND status NOT IN('cancelled') ORDER BY event_date DESC");
$events->execute([$org]); $events=$events->fetchAll();

$invoices = $db->prepare("SELECT inv.*,e.event_title,c.name as client_name FROM invoices inv JOIN events e ON inv.event_id=e.id JOIN clients c ON e.client_id=c.id WHERE inv.organization_id=? ORDER BY inv.created_at DESC");
$invoices->execute([$org]); $invoices=$invoices->fetchAll();

$eventPrefill = intval($_GET['event_id']??0);
$selEv = null;
if($eventPrefill) foreach($events as $ev) if($ev['id']==$eventPrefill){$selEv=$ev;break;}

// Org info
$orgInfo = $db->prepare("SELECT * FROM organizations WHERE id=?"); $orgInfo->execute([$org]); $orgInfo=$orgInfo->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Invoices — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?>
<style>
.invoice-print-area { background:#fff; color:#111; padding:40px; border-radius:12px; }
.inv-logo-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; }
.inv-company h2 { font-size:1.6rem; font-weight:800; color:#1a1a2e; margin:0; }
.inv-company p { font-size:0.85rem; color:#666; margin:2px 0; }
.inv-badge { background:#1a1a2e; color:#C9A84C; padding:6px 16px; border-radius:20px; font-size:0.8rem; font-weight:700; letter-spacing:.05em; }
.inv-title { font-size:2rem; font-weight:800; color:#1a1a2e; margin:0 0 4px 0; }
.inv-number { font-size:0.9rem; color:#888; }
.inv-meta { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin:24px 0; }
.inv-to { }
.inv-to h4 { font-size:0.75rem; text-transform:uppercase; letter-spacing:.08em; color:#888; margin-bottom:8px; font-weight:700; }
.inv-to p { margin:2px 0; font-size:0.875rem; color:#333; }
.inv-to strong { font-size:1rem; color:#1a1a2e; }
.inv-table { width:100%; border-collapse:collapse; margin:24px 0; }
.inv-table th { background:#1a1a2e; color:#fff; padding:10px 14px; font-size:0.78rem; font-weight:600; text-align:left; }
.inv-table td { padding:10px 14px; border-bottom:1px solid #eee; font-size:0.875rem; }
.inv-table tbody tr:last-child td { border-bottom:none; }
.inv-total-box { display:flex; justify-content:flex-end; }
.inv-total-table { width:240px; }
.inv-total-row { display:flex; justify-content:space-between; padding:6px 0; font-size:0.875rem; }
.inv-total-row.final { border-top:2px solid #1a1a2e; padding-top:10px; font-weight:800; font-size:1.05rem; color:#1a1a2e; }
.inv-total-row.paid-row { color:#16a34a; }
.inv-total-row.due-row { color:#dc2626; font-weight:700; }
.inv-footer { margin-top:40px; border-top:1px solid #eee; padding-top:20px; text-align:center; font-size:0.78rem; color:#999; }
.inv-status-stamp { display:inline-block; border:3px solid; border-radius:8px; padding:4px 16px; font-size:1.1rem; font-weight:800; letter-spacing:.08em; transform:rotate(-15deg); position:absolute; top:60px; right:60px; opacity:0.6; }
.inv-status-stamp.paid { border-color:#16a34a; color:#16a34a; }
.inv-status-stamp.partial { border-color:#d97706; color:#d97706; }
.inv-status-stamp.overdue { border-color:#dc2626; color:#dc2626; }
</style>
</head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Invoices</h2><p class="text-muted-sm">Generate and manage client invoices</p></div>
            <?php if(!$viewInv && in_array($_SESSION['admin_role'], ['org_admin','manager'])): ?><button class="btn-gold" onclick="document.getElementById('invoiceGenModal').style.display='flex'"><i class="fas fa-plus me-2"></i>New Invoice</button><?php endif; ?>
        </div>

        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></div><?php endif; ?>
        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?= $msg ?></div><?php endif; ?>

        <?php if($viewInv): ?>
        <!-- INVOICE VIEW -->
        <div style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap" class="no-print">
            <button class="btn-gold" onclick="window.print()"><i class="fas fa-print me-2"></i>Print / PDF</button>
            <a href="invoices.php" class="btn-outline-gold"><i class="fas fa-arrow-left me-2"></i>Back to Invoices</a>
        </div>
        <div class="invoice-print-area" style="position:relative">
            <?php if(in_array($viewInv['status'],['paid','partial','overdue'])): ?>
            <div class="inv-status-stamp <?=$viewInv['status']?>"><?=strtoupper($viewInv['status'])?></div>
            <?php endif; ?>
            <div class="inv-logo-row">
                <div class="inv-company">
                    <h2><?=sanitize($orgInfo['name']??'')?></h2>
                    <p><?=sanitize($orgInfo['address']??'')?></p>
                    <p><?=sanitize($orgInfo['phone']??'')?> &nbsp;·&nbsp; <?=sanitize($orgInfo['email']??'')?></p>
                    <div class="inv-badge" style="display:inline-block;margin-top:8px">MARRIAGE HALL</div>
                </div>
                <div style="text-align:right">
                    <h1 class="inv-title">INVOICE</h1>
                    <div class="inv-number"><?=sanitize($viewInv['invoice_number'])?></div>
                    <div style="font-size:0.82rem;color:#888;margin-top:8px">Issue Date: <?=date('d M Y',strtotime($viewInv['issue_date']))?></div>
                    <?php if($viewInv['due_date']): ?><div style="font-size:0.82rem;color:#888">Due: <?=date('d M Y',strtotime($viewInv['due_date']))?></div><?php endif; ?>
                </div>
            </div>
            <div class="inv-meta">
                <div class="inv-to">
                    <h4>Bill To</h4>
                    <strong><?=sanitize($viewInv['client_name'])?></strong>
                    <p><?=sanitize($viewInv['client_phone']??'')?></p>
                    <p><?=sanitize($viewInv['client_email']??'')?></p>
                    <p><?=sanitize($viewInv['client_address']??'')?></p>
                    <?php if($viewInv['cnic']): ?><p>CNIC: <?=sanitize($viewInv['cnic'])?></p><?php endif; ?>
                </div>
                <div class="inv-to">
                    <h4>Event Details</h4>
                    <strong><?=sanitize($viewInv['event_title'])?></strong>
                    <p>Type: <?=ucfirst(str_replace('_',' ',$viewInv['event_type']))?></p>
                    <p>Date: <?=date('d F Y',strtotime($viewInv['event_date']))?></p>
                    <p>Slot: <?=ucfirst(str_replace('_',' ',$viewInv['slot']))?></p>
                    <p>Venue: <?=sanitize($viewInv['hall_name'])?></p>
                </div>
            </div>
            <table class="inv-table">
                <thead><tr><th>#</th><th>Description</th><th style="text-align:right">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr></thead>
                <tbody>
                <?php $n=1; foreach($viewInv['items'] as $it): ?>
                <tr>
                    <td style="color:#888"><?=$n++?></td>
                    <td><?=sanitize($it['description'])?></td>
                    <td style="text-align:right"><?=$it['quantity']?></td>
                    <td style="text-align:right"><?=formatPKR($it['unit_price'])?></td>
                    <td style="text-align:right;font-weight:600"><?=formatPKR($it['total'])?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="inv-total-box">
                <div class="inv-total-table">
                    <div class="inv-total-row"><span>Subtotal</span><span><?=formatPKR($viewInv['subtotal'])?></span></div>
                    <?php if($viewInv['discount']>0): ?><div class="inv-total-row"><span>Discount</span><span style="color:#16a34a">- <?=formatPKR($viewInv['discount'])?></span></div><?php endif; ?>
                    <?php if($viewInv['tax']>0): ?><div class="inv-total-row"><span>Tax</span><span><?=formatPKR($viewInv['tax'])?></span></div><?php endif; ?>
                    <div class="inv-total-row final"><span>TOTAL</span><span><?=formatPKR($viewInv['total_amount'])?></span></div>
                    <div class="inv-total-row paid-row"><span>Amount Paid</span><span><?=formatPKR($viewInv['amount_paid'])?></span></div>
                    <div class="inv-total-row due-row"><span>Amount Due</span><span><?=formatPKR($viewInv['amount_due'])?></span></div>
                </div>
            </div>
            <?php if($viewInv['notes']): ?><div style="margin-top:20px;background:#f8f8f8;border-radius:8px;padding:12px 16px;font-size:0.82rem;color:#666"><strong>Notes:</strong> <?=sanitize($viewInv['notes'])?></div><?php endif; ?>
            <div class="inv-footer">
                <p>Thank you for choosing <?=sanitize($orgInfo['name']??'us')?>. We look forward to serving you.</p>
                <p><?=sanitize($orgInfo['address']??'')?> &nbsp;|&nbsp; <?=sanitize($orgInfo['phone']??'')?></p>
            </div>
        </div>

        <?php else: ?>
        <!-- INVOICES LIST -->
        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-file-invoice me-2"></i>All Invoices (<?=count($invoices)?>)</h3>
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search invoices..." oninput="filterTable(this.value,'invTable')"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="invTable">
                    <thead><tr><th>Invoice #</th><th>Event</th><th>Client</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($invoices as $inv): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:600;color:var(--gold)"><?=sanitize($inv['invoice_number'])?></td>
                        <td><?=sanitize($inv['event_title'])?></td>
                        <td><?=sanitize($inv['client_name'])?></td>
                        <td style="font-weight:600"><?=formatPKR($inv['total_amount'])?></td>
                        <td style="color:var(--success)"><?=formatPKR($inv['amount_paid'])?></td>
                        <td style="color:<?=$inv['amount_due']>0?'var(--danger)':'var(--success)'?>;font-weight:600;"><?=formatPKR($inv['amount_due'])?></td>
                        <td><span class="status-badge <?=$inv['status']?>"><?=ucfirst($inv['status'])?></span></td>
                        <td style="font-size:0.8rem;color:var(--text-muted)"><?=date('d M Y',strtotime($inv['issue_date']))?></td>
                        <td><div class="action-btns">
                            <a href="invoices.php?view=<?=$inv['id']?>" class="btn-action view" title="View/Print"><i class="fas fa-eye"></i></a>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($invoices)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:40px"><i class="fas fa-file-invoice" style="font-size:2rem;display:block;margin-bottom:10px"></i>No invoices yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Generate Invoice Modal (full page overlay) -->
<div id="invoiceGenModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:2000;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--dark-card);border:1px solid var(--dark-border);border-radius:20px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto">
    <div style="padding:20px 24px;border-bottom:1px solid var(--dark-border);display:flex;justify-content:space-between;align-items:center">
      <h5 style="font-size:1.05rem;font-weight:600;margin:0">Generate Invoice</h5>
      <button onclick="document.getElementById('invoiceGenModal').style.display='none'" style="background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" style="padding:24px">
      <?= csrfField() ?>
      <input type="hidden" name="gen_invoice" value="1">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Select Event *</label>
          <select name="event_id" class="form-select" required onchange="fillInvoiceEvent(this)">
            <option value="">Choose event...</option>
            <?php foreach($events as $ev): ?>
            <option value="<?=$ev['id']?>" data-total="<?=$ev['total_amount']?>" data-paid="<?=$ev['advance_paid']?>" <?=$eventPrefill==$ev['id']?'selected':''?>>
              <?=sanitize($ev['event_title'])?> — <?=date('d M Y',strtotime($ev['event_date']))?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label">Discount (PKR)</label><input type="number" step="0.01" name="discount" class="form-control" value="0"></div>
        <div class="col-md-4"><label class="form-label">Tax (PKR)</label><input type="number" step="0.01" name="tax" class="form-control" value="0"></div>
        <div class="col-md-4"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Payment instructions, terms..."></textarea></div>

        <!-- Line Items -->
        <div class="col-12">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <label class="form-label" style="margin:0">Line Items</label>
            <button type="button" class="btn-outline-gold" style="padding:5px 12px;font-size:0.78rem" onclick="addItem()"><i class="fas fa-plus me-1"></i>Add Item</button>
          </div>
          <div id="itemsContainer">
            <div class="inv-item-row row g-2" style="margin-bottom:8px">
              <div class="col-md-6"><input type="text" name="items[0][desc]" class="form-control" placeholder="Description" required></div>
              <div class="col-md-2"><input type="number" name="items[0][qty]" class="form-control" placeholder="Qty" value="1" min="1"></div>
              <div class="col-md-3"><input type="number" name="items[0][price]" class="form-control" placeholder="Price" step="0.01"></div>
              <div class="col-md-1"><button type="button" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:10px" onclick="this.closest('.inv-item-row').remove()"><i class="fas fa-trash"></i></button></div>
            </div>
          </div>
        </div>
      </div>
      <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('invoiceGenModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-gold"><i class="fas fa-file-invoice me-2"></i>Generate Invoice</button>
      </div>
    </form>
  </div>
</div>

<script>
let itemIdx = 1;
function addItem() {
    const c = document.getElementById('itemsContainer');
    c.insertAdjacentHTML('beforeend', `<div class="inv-item-row row g-2" style="margin-bottom:8px">
      <div class="col-md-6"><input type="text" name="items[${itemIdx}][desc]" class="form-control" placeholder="Description"></div>
      <div class="col-md-2"><input type="number" name="items[${itemIdx}][qty]" class="form-control" placeholder="Qty" value="1" min="1"></div>
      <div class="col-md-3"><input type="number" name="items[${itemIdx}][price]" class="form-control" placeholder="Price" step="0.01"></div>
      <div class="col-md-1"><button type="button" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:10px" onclick="this.closest('.inv-item-row').remove()"><i class="fas fa-trash"></i></button></div>
    </div>`);
    itemIdx++;
}
function fillInvoiceEvent(sel) {
    const opt = sel.options[sel.selectedIndex];
    const total = parseFloat(opt.dataset.total||0);
    const firstItem = document.querySelector('[name="items[0][price]"]');
    if(firstItem && total > 0) firstItem.value = total;
    const firstDesc = document.querySelector('[name="items[0][desc]"]');
    if(firstDesc) firstDesc.value = sel.options[sel.selectedIndex].text.split(' — ')[0] + ' — Hall Booking';
}
function filterTable(q,tid){q=q.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none');}
<?php if($eventPrefill && !$viewInv): ?>window.addEventListener('DOMContentLoaded',()=>{document.getElementById('invoiceGenModal').style.display='flex';const sel=document.querySelector('[name="event_id"]');if(sel)fillInvoiceEvent(sel);});<?php endif; ?>
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
