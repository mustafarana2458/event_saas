<?php
require_once '../../includes/config.php';
requireSuperAdmin();

$db = getDB();

// Stats
$totalOrgs = $db->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$activeOrgs = $db->query("SELECT COUNT(*) FROM organizations WHERE is_active=1")->fetchColumn();
$totalAdmins = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$totalEvents = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$thisMonthRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())")->fetchColumn();

// Recent Organizations
$orgs = $db->query("
    SELECT o.*, COUNT(DISTINCT a.id) as admin_count, COUNT(DISTINCT e.id) as event_count
    FROM organizations o
    LEFT JOIN admins a ON a.organization_id = o.id
    LEFT JOIN events e ON e.organization_id = o.id
    GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10
")->fetchAll();

// Recent Activity
$recentLogs = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard — HallSaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php include '../../includes/dashboard_styles.php'; ?>
</head>
<body>
<?php include '../../includes/superadmin_sidebar.php'; ?>

<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>

    <div class="page-body">
        <div class="page-header">
            <div>
                <h2>Super Admin Dashboard</h2>
                <p class="text-muted-sm">Platform-wide overview & management</p>
            </div>
            <a href="organizations.php" class="btn-gold"><i class="fas fa-plus me-2"></i>New Organization</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#C9A84C,#E8C97A)">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Organizations</span>
                    <span class="stat-value"><?= $totalOrgs ?></span>
                    <span class="stat-sub"><?= $activeOrgs ?> Active</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Admins</span>
                    <span class="stat-value"><?= $totalAdmins ?></span>
                    <span class="stat-sub">Across all orgs</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Events</span>
                    <span class="stat-value"><?= $totalEvents ?></span>
                    <span class="stat-sub">All time</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f857a6,#ff5858)">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Revenue</span>
                    <span class="stat-value"><?= formatPKR($totalRevenue) ?></span>
                    <span class="stat-sub">This month: <?= formatPKR($thisMonthRevenue) ?></span>
                </div>
            </div>
        </div>

        <!-- Organizations Table -->
        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-building me-2"></i>Organizations</h3>
                <a href="organizations.php" class="btn-link">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>City</th>
                            <th>Plan</th>
                            <th>Admins</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orgs as $org): ?>
                        <tr>
                            <td>
                                <div class="org-cell">
                                    <div class="org-avatar"><?= strtoupper(substr($org['name'],0,2)) ?></div>
                                    <div>
                                        <strong><?= sanitize($org['name']) ?></strong>
                                        <small><?= sanitize($org['email'] ?? '') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($org['city'] ?? '-') ?></td>
                            <td><span class="badge-plan <?= $org['subscription_plan'] ?>"><?= ucfirst($org['subscription_plan']) ?></span></td>
                            <td><?= $org['admin_count'] ?></td>
                            <td><?= $org['event_count'] ?></td>
                            <td>
                                <span class="status-badge <?= $org['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $org['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-action edit" onclick="editOrg(<?= $org['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-action <?= $org['is_active'] ? 'danger' : 'success' ?>"
                                        onclick="toggleOrg(<?= $org['id'] ?>, <?= $org['is_active'] ?>)"
                                        title="<?= $org['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="fas fa-<?= $org['is_active'] ? 'ban' : 'check' ?>"></i>
                                    </button>
                                    <a href="admins.php?org_id=<?= $org['id'] ?>" class="btn-action view" title="Manage Admins"><i class="fas fa-users"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card-section">
            <div class="section-header">
                <h3><i class="fas fa-history me-2"></i>Recent Activity</h3>
            </div>
            <div class="activity-list">
                <?php foreach ($recentLogs as $log): ?>
                <div class="activity-item">
                    <div class="activity-dot <?= $log['user_type'] ?>"></div>
                    <div class="activity-info">
                        <strong><?= sanitize($log['action']) ?></strong>
                        <span><?= sanitize($log['description'] ?? '') ?></span>
                    </div>
                    <div class="activity-time"><?= date('d M, h:i A', strtotime($log['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Org Modal -->
<div class="modal fade" id="toggleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#161616;border:1px solid #2A2A2A;border-radius:16px;">
            <div class="modal-header" style="border-bottom:1px solid #2A2A2A;">
                <h5 class="modal-title text-white" id="toggleModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-white" id="toggleModalBody">Are you sure?</div>
            <div class="modal-footer" style="border-top:1px solid #2A2A2A;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn" id="confirmToggleBtn" style="background:var(--gold);color:#000;font-weight:600;">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingOrgId = null, pendingStatus = null;

function toggleOrg(id, currentStatus) {
    pendingOrgId = id; pendingStatus = currentStatus;
    const action = currentStatus ? 'Deactivate' : 'Activate';
    const warning = currentStatus ? '<div class="alert" style="background:rgba(255,50,50,0.1);border:1px solid rgba(255,50,50,0.3);color:#ff6b6b;border-radius:10px;padding:12px 16px;margin-top:10px;font-size:0.875rem;"><i class="fas fa-exclamation-triangle me-2"></i>All admins & users of this organization will be logged out immediately.</div>' : '';
    document.getElementById('toggleModalTitle').textContent = action + ' Organization';
    document.getElementById('toggleModalBody').innerHTML = `Are you sure you want to <strong>${action.toLowerCase()}</strong> this organization?${warning}`;
    document.getElementById('confirmToggleBtn').style.background = currentStatus ? '#dc3545' : 'var(--gold)';
    document.getElementById('confirmToggleBtn').style.color = currentStatus ? '#fff' : '#000';
    new bootstrap.Modal(document.getElementById('toggleModal')).show();
}

document.getElementById('confirmToggleBtn').addEventListener('click', async function() {
    const res = await fetch('../../api/toggle_org.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({org_id: pendingOrgId, status: pendingStatus ? 0 : 1, csrf_token: '<?= csrfToken() ?>'})
    });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message || 'Error occurred');
});

function editOrg(id) { window.location.href = 'organizations.php?edit=' + id; }
</script>
</body>
</html>
