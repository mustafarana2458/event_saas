<?php
// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'event_saas');
define('DB_CHARSET', 'utf8mb4');

// App Config
define('APP_NAME', 'Event Saas');
define('APP_URL', 'http://localhost/event_saas');
define('APP_VERSION', '1.0.0');
define('CURRENCY', 'PKR');
define('CURRENCY_SYMBOL', '₨');

// Session timeout in minutes
define('SESSION_TIMEOUT', 10);

// Login rate-limiting
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ============================================================
// Database Connection (PDO)
// ============================================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ============================================================
// Session Management
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// ============================================================
// Helper Functions
// ============================================================
function formatPKR($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// ============================================================
// CSRF Protection
// ============================================================
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(64);
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf($token) {
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// Login Rate-Limiting (fails open if login_attempts table is missing)
// ============================================================
function isLoginRateLimited($email, $ip) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempted_at > (NOW() - INTERVAL ? MINUTE)");
        $stmt->execute([$email, $ip, LOGIN_LOCKOUT_MINUTES]);
        return $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    } catch (Exception $e) {
        return false;
    }
}

function recordFailedLogin($email, $ip) {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)")->execute([$email, $ip]);
    } catch (Exception $e) {}
}

function clearLoginAttempts($email, $ip) {
    try {
        $db = getDB();
        $db->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?")->execute([$email, $ip]);
    } catch (Exception $e) {}
}

function logActivity($userType, $userId, $action, $description = '', $orgId = null) {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_type, user_id, organization_id, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userType, $userId, $orgId, $action, $description, $ip]);
    } catch (Exception $e) {}
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn($type = 'admin') {
    if ($type === 'super_admin') {
        return isset($_SESSION['super_admin_id']) && !empty($_SESSION['super_admin_id']);
    }
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireSuperAdmin() {
    if (!isLoggedIn('super_admin')) {
        redirect(APP_URL . '/modules/auth/login.php?type=superadmin');
    }
}

function requireAdmin() {
    if (!isLoggedIn('admin')) {
        redirect(APP_URL . '/modules/auth/login.php');
    }
    // Check if admin is still active
    checkAdminActivation();
}

function checkAdminActivation() {
    if (!isset($_SESSION['admin_id'])) return;

    $db = getDB();
    $stmt = $db->prepare("
        SELECT a.is_active, a.session_token, a.session_expires, o.is_active as org_active
        FROM admins a
        JOIN organizations o ON a.organization_id = o.id
        WHERE a.id = ?
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || !$admin['is_active'] || !$admin['org_active']) {
        session_destroy();
        redirect(APP_URL . '/modules/auth/login.php?deactivated=1');
    }

    // Check session timeout (10 minutes)
    if (isset($_SESSION['last_activity'])) {
        $inactiveSince = time() - $_SESSION['last_activity'];
        if ($inactiveSince > (SESSION_TIMEOUT * 60)) {
            session_destroy();
            redirect(APP_URL . '/modules/auth/login.php?timeout=1');
        }
    }
    $_SESSION['last_activity'] = time();
}

function generateInvoiceNumber($orgId) {
    $db = getDB();
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE organization_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$orgId, $year]);
    $row = $stmt->fetch();
    $seq = str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    return "INV-{$year}-{$orgId}-{$seq}";
}
