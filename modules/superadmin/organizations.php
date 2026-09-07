<?php
require_once '../../includes/config.php';
requireSuperAdmin();
$db = getDB();

// Handle save
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id   = intval($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($name));
    $city = sanitize($_POST['city'] ?? '');
    $addr = sanitize($_POST['address'] ?? '');
    $phone= sanitize($_POST['phone'] ?? '');
    $email= sanitize($_POST['email'] ?? '');
    $plan = in_array($_POST['plan'] ?? '', ['basic','professional','enterprise']) ? $_POST['plan'] : 'basic';
    $halls= intval($_POST['total_halls'] ?? 1);
    $desc = sanitize($_POST['description'] ?? '');

    if ($id) {
        $db->prepare("UPDATE organizations SET name=?,slug=?,city=?,address=?,phone=?,email=?,subscription_plan=?,total_halls=?,description=? WHERE id=?")
           ->execute([$name,$slug,$city,$addr,$phone,$email,$plan,$halls,$desc,$id]);
        $msg = 'Organization updated successfully.';
    } else {
        // Also create default admin
        $aName  = sanitize($_POST['admin_name'] ?? '');
        $aEmail = sanitize($_POST['admin_email'] ?? '');
        $aPass  = $_POST['admin_password'] ?? '';
        $aPhone = sanitize($_POST['admin_phone'] ?? '');

        // Ensure unique slug
        $base = $slug; $i = 1;
        $slugCheck = $db->prepare("SELECT id FROM organizations WHERE slug=?");
        $slugCheck->execute([$slug]);
        while ($slugCheck->fetchColumn()) {
            $slug = $base . '-' . $i++;
            $slugCheck->execute([$slug]);
        }

        $db->prepare("INSERT INTO organizations (name,slug,city,address,phone,email,subscription_plan,total_halls,description,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$name,$slug,$city,$addr,$phone,$email,$plan,$halls,$desc,$_SESSION['super_admin_id']]);
        $orgId = $db->lastInsertId();

        $newAdminId = null;
        if ($aName && $aEmail && $aPass) {
            $hash = password_hash($aPass, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO admins (organization_id,name,email,password,phone,role) VALUES (?,?,?,?,?,?)")
               ->execute([$orgId,$aName,$aEmail,$hash,$aPhone,'org_admin']);
            $newAdminId = $db->lastInsertId();
        }

        // Every organization needs at least one branch for halls/events to attach to
        $db->prepare("INSERT INTO branches (organization_id,name,address,phone,manager_id) VALUES (?,?,?,?,?)")
           ->execute([$orgId,'Main Branch',$addr,$phone,$newAdminId]);
        if ($newAdminId) {
            $mainBranchId = $db->lastInsertId();
            $db->prepare("UPDATE admins SET branch_id=? WHERE id=?")->execute([$mainBranchId,$newAdminId]);
        }

        logActivity('super_admin', $_SESSION['super_admin_id'], 'ORG_CREATE', "Created org: $name");
        $msg = 'Organization created successfully.';
    }
    }
}

$editOrg = null;
if (isset($_GET['edit'])) {
    $editOrg = $db->prepare("SELECT * FROM organizations WHERE id=?");
    $editOrg->execute([intval($_GET['edit'])]);
    $editOrg = $editOrg->fetch();
}

