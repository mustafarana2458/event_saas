<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$org = $_SESSION['org_id'];

$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));
$focusDate = $_GET['date'] ?? null;
$branchFilter = intval($_GET['branch'] ?? 0);

// Fetch all events for this month
$params = [$org, $month, $year];
$branchWhere = '';
if ($branchFilter) { $branchWhere = " AND e.branch_id=?"; $params[] = $branchFilter; }

$events = $db->prepare("
    SELECT e.id, e.event_title, e.event_date, e.event_type, e.slot, e.status,
           e.total_amount, e.advance_paid, e.remaining_amount,
           c.name as client_name, h.name as hall_name, h.id as hall_id
    FROM events e
    JOIN clients c ON e.client_id=c.id
    JOIN halls h ON e.hall_id=h.id
    WHERE e.organization_id=? AND MONTH(e.event_date)=? AND YEAR(e.event_date)=?$branchWhere
    ORDER BY e.event_date, e.event_time_start
");
$events->execute($params); $events=$events->fetchAll();

// Group by date
$evByDate = [];
foreach($events as $ev) $evByDate[$ev['event_date']][] = $ev;

// Halls for filtering
$halls = $db->prepare("SELECT id, name FROM halls WHERE organization_id=? AND is_active=1"); $halls->execute([$org]); $halls=$halls->fetchAll();

// Branches for filtering
$branchesAll = $db->prepare("SELECT id, name FROM branches WHERE organization_id=? ORDER BY name"); $branchesAll->execute([$org]); $branchesAll=$branchesAll->fetchAll();

$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth=12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth=1; $nextYear++; }
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Calendar — <?= sanitize($_SESSION['org_name']) ?></title><?php include '../../includes/dashboard_styles.php'; ?>
<style>
.full-calendar-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:var(--dark-border); }
.full-cal-day { background:var(--dark-card); min-height:110px; padding:8px; transition:var(--transition); }
.full-cal-day:hover { background:var(--dark-hover); }
.full-cal-day.today { background:rgba(201,168,76,0.06); }
.full-cal-day.other-month { opacity:0.4; }
.full-cal-day-num { font-size:0.82rem; font-weight:600; color:var(--text-muted); margin-bottom:4px; display:flex; align-items:center; justify-content:space-between; }
.full-cal-day.today .full-cal-day-num span:first-child { background:var(--gold); color:#000; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.78rem; }
.ev-chip { padding:3px 7px; border-radius:5px; font-size:0.7rem; font-weight:500; margin-bottom:2px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:var(--transition); }
.ev-chip:hover { opacity:0.85; transform:translateX(2px); }
.ev-chip.confirmed { background:rgba(59,130,246,0.2); color:#93c5fd; border-left:2px solid #60a5fa; }
.ev-chip.tentative { background:rgba(245,158,11,0.2); color:#fde68a; border-left:2px solid #fbbf24; }
.ev-chip.completed { background:rgba(34,197,94,0.2); color:#86efac; border-left:2px solid #4ade80; }
.ev-chip.cancelled { background:rgba(239,68,68,0.1); color:#fca5a5; border-left:2px solid #f87171; text-decoration:line-through; }
.ev-chip.in_progress { background:rgba(139,92,246,0.2); color:#c4b5fd; border-left:2px solid #a78bfa; }
.slot-indicators { display:flex; gap:3px; }
.slot-dot { width:6px; height:6px; border-radius:50%; }
.more-chip { font-size:0.65rem; color:var(--text-muted); text-align:center; padding:2px; }
</style>
</head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div><h2>Booking Calendar</h2><p class="text-muted-sm"><?= $months[$month] ?> <?= $year ?></p></div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <?php if(count($branchesAll) > 1): ?>
                <form method="GET" style="display:inline-flex">
                    <input type="hidden" name="year" value="<?=$year?>">
                    <input type="hidden" name="month" value="<?=$month?>">
                    <select name="branch" class="form-select" style="width:170px" onchange="this.form.submit()">
                        <option value="">All Branches</option>
                        <?php foreach($branchesAll as $br): ?>
                        <option value="<?=$br['id']?>" <?= $branchFilter===(int)$br['id']?'selected':'' ?>><?= sanitize($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
                <a href="?year=<?=$prevYear?>&month=<?=$prevMonth?>&branch=<?=$branchFilter?>" class="btn-outline-gold" style="padding:9px 14px"><i class="fas fa-chevron-left"></i></a>
                <a href="?year=<?=date('Y')?>&month=<?=date('n')?>&branch=<?=$branchFilter?>" class="btn-outline-gold" style="padding:9px 14px;font-size:0.82rem">Today</a>
                <a href="?year=<?=$nextYear?>&month=<?=$nextMonth?>&branch=<?=$branchFilter?>" class="btn-outline-gold" style="padding:9px 14px"><i class="fas fa-chevron-right"></i></a>
                <a href="events.php?action=new" class="btn-gold"><i class="fas fa-plus me-2"></i>New Booking</a>
            </div>
        </div>

        <!-- Stats for this month -->
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
            <?php
            $cnt = ['confirmed'=>0,'tentative'=>0,'completed'=>0,'total'=>count($events)];
            foreach($events as $ev) if(isset($cnt[$ev['status']])) $cnt[$ev['status']]++;
            ?>
            <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#C9A84C,#E8C97A)"><i class="fas fa-calendar"></i></div><div class="stat-info"><span class="stat-label">Total</span><span class="stat-value"><?=$cnt['total']?></span></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)"><i class="fas fa-check"></i></div><div class="stat-info"><span class="stat-label">Confirmed</span><span class="stat-value"><?=$cnt['confirmed']?></span></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="fas fa-clock"></i></div><div class="stat-info"><span class="stat-label">Tentative</span><span class="stat-value"><?=$cnt['tentative']?></span></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)"><i class="fas fa-star"></i></div><div class="stat-info"><span class="stat-label">Completed</span><span class="stat-value"><?=$cnt['completed']?></span></div></div>
        </div>

        <div class="card-section">
            <div class="section-header">
                <h3><?= $months[$month] ?> <?= $year ?></h3>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <?php foreach(['confirmed','tentative','completed','cancelled'] as $s): ?>
                    <span style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:var(--text-muted)">
                        <span class="slot-dot" style="background:<?= ['confirmed'=>'#60a5fa','tentative'=>'#fbbf24','completed'=>'#4ade80','cancelled'=>'#f87171'][$s] ?>"></span>
                        <?= ucfirst($s) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="padding:0">
                <!-- Day headers -->
                <div class="full-calendar-grid" style="margin-bottom:1px">
                    <?php foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                    <div style="padding:10px;text-align:center;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;background:var(--dark-surface)"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="full-calendar-grid">
                <?php
                $firstDay = date('w', mktime(0,0,0,$month,1,$year));
                $daysInMonth = date('t', mktime(0,0,0,$month,1,$year));
                $today = date('Y-m-d');
                // Previous month days
                $prevDays = date('t', mktime(0,0,0,$prevMonth,1,$prevYear));
                for ($i=$firstDay-1; $i>=0; $i--) {
                    $d = $prevDays - $i;
                    echo '<div class="full-cal-day other-month"><div class="full-cal-day-num"><span>'.$d.'</span></div></div>';
                }
                for ($d=1; $d<=$daysInMonth; $d++) {
                    $dateStr = sprintf('%04d-%02d-%02d',$year,$month,$d);
                    $isToday = $dateStr === $today;
                    $dayEvts = $evByDate[$dateStr] ?? [];
                    $isFocus = $dateStr === $focusDate;
                    $extra = $isFocus ? 'outline:2px solid var(--gold);' : '';
                    echo '<div class="full-cal-day '.($isToday?'today':'').'" style="'.$extra.'" id="day-'.$d.'">';
                    echo '<div class="full-cal-day-num"><span>'.$d.'</span>';
                    if (count($dayEvts)>0) echo '<span style="font-size:0.65rem;background:var(--gold-dim);color:var(--gold);padding:1px 5px;border-radius:10px;">'.count($dayEvts).'</span>';
                    echo '</div>';
                    $shown = 0;
                    foreach($dayEvts as $ev) {
                        if($shown>=3) { echo '<div class="more-chip">+'.( count($dayEvts)-3).' more</div>'; break; }
                        echo '<div class="ev-chip '.$ev['status'].'" onclick="showEventPopup('.htmlspecialchars(json_encode($ev)).')" title="'.htmlspecialchars($ev['event_title']).'">'.htmlspecialchars(substr($ev['event_title'],0,20)).'</div>';
                        $shown++;
                    }
                    echo '</div>';
                }
                $remaining = 42 - ($firstDay + $daysInMonth);
                for ($d=1; $d<=$remaining; $d++) echo '<div class="full-cal-day other-month"><div class="full-cal-day-num"><span>'.$d.'</span></div></div>';
                ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Event Detail Modal -->
<div class="modal fade" id="evDetailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="evDetailTitle">Event Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="evDetailBody"></div>
      <div class="modal-footer">
        <a id="evDetailEdit" href="#" class="btn-gold">Edit Event</a>
        <a id="evDetailView" href="#" class="btn-outline-gold">View Full</a>
      </div>
    </div>
  </div>
</div>

<script>
const detailModal = new bootstrap.Modal(document.getElementById('evDetailModal'));
function showEventPopup(ev) {
    document.getElementById('evDetailTitle').textContent = ev.event_title;
    document.getElementById('evDetailEdit').href = '<?= APP_URL ?>/modules/admin/events.php?edit='+ev.id;
    document.getElementById('evDetailView').href = '<?= APP_URL ?>/modules/admin/events.php?view='+ev.id;
    const pct = ev.total_amount > 0 ? (ev.advance_paid / ev.total_amount * 100) : 0;
    document.getElementById('evDetailBody').innerHTML = `
        <div style="display:flex;gap:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:150px">
                <div style="margin-bottom:10px"><span style="font-size:0.78rem;color:var(--text-muted);display:block">Client</span><strong>${ev.client_name}</strong></div>
                <div style="margin-bottom:10px"><span style="font-size:0.78rem;color:var(--text-muted);display:block">Hall</span><strong>${ev.hall_name}</strong></div>
                <div style="margin-bottom:10px"><span style="font-size:0.78rem;color:var(--text-muted);display:block">Type</span><strong style="text-transform:capitalize">${ev.event_type.replace('_',' ')}</strong></div>
                <div><span style="font-size:0.78rem;color:var(--text-muted);display:block">Slot</span><strong style="text-transform:capitalize">${ev.slot.replace('_',' ')}</strong></div>
            </div>
            <div style="flex:1;min-width:150px">
                <div style="margin-bottom:10px"><span style="font-size:0.78rem;color:var(--text-muted);display:block">Total</span><strong style="color:var(--gold)">PKR ${parseFloat(ev.total_amount).toLocaleString()}</strong></div>
                <div style="margin-bottom:10px"><span style="font-size:0.78rem;color:var(--text-muted);display:block">Paid</span><strong style="color:#4ade80">PKR ${parseFloat(ev.advance_paid).toLocaleString()}</strong></div>
                <div style="margin-bottom:10px"><span style="font-size:0.78rem;color:var(--text-muted);display:block">Remaining</span><strong style="color:#f87171">PKR ${parseFloat(ev.remaining_amount).toLocaleString()}</strong></div>
                <div class="progress-custom"><div class="progress-bar-custom" style="width:${pct}%"></div></div>
                <small style="color:var(--text-muted)">${Math.round(pct)}% paid</small>
            </div>
        </div>
        <div style="margin-top:12px"><span class="status-badge ${ev.status}">${ev.status.replace('_',' ')}</span></div>
    `;
    detailModal.show();
}
<?php if($focusDate && isset($evByDate[$focusDate])): ?>
window.addEventListener('DOMContentLoaded',()=>{ const el=document.getElementById('day-<?=date('j',strtotime($focusDate))?>'); if(el) el.scrollIntoView({behavior:'smooth',block:'center'}); });
<?php endif; ?>
</script>
<?php include '../../includes/session_check.php'; ?>
</body></html>
