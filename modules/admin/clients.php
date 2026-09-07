<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id    = intval($_POST['id']??0);
    $name  = sanitize($_POST['name']??'');
    $phone = sanitize($_POST['phone']??'');
    $email = sanitize($_POST['email']??'');
    $cnic  = sanitize($_POST['cnic']??'');
    $addr  = sanitize($_POST['address']??'');
    if($id) {
        $db->prepare("UPDATE clients SET name=?,phone=?,email=?,cnic=?,address=? WHERE id=? AND organization_id=?")->execute([$name,$phone,$email,$cnic,$addr,$id,$org]);
    } else {
        $db->prepare("INSERT INTO clients (organization_id,name,phone,email,cnic,address) VALUES (?,?,?,?,?,?)")->execute([$org,$name,$phone,$email,$cnic,$addr]);
    }
    $msg='Client saved.';
    }
}
if(isset($_GET['delete'])){
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
    $db->prepare("DELETE FROM clients WHERE id=? AND organization_id=?")->execute([intval($_GET['delete']),$org]);
    $msg='Client deleted.';
    }
}

$clients = $db->prepare("
    SELECT c.*, COUNT(e.id) as event_count, COALESCE(SUM(e.total_amount),0) as total_revenue,
           COALESCE(SUM(e.advance_paid),0) as total_paid
    FROM clients c LEFT JOIN events e ON e.client_id=c.id AND e.status!='cancelled'
    WHERE c.organization_id=? GROUP BY c.id ORDER BY c.name
");
$clients->execute([$org]); $clients=$clients->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Clients — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Clients</h2><p class="text-muted-sm">Manage your client database</p></div>
            <button class="btn-gold" onclick="openModal()"><i class="fas fa-plus me-2"></i>New Client</button>
        </div>
        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?=$err?></div><?php endif; ?>
        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-users me-2"></i>All Clients (<?=count($clients)?>)</h3>
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search clients..." oninput="filterTable(this.value,'cTable')"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="cTable">
                    <thead><tr><th>Client</th><th>Phone</th><th>CNIC</th><th>Events</th><th>Total Charged</th><th>Total Paid</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($clients as $c): ?>
                    <tr>
                        <td><div class="org-cell">
                            <div class="user-avatar" style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#4facfe,#00f2fe)"><?=strtoupper(substr($c['name'],0,2))?></div>
                            <div><strong><?=sanitize($c['name'])?></strong><small><?=sanitize($c['email']??'')?></small></div>
                        </div></td>
                        <td><?=sanitize($c['phone'])?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted)"><?=sanitize($c['cnic']??'-')?></td>
                        <td><?=$c['event_count']?></td>
                        <td style="font-weight:600"><?=formatPKR($c['total_revenue'])?></td>
                        <td style="color:var(--success)"><?=formatPKR($c['total_paid'])?></td>
                        <td><div class="action-btns">
                            <button class="btn-action edit" onclick="editClient(<?=htmlspecialchars(json_encode($c))?>)"><i class="fas fa-edit"></i></button>
                            <a href="events.php?q=<?=urlencode($c['name'])?>" class="btn-action view" title="View Events"><i class="fas fa-calendar"></i></a>
                            <a href="clients.php?delete=<?=$c['id']?>&csrf_token=<?= urlencode(csrfToken()) ?>" class="btn-action danger" onclick="return confirm('Delete client? This cannot be undone if they have events.')"><i class="fas fa-trash"></i></a>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($clients)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px"><i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:10px"></i>No clients yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="clientModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cModalTitle">New Client</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="cId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label><input name="name" id="cName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Phone *</label><input name="phone" id="cPhone" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="cEmail" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">CNIC</label><input name="cnic" id="cCnic" class="form-control" placeholder="35202-1234567-1"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="cAddr" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Save Client</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const modal = new bootstrap.Modal(document.getElementById('clientModal'));
function openModal(){document.getElementById('clientModal').querySelector('form').reset();document.getElementById('cId').value='';document.getElementById('cModalTitle').textContent='New Client';modal.show();}
function editClient(c){document.getElementById('cModalTitle').textContent='Edit Client';document.getElementById('cId').value=c.id;document.getElementById('cName').value=c.name;document.getElementById('cPhone').value=c.phone;document.getElementById('cEmail').value=c.email||'';document.getElementById('cCnic').value=c.cnic||'';document.getElementById('cAddr').value=c.address||'';modal.show();}
function filterTable(q,tid){q=q.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none');}
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
