<?php
/**
 * Emergency admin password reset.
 * Only works while 'admin_password_reset' => true is set in config/config.php; otherwise it sends people to sign in.
 * Turn it back to false straight after use.
 */
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (hj_config('admin_password_reset', false) !== true) {
    hj_redirect('login.php');
}

hj_session_start();
hj_run_auto_migrations(null);

$ready = hj_schema_ready();
$errors = [];
$done = false;
$inactive = false;
$email = '';

if ($ready && hj_is_post()) {
    hj_csrf_verify();
    $email = strtolower(hj_input($_POST, 'email', 190));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    $ipHash = hj_ip_hash();

    if (hj_login_throttled($ipHash)) {
        $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $passwordError = hj_validate_password($password);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        } elseif ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }
    }

    if (!$errors) {
        $row = hj_db_one('SELECT id, is_active FROM admins WHERE email = ?', [$email]);
        if (!$row) {
            // Counts as a failed attempt so the page can't be used to guess admin emails quickly.
            hj_db_exec(
                'INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?, ?, 0, ?)',
                [$ipHash, hj_substr($email, 0, 190), hj_now()]
            );
            $errors[] = 'No admin account uses that email address.';
        } else {
            hj_db_exec(
                'UPDATE admins SET password_hash = ?, updated_at = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), hj_now(), (int)$row['id']]
            );
            hj_db_exec('DELETE FROM login_attempts WHERE ip_hash = ? AND success = 0', [$ipHash]);
            hj_audit('admin_password_reset', 'admin', (int)$row['id'], $email . ' (reset page)');
            $inactive = !(int)$row['is_active'];
            $done = true;
        }
    }
}

hj_admin_guest_header('Reset admin password');
?>
<div class="hj-card p-6 sm:p-8 shadow-sm">
  <h1 class="text-xl font-bold text-primary mb-1">Reset admin password</h1>
  <?php if (!$ready): ?>
    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      The database is not ready yet. Check the error above, then reload this page.
    </div>
  <?php elseif ($done): ?>
    <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
      <p>The password has been changed. You can sign in with it now.</p>
      <?php if ($inactive): ?><p class="mt-2">Note: this account is switched off. Another owner must turn it back on in Admin users before it can sign in.</p><?php endif; ?>
    </div>
    <p class="mt-4 text-sm text-text-muted">Now set <code>'admin_password_reset' => false</code> in <code>config/config.php</code> so this page is locked again.</p>
    <a class="hj-btn hj-btn-primary w-full mt-5" href="login.php">Go to sign in</a>
  <?php else: ?>
    <p class="text-sm text-text-muted mb-4">Enter the admin account's email and choose a new password.</p>
    <?php if ($errors): ?>
      <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form class="space-y-4" method="post" action="reset-password.php">
      <?= hj_csrf_field() ?>
      <div>
        <label class="hj-label" for="email">Admin email</label>
        <input class="hj-input" id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= e($email) ?>" autofocus>
      </div>
      <div>
        <label class="hj-label" for="password">New password</label>
        <input class="hj-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
        <p class="hj-help">At least 10 characters.</p>
      </div>
      <div>
        <label class="hj-label" for="password_confirm">Confirm new password</label>
        <input class="hj-input" id="password_confirm" name="password_confirm" type="password" required minlength="10" autocomplete="new-password">
      </div>
      <button class="hj-btn hj-btn-primary w-full" type="submit">Reset password</button>
    </form>
    <p class="mt-5 text-xs text-text-subtle">Security: turn this page off in <code>config/config.php</code> once you are done.</p>
    <p class="mt-4 text-sm"><a class="text-primary hover:underline" href="login.php">&larr; Back to sign in</a></p>
  <?php endif; ?>
</div>
<?php
hj_admin_guest_footer();
