<?php
require_once '../../includes/config.php';
requireAdmin();
$db   = getDB();
$org  = $_SESSION['org_id'];
$role = $_SESSION['admin_role'];

// Only org_admin can manage branches
if ($role !== 'org_admin') {
    redirect(APP_URL . '/modules/admin/dashboard.php');
}

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id        = intval($_POST['id']??0);
    $name      = sanitize($_POST['name']??'');
    $address   = sanitize($_POST['address']??'');
    $phone     = sanitize($_POST['phone']??'');
    $managerId = intval($_POST['manager_id']??0) ?: null;

    if ($name === '') {
        $err = 'Branch name is required.';
    } elseif ($id) {
        $db->prepare("UPDATE branches SET name=?,address=?,phone=?,manager_id=? WHERE id=? AND organization_id=?")
           ->execute([$name,$address,$phone,$managerId,$id,$org]);
        $msg = 'Branch updated.';
    } else {
        $db->prepare("INSERT INTO branches (organization_id,name,address,phone,manager_id) VALUES (?,?,?,?,?)")
           ->execute([$org,$name,$address,$phone,$managerId]);
        logActivity('admin',$_SESSION['admin_id'],'BRANCH_ADD',"Added branch: $name",$org);
        $msg = 'Branch added.';
    }
    }
}

if (isset($_GET['toggle'])) {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
    $bid = intval($_GET['toggle']);
    $b = $db->prepare("SELECT status FROM branches WHERE id=? AND organization_id=?");
    $b->execute([$bid,$org]); $b=$b->fetch();
    if ($b) {
        $newStatus = $b['status']==='active' ? 'inactive' : 'active';
        $db->prepare("UPDATE branches SET status=? WHERE id=? AND organization_id=?")->execute([$newStatus,$bid,$org]);
        $msg = 'Branch status updated.';
    }
    }
}

$branches = $db->prepare("
    SELECT br.*, m.name as manager_name,
           COUNT(DISTINCT h.id) as hall_count,
           COUNT(DISTINCT a.id) as staff_count
    FROM branches br
    LEFT JOIN admins m ON br.manager_id = m.id
    LEFT JOIN halls h ON h.branch_id = br.id
    LEFT JOIN admins a ON a.branch_id = br.id
    WHERE br.organization_id=? GROUP BY br.id ORDER BY br.name
");
$branches->execute([$org]); $branches=$branches->fetchAll();

$admins = $db->prepare("SELECT id,name,role FROM admins WHERE organization_id=? ORDER BY name");
$admins->execute([$org]); $admins=$admins->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Branches — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Branches</h2><p class="text-muted-sm">Manage your organization's branches / locations</p></div>
            <button class="btn-gold" onclick="openModal()"><i class="fas fa-plus me-2"></i>New Branch</button>
        </div>

        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?=$err?></div><?php endif; ?>

        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-code-branch me-2"></i>All Branches (<?=count($branches)?>)</h3>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Branch</th><th>Manager</th><th>Phone</th><th>Halls</th><th>Staff</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($branches as $b): ?>
                    <tr>
                        <td><div class="org-cell">
                            <div class="user-avatar" style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#C9A84C,#E8C97A)"><?=strtoupper(substr($b['name'],0,2))?></div>
                            <div><strong><?=sanitize($b['name'])?></strong><small><?=sanitize($b['address']??'')?></small></div>
                        </div></td>
                        <td style="font-size:0.85rem"><?=sanitize($b['manager_name']??'—')?></td>
                        <td style="font-size:0.82rem"><?=sanitize($b['phone']??'-')?></td>
                        <td><?=$b['hall_count']?></td>
                        <td><?=$b['staff_count']?></td>
                        <td><span class="status-badge <?=$b['status']==='active'?'active':'inactive'?>"><?=ucfirst($b['status'])?></span></td>
                        <td><div class="action-btns">
                            <button class="btn-action edit" onclick="editBranch(<?=htmlspecialchars(json_encode($b))?>)" title="Edit"><i class="fas fa-edit"></i></button>
                            <a href="branches.php?toggle=<?=$b['id']?>&csrf_token=<?=urlencode(csrfToken())?>" class="btn-action <?=$b['status']==='active'?'danger':'success'?>" title="<?=$b['status']==='active'?'Deactivate':'Activate'?>"><i class="fas fa-<?=$b['status']==='active'?'ban':'check'?>"></i></a>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($branches)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px"><i class="fas fa-code-branch" style="font-size:2rem;display:block;margin-bottom:10px"></i>No branches yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bModalTitle">New Branch</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="bId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Branch Name *</label><input name="name" id="bName" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="bAddr" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" id="bPhone" class="form-control"></div>
            <div class="col-md-6">
              <label class="form-label">Branch Manager <small style="color:var(--text-muted)">(optional)</small></label>
              <select name="manager_id" id="bManager" class="form-select">
                <option value="">— None —</option>
                <?php foreach($admins as $a): ?><option value="<?=$a['id']?>"><?=sanitize($a['name'])?> (<?=ucfirst(str_replace('_',' ',$a['role']))?>)</option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Save Branch</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const modal = new bootstrap.Modal(document.getElementById('branchModal'));
function openModal(){document.getElementById('branchModal').querySelector('form').reset();document.getElementById('bId').value='';document.getElementById('bModalTitle').textContent='New Branch';modal.show();}
function editBranch(b){document.getElementById('bModalTitle').textContent='Edit Branch';document.getElementById('bId').value=b.id;document.getElementById('bName').value=b.name;document.getElementById('bAddr').value=b.address||'';document.getElementById('bPhone').value=b.phone||'';document.getElementById('bManager').value=b.manager_id||'';modal.show();}
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
