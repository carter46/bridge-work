<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();

$jobCounts = hj_job_counts();
$appCounts = hj_db_one(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'new'), 0) AS new_count,
            COALESCE(SUM(created_at >= ?), 0) AS last7,
            COALESCE(SUM(notify_status = 'failed' OR confirm_status = 'failed'), 0) AS mail_failed
     FROM applications WHERE anonymised_at IS NULL",
    [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]
) ?: ['total' => 0, 'new_count' => 0, 'last7' => 0, 'mail_failed' => 0];
$recent = hj_db_all(
    'SELECT a.id, a.fullname, a.email, a.role_title, a.status, a.created_at, a.cv_stored_name, j.ref_code
     FROM applications a LEFT JOIN jobs j ON j.id = a.job_id
     WHERE a.anonymised_at IS NULL ORDER BY a.created_at DESC, a.id DESC LIMIT 8'
);
$categories = hj_categories_with_counts();
$mailReady = hj_mail_configured();
$failedMail = hj_db_all(
    "SELECT id, fullname, role_title, notify_status, confirm_status, last_mail_error, created_at
     FROM applications
     WHERE anonymised_at IS NULL AND (notify_status = 'failed' OR confirm_status = 'failed')
     ORDER BY created_at DESC LIMIT 5"
);
$topJobs = hj_db_all(
    "SELECT j.id, j.ref_code, j.title, COUNT(a.id) AS app_count
     FROM jobs j JOIN applications a ON a.job_id = j.id AND a.anonymised_at IS NULL
     GROUP BY j.id, j.ref_code, j.title ORDER BY app_count DESC, j.ref_code LIMIT 5"
);
$completeness = hj_db_one(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(company_name IS NULL OR company_name = ''), 0) AS no_company,
            COALESCE(SUM(employment_types = ''), 0) AS no_type,
            COALESCE(SUM(work_arrangement = 'unspecified'), 0) AS no_arrangement,
            COALESCE(SUM(applicant_region = 'unspecified'), 0) AS no_region,
            COALESCE(SUM(description IS NULL OR description = ''), 0) AS no_description,
            COALESCE(SUM(employer_verified = 0), 0) AS unverified
     FROM jobs WHERE status = 'active'"
) ?: [];

hj_admin_header('Dashboard', 'dashboard');
hj_admin_page_title('Dashboard', 'Welcome back, ' . $admin['name'] . '.');

if (!$mailReady) {
    echo '<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">'
        . 'Email delivery is not set up, so new applications are saved here but no notification or confirmation emails are sent. '
        . (hj_is_owner($admin) ? '<a class="underline font-semibold" href="settings.php#email">Set up SMTP</a>.' : 'Ask the site owner to set up SMTP.')
        . '</div>';
}

