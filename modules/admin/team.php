<?php
require_once '../../includes/config.php';
requireAdmin();
$db   = getDB();
$org  = $_SESSION['org_id'];
$role = $_SESSION['admin_role'];

// Only org_admin can manage team
if ($role !== 'org_admin') {
    redirect(APP_URL . '/modules/admin/dashboard.php');
}

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id       = intval($_POST['id']??0);
    $name     = sanitize($_POST['name']??'');
    $email    = sanitize($_POST['email']??'');
    $phone    = sanitize($_POST['phone']??'');
    $r        = in_array($_POST['role']??'',['manager','staff']) ? $_POST['role'] : 'staff';
    $pass     = $_POST['password']??'';
    $branchId = intval($_POST['branch_id']??0) ?: null;

    if ($id) {
        $sql = "UPDATE admins SET name=?,phone=?,role=?,branch_id=? WHERE id=? AND organization_id=? AND role!='org_admin'";
        $params = [$name,$phone,$r,$branchId,$id,$org];
        if($pass){$sql="UPDATE admins SET name=?,phone=?,role=?,branch_id=?,password=? WHERE id=? AND organization_id=? AND role!='org_admin'";$params=[$name,$phone,$r,$branchId,password_hash($pass,PASSWORD_BCRYPT),$id,$org];}
        $db->prepare($sql)->execute($params);
        $msg = 'Team member updated.';
    } else {
        // Check email unique
        $exists=$db->prepare("SELECT id FROM admins WHERE email=?"); $exists->execute([$email]); $exists=$exists->fetch();
        if($exists){ $err='Email already exists.'; }
        elseif(!$pass){ $err='Password is required.'; }
        else {
            $db->prepare("INSERT INTO admins (organization_id,name,email,password,phone,role,branch_id) VALUES (?,?,?,?,?,?,?)")->execute([$org,$name,$email,password_hash($pass,PASSWORD_BCRYPT),$phone,$r,$branchId]);
            logActivity('admin',$_SESSION['admin_id'],'TEAM_ADD',"Added team member: $name",$org);
            $msg = "Team member '$name' added successfully.";
        }
    }
    }
}

if(isset($_GET['toggle'])){
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
    $tid=intval($_GET['toggle']);
    $tm=$db->prepare("SELECT is_active,role FROM admins WHERE id=? AND organization_id=?"); $tm->execute([$tid,$org]); $tm=$tm->fetch();
    if($tm && $tm['role']!=='org_admin'){
        $db->prepare("UPDATE admins SET is_active=? WHERE id=? AND organization_id=?")->execute([$tm['is_active']?0:1,$tid,$org]);
        $msg='Team member status updated.';
    }
    }
}
if(isset($_GET['delete'])){
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
    $tid=intval($_GET['delete']);
    $db->prepare("DELETE FROM admins WHERE id=? AND organization_id=? AND role!='org_admin'")->execute([$tid,$org]);
    $msg='Team member removed.';
    }
}