$orgs = $db->query("
    SELECT o.*, COUNT(DISTINCT a.id) as admin_count, COUNT(DISTINCT e.id) as event_count,
           COALESCE(SUM(p.amount),0) as total_revenue
    FROM organizations o
    LEFT JOIN admins a ON a.organization_id = o.id
    LEFT JOIN events e ON e.organization_id = o.id
    LEFT JOIN payments p ON p.organization_id = o.id
    GROUP BY o.id ORDER BY o.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizations — HallSaaS</title>
    <?php include '../../includes/dashboard_styles.php'; ?>
</head>
<body>
<?php include '../../includes/superadmin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Organizations</h2><p class="text-muted-sm">Manage all marriage hall organizations</p></div>
            <button class="btn-gold" onclick="openModal()"><i class="fas fa-plus me-2"></i>New Organization</button>
        </div>

        <?php if ($msg): ?>
        <div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
        <div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></div>
        <?php endif; ?>

        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-building me-2"></i>All Organizations (<?= count($orgs) ?>)</h3>
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" id="searchOrg" placeholder="Search organizations..." oninput="filterTable(this.value)"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="orgTable">
                    <thead><tr>
                        <th>Organization</th><th>City</th><th>Plan</th>
                        <th>Admins</th><th>Events</th><th>Revenue</th>
                        <th>Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($orgs as $o): ?>
                    <tr>
                        <td><div class="org-cell">
                            <div class="org-avatar"><?= strtoupper(substr($o['name'],0,2)) ?></div>
                            <div><strong><?= sanitize($o['name']) ?></strong><small><?= sanitize($o['email']??'') ?></small></div>
                        </div></td>
                        <td><?= sanitize($o['city']??'-') ?></td>
                        <td><span class="badge-plan <?= $o['subscription_plan'] ?>"><?= ucfirst($o['subscription_plan']) ?></span></td>
                        <td><?= $o['admin_count'] ?></td>
                        <td><?= $o['event_count'] ?></td>
                        <td><?= formatPKR($o['total_revenue']) ?></td>
                        <td><span class="status-badge <?= $o['is_active']?'active':'inactive' ?>"><?= $o['is_active']?'Active':'Inactive' ?></span></td>
                        <td><div class="action-btns">
                            <button class="btn-action edit" onclick="editOrg(<?= htmlspecialchars(json_encode($o)) ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-action <?= $o['is_active']?'danger':'success' ?>"
                                onclick="toggleOrg(<?= $o['id'] ?>,<?= $o['is_active'] ?>)"
                                title="<?= $o['is_active']?'Deactivate':'Activate' ?>">
                                <i class="fas fa-<?= $o['is_active']?'ban':'check' ?>"></i></button>
                            <a href="admins.php?org_id=<?= $o['id'] ?>" class="btn-action view" title="Manage Admins"><i class="fas fa-users"></i></a>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="orgModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">New Organization</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="formId" value="0">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Organization Name *</label>
              <input type="text" name="name" id="fName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">City</label>
              <input type="text" name="city" id="fCity" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="fAddress" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" id="fPhone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="fEmail" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Subscription Plan</label>
              <select name="plan" id="fPlan" class="form-select">
                <option value="basic">Basic</option>
                <option value="professional">Professional</option>
                <option value="enterprise">Enterprise</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Total Halls</label>
              <input type="number" name="total_halls" id="fHalls" class="form-control" value="1" min="1">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" id="fDesc" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <!-- Admin fields only for new org -->
          <div id="adminFields">
            <hr style="border-color:var(--dark-border);margin:20px 0 16px">
            <p style="font-size:0.82rem;color:var(--gold);font-weight:600;margin-bottom:12px;"><i class="fas fa-user-shield me-2"></i>Default Admin Account</p>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Admin Name</label><input type="text" name="admin_name" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Admin Email</label><input type="email" name="admin_email" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Admin Password</label><input type="password" name="admin_password" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Admin Phone</label><input type="text" name="admin_phone" class="form-control"></div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('orgModal'));
function openModal() {
    document.getElementById('modalTitle').textContent = 'New Organization';
    document.getElementById('formId').value = 0;
    document.getElementById('adminFields').style.display = '';
    document.getElementById('orgModal').querySelector('form').reset();
    modal.show();
}
function editOrg(o) {
    document.getElementById('modalTitle').textContent = 'Edit Organization';
    document.getElementById('formId').value = o.id;
    document.getElementById('fName').value = o.name;
    document.getElementById('fCity').value = o.city||'';
    document.getElementById('fAddress').value = o.address||'';
    document.getElementById('fPhone').value = o.phone||'';
    document.getElementById('fEmail').value = o.email||'';
    document.getElementById('fPlan').value = o.subscription_plan;
    document.getElementById('fHalls').value = o.total_halls;
    document.getElementById('fDesc').value = o.description||'';
    document.getElementById('adminFields').style.display = 'none';
    modal.show();
}
async function toggleOrg(id, cur) {
    if (!confirm(cur ? 'Deactivate this organization? All its admins will be logged out.' : 'Activate this organization?')) return;
    const res = await fetch('../../api/toggle_org.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({org_id:id,status:cur?0:1,csrf_token:'<?= csrfToken() ?>'})});
    const d = await res.json();
    if (d.success) location.reload(); else alert(d.message || 'Error');
}
function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#orgTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>
