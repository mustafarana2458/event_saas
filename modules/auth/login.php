<?php
require_once '../../includes/config.php';

$type = $_GET['type'] ?? 'admin';
$error = '';
$deactivated = isset($_GET['deactivated']);
$timeout = isset($_GET['timeout']);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginType = $_POST['login_type'] ?? 'admin';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } elseif (isLoginRateLimited($email, $ip)) {
        $error = 'Too many failed login attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    } else {
        $db = getDB();

        if ($loginType === 'superadmin') {
            $stmt = $db->prepare("SELECT * FROM super_admins WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                clearLoginAttempts($email, $ip);
                $_SESSION['super_admin_id'] = $user['id'];
                $_SESSION['super_admin_name'] = $user['name'];
                $_SESSION['super_admin_email'] = $user['email'];
                $_SESSION['user_type'] = 'super_admin';
                $_SESSION['last_activity'] = time();

                $db->prepare("UPDATE super_admins SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                logActivity('super_admin', $user['id'], 'LOGIN', 'Super Admin logged in');
                redirect(APP_URL . '/modules/superadmin/dashboard.php');
            } else {
                recordFailedLogin($email, $ip);
                $error = 'Invalid credentials or account is inactive.';
            }
        } else {
            $stmt = $db->prepare("
                SELECT a.*, o.is_active as org_active, o.name as org_name, o.id as org_id
                FROM admins a
                JOIN organizations o ON a.organization_id = o.id
                WHERE a.email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'Your account has been deactivated. Contact Super Admin.';
                } elseif (!$user['org_active']) {
                    $error = 'Your organization account is deactivated. Contact Super Admin.';
                } else {
                    clearLoginAttempts($email, $ip);
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['org_id'] = $user['org_id'];
                    $_SESSION['org_name'] = $user['org_name'];
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['last_activity'] = time();

                    $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    logActivity('admin', $user['id'], 'LOGIN', 'Admin logged in', $user['org_id']);
                    redirect(APP_URL . '/modules/admin/dashboard.php');
                }
            } else {
                recordFailedLogin($email, $ip);
                $error = 'Invalid credentials or account not found.';
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — HallSaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #C9A84C;
            --gold-light: #E8C97A;
            --dark: #0D0D0D;
            --dark-card: #161616;
            --dark-border: #2A2A2A;
            --text-muted: #888;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--dark);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .bg-pattern {
            position: fixed; inset: 0; z-index: 0;
            background: 
                radial-gradient(ellipse at 20% 50%, rgba(201,168,76,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(201,168,76,0.05) 0%, transparent 50%);
        }
        .grid-lines {
            position: fixed; inset: 0; z-index: 0;
            background-image: 
                linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .login-wrapper {
            position: relative; z-index: 10;
            width: 100%; max-width: 460px; padding: 20px;
        }
        .brand {
            text-align: center; margin-bottom: 40px;
        }
        .brand-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px rgba(201,168,76,0.3);
        }
        .brand-icon i { font-size: 28px; color: #fff; }
        .brand h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem; font-weight: 700;
            color: #fff; letter-spacing: -0.02em;
        }
        .brand p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }
        .card-login {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
        }
        .tab-switcher {
            display: flex; background: rgba(255,255,255,0.04);
            border-radius: 10px; padding: 4px; margin-bottom: 28px; gap: 4px;
        }
        .tab-btn {
            flex: 1; padding: 10px; border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 500;
            cursor: pointer; transition: all 0.25s;
            color: var(--text-muted); background: transparent;
        }
        .tab-btn.active {
            background: var(--gold); color: #000; font-weight: 600;
        }
        .form-label { color: #ccc; font-size: 0.85rem; font-weight: 500; margin-bottom: 6px; }
        .form-control {
            background: rgba(255,255,255,0.05) !important;
            border: 1px solid var(--dark-border) !important;
            color: #fff !important; border-radius: 10px !important;
            padding: 12px 16px !important; font-size: 0.9rem !important;
            transition: border-color 0.2s !important;
        }
        .form-control:focus {
            border-color: var(--gold) !important;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15) !important;
            outline: none !important;
        }
        .form-control::placeholder { color: #555 !important; }
        .input-group-text {
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid var(--dark-border) !important;
            border-right: none !important; color: var(--text-muted) !important;
            border-radius: 10px 0 0 10px !important;
        }
        .input-group .form-control { border-left: none !important; border-radius: 0 10px 10px 0 !important; }
        .btn-login {
            width: 100%; padding: 13px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: #000; font-weight: 700; font-size: 0.95rem;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            transition: all 0.25s; letter-spacing: 0.02em;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(201,168,76,0.4); }
        .alert-custom {
            background: rgba(220,53,69,0.15); border: 1px solid rgba(220,53,69,0.3);
            color: #ff6b6b; border-radius: 10px; padding: 12px 16px; font-size: 0.875rem;
        }
        .alert-warning-custom {
            background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3);
            color: #ffc107; border-radius: 10px; padding: 12px 16px; font-size: 0.875rem;
        }
        .demo-box {
            margin-top: 20px; padding: 14px 16px;
            background: rgba(201,168,76,0.06); border: 1px solid rgba(201,168,76,0.2);
            border-radius: 10px;
        }
        .demo-box p { color: var(--text-muted); font-size: 0.78rem; margin: 0 0 6px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .demo-box span { color: #bbb; font-size: 0.82rem; display: block; line-height: 1.6; }
        .demo-box strong { color: var(--gold); }
    </style>
</head>
<body>
<div class="bg-pattern"></div>
<div class="grid-lines"></div>

<div class="login-wrapper">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-building-columns"></i></div>
        <h1>HallSaaS</h1>
        <p>Marriage Hall Management Platform</p>
    </div>

    <div class="card-login">
        <div class="tab-switcher">
            <button class="tab-btn <?= $type !== 'superadmin' ? 'active' : '' ?>" onclick="switchTab('admin')">
                <i class="fas fa-user-tie me-1"></i> Hall Admin
            </button>
            <button class="tab-btn <?= $type === 'superadmin' ? 'active' : '' ?>" onclick="switchTab('superadmin')">
                <i class="fas fa-crown me-1"></i> Super Admin
            </button>
        </div>

        <?php if ($deactivated): ?>
        <div class="alert-warning-custom mb-3"><i class="fas fa-ban me-2"></i>Your account or organization has been deactivated.</div>
        <?php endif; ?>
        <?php if ($timeout): ?>
        <div class="alert-warning-custom mb-3"><i class="fas fa-clock me-2"></i>Session expired due to inactivity (10 min).</div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert-custom mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <?= csrfField() ?>
            <input type="hidden" name="login_type" id="login_type" value="<?= $type === 'superadmin' ? 'superadmin' : 'admin' ?>">

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                           value="<?= $type === 'superadmin' ? 'superadmin@hallsaas.com' : 'admin@alnoor.com' ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter your password" required value="password">
                    <button type="button" class="input-group-text" style="border-left:none;border-radius:0 10px 10px 0;cursor:pointer;" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

    </div>
</div>


</body>
</html>
