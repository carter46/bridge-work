<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();

$statusLabels = hj_labels('app_status');
$filters = [
    'q'      => hj_input($_GET, 'q', 100),
    'status' => array_key_exists((string)($_GET['status'] ?? ''), $statusLabels) ? (string)$_GET['status'] : '',
    'job'    => (int)($_GET['job'] ?? 0) ?: '',
    'mail'   => ($_GET['mail'] ?? '') === 'failed' ? 'failed' : '',
    'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : '',
    'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : '',
    'keep'   => ($_GET['keep'] ?? '') === '1' ? '1' : '',
];

$where = ['a.anonymised_at IS NULL'];
$params = [];
if ($filters['q'] !== '') {
    $where[] = '(a.fullname LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR a.role_title LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($filters['status'] !== '') {
    $where[] = 'a.status = ?';
    $params[] = $filters['status'];
}
if ($filters['job'] !== '') {
    $where[] = 'a.job_id = ?';
    $params[] = (int)$filters['job'];
}
if ($filters['mail'] === 'failed') {
    $where[] = "(a.notify_status = 'failed' OR a.confirm_status = 'failed')";
}
if ($filters['from'] !== '') {
    $where[] = 'a.created_at >= ?';
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where[] = 'a.created_at <= ?';
    $params[] = $filters['to'] . ' 23:59:59';
}
if ($filters['keep'] === '1') {
    $where[] = 'a.keep_flag = 1';
}
$whereSql = ' WHERE ' . implode(' AND ', $where);
$fromSql = ' FROM applications a LEFT JOIN jobs j ON j.id = a.job_id';

