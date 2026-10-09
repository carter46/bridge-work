<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin('owner');
$roles = ['owner' => 'Owner (everything)', 'recruiter' => 'Recruiter (jobs, categories and applications)'];

function hj_active_owner_count(): int
{
    return (int)hj_db_value("SELECT COUNT(*) FROM admins WHERE role = 'owner' AND is_active = 1");
}

if (hj_is_post()) {
    hj_csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = hj_input($_POST, 'name', 120);
        $email = strtolower(hj_input($_POST, 'email', 190));
        $role = array_key_exists($_POST['role'] ?? '', $roles) ? $_POST['role'] : 'recruiter';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $error = null;
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a name and a valid email address.';
        } elseif ((int)hj_db_value('SELECT COUNT(*) FROM admins WHERE email = ?', [$email]) > 0) {
            $error = 'An admin with that email already exists.';
        } else {
            $error = hj_validate_password($password);
        }
        if ($error !== null) {
            hj_flash('error', $error);
        } else {
            hj_db_exec(
                'INSERT INTO admins (name, email, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, hj_now(), hj_now()]
            );
            $newId = (int)hj_db()->lastInsertId();
            hj_audit('admin_created', 'admin', $newId, $email . ' as ' . $role);
            hj_flash('success', 'Admin ' . $email . ' added. Give them the password securely and ask them to change it under My account.');
        }
        hj_redirect('users.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $target = $id > 0 ? hj_db_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;
    if ($target === null) {
        hj_flash('error', 'That admin no longer exists.');
        hj_redirect('users.php');
    }
    $isSelf = (int)$target['id'] === (int)$admin['id'];

    if ($action === 'update') {
        $role = array_key_exists($_POST['role'] ?? '', $roles) ? $_POST['role'] : $target['role'];
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $losesOwner = $target['role'] === 'owner' && (int)$target['is_active'] === 1 && ($role !== 'owner' || !$active);
        if ($isSelf && ($role !== $target['role'] || !$active)) {
            hj_flash('error', 'You can\'t change your own role or deactivate yourself. Ask another owner.');
        } elseif ($losesOwner && hj_active_owner_count() <= 1) {
            hj_flash('error', 'There must always be at least one active owner.');
        } else {
            hj_db_exec('UPDATE admins SET role = ?, is_active = ?, updated_at = ? WHERE id = ?', [$role, $active, hj_now(), $id]);
            hj_audit('admin_updated', 'admin', $id, $target['email'] . ': role ' . $role . ', ' . ($active ? 'active' : 'deactivated'));
            hj_flash('success', 'Saved ' . $target['email'] . '.');
        }
        hj_redirect('users.php');
    }

    if ($action === 'reset_password') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $error = hj_validate_password($password);
        if ($error !== null) {
            hj_flash('error', $error);
        } else {
            hj_db_exec('UPDATE admins SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), hj_now(), $id]);
            hj_audit('admin_password_reset', 'admin', $id, $target['email']);
            hj_flash('success', 'Password for ' . $target['email'] . ' changed.');
        }
        hj_redirect('users.php');
    }

    if ($action === 'delete') {
        if ($isSelf) {
            hj_flash('error', 'You can\'t remove your own account. Ask another owner.');
        } elseif ($target['role'] === 'owner' && (int)$target['is_active'] === 1 && hj_active_owner_count() <= 1) {
            hj_flash('error', 'There must always be at least one active owner.');
        } else {
            // Audit entries keep the admin id; the history still shows what they did.
            hj_db_exec('DELETE FROM admins WHERE id = ?', [$id]);
            hj_audit('admin_deleted', 'admin', $id, $target['email'] . ' (' . $target['role'] . ')');
            hj_flash('success', 'Admin ' . $target['email'] . ' removed. They can no longer sign in.');
        }
        hj_redirect('users.php');
    }

    hj_redirect('users.php');
}

$admins = hj_db_all('SELECT * FROM admins ORDER BY is_active DESC, role, name');

hj_admin_header('Admin users', 'users');
hj_admin_page_title('Admin users', 'Owners can change settings, manage users, export data and run database tools. Recruiters work with jobs, categories and applications.');
?>
<div class="hj-card overflow-hidden mb-6">
  <div class="overflow-x-auto">
    <table class="hj-table w-full">
      <thead><tr><th>Admin</th><th>Role & access</th><th>Last sign-in</th><th>Reset password</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $row): $self = (int)$row['id'] === (int)$admin['id']; ?>
        <tr>
          <td>
            <p class="font-semibold text-primary"><?= e($row['name']) ?><?= $self ? ' ' . hj_admin_badge('You', 'blue') : '' ?></p>
            <p class="text-xs text-text-subtle"><?= e($row['email']) ?></p>
          </td>
          <td>
            <form class="flex flex-wrap items-center gap-2" method="post" action="users.php">
              <?= hj_csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <?= hj_admin_select('role', ['owner' => 'Owner', 'recruiter' => 'Recruiter'], $row['role'], 'style="width:auto"' . ($self ? ' disabled' : '')) ?>
              <label class="inline-flex items-center gap-1.5 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="is_active" value="1" <?= (int)$row['is_active'] ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>> Active</label>
              <?php if (!$self): ?><button class="hj-btn hj-btn-light hj-btn-sm" type="submit">Save</button><?php endif; ?>
            </form>
          </td>
          <td class="text-sm text-text-muted whitespace-nowrap"><?= e(hj_format_datetime($row['last_login_at'])) ?></td>
          <td>
            <?php if ($self): ?>
              <a class="text-sm text-primary underline" href="account.php">Change in My account</a>
            <?php else: ?>
              <form class="flex flex-wrap items-center gap-2" method="post" action="users.php">
                <?= hj_csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input class="hj-input !w-44" name="password" type="password" minlength="10" required autocomplete="new-password" placeholder="New password">
                <button class="hj-btn hj-btn-light hj-btn-sm" type="submit">Set</button>
              </form>
            <?php endif; ?>
          </td>
          <td class="text-right">
            <?php if (!$self): ?>
              <form method="post" action="users.php" data-confirm="Remove <?= e($row['email']) ?>? They will no longer be able to sign in.">
                <?= hj_csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button class="hj-btn hj-btn-danger hj-btn-sm" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form class="hj-card p-5 sm:p-6 space-y-4 max-w-2xl" method="post" action="users.php">
  <?= hj_csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h2 class="text-lg font-bold text-primary">Add an admin</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div><label class="hj-label" for="u-name">Name</label><input class="hj-input" id="u-name" name="name" required maxlength="120"></div>
    <div><label class="hj-label" for="u-email">Email</label><input class="hj-input" id="u-email" name="email" type="email" required maxlength="190" autocomplete="off"></div>
    <div><label class="hj-label" for="u-role">Role</label><?= hj_admin_select('role', $roles, 'recruiter', 'id="u-role"') ?></div>
    <div><label class="hj-label" for="u-password">Temporary password</label><input class="hj-input" id="u-password" name="password" type="password" minlength="10" required autocomplete="new-password"><p class="hj-help">At least 10 characters.</p></div>
  </div>
  <button class="hj-btn hj-btn-accent" type="submit">Add admin</button>
</form>
<?php
hj_admin_footer();
