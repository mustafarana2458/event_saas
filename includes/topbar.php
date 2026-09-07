<?php
$isSuperAdmin = isset($_SESSION['super_admin_id']);
$userName = $isSuperAdmin ? ($_SESSION['super_admin_name'] ?? 'Super Admin') : ($_SESSION['admin_name'] ?? 'Admin');
$orgName = $isSuperAdmin ? 'Platform Control' : ($_SESSION['org_name'] ?? '');
?>
<div class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="topbar-title" id="pageTitle">Dashboard</div>
    <div class="topbar-actions">
        <?php if (!$isSuperAdmin): ?>
        <div class="session-timer" id="sessionTimer">
            <i class="fas fa-clock"></i>
            <span id="timerDisplay">10:00</span>
        </div>
        <?php endif; ?>
        <div class="topbar-time" id="topbarClock"></div>
        <?php if (!$isSuperAdmin): ?>
        <a href="<?= APP_URL ?>/modules/admin/events.php?action=new" class="topbar-btn" title="New Booking">
            <i class="fas fa-plus"></i>
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/auth/logout.php" class="topbar-btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
// Clock
function updateClock() {
    const now = new Date();
    document.getElementById('topbarClock').textContent = now.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);

// Sidebar toggle
function toggleSidebar() {
    document.getElementById('mainSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('mainSidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

// Toast notification
function showToast(msg, type = 'success') {
    const icons = {success:'check-circle', error:'exclamation-circle', warning:'exclamation-triangle'};
    const div = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

// Set page title from h2
window.addEventListener('DOMContentLoaded', () => {
    const h2 = document.querySelector('.page-header h2');
    if (h2) document.getElementById('pageTitle').textContent = h2.textContent;
});
</script>
