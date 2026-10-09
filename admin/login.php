<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

hj_session_start();
// Apply pending database updates before anyone signs in, so a fresh upload needs no phpMyAdmin import.
hj_run_auto_migrations(null);

function hj_safe_next(string $next): string
{
    return preg_match('/^[A-Za-z0-9_-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $next) ? $next : 'index.php';
}

$next = hj_safe_next((string)($_GET['next'] ?? $_POST['next'] ?? 'index.php'));

if (hj_schema_ready() && (int)hj_db_value('SELECT COUNT(*) FROM admins') === 0) {
    hj_redirect('setup.php');
}
if (hj_schema_ready() && hj_current_admin() !== null) {
    hj_redirect($next);
}

$error = null;
$email = '';
if (hj_is_post() && hj_schema_ready()) {
    hj_csrf_verify();
    $email = hj_input($_POST, 'email', 190);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $error = hj_attempt_login($email, $password);
        if ($error === null) {
            try {
                $purge = hj_run_retention_purge(false);
                if ($purge['anonymised'] > 0) {
                    hj_flash('info', $purge['anonymised'] . ' application(s) reached the end of the retention period and were anonymised.');
                }
            } catch (Throwable $e) {
                error_log('[hubjob] Retention purge failed: ' . $e->getMessage());
            }
            hj_redirect($next);
        }
    }
}

hj_admin_guest_header('Sign in');
?>
<div class="hj-card p-6 sm:p-8 shadow-sm">
  <h1 class="text-xl font-bold text-primary mb-1">Sign in</h1>
  <p class="text-sm text-text-muted mb-6">Admin access for jobs, applications and site settings.</p>
  <?php if (!hj_schema_ready()): ?>
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      The database is not ready yet. The automatic update did not finish; the error is shown above. Fix the cause (usually the database user's permissions) and reload this page.
    </div>
  <?php else: ?>
    <?php if ($error !== null): ?>
      <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form class="space-y-4" method="post" action="login.php">
      <?= hj_csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div>
        <label class="hj-label" for="email">Email</label>
        <input class="hj-input" id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>" autofocus>
      </div>
      <div>
        <label class="hj-label" for="password">Password</label>
        <input class="hj-input" id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="hj-btn hj-btn-primary w-full" type="submit"><span class="material-symbols-outlined text-[18px]">login</span>Sign in</button>
    </form>
    <p class="text-xs text-text-subtle mt-5">Forgot your password? Ask the site owner to reset it from Admin users.</p>
  <?php endif; ?>
</div>
<?php
hj_admin_guest_footer();
