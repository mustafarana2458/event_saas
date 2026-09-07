<?php
require_once '../../includes/config.php';
requireAdmin();
$db  = getDB();
$id  = $_SESSION['admin_id'];
$org = $_SESSION['org_id'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $name  = sanitize($_POST['name']??'');
    $phone = sanitize($_POST['phone']??'');
    $curPw = $_POST['current_password']??'';
    $newPw = $_POST['new_password']??'';
    $me    = $db->prepare("SELECT * FROM admins WHERE id=?"); $me->execute([$id]); $me=$me->fetch();

    if ($newPw) {
        if (!password_verify($curPw, $me['password'])) {
            $err = 'Current password is incorrect.';
        } else {
            $db->prepare("UPDATE admins SET name=?,phone=?,password=? WHERE id=?")->execute([$name,$phone,password_hash($newPw,PASSWORD_BCRYPT),$id]);
            $_SESSION['admin_name']=$name; $msg='Profile & password updated.';
        }
    } else {
        $db->prepare("UPDATE admins SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$id]);
        $_SESSION['admin_name']=$name; $msg='Profile updated.';
    }
    }
}

$me=$db->prepare("SELECT a.*,o.name as org_name,o.address,o.phone as org_phone,o.email as org_email,o.subscription_plan FROM admins a JOIN organizations o ON a.organization_id=o.id WHERE a.id=?");
$me->execute([$id]); $me=$me->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Profile — <?=sanitize($_SESSION['org_name'])?></title><?php include '../../includes/dashboard_styles.php'; ?></head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header"><div><h2>My Profile</h2><p class="text-muted-sm">Manage your account settings</p></div></div>
        <?php if($msg): ?><div class="alert-success-dark mb-3"><i class="fas fa-check-circle me-2"></i><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert-danger-dark mb-3"><i class="fas fa-exclamation-circle me-2"></i><?=$err?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card-section">
                    <div style="padding:32px;text-align:center">
                        <div style="width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,var(--gold),var(--gold-light));display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;color:#000;margin:0 auto 16px"><?=strtoupper(substr($me['name'],0,2))?></div>
                        <h4 style="font-size:1.1rem;font-weight:700;margin-bottom:4px"><?=sanitize($me['name'])?></h4>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px"><?=sanitize($me['email'])?></p>
                        <span class="role-badge <?=$me['role']?>" style="font-size:0.82rem"><?=ucfirst(str_replace('_',' ',$me['role']))?></span>
                        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--dark-border)">
                            <div style="margin-bottom:10px"><span style="font-size:0.75rem;color:var(--text-muted);display:block">Organization</span><strong><?=sanitize($me['org_name'])?></strong></div>
                            <div style="margin-bottom:10px"><span style="font-size:0.75rem;color:var(--text-muted);display:block">Plan</span><span class="badge-plan <?=$me['subscription_plan']?>"><?=ucfirst($me['subscription_plan'])?></span></div>
                            <div><span style="font-size:0.75rem;color:var(--text-muted);display:block">Last Login</span><strong style="font-size:0.82rem"><?=$me['last_login']?date('d M Y H:i',strtotime($me['last_login'])):'—'?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card-section">
                    <div class="section-header"><h3><i class="fas fa-user-edit me-2"></i>Edit Profile</h3></div>
                    <div style="padding:24px">
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full Name</label><input name="name" class="form-control" value="<?=sanitize($me['name'])?>" required></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?=sanitize($me['phone']??'')?>"></div>
                                <div class="col-12"><label class="form-label">Email <small style="color:var(--text-muted)">(contact Super Admin to change)</small></label><input type="email" class="form-control" value="<?=sanitize($me['email'])?>" disabled></div>
                            </div>
                            <hr style="border-color:var(--dark-border);margin:24px 0">
                            <h5 style="font-size:0.9rem;font-weight:600;margin-bottom:16px;color:var(--gold)"><i class="fas fa-lock me-2"></i>Change Password</h5>
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" placeholder="Enter current password"></div>
                                <div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="New password"></div>
                                <div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" id="confirmPw" class="form-control" placeholder="Repeat new password"></div>
                            </div>
                            <div style="margin-top:20px">
                                <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Organization Info -->
                <div class="card-section" style="margin-top:20px">
                    <div class="section-header"><h3><i class="fas fa-building me-2"></i>Organization Info</h3></div>
                    <div style="padding:20px 24px">
                        <div class="row g-3">
                            <div class="col-md-6"><span style="font-size:0.75rem;color:var(--text-muted);display:block">Hall Name</span><strong><?=sanitize($me['org_name'])?></strong></div>
                            <div class="col-md-6"><span style="font-size:0.75rem;color:var(--text-muted);display:block">Phone</span><strong><?=sanitize($me['org_phone']??'—')?></strong></div>
                            <div class="col-md-6"><span style="font-size:0.75rem;color:var(--text-muted);display:block">Email</span><strong><?=sanitize($me['org_email']??'—')?></strong></div>
                            <div class="col-md-6"><span style="font-size:0.75rem;color:var(--text-muted);display:block">Address</span><strong><?=sanitize($me['address']??'—')?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/session_check.php'; ?>
</body></html>
