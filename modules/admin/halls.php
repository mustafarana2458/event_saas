<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];
$role = $_SESSION['admin_role'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role,['org_admin','manager'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $id       = intval($_POST['id']??0);
    $name     = sanitize($_POST['name']??'');
    $cap      = intval($_POST['capacity']??0);
    $price    = floatval($_POST['price_per_event']??0);
    $desc     = sanitize($_POST['description']??'');
    $amen     = sanitize($_POST['amenities']??'');
    $branchId = intval($_POST['branch_id']??0) ?: null;
    if($id) {
        $db->prepare("UPDATE halls SET name=?,capacity=?,price_per_event=?,description=?,amenities=?,branch_id=? WHERE id=? AND organization_id=?")->execute([$name,$cap,$price,$desc,$amen,$branchId,$id,$org]);
    } else {
        $db->prepare("INSERT INTO halls (organization_id,name,capacity,price_per_event,description,amenities,branch_id) VALUES (?,?,?,?,?,?,?)")->execute([$org,$name,$cap,$price,$desc,$amen,$branchId]);
    }
    $msg='Hall saved.';
    }
}
if(isset($_GET['toggle']) && in_array($role,['org_admin'])){
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please try again.';
    } else {
    $h=$db->prepare("SELECT is_active FROM halls WHERE id=? AND organization_id=?"); $h->execute([intval($_GET['toggle']),$org]); $h=$h->fetch();
    if($h){ $db->prepare("UPDATE halls SET is_active=? WHERE id=? AND organization_id=?")->execute([$h['is_active']?0:1,intval($_GET['toggle']),$org]); $msg='Hall status updated.'; }
    }
}

$halls = $db->prepare("
    SELECT h.*, br.name as branch_name, COUNT(e.id) as total_events,
           SUM(CASE WHEN e.event_date>=CURDATE() AND e.status NOT IN('cancelled') THEN 1 ELSE 0 END) as upcoming
    FROM halls h LEFT JOIN events e ON e.hall_id=h.id
    LEFT JOIN branches br ON h.branch_id=br.id
    WHERE h.organization_id=? GROUP BY h.id ORDER BY h.name
");
$halls->execute([$org]); $halls=$halls->fetchAll();

$branches = $db->prepare("SELECT id,name FROM branches WHERE organization_id=? ORDER BY name");
$branches->execute([$org]); $branches=$branches->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Halls — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Halls & Venues</h2><p class="text-muted-sm">Manage your hall inventory</p></div>
            <?php if(in_array($role,['org_admin','manager'])): ?>
            <button class="btn-gold" onclick="openModal()"><i class="fas fa-plus me-2"></i>Add Hall</button>
            <?php endif; ?>
        </div>
        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?=$err?></div><?php endif; ?>

        <div class="row g-4">
            <?php foreach($halls as $h): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-section" style="margin-bottom:0;transition:transform .25s" onmouseenter="this.style.transform='translateY(-4px)'" onmouseleave="this.style.transform=''">
                    <div style="padding:24px">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
                            <div>
                                <h4 style="font-size:1.05rem;font-weight:700;margin-bottom:4px"><?=sanitize($h['name'])?></h4>
                                <span class="status-badge <?=$h['is_active']?'active':'inactive'?>"><?=$h['is_active']?'Active':'Inactive'?></span>
                                <?php if($h['branch_name']): ?><span style="font-size:0.72rem;color:var(--text-muted);display:block;margin-top:4px"><i class="fas fa-code-branch me-1"></i><?=sanitize($h['branch_name'])?></span><?php endif; ?>
                            </div>
                            <div style="width:44px;height:44px;border-radius:12px;background:var(--gold-dim);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1.2rem">
                                <i class="fas fa-door-open"></i>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                            <div style="background:var(--dark-surface);border-radius:10px;padding:12px;text-align:center">
                                <div style="font-size:1.2rem;font-weight:700;color:var(--gold)"><?=$h['capacity']?></div>
                                <div style="font-size:0.72rem;color:var(--text-muted)">Capacity</div>
                            </div>
                            <div style="background:var(--dark-surface);border-radius:10px;padding:12px;text-align:center">
                                <div style="font-size:1.2rem;font-weight:700;color:var(--success)"><?=$h['total_events']?></div>
                                <div style="font-size:0.72rem;color:var(--text-muted)">Total Events</div>
                            </div>
                        </div>
                        <div style="margin-bottom:12px">
                            <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:2px">Price per Event</div>
                            <div style="font-size:1rem;font-weight:700;color:var(--gold)"><?=formatPKR($h['price_per_event'])?></div>
                        </div>
                        <?php if($h['amenities']): ?>
                        <div style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:4px">
                            <?php foreach(explode(',',$h['amenities']) as $am): ?>
                            <span style="background:var(--gold-dim);color:var(--gold);font-size:0.68rem;padding:2px 8px;border-radius:20px;font-weight:500"><?=trim($am)?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if($h['description']): ?>
                        <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:14px"><?=sanitize($h['description'])?></div>
                        <?php endif; ?>
                        <?php if(in_array($role,['org_admin','manager'])): ?>
                        <div style="display:flex;gap:8px">
                            <button class="btn-outline-gold" style="flex:1;padding:8px;font-size:0.82rem" onclick="editHall(<?=htmlspecialchars(json_encode($h))?>)"><i class="fas fa-edit me-1"></i>Edit</button>
                            <a href="halls.php?toggle=<?=$h['id']?>&csrf_token=<?= urlencode(csrfToken()) ?>" class="btn-action <?=$h['is_active']?'danger':'success'?>" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px" title="<?=$h['is_active']?'Deactivate':'Activate'?>"><i class="fas fa-<?=$h['is_active']?'ban':'check'?>"></i></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if($h['upcoming']>0): ?>
                    <div style="padding:10px 24px;border-top:1px solid var(--dark-border);font-size:0.8rem;color:var(--info)"><i class="fas fa-calendar me-1"></i><?=$h['upcoming']?> upcoming booking<?=$h['upcoming']>1?'s':''?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($halls)): ?>
            <div class="col-12"><div class="card-section"><div style="padding:60px;text-align:center;color:var(--text-muted)"><i class="fas fa-door-open" style="font-size:2.5rem;display:block;margin-bottom:12px"></i>No halls added yet</div></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if(in_array($role,['org_admin','manager'])): ?>
