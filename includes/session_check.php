<script>
// ============================================================
// Session Activity Checker — 10 minute inactivity logout
// Checks activation status every 10 minutes via AJAX
// ============================================================
(function() {
    const TIMEOUT_MS = 10 * 60 * 1000; // 10 minutes
    const WARN_MS    = 2 * 60 * 1000;  // warn at 2 min left
    const CHECK_INTERVAL = 60 * 1000;  // check server every 60s

    let lastActivity = Date.now();
    let timerInterval = null;
    let checkInterval = null;

    // Track any activity
    ['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
        document.addEventListener(evt, () => { lastActivity = Date.now(); }, { passive: true });
    });

    // Update the visible countdown timer
    const timerEl = document.getElementById('sessionTimer');
    const timerDisplay = document.getElementById('timerDisplay');

    function updateTimer() {
        if (!timerDisplay) return;
        const elapsed = Date.now() - lastActivity;
        const remaining = Math.max(0, TIMEOUT_MS - elapsed);
        const mins = Math.floor(remaining / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        timerDisplay.textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;

        if (timerEl) {
            timerEl.className = 'session-timer';
            if (remaining <= WARN_MS) timerEl.classList.add('warning');
            if (remaining <= 60000) timerEl.classList.add('danger');
        }

        if (remaining === 0) {
            clearInterval(timerInterval);
            clearInterval(checkInterval);
            window.location.href = '<?= APP_URL ?>/modules/auth/logout.php';
        }
    }

    timerInterval = setInterval(updateTimer, 1000);
    updateTimer();

    // Server-side activation check every 60 seconds
    async function checkActivation() {
        try {
            const res = await fetch('<?= APP_URL ?>/api/check_session.php', { cache: 'no-store' });
            const data = await res.json();
            if (!data.active) {
                window.location.href = '<?= APP_URL ?>/modules/auth/login.php?deactivated=1';
            }
        } catch(e) {}
    }

    checkInterval = setInterval(checkActivation, CHECK_INTERVAL);
    checkActivation();
})();
</script>
