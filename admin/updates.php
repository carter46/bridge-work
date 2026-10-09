<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin('owner');

if (isset($_GET['download'])) {
    $path = hj_backup_path((string)$_GET['download']);
    if ($path === null) {
        hj_flash('error', 'Backup file not found.');
        hj_redirect('updates.php');
    }
    hj_audit('backup_downloaded', 'backup', null, basename($path));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if (hj_is_post()) {
    hj_csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'backup') {
        try {
            $name = hj_create_backup('manual');
            hj_audit('backup_created', 'backup', null, (string)$name);
            hj_flash('success', 'Backup created: ' . $name);
        } catch (Throwable $e) {
            hj_flash('error', 'Backup failed: ' . $e->getMessage());
        }
    } elseif ($action === 'restore') {
        $name = (string)($_POST['name'] ?? '');
        if (trim((string)($_POST['confirm_word'] ?? '')) !== 'RESTORE') {
            hj_flash('error', 'Type RESTORE in capitals to confirm.');
        } else {
            try {
                hj_restore_backup($name);
                hj_schema_ready(true);
                hj_settings_all(true);
                hj_audit('backup_restored', 'backup', null, $name);
                hj_refresh_public_data();
                hj_flash('success', 'Restored ' . $name . '. A "pre-restore" backup of the previous state was taken first.');
            } catch (Throwable $e) {
                hj_flash('error', 'Restore failed: ' . $e->getMessage() . ' — the pre-restore backup is listed below.');
            }
        }
    } elseif ($action === 'snapshots') {
        try {
            hj_regenerate_snapshots();
            hj_flash('success', 'Public backup data files rewritten.');
        } catch (Throwable $e) {
            hj_flash('error', 'Could not write the public data files: ' . $e->getMessage());
        }
    }
    hj_redirect('updates.php');
}

$migrations = [];
$migrationError = null;
try {
    $migrations = HJ_AutoMigrate::status();
} catch (Throwable $e) {
    $migrationError = $e->getMessage();
}
$pending = count(array_filter($migrations, function ($m) { return $m['status'] !== 'success'; }));
$backups = hj_list_backups();

