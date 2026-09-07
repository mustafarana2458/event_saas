<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<nav class="sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="brand-logo"><i class="fas fa-building-columns"></i></div>
        <div class="brand-text">
            <h2>HallSaaS</h2>
            <span>Super Admin Panel</span>
        </div>
    </div>

    <div class="sidebar-section-label">Overview</div>
    <a href="<?= APP_URL ?>/modules/superadmin/dashboard.php" class="nav-item <?= $currentFile === 'dashboard.php' && $currentDir === 'superadmin' ? 'active' : '' ?>">
        <i class="fas fa-chart-pie"></i> Dashboard
    </a>

    <div class="sidebar-section-label">Management</div>
    <a href="<?= APP_URL ?>/modules/superadmin/organizations.php" class="nav-item <?= $currentFile === 'organizations.php' ? 'active' : '' ?>">
        <i class="fas fa-building"></i> Organizations
    </a>
    <a href="<?= APP_URL ?>/modules/superadmin/admins.php" class="nav-item <?= $currentFile === 'admins.php' ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i> All Admins
    </a>

    <div class="sidebar-section-label">Finance</div>
    <a href="<?= APP_URL ?>/modules/superadmin/revenue.php" class="nav-item <?= $currentFile === 'revenue.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-line"></i> Revenue Report
    </a>

    <div class="sidebar-section-label">System</div>
    <a href="<?= APP_URL ?>/modules/superadmin/activity_logs.php" class="nav-item <?= $currentFile === 'activity_logs.php' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> Activity Logs
    </a>
    <a href="<?= APP_URL ?>/modules/superadmin/profile.php" class="nav-item <?= $currentFile === 'profile.php' ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> Profile Settings
    </a>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['super_admin_name'] ?? 'SA', 0, 2)) ?></div>
            <div class="user-chip-info">
                <strong><?= htmlspecialchars($_SESSION['super_admin_name'] ?? 'Super Admin') ?></strong>
                <span>Super Administrator</span>
            </div>
            <a href="<?= APP_URL ?>/modules/auth/logout.php" title="Logout" style="color:var(--text-muted);font-size:0.85rem;margin-left:4px;">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>
