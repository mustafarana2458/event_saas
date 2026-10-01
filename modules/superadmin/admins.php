<?php
require_once '../../includes/config.php';
requireSuperAdmin();
$db = getDB();

$orgFilter = intval($_GET['org_id'] ?? 0);
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id    = intval($_POST['id'] ?? 0);
    $orgId = intval($_POST['organization_id'] ?? 0);
    $name  = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $role  = in_array($_POST['role']??'',['org_admin','manager','staff']) ? $_POST['role'] : 'staff';
    $pass  = $_POST['password'] ?? '';

    if ($id) {
        $sql = "UPDATE admins SET name=?,email=?,phone=?,role=? WHERE id=?";
        $params = [$name,$email,$phone,$role,$id];
        if ($pass) { $sql = "UPDATE admins SET name=?,email=?,phone=?,role=?,password=? WHERE id=?"; $params = [$name,$email,$phone,$role,password_hash($pass,PASSWORD_BCRYPT),$id]; }
        $db->prepare($sql)->execute($params);
    } else {
        $db->prepare("INSERT INTO admins (organization_id,name,email,password,phone,role) VALUES (?,?,?,?,?,?)")
           ->execute([$orgId,$name,$email,password_hash($pass,PASSWORD_BCRYPT),$phone,$role]);
    }
    $msg = 'Admin saved successfully.';
    }
}

$orgs = $db->query("SELECT id,name FROM organizations ORDER BY name")->fetchAll();

if ($orgFilter) {
    $admins = $db->prepare("
        SELECT a.*, o.name as org_name, o.is_active as org_active
        FROM admins a JOIN organizations o ON a.organization_id=o.id
        WHERE a.organization_id = ? ORDER BY o.name, a.name
    ");
    $admins->execute([$orgFilter]);
} else {
    $admins = $db->query("
        SELECT a.*, o.name as org_name, o.is_active as org_active
        FROM admins a JOIN organizations o ON a.organization_id=o.id
        ORDER BY o.name, a.name
    ");
}
$admins = $admins->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admins — HallSaaS</title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/superadmin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Admin Users</h2><p class="text-muted-sm">Manage all organization administrators</p></div>
            <button class="btn-gold" onclick="openModal()"><i class="fas fa-plus me-2"></i>New Admin</button>
        </div>
        <?php if ($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></div><?php endif; ?>

        <?php if ($orgFilter): $org=$db->prepare("SELECT name FROM organizations WHERE id=?");$org->execute([$orgFilter]);$org=$org->fetch(); ?>
        <div class="alert-warning-dark mb-3"><i class="fas fa-filter me-2"></i>Showing admins for: <strong><?= sanitize($org['name']??'') ?></strong> — <a href="admins.php" style="color:var(--gold)">Show All</a></div>
        <?php endif; ?>

        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-user-shield me-2"></i>All Admins (<?= count($admins) ?>)</h3>
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search..." oninput="filterTable(this.value,'adminTable')"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="adminTable">
                    <thead><tr><th>Admin</th><th>Organization</th><th>Role</th><th>Phone</th><th>Last Login</th><th>Org Status</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $a): ?>
                    <tr>
                        <td><div class="org-cell">
                            <div class="user-avatar" style="width:34px;height:34px;border-radius:8px;"><?= strtoupper(substr($a['name'],0,2)) ?></div>
                            <div><strong><?= sanitize($a['name']) ?></strong><small><?= sanitize($a['email']) ?></small></div>
                        </div></td>
                        <td><?= sanitize($a['org_name']) ?></td>
                        <td><span class="role-badge <?= $a['role'] ?>"><?= ucfirst(str_replace('_',' ',$a['role'])) ?></span></td>
                        <td><?= sanitize($a['phone']??'-') ?></td>
                        <td><?= $a['last_login'] ? date('d M Y H:i',strtotime($a['last_login'])) : 'Never' ?></td>
                        <td><span class="status-badge <?= $a['org_active']?'active':'inactive' ?>"><?= $a['org_active']?'Active':'Inactive' ?></span></td>
                        <td><span class="status-badge <?= $a['is_active']?'active':'inactive' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span></td>
                        <td><div class="action-btns">
                            <button class="btn-action edit" onclick="editAdmin(<?= htmlspecialchars(json_encode($a)) ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn-action <?= $a['is_active']?'danger':'success' ?>" onclick="toggleAdmin(<?= $a['id'] ?>,<?= $a['is_active'] ?>)">
                                <i class="fas fa-<?= $a['is_active']?'ban':'check' ?>"></i></button>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adminModalTitle">New Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="aId" value="0">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Organization *</label>
              <select name="organization_id" id="aOrg" class="form-select" required>
                <?php foreach ($orgs as $o): ?><option value="<?= $o['id'] ?>"><?= sanitize($o['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Name *</label><input name="name" id="aName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Email *</label><input name="email" id="aEmail" type="email" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" id="aPhone" class="form-control"></div>
            <div class="col-md-6">
              <label class="form-label">Role</label>
              <select name="role" id="aRole" class="form-select">
                <option value="org_admin">Org Admin</option>
                <option value="manager">Manager</option>
                <option value="staff">Staff</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label" id="passLabel">Password *</label><input type="password" name="password" id="aPass" class="form-control"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const modal = new bootstrap.Modal(document.getElementById('adminModal'));
function openModal() {
    document.getElementById('adminModalTitle').textContent='New Admin';
    document.getElementById('aId').value=0;
    document.getElementById('adminModal').querySelector('form').reset();
    document.getElementById('passLabel').textContent='Password *';
    document.getElementById('aPass').required=true;
    modal.show();
}
function editAdmin(a) {
    document.getElementById('adminModalTitle').textContent='Edit Admin';
    document.getElementById('aId').value=a.id;
    document.getElementById('aOrg').value=a.organization_id;
    document.getElementById('aName').value=a.name;
    document.getElementById('aEmail').value=a.email;
    document.getElementById('aPhone').value=a.phone||'';
    document.getElementById('aRole').value=a.role;
    document.getElementById('passLabel').textContent='New Password (leave blank to keep)';
    document.getElementById('aPass').required=false;
    document.getElementById('aPass').value='';
    modal.show();
}
async function toggleAdmin(id, cur) {
    if (!confirm(cur?'Deactivate this admin?':'Activate this admin?')) return;
    const r = await fetch('../../api/toggle_admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({admin_id:id,status:cur?0:1,csrf_token:'<?= csrfToken() ?>'})});
    const d = await r.json();
    if(d.success) location.reload(); else alert(d.message || 'Error');
}
function filterTable(q,tid) {
    q=q.toLowerCase();
    document.querySelectorAll('#'+tid+' tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none');
}
</script>
</body>
</html>