<div class="modal fade" id="hallModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="hModalTitle">Add Hall</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="hId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Hall Name *</label><input name="name" id="hName" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Capacity</label><input type="number" name="capacity" id="hCap" class="form-control" value="100"></div>
            <div class="col-12">
              <label class="form-label">Branch <small style="color:var(--text-muted)">(which location this hall belongs to)</small></label>
              <select name="branch_id" id="hBranch" class="form-select">
                <option value="">— Unassigned —</option>
                <?php foreach($branches as $br): ?><option value="<?=$br['id']?>"><?=sanitize($br['name'])?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Price per Event (PKR)</label><input type="number" step="0.01" name="price_per_event" id="hPrice" class="form-control" value="0"></div>
            <div class="col-12"><label class="form-label">Amenities <small style="color:var(--text-muted)">(comma separated)</small></label><input name="amenities" id="hAmen" class="form-control" placeholder="AC, Stage, Catering, Parking"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="hDesc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Save Hall</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const modal = new bootstrap.Modal(document.getElementById('hallModal'));
function openModal(){document.getElementById('hallModal').querySelector('form').reset();document.getElementById('hId').value='';document.getElementById('hModalTitle').textContent='Add Hall';modal.show();}
function editHall(h){document.getElementById('hModalTitle').textContent='Edit Hall';document.getElementById('hId').value=h.id;document.getElementById('hName').value=h.name;document.getElementById('hCap').value=h.capacity;document.getElementById('hBranch').value=h.branch_id||'';document.getElementById('hPrice').value=h.price_per_event;document.getElementById('hAmen').value=h.amenities||'';document.getElementById('hDesc').value=h.description||'';modal.show();}
</script>
<?php endif; ?>
<?php include '../../includes/session_check.php'; ?>
</body></html>
