/**
 * HallSaaS - Global JavaScript Utilities
 */

'use strict';

// ============================================================
// Animated Counters
// ============================================================
function animateCounter(el, target, duration = 1200, prefix = '', suffix = '') {
    const start = 0;
    const step  = target / (duration / 16);
    let current = start;
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = prefix + Math.floor(current).toLocaleString() + suffix;
        if (current >= target) clearInterval(timer);
    }, 16);
}

// Run counters on stat cards when page loads
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stat-value[data-count]').forEach(el => {
        const val = parseFloat(el.dataset.count) || 0;
        const pre = el.dataset.prefix || '';
        const suf = el.dataset.suffix || '';
        animateCounter(el, val, 1200, pre, suf);
    });
});

// ============================================================
// Topbar scroll shadow
// ============================================================
window.addEventListener('scroll', () => {
    const topbar = document.querySelector('.topbar');
    if (topbar) topbar.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });

// ============================================================
// Table filter utility (used across pages)
// ============================================================
function filterTable(query, tableId) {
    const q = query.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ============================================================
// Confirm delete with custom dialog
// ============================================================
function confirmDelete(message, callback) {
    if (confirm(message || 'Are you sure you want to delete this?')) {
        if (typeof callback === 'function') callback();
    }
}

// ============================================================
// Auto-dismiss alerts after 5 seconds
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert-success-dark, .alert-danger-dark, .alert-warning-dark');
    alerts.forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity 0.5s ease';
            a.style.opacity    = '0';
            setTimeout(() => a.remove(), 500);
        }, 5000);
    });
});

// ============================================================
// Format PKR currency
// ============================================================
function formatPKR(amount) {
    return '₨ ' + parseFloat(amount || 0).toLocaleString('en-PK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ============================================================
// Keyboard shortcut: Ctrl+N = new booking (admin panel only)
// ============================================================
document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'n' && document.querySelector('.main-content')) {
        e.preventDefault();
        const newBtn = document.querySelector('[href*="events.php?action=new"], .btn-gold[onclick*="EventModal"]');
        if (newBtn) newBtn.click();
    }
    // Ctrl+/ = focus search
    if (e.ctrlKey && e.key === '/') {
        e.preventDefault();
        const search = document.querySelector('.search-bar input');
        if (search) search.focus();
    }
});

// ============================================================
// Print invoice
// ============================================================
function printInvoice() { window.print(); }

// ============================================================
// Sidebar state persist on mobile
// ============================================================
function toggleSidebar() {
    const sidebar  = document.getElementById('mainSidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
}
function closeSidebar() {
    const sidebar  = document.getElementById('mainSidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    if (sidebar)  sidebar.classList.remove('open');
    if (overlay)  overlay.classList.remove('open');
}

// ============================================================
// Show/hide password toggle
// ============================================================
function togglePwVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        if (icon) icon.className = 'fas fa-eye';
    }
}

// ============================================================
// Bootstrap tooltips init
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[title]').forEach(el => {
            new bootstrap.Tooltip(el, { trigger: 'hover', placement: 'top' });
        });
    }
});
