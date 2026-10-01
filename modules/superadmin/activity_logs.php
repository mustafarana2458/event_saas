<?php
require_once '../../includes/config.php';
requireSuperAdmin();
$db = getDB();
$logs = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 200")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Activity Logs — HallSaaS</title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/superadmin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header"><div><h2>Activity Logs</h2><p class="text-muted-sm">System-wide audit trail</p></div></div>
        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-history me-2"></i>Recent Activity</h3>
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Filter logs..." oninput="filterTable(this.value,'logTable')"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="logTable">
                    <thead><tr><th>Type</th><th>Action</th><th>Description</th><th>IP Address</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><span class="role-badge <?= $l['user_type']==='super_admin'?'org_admin':'manager' ?>"><?= $l['user_type']==='super_admin'?'Super Admin':'Admin' ?></span></td>
                        <td><?= sanitize($l['action']) ?></td>
                        <td style="color:var(--text-muted);font-size:0.82rem;"><?= sanitize($l['description']??'-') ?></td>
                        <td style="font-family:monospace;font-size:0.8rem;"><?= sanitize($l['ip_address']??'-') ?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted);"><?= date('d M Y H:i:s',strtotime($l['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>function filterTable(q,tid){q=q.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(tr=>tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none');}</script>
</body></html>
