<?php
require_once '../../includes/config.php';
requireSuperAdmin();
$db = getDB();
$id = $_SESSION['super_admin_id'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $name  = sanitize($_POST['name']??'');
    $email = sanitize($_POST['email']??'');
    $pass  = $_POST['password']??'';
    if ($pass) {
        $db->prepare("UPDATE super_admins SET name=?,email=?,password=? WHERE id=?")->execute([$name,$email,password_hash($pass,PASSWORD_BCRYPT),$id]);
    } else {
        $db->prepare("UPDATE super_admins SET name=?,email=? WHERE id=?")->execute([$name,$email,$id]);
    }
    $_SESSION['super_admin_name'] = $name;
    $msg = 'Profile updated.';
    }
}
$me = $db->prepare("SELECT * FROM super_admins WHERE id=?"); $me->execute([$id]); $me = $me->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Profile — HallSaaS</title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/superadmin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header"><div><h2>Profile Settings</h2></div></div>
        <?php if(isset($msg)): ?><div class="alert-success-dark mb-3"><?= $msg ?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><?= $err ?></div><?php endif; ?>
        <div class="card-section" style="max-width:500px;">
            <div class="section-header"><h3><i class="fas fa-user me-2"></i>Edit Profile</h3></div>
            <div style="padding:24px;">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= sanitize($me['name']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= sanitize($me['email']) ?>" required></div>
                    <div class="mb-4"><label class="form-label">New Password <small style="color:var(--text-muted)">(leave blank to keep)</small></label><input type="password" name="password" class="form-control"></div>
                    <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
