<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();
$categoryOptions = hj_category_options();
$categoryIds = array_map('intval', array_column($categoryOptions, 'id'));

if (hj_is_post()) {
    hj_csrf_verify();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $action = (string)($_POST['bulk_action'] ?? '');
    $back = (string)($_POST['back'] ?? 'jobs.php');
    if (!preg_match('/^jobs\.php(\?[A-Za-z0-9_=&%.\-+]*)?$/', $back)) {
        $back = 'jobs.php';
    }

    if (!$ids) {
        hj_flash('warning', 'Tick at least one job first.');
        hj_redirect($back);
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $changed = 0;
    $detail = '';

    if ($action === 'move') {
        $target = (int)($_POST['target_category'] ?? 0);
        if (!in_array($target, $categoryIds, true)) {
            hj_flash('error', 'Choose the category to move the jobs to.');
            hj_redirect($back);
        }
        $changed = hj_db_exec("UPDATE jobs SET category_id = ?, updated_at = ? WHERE id IN ($placeholders)", array_merge([$target, hj_now()], $ids));
        $detail = 'Moved to category #' . $target;
    } elseif ($action === 'active') {
        $changed = hj_db_exec("UPDATE jobs SET status = 'active', posted_at = COALESCE(posted_at, ?), updated_at = ? WHERE id IN ($placeholders)", array_merge([hj_now(), hj_now()], $ids));
        $detail = 'Status set to active';
    } elseif ($action === 'draft' || $action === 'closed') {
        $changed = hj_db_exec("UPDATE jobs SET status = ?, updated_at = ? WHERE id IN ($placeholders)", array_merge([$action, hj_now()], $ids));
        $detail = 'Status set to ' . $action;
    } elseif ($action === 'feature' || $action === 'unfeature') {
        $changed = hj_db_exec("UPDATE jobs SET is_featured = ?, updated_at = ? WHERE id IN ($placeholders)", array_merge([$action === 'feature' ? 1 : 0, hj_now()], $ids));
        $detail = $action === 'feature' ? 'Featured on the homepage' : 'Removed from the homepage';
    } else {
        hj_flash('error', 'Choose a bulk action.');
        hj_redirect($back);
    }

    hj_audit('jobs_bulk_update', 'job', null, $detail . ' — job ids ' . implode(',', $ids));
    hj_refresh_public_data();
    hj_flash('success', $changed . ' job(s) updated. ' . $detail . '.');
    hj_redirect($back);
}

$filters = [
    'q'        => hj_input($_GET, 'q', 100),
    'category' => (int)($_GET['category'] ?? 0),
    'status'   => in_array($_GET['status'] ?? '', ['active', 'draft', 'closed'], true) ? $_GET['status'] : '',
    'featured' => ($_GET['featured'] ?? '') === '1' ? '1' : '',
];

$where = [];
$params = [];
if ($filters['q'] !== '') {
    $where[] = '(j.title LIKE ? OR j.ref_code LIKE ? OR j.company_name LIKE ? OR j.skills LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($filters['category'] > 0) {
    $where[] = 'j.category_id = ?';
    $params[] = $filters['category'];
}
if ($filters['status'] !== '') {
    $where[] = 'j.status = ?';
    $params[] = $filters['status'];
}
if ($filters['featured'] === '1') {
    $where[] = 'j.is_featured = 1';
}

$jobs = hj_db_all(
    'SELECT j.*, c.name AS category_name,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.anonymised_at IS NULL) AS app_count
     FROM jobs j JOIN categories c ON c.id = j.category_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY c.sort_order, c.name, j.sort_order, j.id'
    , $params
);

$currentUrl = hj_admin_url('jobs.php', array_merge($filters, ['category' => $filters['category'] ?: '']));
$arrangementLabels = hj_labels('work_arrangement');
$regionLabels = hj_labels('applicant_region');
$typeLabels = hj_labels('employment_types');

hj_admin_header('Jobs', 'jobs');
hj_admin_page_title('Jobs', count($jobs) . ' job(s) shown. Only active jobs in visible categories appear on the website.',
    '<a class="hj-btn hj-btn-accent" href="job-edit.php"><span class="material-symbols-outlined text-[18px]">add</span>Add job</a>');
?>
<form class="hj-card p-4 mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end" method="get" action="jobs.php">
  <div class="lg:col-span-2">
    <label class="hj-label" for="q">Search</label>
    <input class="hj-input" id="q" name="q" placeholder="Title, ref, company or skill" value="<?= e($filters['q']) ?>">
  </div>
  <div>
    <label class="hj-label" for="category">Category</label>
    <?php
    $catChoices = ['' => 'All categories'];
    foreach ($categoryOptions as $c) {
        $catChoices[$c['id']] = $c['name'];
    }
    echo hj_admin_select('category', $catChoices, $filters['category'] ?: '', 'id="category"');
    ?>
  </div>
  <div>
    <label class="hj-label" for="status">Status</label>
    <?= hj_admin_select('status', ['' => 'Any status'] + hj_labels('job_status'), $filters['status'], 'id="status"') ?>
  </div>
  <div class="flex items-center gap-3">
    <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" name="featured" type="checkbox" value="1" <?= $filters['featured'] ? 'checked' : '' ?>> Featured</label>
    <button class="hj-btn hj-btn-primary" type="submit">Filter</button>
    <a class="text-sm text-text-muted hover:underline" href="jobs.php">Reset</a>
  </div>
</form>

<form method="post" action="jobs.php">
  <?= hj_csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($currentUrl) ?>">
  <div class="hj-card p-3 mb-3 flex flex-wrap items-center gap-2 text-sm">
    <span class="font-semibold text-primary mr-1">With ticked jobs:</span>
    <select class="hj-input !w-auto" name="bulk_action">
      <option value="">Choose action…</option>
      <option value="move">Move to category…</option>
      <option value="active">Set status: Active</option>
      <option value="draft">Set status: Draft</option>
      <option value="closed">Set status: Closed</option>
      <option value="feature">Feature on homepage</option>
      <option value="unfeature">Remove from homepage</option>
    </select>
    <div class="hidden" data-show-when="bulk_action=move">
      <select class="hj-input !w-auto" name="target_category">
        <option value="">Target category…</option>
        <?php foreach ($categoryOptions as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="hj-btn hj-btn-light hj-btn-sm" type="submit">Apply</button>
  </div>

  <div class="hj-card overflow-hidden">
    <?php if (!$jobs): ?>
      <p class="px-5 py-10 text-center text-sm text-text-muted">No jobs match these filters.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="hj-table w-full">
        <thead>
          <tr>
            <th class="w-8"><input aria-label="Select all" class="rounded border-slate-300 text-primary" data-check-all="ids[]" type="checkbox"></th>
            <th>Job</th><th>Category</th><th>Work details</th><th>Pay</th><th>Status</th><th>Applications</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $job):
            $types = array_map(function ($t) use ($typeLabels) { return $typeLabels[$t] ?? $t; }, hj_employment_types_array($job['employment_types']));
            $pay = hj_job_pay_label($job); ?>
          <tr>
            <td><input aria-label="Select <?= e($job['ref_code']) ?>" class="rounded border-slate-300 text-primary" name="ids[]" type="checkbox" value="<?= (int)$job['id'] ?>"></td>
            <td class="min-w-[220px]">
              <a class="font-semibold text-primary hover:underline" href="job-edit.php?id=<?= (int)$job['id'] ?>"><?= e($job['title']) ?></a>
              <div class="text-xs text-text-subtle"><?= e($job['ref_code']) ?> · <?= $job['company_name'] ? e($job['company_name']) : '<em>Company not specified</em>' ?></div>
              <div class="mt-1 flex flex-wrap gap-1">
                <?= (int)$job['is_featured'] ? hj_admin_badge('Featured', 'indigo') : '' ?>
                <?= (int)$job['employer_verified'] ? hj_admin_badge('Employer verified', 'green') : '' ?>
              </div>
            </td>
            <td class="whitespace-nowrap"><?= e($job['category_name']) ?></td>
            <td class="text-xs text-text-muted min-w-[180px]">
              <div><?= $types ? e(implode(', ', $types)) : '<em>Type unspecified</em>' ?></div>
              <div><?= e($arrangementLabels[$job['work_arrangement']] ?? $job['work_arrangement']) ?> · <?= e($regionLabels[$job['applicant_region']] ?? $job['applicant_region']) ?></div>
              <?php if ($job['location_text']): ?><div><?= e($job['location_text']) ?></div><?php endif; ?>
            </td>
            <td class="whitespace-nowrap"><?= $pay !== '' ? e($pay) : '<em class="text-text-subtle">Unspecified</em>' ?></td>
            <td><?= hj_admin_status_badge('job_status', $job['status']) ?></td>
            <td><a class="text-primary hover:underline" href="applications.php?job=<?= (int)$job['id'] ?>"><?= (int)$job['app_count'] ?></a></td>
            <td class="text-right"><a class="hj-btn hj-btn-light hj-btn-sm" href="job-edit.php?id=<?= (int)$job['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</form>
<?php
hj_admin_footer();
