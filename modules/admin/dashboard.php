<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];

// Stats
$totalEvents    = $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=?"); $totalEvents->execute([$org]); $totalEvents = $totalEvents->fetchColumn();
$confirmedEvents= $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=? AND status='confirmed'"); $confirmedEvents->execute([$org]); $confirmedEvents=$confirmedEvents->fetchColumn();
$todayEvents    = $db->prepare("SELECT COUNT(*) FROM events WHERE organization_id=? AND event_date=CURDATE()"); $todayEvents->execute([$org]); $todayEvents=$todayEvents->fetchColumn();
$totalRevenue   = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=?"); $totalRevenue->execute([$org]); $totalRevenue=$totalRevenue->fetchColumn();
$pendingAmount  = $db->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM events WHERE organization_id=? AND status NOT IN('cancelled','completed')"); $pendingAmount->execute([$org]); $pendingAmount=$pendingAmount->fetchColumn();
$thisMonthRev   = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=? AND MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())"); $thisMonthRev->execute([$org]); $thisMonthRev=$thisMonthRev->fetchColumn();
$totalClients   = $db->prepare("SELECT COUNT(*) FROM clients WHERE organization_id=?"); $totalClients->execute([$org]); $totalClients=$totalClients->fetchColumn();

// Upcoming Events
$upcomingEvents = $db->prepare("
    SELECT e.*, c.name as client_name, c.phone as client_phone, h.name as hall_name
    FROM events e JOIN clients c ON e.client_id=c.id JOIN halls h ON e.hall_id=h.id
    WHERE e.organization_id=? AND e.event_date >= CURDATE() AND e.status NOT IN('cancelled')
    ORDER BY e.event_date ASC LIMIT 8
");
$upcomingEvents->execute([$org]); $upcomingEvents=$upcomingEvents->fetchAll();

// Recent Payments
$recentPayments = $db->prepare("
    SELECT p.*, e.event_title, c.name as client_name
    FROM payments p JOIN events e ON p.event_id=e.id JOIN clients c ON e.client_id=c.id
    WHERE p.organization_id=? ORDER BY p.created_at DESC LIMIT 5
");
$recentPayments->execute([$org]); $recentPayments=$recentPayments->fetchAll();

// Calendar data for current month
$calEvents = $db->prepare("
    SELECT event_date, status, COUNT(*) as cnt
    FROM events WHERE organization_id=? AND MONTH(event_date)=MONTH(NOW()) AND YEAR(event_date)=YEAR(NOW())
    GROUP BY event_date, status
");
$calEvents->execute([$org]); $calEventsRaw=$calEvents->fetchAll();
$calMap = [];
foreach($calEventsRaw as $ce) { $calMap[$ce['event_date']][] = $ce; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Dashboard — <?= sanitize($_SESSION['org_name']) ?></title>
    <?php include '../../includes/dashboard_styles.php'; ?>
</head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div>
                <h2>Dashboard</h2>
                <p class="text-muted-sm">Welcome back, <?= sanitize($_SESSION['admin_name']) ?> · <?= sanitize($_SESSION['org_name']) ?></p>
            </div>
            <a href="events.php?action=new" class="btn-gold"><i class="fas fa-plus me-2"></i>New Booking</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#C9A84C,#E8C97A)"><i class="fas fa-calendar-star"></i></div>
                <div class="stat-info"><span class="stat-label">Total Events</span><span class="stat-value"><?= $totalEvents ?></span><span class="stat-sub"><?= $confirmedEvents ?> Confirmed</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f857a6,#ff5858)"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-info"><span class="stat-label">Today's Events</span><span class="stat-value"><?= $todayEvents ?></span><span class="stat-sub"><?= date('d M Y') ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info"><span class="stat-label">Revenue Collected</span><span class="stat-value" style="font-size:1.2rem"><?= formatPKR($totalRevenue) ?></span><span class="stat-sub">This month: <?= formatPKR($thisMonthRev) ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="fas fa-clock"></i></div>
                <div class="stat-info"><span class="stat-label">Pending Amount</span><span class="stat-value" style="font-size:1.2rem"><?= formatPKR($pendingAmount) ?></span><span class="stat-sub"><?= $totalClients ?> total clients</span></div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Calendar -->
            <div class="col-lg-5">
                <div class="card-section h-100">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-alt me-2"></i>Booking Calendar</h3>
                        <a href="calendar.php" class="btn-link">Full View <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="calendar-wrapper">
                        <div class="calendar-header">
                            <h4 id="calMonthLabel"><?= date('F Y') ?></h4>
                            <div class="cal-nav">
                                <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                                <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calGrid"></div>
                        <div style="display:flex;gap:16px;margin-top:14px;flex-wrap:wrap;">
                            <span style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:var(--text-muted)"><span style="width:8px;height:8px;border-radius:50%;background:#60a5fa;display:inline-block"></span>Confirmed</span>
                            <span style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:var(--text-muted)"><span style="width:8px;height:8px;border-radius:50%;background:#fbbf24;display:inline-block"></span>Tentative</span>
                            <span style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:var(--text-muted)"><span style="width:8px;height:8px;border-radius:50%;background:#4ade80;display:inline-block"></span>Completed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="col-lg-7">
                <div class="card-section h-100">
                    <div class="section-header">
                        <h3><i class="fas fa-star me-2"></i>Upcoming Events</h3>
                        <a href="events.php" class="btn-link">All Events <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Event</th><th>Date</th><th>Hall</th><th>Client</th><th>Payment</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($upcomingEvents as $ev): ?>
                            <tr style="cursor:pointer" onclick="window.location='events.php?view=<?= $ev['id'] ?>'">
                                <td>
                                    <strong><?= sanitize($ev['event_title']) ?></strong>
                                    <small style="display:block;color:var(--text-muted);text-transform:capitalize"><?= str_replace('_',' ',$ev['event_type']) ?></small>
                                </td>
                                <td>
                                    <strong><?= date('d M', strtotime($ev['event_date'])) ?></strong>
                                    <small style="display:block;color:var(--text-muted)"><?= date('Y', strtotime($ev['event_date'])) ?></small>
                                </td>
                                <td style="font-size:0.82rem"><?= sanitize($ev['hall_name']) ?></td>
                                <td style="font-size:0.82rem"><?= sanitize($ev['client_name']) ?></td>
                                <td>
                                    <?php $pct = $ev['total_amount']>0 ? ($ev['advance_paid']/$ev['total_amount']*100) : 0; ?>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:3px"><?= round($pct) ?>% paid</div>
                                    <div class="progress-custom" style="width:80px">
                                        <div class="progress-bar-custom" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </td>
                                <td><span class="status-badge <?= $ev['status'] ?>"><?= ucfirst(str_replace('_',' ',$ev['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upcomingEvents)): ?>
                            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:40px">No upcoming events</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="card-section" style="margin-top:24px">
            <div class="section-header">
                <h3><i class="fas fa-receipt me-2"></i>Recent Payments</h3>
                <a href="payments.php" class="btn-link">All Payments <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Event</th><th>Client</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><?= sanitize($p['event_title']) ?></td>
                        <td><?= sanitize($p['client_name']) ?></td>
                        <td style="color:var(--success);font-weight:600"><?= formatPKR($p['amount']) ?></td>
                        <td><span class="role-badge manager" style="text-transform:capitalize"><?= str_replace('_',' ',$p['payment_method']) ?></span></td>
                        <td style="font-size:0.82rem;color:var(--text-muted)"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recentPayments)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">No payments yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const calEventData = <?= json_encode($calMap) ?>;
let currentYear = <?= date('Y') ?>, currentMonth = <?= date('n') - 1 ?>;

function renderCalendar(year, month) {
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const grid = document.getElementById('calGrid');
    const label = document.getElementById('calMonthLabel');
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    label.textContent = months[month] + ' ' + year;

    grid.innerHTML = days.map(d => `<div class="cal-day-header">${d}</div>`).join('');

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month+1, 0).getDate();
    const today = new Date();

    for (let i=0;i<firstDay;i++) grid.innerHTML += `<div class="cal-day other-month"></div>`;

    for (let d=1; d<=daysInMonth; d++) {
        const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = (d===today.getDate() && month===today.getMonth() && year===today.getFullYear());
        const evts = calEventData[dateStr] || [];
        let dots = evts.map(e => `<div class="cal-dot ${e.status}" title="${e.cnt} ${e.status}"></div>`).join('');
        grid.innerHTML += `<div class="cal-day ${isToday?'today':''} ${evts.length?'has-events':''}" onclick="goToDate('${dateStr}')">
            <span class="day-num">${d}</span>
            <div class="cal-dots">${dots}</div>
        </div>`;
    }
}

function changeMonth(dir) {
    currentMonth += dir;
    if (currentMonth > 11) { currentMonth=0; currentYear++; }
    if (currentMonth < 0) { currentMonth=11; currentYear--; }
    renderCalendar(currentYear, currentMonth);
}

function goToDate(d) { window.location.href = '<?= APP_URL ?>/modules/admin/calendar.php?date='+d; }

renderCalendar(currentYear, currentMonth);
</script>
<?php include '../../includes/session_check.php'; ?>
</body>
</html>
