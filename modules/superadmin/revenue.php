<?php
require_once '../../includes/config.php';
requireSuperAdmin();
$db = getDB();

$orgsRevenue = $db->query("
    SELECT o.name, o.subscription_plan,
           COALESCE(SUM(p.amount),0) as total_revenue,
           COUNT(DISTINCT e.id) as events
    FROM organizations o
    LEFT JOIN payments p ON p.organization_id=o.id
    LEFT JOIN events e ON e.organization_id=o.id
    WHERE o.is_active=1
    GROUP BY o.id ORDER BY total_revenue DESC
")->fetchAll();

$monthlyRevenue = $db->query("
    SELECT DATE_FORMAT(payment_date,'%Y-%m') as month,
           SUM(amount) as revenue, COUNT(*) as transactions
    FROM payments
    GROUP BY DATE_FORMAT(payment_date,'%Y-%m')
    ORDER BY month DESC LIMIT 12
")->fetchAll();

$totalRevenue = array_sum(array_column($orgsRevenue,'total_revenue'));
$totalEvents  = array_sum(array_column($orgsRevenue,'events'));
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Revenue Report — HallSaaS</title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/superadmin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Revenue Report</h2><p class="text-muted-sm">Platform-wide financial overview</p></div>
        </div>

        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#C9A84C,#E8C97A)"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info"><span class="stat-label">Total Revenue</span><span class="stat-value"><?= formatPKR($totalRevenue) ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info"><span class="stat-label">Total Events</span><span class="stat-value"><?= $totalEvents ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)"><i class="fas fa-building"></i></div>
                <div class="stat-info"><span class="stat-label">Active Orgs</span><span class="stat-value"><?= count($orgsRevenue) ?></span></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-chart-bar me-2"></i>Revenue by Organization</h3></div>
                    <div style="padding:20px 24px;">
                        <?php foreach ($orgsRevenue as $o): $pct = $totalRevenue > 0 ? ($o['total_revenue']/$totalRevenue*100) : 0; ?>
                        <div style="margin-bottom:18px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:0.875rem;font-weight:500;"><?= sanitize($o['name']) ?></span>
                                <span style="font-size:0.875rem;color:var(--gold);font-weight:600;"><?= formatPKR($o['total_revenue']) ?></span>
                            </div>
                            <div class="progress-custom">
                                <div class="progress-bar-custom" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-top:4px;">
                                <span style="font-size:0.72rem;color:var(--text-muted);"><?= $o['events'] ?> events</span>
                                <span style="font-size:0.72rem;color:var(--text-muted);"><?= round($pct,1) ?>%</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-calendar me-2"></i>Monthly Breakdown</h3></div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Month</th><th>Revenue</th><th>Transactions</th></tr></thead>
                            <tbody>
                            <?php foreach ($monthlyRevenue as $m): ?>
                            <tr>
                                <td><?= date('M Y', strtotime($m['month'].'-01')) ?></td>
                                <td style="color:var(--gold);font-weight:600;"><?= formatPKR($m['revenue']) ?></td>
                                <td><?= $m['transactions'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