$checks = [
    ['PHP version', PHP_VERSION, PHP_VERSION_ID >= 70400],
    ['MySQL / MariaDB version', (string)hj_db_value('SELECT VERSION()'), true],
    ['CV storage folder writable', hj_cv_dir(), is_dir(hj_cv_dir()) ? is_writable(hj_cv_dir()) : hj_ensure_dir(hj_cv_dir())],
    ['Backup folder writable', hj_backup_dir(), is_dir(hj_backup_dir()) ? is_writable(hj_backup_dir()) : hj_ensure_dir(hj_backup_dir())],
    ['Public data folder writable', 'assets/data', is_writable(hj_snapshot_dir())],
    ['Uploads folder writable (logo)', 'uploads', is_dir(HJ_ROOT . '/uploads') ? is_writable(HJ_ROOT . '/uploads') : hj_ensure_dir(HJ_ROOT . '/uploads', 0755)],
    ['Encryption available', function_exists('sodium_crypto_secretbox') ? 'sodium' : (function_exists('openssl_encrypt') ? 'openssl' : 'none'), function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt')],
    ['File type detection (finfo)', class_exists('finfo') ? 'available' : 'missing — CV checks use file signatures only', class_exists('finfo')],
    ['Email delivery', hj_mail_configured() ? 'SMTP set (' . hj_mail_config()['source'] . ')' : 'not set up', hj_mail_configured()],
    ['Last retention check', hj_format_datetime(hj_setting('last_purge_at') ?: null), hj_setting('last_purge_at') !== ''],
];

hj_admin_header('Database updates', 'updates');
hj_admin_page_title(
    'Database updates',
    'Database changes ship as files in database/auto-migrations and apply themselves when an admin opens any admin page. No phpMyAdmin imports are needed.',
    ($pending ? '<a class="hj-btn hj-btn-primary" href="updates.php"><span class="material-symbols-outlined text-[18px]">refresh</span>Retry now</a>' : '')
    . '<a class="hj-btn hj-btn-light" href="verify.php"><span class="material-symbols-outlined text-[18px]">fact_check</span>Job data check</a>'
);
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <div class="xl:col-span-2 space-y-6">
    <section class="hj-card overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold text-primary">Updates</h2>
        <span class="text-sm <?= $pending ? 'text-amber-800' : 'text-emerald-700' ?>"><?= $pending ? $pending . ' not applied yet — retried on every admin page load' : 'All updates applied' ?></span>
      </div>
      <?php if ($migrationError !== null): ?>
        <p class="px-5 py-4 text-sm text-red-700"><?= e($migrationError) ?></p>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="hj-table w-full">
          <thead><tr><th>Update</th><th>Status</th><th>Applied</th></tr></thead>
          <tbody>
          <?php foreach ($migrations as $m): ?>
            <tr>
              <td>
                <p class="font-semibold text-primary text-sm"><?= e($m['description']) ?></p>
                <p class="text-xs text-text-subtle font-mono"><?= e($m['id']) ?></p>
                <?php if ($m['error']): ?><p class="text-xs text-red-700 mt-1"><?= e($m['error']) ?></p><?php endif; ?>
              </td>
              <td><?= hj_admin_status_badge('migration', $m['status']) ?></td>
              <td class="text-sm text-text-muted whitespace-nowrap"><?= e(hj_format_datetime($m['applied_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <section class="hj-card overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold text-primary">Backups</h2>
        <form method="post" action="updates.php">
          <?= hj_csrf_field() ?>
          <input type="hidden" name="action" value="backup">
          <button class="hj-btn hj-btn-primary hj-btn-sm" type="submit"><span class="material-symbols-outlined text-[18px]">backup</span>Back up now</button>
        </form>
      </div>
      <p class="px-5 pt-4 text-sm text-text-muted">A backup is taken automatically before every database update and before every restore. The newest 10 are kept in the private <code>storage/backups</code> folder. CV files are not included — back up <code>storage/cvs</code> with your hosting file manager.</p>
      <?php if (!$backups): ?>
        <p class="px-5 py-6 text-sm text-text-muted">No backups yet.</p>
      <?php else: ?>
      <div class="overflow-x-auto mt-2">
        <table class="hj-table w-full">
          <thead><tr><th>File</th><th>Size</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($backups as $b): ?>
            <tr>
              <td class="font-mono text-xs"><?= e($b['name']) ?><div class="font-sans text-text-subtle"><?= e(hj_format_datetime(gmdate('Y-m-d H:i:s', (int)$b['mtime']))) ?></div></td>
              <td class="text-sm whitespace-nowrap"><?= e(hj_admin_format_bytes($b['size'])) ?></td>
              <td>
                <div class="flex flex-wrap items-center gap-2">
                  <a class="hj-btn hj-btn-light hj-btn-sm" href="updates.php?download=<?= e(rawurlencode($b['name'])) ?>">Download</a>
                  <form class="flex items-center gap-2" method="post" action="updates.php" data-confirm="Replace ALL current data (jobs, applications, settings, admins) with this backup?">
                    <?= hj_csrf_field() ?>
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="name" value="<?= e($b['name']) ?>">
                    <input class="hj-input !w-28 !py-1 text-xs" name="confirm_word" placeholder="Type RESTORE" required autocomplete="off">
                    <button class="hj-btn hj-btn-danger hj-btn-sm" type="submit">Restore</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="space-y-6">
    <section class="hj-card p-5">
      <h2 class="font-bold text-primary mb-3">Server checks</h2>
      <ul class="space-y-2.5 text-sm">
        <?php foreach ($checks as $check): ?>
          <li class="flex items-start gap-2">
            <span class="material-symbols-outlined text-[18px] <?= $check[2] ? 'text-emerald-600' : 'text-amber-600' ?>"><?= $check[2] ? 'check_circle' : 'error' ?></span>
            <span><span class="font-semibold"><?= e($check[0]) ?></span><br><span class="text-xs text-text-subtle break-all"><?= e($check[1]) ?></span></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="hj-card p-5">
      <h2 class="font-bold text-primary mb-2">Public backup data</h2>
      <p class="text-sm text-text-muted mb-3">The website reads jobs and contact details from the database, and falls back to <code>assets/data/*.json</code> if the database is unreachable. These files are rewritten after every change; use this if they look out of date.</p>
      <form method="post" action="updates.php">
        <?= hj_csrf_field() ?>
        <input type="hidden" name="action" value="snapshots">
        <button class="hj-btn hj-btn-light hj-btn-sm" type="submit">Rewrite now</button>
      </form>
    </section>
  </div>
</div>
<?php
hj_admin_footer();
