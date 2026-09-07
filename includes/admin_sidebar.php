<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['admin_role'] ?? 'staff';
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<nav class="sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="brand-logo"><i class="fas fa-building-columns"></i></div>
        <div class="brand-text">
            <h2><?= htmlspecialchars(substr($_SESSION['org_name'] ?? 'HallSaaS', 0, 16)) ?></h2>
            <span><?= ucfirst($role) ?> Panel</span>
        </div>
    </div>

    <div class="sidebar-section-label">Overview</div>
    <a href="<?= APP_URL ?>/modules/admin/dashboard.php" class="nav-item <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-pie"></i> Dashboard
    </a>
    <a href="<?= APP_URL ?>/modules/admin/calendar.php" class="nav-item <?= $currentFile === 'calendar.php' ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> Booking Calendar
    </a>

    <div class="sidebar-section-label">Events</div>
    <a href="<?= APP_URL ?>/modules/admin/events.php" class="nav-item <?= $currentFile === 'events.php' ? 'active' : '' ?>">
        <i class="fas fa-star"></i> All Events
    </a>
    <a href="<?= APP_URL ?>/modules/admin/events.php?action=new" class="nav-item">
        <i class="fas fa-plus-circle"></i> New Booking
    </a>

    <div class="sidebar-section-label">Finance</div>
    <a href="<?= APP_URL ?>/modules/admin/payments.php" class="nav-item <?= $currentFile === 'payments.php' ? 'active' : '' ?>">
        <i class="fas fa-money-bill-wave"></i> Payments
    </a>
    <a href="<?= APP_URL ?>/modules/admin/invoices.php" class="nav-item <?= $currentFile === 'invoices.php' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice"></i> Invoices
    </a>
    <a href="<?= APP_URL ?>/modules/admin/finance_report.php" class="nav-item <?= $currentFile === 'finance_report.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> Finance Report
    </a>

    <div class="sidebar-section-label">Data</div>
    <a href="<?= APP_URL ?>/modules/admin/clients.php" class="nav-item <?= $currentFile === 'clients.php' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Clients
    </a>
    <a href="<?= APP_URL ?>/modules/admin/halls.php" class="nav-item <?= $currentFile === 'halls.php' ? 'active' : '' ?>">
        <i class="fas fa-door-open"></i> Halls / Venues
    </a>

    <?php if ($role === 'org_admin'): ?>
    <div class="sidebar-section-label">Admin</div>
    <a href="<?= APP_URL ?>/modules/admin/branches.php" class="nav-item <?= $currentFile === 'branches.php' ? 'active' : '' ?>">
        <i class="fas fa-code-branch"></i> Branches
    </a>
    <a href="<?= APP_URL ?>/modules/admin/team.php" class="nav-item <?= $currentFile === 'team.php' ? 'active' : '' ?>">
        <i class="fas fa-user-friends"></i> Team Members
    </a>
    <?php endif; ?>

    <div class="sidebar-section-label">Account</div>
    <a href="<?= APP_URL ?>/modules/admin/profile.php" class="nav-item <?= $currentFile === 'profile.php' ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i> My Profile
    </a>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 2)) ?></div>
            <div class="user-chip-info">
                <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? '') ?></strong>
                <span><?= ucfirst(str_replace('_', ' ', $role)) ?></span>
            </div>
            <a href="<?= APP_URL ?>/modules/auth/logout.php" title="Logout" style="color:var(--text-muted);font-size:0.85rem;margin-left:4px;">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>
