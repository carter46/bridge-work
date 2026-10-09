<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();

if (hj_is_post()) {
    hj_csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $hash = (string)hj_db_value('SELECT password_hash FROM admins WHERE id = ?', [(int)$admin['id']]);

    if (!password_verify($current, $hash)) {
        hj_flash('error', 'Your current password is incorrect.');
        hj_redirect('account.php');
    }

    if ($action === 'profile') {
        $name = hj_input($_POST, 'name', 120);
        $email = strtolower(hj_input($_POST, 'email', 190));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            hj_flash('error', 'Enter your name and a valid email address.');
        } elseif ((int)hj_db_value('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, (int)$admin['id']]) > 0) {
            hj_flash('error', 'Another admin already uses that email.');
        } else {
            hj_db_exec('UPDATE admins SET name = ?, email = ?, updated_at = ? WHERE id = ?', [$name, $email, hj_now(), (int)$admin['id']]);
            hj_audit('admin_profile_updated', 'admin', (int)$admin['id'], $email);
            hj_flash('success', 'Profile saved.');
        }
    } elseif ($action === 'password') {
        $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';
        $error = hj_validate_password($new);
        if ($error === null && $new !== $confirm) {
            $error = 'The two new passwords do not match.';
        }
        if ($error !== null) {
            hj_flash('error', $error);
        } else {
            hj_db_exec('UPDATE admins SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), hj_now(), (int)$admin['id']]);
            session_regenerate_id(true);
            hj_audit('admin_password_changed', 'admin', (int)$admin['id']);
            hj_flash('success', 'Password changed.');
        }
    }
    hj_redirect('account.php');
}

hj_admin_header('My account', 'account');
hj_admin_page_title('My account', 'Signed in as ' . $admin['email'] . ' (' . $admin['role'] . '). You are signed out automatically after 30 minutes without activity.');
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-5xl">
  <form class="hj-card p-5 sm:p-6 space-y-4" method="post" action="account.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <h2 class="text-lg font-bold text-primary">Profile</h2>
    <div><label class="hj-label" for="a-name">Name</label><input class="hj-input" id="a-name" name="name" required maxlength="120" value="<?= e($admin['name']) ?>"></div>
    <div><label class="hj-label" for="a-email">Email (used to sign in)</label><input class="hj-input" id="a-email" name="email" type="email" required maxlength="190" value="<?= e($admin['email']) ?>"></div>
    <div><label class="hj-label" for="a-current1">Current password</label><input class="hj-input" id="a-current1" name="current_password" type="password" required autocomplete="current-password"></div>
    <button class="hj-btn hj-btn-accent" type="submit">Save profile</button>
  </form>

  <form class="hj-card p-5 sm:p-6 space-y-4" method="post" action="account.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2 class="text-lg font-bold text-primary">Change password</h2>
    <div><label class="hj-label" for="a-current2">Current password</label><input class="hj-input" id="a-current2" name="current_password" type="password" required autocomplete="current-password"></div>
    <div><label class="hj-label" for="a-new">New password</label><input class="hj-input" id="a-new" name="new_password" type="password" required minlength="10" autocomplete="new-password"><p class="hj-help">At least 10 characters.</p></div>
    <div><label class="hj-label" for="a-new2">Confirm new password</label><input class="hj-input" id="a-new2" name="new_password_confirm" type="password" required minlength="10" autocomplete="new-password"></div>
    <button class="hj-btn hj-btn-accent" type="submit">Change password</button>
  </form>
</div>
<?php
hj_admin_footer();