$cards = [
    ['label' => 'Live jobs', 'value' => $jobCounts['active'] ?? 0, 'icon' => 'work', 'href' => 'jobs.php?status=active', 'note' => ($jobCounts['total'] ?? 0) . ' in total · ' . ($jobCounts['draft'] ?? 0) . ' draft · ' . ($jobCounts['closed'] ?? 0) . ' closed'],
    ['label' => 'Applications', 'value' => (int)$appCounts['total'], 'icon' => 'inbox', 'href' => 'applications.php', 'note' => (int)$appCounts['last7'] . ' in the last 7 days'],
    ['label' => 'New (unreviewed)', 'value' => (int)$appCounts['new_count'], 'icon' => 'mark_email_unread', 'href' => 'applications.php?status=new', 'note' => 'Waiting for review'],
    ['label' => 'Email problems', 'value' => (int)$appCounts['mail_failed'], 'icon' => 'warning', 'href' => 'applications.php?mail=failed', 'note' => 'Notification or confirmation failed'],
];
?>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
  <?php foreach ($cards as $card): ?>
    <a class="hj-card p-5 hover:shadow-md transition-shadow block" href="<?= e($card['href']) ?>">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-bold uppercase tracking-wider text-text-subtle"><?= e($card['label']) ?></span>
        <span class="material-symbols-outlined text-primary"><?= e($card['icon']) ?></span>
      </div>
      <p class="text-3xl font-extrabold text-primary"><?= (int)$card['value'] ?></p>
      <p class="text-xs text-text-muted mt-1"><?= e($card['note']) ?></p>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <section class="hj-card xl:col-span-2 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-bold text-primary">Latest applications</h2>
      <a class="text-sm font-semibold text-secondary hover:underline" href="applications.php">View all</a>
    </div>
    <?php if (!$recent): ?>
      <p class="px-5 py-8 text-sm text-text-muted text-center">No applications yet.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="hj-table w-full">
          <thead><tr><th>Applicant</th><th>Role</th><th>Status</th><th>Received</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td><a class="font-semibold text-primary hover:underline" href="application.php?id=<?= (int)$row['id'] ?>"><?= e($row['fullname']) ?></a><div class="text-xs text-text-subtle"><?= e($row['email']) ?></div></td>
              <td><?= e($row['role_title'] ?: 'General application') ?><?= $row['ref_code'] ? ' <span class="text-xs text-text-subtle">(' . e($row['ref_code']) . ')</span>' : '' ?><?= $row['cv_stored_name'] ? ' <span class="material-symbols-outlined text-[16px] text-text-subtle" title="CV attached">attach_file</span>' : '' ?></td>
              <td><?= hj_admin_status_badge('app_status', $row['status']) ?></td>
              <td class="whitespace-nowrap text-text-muted"><?= e(hj_format_datetime($row['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="hj-card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-bold text-primary">Jobs by category</h2>
      <a class="text-sm font-semibold text-secondary hover:underline" href="categories.php">Manage</a>
    </div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($categories as $cat): ?>
        <li class="flex items-center justify-between px-5 py-3 text-sm">
          <a class="flex items-center gap-2 text-primary hover:underline" href="jobs.php?category=<?= (int)$cat['id'] ?>">
            <span class="material-symbols-outlined text-[18px] text-text-subtle"><?= e($cat['icon'] ?: 'work') ?></span><?= e($cat['name']) ?>
          </a>
          <span class="text-text-muted"><?= (int)$cat['active_count'] ?> live<?= (int)$cat['total_count'] !== (int)$cat['active_count'] ? ' / ' . (int)$cat['total_count'] : '' ?><?= (int)$cat['is_visible'] ? '' : ' · hidden' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="px-5 py-4 border-t border-slate-100">
      <a class="hj-btn hj-btn-accent w-full" href="job-edit.php"><span class="material-symbols-outlined text-[18px]">add</span>Add a job</a>
    </div>
  </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
  <section class="hj-card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100"><h2 class="font-bold text-primary">Emails that failed</h2></div>
    <?php if (!$failedMail): ?>
      <p class="px-5 py-6 text-sm text-text-muted">No failed emails.</p>
    <?php else: ?>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($failedMail as $f): ?>
          <li class="px-5 py-3 text-sm">
            <a class="font-semibold text-primary hover:underline" href="application.php?id=<?= (int)$f['id'] ?>"><?= e($f['fullname']) ?></a>
            <span class="text-xs text-text-subtle"> · <?= e(hj_format_datetime($f['created_at'])) ?></span>
            <?php if ($f['last_mail_error']): ?><p class="text-xs text-red-700 mt-0.5 truncate" title="<?= e($f['last_mail_error']) ?>"><?= e($f['last_mail_error']) ?></p><?php endif; ?>
            <form class="flex flex-wrap gap-2 mt-2" method="post" action="application.php?id=<?= (int)$f['id'] ?>">
              <?= hj_csrf_field() ?>
              <?php if ($f['notify_status'] === 'failed'): ?><button class="hj-btn hj-btn-light hj-btn-sm" name="action" value="resend_notify" type="submit">Resend notification</button><?php endif; ?>
              <?php if ($f['confirm_status'] === 'failed'): ?><button class="hj-btn hj-btn-light hj-btn-sm" name="action" value="resend_confirm" type="submit">Resend confirmation</button><?php endif; ?>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="hj-card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100"><h2 class="font-bold text-primary">Most applied-for jobs</h2></div>
    <?php if (!$topJobs): ?>
      <p class="px-5 py-6 text-sm text-text-muted">No applications linked to a job yet.</p>
    <?php else: ?>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($topJobs as $t): ?>
          <li class="flex items-center justify-between px-5 py-3 text-sm">
            <a class="text-primary hover:underline" href="applications.php?job=<?= (int)$t['id'] ?>"><?= e($t['title']) ?> <span class="text-xs text-text-subtle"><?= e($t['ref_code']) ?></span></a>
            <span class="font-semibold"><?= (int)$t['app_count'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="hj-card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100"><h2 class="font-bold text-primary">Live job data to complete</h2></div>
    <?php
    $gaps = [
        'no_company' => 'No company name',
        'no_type' => 'Employment type unspecified',
        'no_arrangement' => 'Work arrangement unspecified',
        'no_region' => 'Applicant region unspecified',
        'no_description' => 'No description',
        'unverified' => 'Employer not verified',
    ];
    ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($gaps as $key => $label): ?>
        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
          <span><?= e($label) ?></span>
          <span class="<?= (int)($completeness[$key] ?? 0) > 0 ? 'text-amber-700 font-semibold' : 'text-emerald-700' ?>"><?= (int)($completeness[$key] ?? 0) ?> / <?= (int)($completeness['total'] ?? 0) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="px-5 py-3 text-xs text-text-subtle border-t border-slate-100">The website shows "unspecified" fields honestly; fill them in only with details the employer has confirmed.</p>
  </section>
</div>
<?php
hj_admin_footer();
