<?php
/**
 * First-time setup: applies the database updates and creates the first owner account.
 * Only works while there are no admin accounts at all.
 */
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

hj_session_start();
$migration = hj_run_auto_migrations(null);

$ready = hj_schema_ready();
if ($ready && (int)hj_db_value('SELECT COUNT(*) FROM admins') > 0) {
    hj_redirect('login.php');
}

$errors = [];
$values = ['name' => '', 'email' => ''];
if ($ready && hj_is_post()) {
    hj_csrf_verify();
    $values['name'] = hj_input($_POST, 'name', 120);
    $values['email'] = strtolower(hj_input($_POST, 'email', 190));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    if ($values['name'] === '') {
        $errors[] = 'Enter your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    $passwordError = hj_validate_password($password);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $db = hj_db();
        // Lock so two people can't both create the "first" owner at the same moment.
        $locked = (int)$db->query("SELECT GET_LOCK('hubjob_setup', 5)")->fetchColumn() === 1;
        try {
            if (!$locked || (int)hj_db_value('SELECT COUNT(*) FROM admins') > 0) {
                hj_redirect('login.php');
            }
            hj_db_exec(
                "INSERT INTO admins (name, email, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, 'owner', 1, ?, ?)",
                [$values['name'], $values['email'], password_hash($password, PASSWORD_DEFAULT), hj_now(), hj_now()]
            );
            $id = (int)$db->lastInsertId();
        } finally {
            if ($locked) {
                $db->query("SELECT RELEASE_LOCK('hubjob_setup')");
            }
        }
        $_SESSION['admin_id'] = $id;
        hj_audit('admin_created', 'admin', $id, 'First owner account created during setup');
        hj_logout_session();
        hj_session_start();
        hj_flash('success', 'Owner account created. Sign in to continue.');
        hj_redirect('login.php');
    }
}

hj_admin_guest_header('First-time setup');
?>
<div class="hj-card p-6 sm:p-8 shadow-sm">
  <h1 class="text-xl font-bold text-primary mb-1">First-time setup</h1>
  <?php if (!$ready): ?>
    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      The database tables could not be created. Check the error above, confirm the database user can CREATE and ALTER tables, then reload this page.
    </div>
  <?php else: ?>
    <p class="text-sm text-text-muted mb-4">The database is ready<?= $migration['applied'] ? ' (' . count($migration['applied']) . ' update(s) applied just now)' : '' ?>. Create the owner account. The owner can add more admins later.</p>
    <?php if ($errors): ?>
      <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form class="space-y-4" method="post" action="setup.php">
      <?= hj_csrf_field() ?>
      <div>
        <label class="hj-label" for="name">Your name</label>
        <input class="hj-input" id="name" name="name" required maxlength="120" value="<?= e($values['name']) ?>">
      </div>
      <div>
        <label class="hj-label" for="email">Email</label>
        <input class="hj-input" id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= e($values['email']) ?>">
      </div>
      <div>
        <label class="hj-label" for="password">Password</label>
        <input class="hj-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
        <p class="hj-help">At least 10 characters.</p>
      </div>
      <div>
        <label class="hj-label" for="password_confirm">Confirm password</label>
        <input class="hj-input" id="password_confirm" name="password_confirm" type="password" required minlength="10" autocomplete="new-password">
      </div>
      <button class="hj-btn hj-btn-primary w-full" type="submit">Create owner account</button>
    </form>
  <?php endif; ?>
</div>
<?php
hj_admin_guest_footer();