if (($_GET['export'] ?? '') === 'csv') {
    if (!hj_is_owner($admin)) {
        hj_flash('error', 'Only an owner can export applications.');
        hj_redirect(hj_admin_url('applications.php', $filters));
    }
    $rows = hj_db_all(
        'SELECT a.id, a.created_at, a.status, a.fullname, a.email, a.phone, a.residence_country, a.right_to_work, a.work_type,
                a.role_title, j.ref_code, a.cv_original_name, a.notify_status, a.confirm_status, a.keep_flag, a.retain_until,
                a.utm_source, a.utm_medium, a.utm_campaign, a.source_page, a.form_version'
        . $fromSql . $whereSql . ' ORDER BY a.created_at DESC, a.id DESC',
        $params
    );
    hj_audit('applications_exported', 'application', null, count($rows) . ' row(s) exported to CSV');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="applications-' . gmdate('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Received (UTC)', 'Status', 'Name', 'Email', 'Phone', 'Country of residence', 'Right to work UK/EU', 'Work type',
        'Role', 'Job ref', 'CV file', 'Notification email', 'Confirmation email', 'Keep', 'Delete after (UTC)',
        'UTM source', 'UTM medium', 'UTM campaign', 'Form page', 'Form version']);
    foreach ($rows as $row) {
        // Neutralise spreadsheet formulas in applicant-supplied text.
        $row = array_map(function ($v) {
            $v = (string)$v;
            return ($v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v;
        }, $row);
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

$perPage = 25;
$total = (int)hj_db_value('SELECT COUNT(*)' . $fromSql . $whereSql, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$rows = hj_db_all(
    'SELECT a.*, j.ref_code, j.title AS job_title' . $fromSql . $whereSql
    . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
);

$jobChoices = ['' => 'Any job'];
foreach (hj_db_all('SELECT id, ref_code, title FROM jobs ORDER BY ref_code') as $j) {
    $jobChoices[$j['id']] = $j['ref_code'] . ' — ' . $j['title'];
}

$actions = hj_is_owner($admin)
    ? '<a class="hj-btn hj-btn-light" href="' . e(hj_admin_url('applications.php', array_merge($filters, ['export' => 'csv']))) . '"><span class="material-symbols-outlined text-[18px]">download</span>Export CSV</a>'
    : '';

hj_admin_header('Applications', 'applications');
hj_admin_page_title('Applications', $total . ' application(s). Applications are deleted automatically ' . (int)hj_setting('retention_months', '12') . ' months after their last update unless marked Keep.', $actions);
?>
<form class="hj-card p-4 mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end" method="get" action="applications.php">
  <div class="lg:col-span-2">
    <label class="hj-label" for="q">Search</label>
    <input class="hj-input" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email, phone or role">
  </div>
  <div>
    <label class="hj-label" for="status">Status</label>
    <?= hj_admin_select('status', ['' => 'Any status'] + $statusLabels, $filters['status'], 'id="status"') ?>
  </div>
  <div>
    <label class="hj-label" for="job">Job</label>
    <?= hj_admin_select('job', $jobChoices, $filters['job'], 'id="job"') ?>
  </div>
  <div>
    <label class="hj-label" for="from">From</label>
    <input class="hj-input" id="from" name="from" type="date" value="<?= e($filters['from']) ?>">
  </div>
  <div>
    <label class="hj-label" for="to">To</label>
    <input class="hj-input" id="to" name="to" type="date" value="<?= e($filters['to']) ?>">
  </div>
  <div class="lg:col-span-6 flex flex-wrap items-center gap-4">
    <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="mail" value="failed" <?= $filters['mail'] ? 'checked' : '' ?>> Email problems only</label>
    <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="keep" value="1" <?= $filters['keep'] ? 'checked' : '' ?>> Marked Keep only</label>
    <button class="hj-btn hj-btn-primary" type="submit">Filter</button>
    <a class="text-sm text-text-muted hover:underline" href="applications.php">Reset</a>
  </div>
</form>

<div class="hj-card overflow-hidden">
  <?php if (!$rows): ?>
    <p class="px-5 py-10 text-center text-sm text-text-muted">No applications match these filters.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="hj-table w-full">
      <thead><tr><th>Applicant</th><th>Role</th><th>Residence</th><th>CV</th><th>Status</th><th>Emails</th><th>Received</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="min-w-[200px]">
            <a class="font-semibold text-primary hover:underline" href="application.php?id=<?= (int)$row['id'] ?>"><?= e($row['fullname']) ?></a>
            <?= (int)$row['keep_flag'] ? hj_admin_badge('Keep', 'indigo') : '' ?>
            <div class="text-xs text-text-subtle break-all"><?= e($row['email']) ?></div>
            <div class="text-xs text-text-subtle"><?= e($row['phone']) ?></div>
          </td>
          <td class="min-w-[180px]">
            <?= e($row['role_title'] ?: ($row['job_title'] ?: 'General application')) ?>
            <?php if ($row['ref_code']): ?><div class="text-xs text-text-subtle"><?= e($row['ref_code']) ?></div><?php endif; ?>
            <div class="text-xs text-text-subtle"><?= e($row['work_type']) ?></div>
          </td>
          <td class="text-sm"><?= e($row['residence_country'] ?: '—') ?></td>
          <td>
            <?php if ($row['cv_stored_name']): ?>
              <a class="inline-flex items-center gap-1 text-primary hover:underline text-sm" href="cv.php?id=<?= (int)$row['id'] ?>" target="_blank"><span class="material-symbols-outlined text-[18px]">description</span>View</a>
            <?php else: ?><span class="text-xs text-text-subtle">None</span><?php endif; ?>
          </td>
          <td><?= hj_admin_status_badge('app_status', $row['status']) ?></td>
          <td class="text-xs whitespace-nowrap">
            <div>Team: <?= hj_admin_status_badge('mail_status', $row['notify_status']) ?></div>
            <div class="mt-1">Applicant: <?= hj_admin_status_badge('mail_status', $row['confirm_status']) ?></div>
          </td>
          <td class="whitespace-nowrap text-sm text-text-muted"><?= e(hj_format_datetime($row['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php
hj_admin_pagination('applications.php', $filters, $page, $pages);
hj_admin_footer();
