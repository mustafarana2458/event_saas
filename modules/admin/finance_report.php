<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];

$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? 0);

if ($month) {
    $totalCollected = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=? AND YEAR(payment_date)=? AND MONTH(payment_date)=?");
    $totalCollected->execute([$org,$year,$month]); $totalCollected = $totalCollected->fetchColumn();

    $totalEvents = $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=? AND YEAR(event_date)=? AND MONTH(event_date)=?");
    $totalEvents->execute([$org,$year,$month]); $totalEvents = $totalEvents->fetchColumn();
} else {
    $totalCollected = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=? AND YEAR(payment_date)=?");
    $totalCollected->execute([$org,$year]); $totalCollected = $totalCollected->fetchColumn();

    $totalEvents = $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=? AND YEAR(event_date)=?");
    $totalEvents->execute([$org,$year]); $totalEvents = $totalEvents->fetchColumn();
}

$pendingRevenue = $db->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM events WHERE organization_id=? AND status NOT IN('cancelled','completed') AND YEAR(event_date)=?");
$pendingRevenue->execute([$org,$year]); $pendingRevenue = $pendingRevenue->fetchColumn();

// Monthly breakdown
$monthly = $db->prepare("
    SELECT MONTH(payment_date) as m, MONTHNAME(payment_date) as month_name,
           SUM(amount) as revenue, COUNT(*) as txn_count
    FROM payments WHERE organization_id=? AND YEAR(payment_date)=?
    GROUP BY MONTH(payment_date) ORDER BY m
");
$monthly->execute([$org,$year]); $monthly = $monthly->fetchAll();

// Payment method breakdown
if ($month) {
    $byMethod = $db->prepare("
        SELECT payment_method, SUM(amount) as total, COUNT(*) as cnt
        FROM payments WHERE organization_id=? AND YEAR(payment_date)=? AND MONTH(payment_date)=?
        GROUP BY payment_method
    ");
    $byMethod->execute([$org,$year,$month]);
} else {
    $byMethod = $db->prepare("
        SELECT payment_method, SUM(amount) as total, COUNT(*) as cnt
        FROM payments WHERE organization_id=? AND YEAR(payment_date)=?
        GROUP BY payment_method
    ");
    $byMethod->execute([$org,$year]);
}
$byMethod = $byMethod->fetchAll();

// Events by type
if ($month) {
    $byType = $db->prepare("
        SELECT event_type, COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as revenue
        FROM events WHERE organization_id=? AND YEAR(event_date)=? AND MONTH(event_date)=?
        GROUP BY event_type ORDER BY revenue DESC
    ");
    $byType->execute([$org,$year,$month]);
} else {
    $byType = $db->prepare("
        SELECT event_type, COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as revenue
        FROM events WHERE organization_id=? AND YEAR(event_date)=?
        GROUP BY event_type ORDER BY revenue DESC
    ");
    $byType->execute([$org,$year]);
}
$byType = $byType->fetchAll();

// Top paying clients
$topClients = $db->prepare("
    SELECT c.name, SUM(p.amount) as paid, COUNT(DISTINCT e.id) as events
    FROM payments p JOIN events e ON p.event_id=e.id JOIN clients c ON e.client_id=c.id
    WHERE p.organization_id=? AND YEAR(p.payment_date)=?
    GROUP BY c.id ORDER BY paid DESC LIMIT 5
");
$topClients->execute([$org,$year]); $topClients = $topClients->fetchAll();

// Hall utilization
$hallUtil = $db->prepare("
    SELECT h.name, COUNT(e.id) as bookings, COALESCE(SUM(e.total_amount),0) as revenue
    FROM halls h LEFT JOIN events e ON e.hall_id=h.id AND e.organization_id=? AND YEAR(e.event_date)=? AND e.status!='cancelled'
    WHERE h.organization_id=?
    GROUP BY h.id ORDER BY bookings DESC
");
$hallUtil->execute([$org,$year,$org]); $hallUtil = $hallUtil->fetchAll();

$maxMonthly = count($monthly) ? max(array_column($monthly,'revenue')) : 1;
$years = range(date('Y'), date('Y')-3);
$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Finance Report — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?>
<style>
.bar-chart { display:flex; align-items:flex-end; gap:8px; height:140px; padding:0 4px; }
.bar-item { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; }
.bar-fill { width:100%; border-radius:6px 6px 0 0; background:linear-gradient(180deg,var(--gold-light),var(--gold)); transition:height 1s ease; min-width:20px; }
.bar-label { font-size:0.62rem; color:var(--text-muted); text-align:center; }
.bar-value { font-size:0.6rem; color:var(--gold); }
.method-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--dark-border); }
.method-row:last-child { border-bottom:none; }
</style>
</head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Finance Report</h2><p class="text-muted-sm">Financial overview for <?=sanitize($_SESSION['org_name'])?></p></div>
            <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select name="year" class="form-select" style="width:100px">
                    <?php foreach($years as $y): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endforeach; ?>
                </select>
                <select name="month" class="form-select" style="width:130px">
                    <option value="0">All Months</option>
                    <?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>" <?=$i==$month?'selected':''?>><?=$months[$i]?></option><?php endfor; ?>
                </select>
                <button type="submit" class="btn-gold" style="padding:9px 16px"><i class="fas fa-filter me-1"></i>Apply</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info"><span class="stat-label">Revenue Collected</span><span class="stat-value" style="font-size:1.15rem"><?=formatPKR($totalCollected)?></span><span class="stat-sub"><?=$year?><?=$month?" · ".$months[$month]:""?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="fas fa-clock"></i></div>
                <div class="stat-info"><span class="stat-label">Pending Collection</span><span class="stat-value" style="font-size:1.15rem"><?=formatPKR($pendingRevenue)?></span><span class="stat-sub">From active events</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#C9A84C,#E8C97A)"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info"><span class="stat-label">Total Events</span><span class="stat-value"><?=$totalEvents?></span><span class="stat-sub"><?=$year?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info"><span class="stat-label">Avg per Event</span><span class="stat-value" style="font-size:1.15rem"><?=formatPKR($totalEvents>0?$totalCollected/$totalEvents:0)?></span><span class="stat-sub">Revenue per event</span></div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Bar Chart -->
            <div class="col-lg-8">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-chart-bar me-2"></i>Monthly Revenue — <?=$year?></h3></div>
                    <div style="padding:24px">
                        <div class="bar-chart">
                            <?php for($m=1;$m<=12;$m++):
                                $mData = null;
                                foreach($monthly as $md) if($md['m']==$m){$mData=$md;break;}
                                $rev = $mData ? $mData['revenue'] : 0;
                                $h = $maxMonthly > 0 ? max(4, round($rev/$maxMonthly*130)) : 4;
                            ?>
                            <div class="bar-item">
                                <div class="bar-value"><?=$rev>0?'₨'.number_format($rev/1000,0).'K':'':''?></div>
                                <div class="bar-fill" style="height:<?=$h?>px" title="<?=$months[$m]?>: <?=formatPKR($rev)?>"></div>
                                <div class="bar-label"><?=substr($months[$m],0,3)?></div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="col-lg-4">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-wallet me-2"></i>By Method</h3></div>
                    <div style="padding:16px 24px">
                        <?php $methodIcons=['cash'=>'fa-money-bill','bank_transfer'=>'fa-university','cheque'=>'fa-file-alt','online'=>'fa-mobile-alt'];
                        $methodColors=['cash'=>'#4ade80','bank_transfer'=>'#60a5fa','cheque'=>'#fbbf24','online'=>'#c4b5fd'];
                        foreach($byMethod as $bm): ?>
                        <div class="method-row">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:32px;height:32px;border-radius:8px;background:rgba(201,168,76,0.1);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:0.85rem">
                                    <i class="fas <?=$methodIcons[$bm['payment_method']]??'fa-money'?>"></i>
                                </div>
                                <div>
                                    <div style="font-size:0.85rem;font-weight:500;text-transform:capitalize"><?=str_replace('_',' ',$bm['payment_method'])?></div>
                                    <div style="font-size:0.72rem;color:var(--text-muted)"><?=$bm['cnt']?> transactions</div>
                                </div>
                            </div>
                            <div style="font-weight:700;color:var(--gold)"><?=formatPKR($bm['total'])?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($byMethod)): ?><div style="text-align:center;color:var(--text-muted);padding:20px">No data</div><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Events by Type -->
            <div class="col-lg-6">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-star me-2"></i>Events by Type</h3></div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Type</th><th>Events</th><th>Revenue</th></tr></thead>
                            <tbody>
                            <?php foreach($byType as $bt): ?>
                            <tr>
                                <td style="text-transform:capitalize;font-weight:500"><?=str_replace('_',' ',$bt['event_type'])?></td>
                                <td><?=$bt['cnt']?></td>
                                <td style="color:var(--gold);font-weight:600"><?=formatPKR($bt['revenue'])?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($byType)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px">No data</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Hall Utilization -->
            <div class="col-lg-6">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-door-open me-2"></i>Hall Utilization</h3></div>
                    <div style="padding:16px 24px">
                        <?php $maxBook=count($hallUtil)?max(array_column($hallUtil,'bookings')):1; foreach($hallUtil as $h): $pct=$maxBook>0?($h['bookings']/$maxBook*100):0; ?>
                        <div style="margin-bottom:16px">
                            <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                                <span style="font-size:0.875rem;font-weight:500"><?=sanitize($h['name'])?></span>
                                <span style="font-size:0.82rem;color:var(--gold);font-weight:600"><?=$h['bookings']?> bookings</span>
                            </div>
                            <div class="progress-custom"><div class="progress-bar-custom" style="width:<?=$pct?>%"></div></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:3px"><?=formatPKR($h['revenue'])?> revenue</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Top Clients -->
            <div class="col-12">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-trophy me-2"></i>Top Clients — <?=$year?></h3></div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Rank</th><th>Client</th><th>Events</th><th>Total Paid</th></tr></thead>
                            <tbody>
                            <?php $rank=1; foreach($topClients as $tc): ?>
                            <tr>
                                <td>
                                    <?php if($rank<=3): ?>
                                    <span style="font-size:1.1rem"><?=['🥇','🥈','🥉'][$rank-1]?></span>
                                    <?php else: ?><span style="color:var(--text-muted)">#<?=$rank?></span><?php endif; ?>
                                </td>
                                <td style="font-weight:600"><?=sanitize($tc['name'])?></td>
                                <td><?=$tc['events']?></td>
                                <td style="color:var(--gold);font-weight:700;font-size:1rem"><?=formatPKR($tc['paid'])?></td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                            <?php if(empty($topClients)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px">No data</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/session_check.php'; ?>
</body></html>