$team=$db->prepare("SELECT a.*, br.name as branch_name FROM admins a LEFT JOIN branches br ON a.branch_id=br.id WHERE a.organization_id=? ORDER BY a.role,a.name"); $team->execute([$org]); $team=$team->fetchAll();
$branches=$db->prepare("SELECT id,name FROM branches WHERE organization_id=? ORDER BY name"); $branches->execute([$org]); $branches=$branches->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Team — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?>
<style>
.permission-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1px; background:var(--dark-border); border-radius:10px; overflow:hidden; }
.perm-cell { background:var(--dark-surface); padding:10px 14px; font-size:0.8rem; }
.perm-cell.header { background:var(--dark-hover); font-weight:700; color:var(--text-muted); text-transform:uppercase; font-size:0.72rem; letter-spacing:.05em; }
.perm-yes { color:var(--success); } .perm-no { color:var(--danger); }
</style>
</head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Team Members</h2><p class="text-muted-sm">Manage staff with role-based access</p></div>
            <button class="btn-gold" onclick="openModal()"><i class="fas fa-plus me-2"></i>Add Member</button>
        </div>

        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?=$err?></div><?php endif; ?>

        <!-- Role Permissions Guide -->
        <div class="card-section" style="margin-bottom:20px">
            <div class="section-header"><h3><i class="fas fa-shield-alt me-2"></i>Role Permissions</h3></div>
            <div style="padding:16px 20px">
                <div class="permission-grid">
                    <div class="perm-cell header">Permission</div>
                    <div class="perm-cell header">Manager</div>
                    <div class="perm-cell header">Staff</div>
                    <?php
                    $perms=[
                        ['View Events','✓','✓'],
                        ['Create/Edit Events','✓','✓'],
                        ['Delete Events','✓','✗'],
                        ['Manage Payments','✓','✓'],
                        ['Generate Invoices','✓','✗'],
                        ['Manage Clients','✓','✓'],
                        ['Manage Halls','✓','✗'],
                        ['Manage Team','✗','✗'],
                        ['Finance Reports','✓','✗'],
                    ];
                    foreach($perms as $p):
                    ?>
                    <div class="perm-cell"><?=$p[0]?></div>
                    <div class="perm-cell <?=$p[1]==='✓'?'perm-yes':'perm-no'?>"><?=$p[1]==='✓'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'?></div>
                    <div class="perm-cell <?=$p[2]==='✓'?'perm-yes':'perm-no'?>"><?=$p[2]==='✓'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card-section">
            <div class="section-header"><h3><i class="fas fa-users me-2"></i>Team (<?=count($team)?>)</h3></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Member</th><th>Role</th><th>Branch</th><th>Phone</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($team as $m): ?>
                    <tr>
                        <td><div class="org-cell">
                            <div class="user-avatar" style="width:36px;height:36px;border-radius:9px"><?=strtoupper(substr($m['name'],0,2))?></div>
                            <div><strong><?=sanitize($m['name'])?></strong><small><?=sanitize($m['email'])?></small></div>
                        </div></td>
                        <td><span class="role-badge <?=$m['role']?>"><?=ucfirst(str_replace('_',' ',$m['role']))?></span></td>
                        <td style="font-size:0.82rem"><?=sanitize($m['branch_name']??'—')?></td>
                        <td style="font-size:0.82rem"><?=sanitize($m['phone']??'-')?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted)"><?=$m['last_login']?date('d M Y H:i',strtotime($m['last_login'])):'Never'?></td>
                        <td><span class="status-badge <?=$m['is_active']?'active':'inactive'?>"><?=$m['is_active']?'Active':'Inactive'?></span></td>
                        <td>
                            <div class="action-btns">
                                <?php if($m['role']!=='org_admin'): ?>
                                <button class="btn-action edit" onclick="editMember(<?=htmlspecialchars(json_encode($m))?>)"><i class="fas fa-edit"></i></button>
                                <a href="team.php?toggle=<?=$m['id']?>&csrf_token=<?= urlencode(csrfToken()) ?>" class="btn-action <?=$m['is_active']?'danger':'success'?>"><i class="fas fa-<?=$m['is_active']?'ban':'check'?>"></i></a>
                                <a href="team.php?delete=<?=$m['id']?>&csrf_token=<?= urlencode(csrfToken()) ?>" class="btn-action danger" onclick="return confirm('Remove this team member?')"><i class="fas fa-trash"></i></a>
                                <?php else: ?>
                                <span style="font-size:0.75rem;color:var(--text-muted)">Organization Admin</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teamModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tModalTitle">Add Team Member</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="tId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label><input name="name" id="tName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" id="tPhone" class="form-control"></div>
            <div class="col-12"><label class="form-label">Email *</label><input type="email" name="email" id="tEmail" class="form-control" required></div>
            <div class="col-md-6">
              <label class="form-label">Role</label>
              <select name="role" id="tRole" class="form-select">
                <option value="manager">Manager</option>
                <option value="staff">Staff</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Branch <small style="color:var(--text-muted)">(optional)</small></label>
              <select name="branch_id" id="tBranch" class="form-select">
                <option value="">— None —</option>
                <?php foreach($branches as $br): ?><option value="<?=$br['id']?>"><?=sanitize($br['name'])?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" id="tPassLabel">Password *</label>
              <input type="password" name="password" id="tPass" class="form-control">
            </div>
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
const modal=new bootstrap.Modal(document.getElementById('teamModal'));
function openModal(){document.getElementById('teamModal').querySelector('form').reset();document.getElementById('tId').value='';document.getElementById('tModalTitle').textContent='Add Team Member';document.getElementById('tEmail').disabled=false;document.getElementById('tPassLabel').textContent='Password *';modal.show();}
function editMember(m){document.getElementById('tModalTitle').textContent='Edit Member';document.getElementById('tId').value=m.id;document.getElementById('tName').value=m.name;document.getElementById('tPhone').value=m.phone||'';document.getElementById('tEmail').value=m.email;document.getElementById('tEmail').disabled=true;document.getElementById('tRole').value=m.role;document.getElementById('tBranch').value=m.branch_id||'';document.getElementById('tPassLabel').textContent='New Password (leave blank to keep)';document.getElementById('tPass').value='';modal.show();}
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
