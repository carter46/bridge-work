<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();
$categories = hj_category_options();
$categoryIds = array_map('intval', array_column($categories, 'id'));

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$job = $id > 0 ? hj_job_find($id) : null;
if ($id > 0 && $job === null) {
    hj_flash('error', 'That job no longer exists.');
    hj_redirect('jobs.php');
}

function hj_ref_prefix_for(string $slug): string
{
    $known = [
        'tech' => 'TECH', 'support' => 'SUP', 'operations' => 'OPS', 'marketing' => 'MKT',
        'media' => 'MED', 'sales' => 'SLS', 'design' => 'DES', 'education' => 'EDU',
    ];
    return $known[$slug] ?? strtoupper(substr(preg_replace('/[^a-z]/', '', $slug), 0, 4));
}

/**
 * Insert or update a validated job, then refresh the public files and redirect.
 * Throws PDOException (code 23000) when the ref code or category constraint fails.
 */
function hj_job_save(?array $job, int $id, array $in, array $editable): void
{
    if ($job === null) {
        $cols = $editable;
        $cols[] = 'created_at';
        $cols[] = 'updated_at';
        $params = [];
        foreach ($editable as $col) {
            $params[] = $in[$col];
        }
        $params[] = hj_now();
        $params[] = hj_now();
        hj_db_exec(
            'INSERT INTO jobs (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            $params
        );
        $id = (int)hj_db()->lastInsertId();
        hj_audit('job_created', 'job', $id, $in['ref_code'] . ' — ' . $in['title']);
        hj_flash('success', 'Job ' . $in['ref_code'] . ' created.');
    } else {
        $changed = [];
        foreach ($editable as $col) {
            $old = $job[$col];
            $new = $in[$col];
            $same = ($old === null && $new === null)
                || ($old !== null && $new !== null && (is_float($new) ? (float)$old === $new : (string)$old === (string)$new));
            if (!$same) {
                $changed[] = $col;
            }
        }
        if ($changed) {
            $sets = implode(', ', array_map(function ($c) { return $c . ' = ?'; }, $editable));
            $params = [];
            foreach ($editable as $col) {
                $params[] = $in[$col];
            }
            $params[] = hj_now();
            $params[] = $id;
            hj_db_exec('UPDATE jobs SET ' . $sets . ', updated_at = ? WHERE id = ?', $params);
            hj_audit('job_updated', 'job', $id, $in['ref_code'] . ' changed: ' . implode(', ', $changed));
            hj_flash('success', 'Job ' . $in['ref_code'] . ' saved.');
        } else {
            hj_flash('info', 'No changes to save.');
        }
    }
    hj_refresh_public_data();
    hj_redirect('job-edit.php?id=' . $id);
}

$editable = ['title', 'ref_code', 'company_name', 'category_id', 'employment_types', 'work_arrangement', 'applicant_region',
    'location_text', 'schedule', 'schedule_note', 'pay_min', 'pay_max', 'pay_currency', 'pay_period', 'skills', 'icon',
    'badge', 'description', 'employer_verified', 'status', 'is_featured', 'sort_order', 'posted_at'];

$defaults = [
    'title' => '', 'ref_code' => '', 'company_name' => null, 'category_id' => $categories ? (int)$categories[0]['id'] : 0,
    'employment_types' => '', 'work_arrangement' => 'unspecified', 'applicant_region' => 'unspecified', 'location_text' => null,
    'schedule' => 'unspecified', 'schedule_note' => null, 'pay_min' => null, 'pay_max' => null, 'pay_currency' => 'USD',
    'pay_period' => 'hour', 'skills' => null, 'icon' => 'work', 'badge' => null, 'description' => null, 'employer_verified' => 0,
    'status' => 'draft', 'is_featured' => 0, 'sort_order' => 0, 'posted_at' => null,
];
if ($job === null && $categories) {
    $defaults['ref_code'] = hj_suggest_ref_code(hj_ref_prefix_for($categories[0]['slug']));
    $defaults['sort_order'] = (int)hj_db_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM jobs');
}
$values = $job ?? $defaults;
$duplicateOf = null;
if ($job === null && !empty($_GET['duplicate'])) {
    $duplicateOf = hj_job_find((int)$_GET['duplicate']);
    if ($duplicateOf !== null) {
        $values = array_merge($defaults, array_intersect_key($duplicateOf, array_flip($editable)));
        $slug = (string)hj_db_value('SELECT slug FROM categories WHERE id = ?', [(int)$duplicateOf['category_id']]);
        $values['ref_code'] = hj_suggest_ref_code(hj_ref_prefix_for($slug));
        $values['status'] = 'draft';
        $values['is_featured'] = 0;
        $values['employer_verified'] = 0;
        $values['posted_at'] = null;
    }
}
$errors = [];

if (hj_is_post()) {
    hj_csrf_verify();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete' && $job !== null) {
        if (!hj_is_owner($admin)) {
            hj_flash('error', 'Only an owner can delete jobs. Set the status to Closed instead.');
            hj_redirect('job-edit.php?id=' . $id);
        }
        hj_db_exec('DELETE FROM jobs WHERE id = ?', [$id]);
        hj_audit('job_deleted', 'job', $id, $job['ref_code'] . ' — ' . $job['title']);
        hj_refresh_public_data();
        hj_flash('success', 'Job ' . $job['ref_code'] . ' deleted. Applications for it keep the role title.');
        hj_redirect('jobs.php');
    }

    $in = [];
    $in['title'] = hj_input($_POST, 'title', 160);
    $in['ref_code'] = strtoupper(hj_input($_POST, 'ref_code', 30));
    $in['company_name'] = hj_input($_POST, 'company_name', 160) ?: null;
    $in['category_id'] = (int)($_POST['category_id'] ?? 0);
    $types = array_values(array_intersect(HJ_EMPLOYMENT_TYPES, (array)($_POST['employment_types'] ?? [])));
    $in['employment_types'] = implode(',', $types);
    $in['work_arrangement'] = (string)($_POST['work_arrangement'] ?? '');
    $in['applicant_region'] = (string)($_POST['applicant_region'] ?? '');
    $in['location_text'] = hj_input($_POST, 'location_text', 160) ?: null;
    $in['schedule'] = (string)($_POST['schedule'] ?? '');
    $in['schedule_note'] = hj_input($_POST, 'schedule_note', 160) ?: null;
    $in['pay_currency'] = (string)($_POST['pay_currency'] ?? 'USD');
    $in['pay_period'] = (string)($_POST['pay_period'] ?? 'hour');
    $in['skills'] = hj_input($_POST, 'skills', 200) ?: null;
    $in['icon'] = strtolower(hj_input($_POST, 'icon', 60)) ?: 'work';
    $in['badge'] = hj_input($_POST, 'badge', 60) ?: null;
    $in['description'] = hj_clean_text(str_replace("\r\n", "\n", (string)($_POST['description'] ?? '')), 5000) ?: null;
    $in['employer_verified'] = !empty($_POST['employer_verified']) ? 1 : 0;
    $in['status'] = (string)($_POST['status'] ?? 'draft');
    $in['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
    $in['sort_order'] = (int)($_POST['sort_order'] ?? 0);

    foreach (['pay_min', 'pay_max'] as $field) {
        $raw = str_replace([',', ' '], '', hj_input($_POST, $field, 20));
        if ($raw === '') {
            $in[$field] = null;
        } elseif (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 9999999) {
            $errors[] = 'Pay amounts must be positive numbers.';
            $in[$field] = null;
        } else {
            $in[$field] = round((float)$raw, 2);
        }
    }
    if ($in['pay_min'] !== null && $in['pay_max'] !== null && $in['pay_max'] < $in['pay_min']) {
        $errors[] = 'Maximum pay cannot be lower than minimum pay.';
    }

    $postedRaw = hj_input($_POST, 'posted_at', 10);
    if ($postedRaw === '') {
        $in['posted_at'] = null;
    } elseif ($job !== null && $job['posted_at'] && substr((string)$job['posted_at'], 0, 10) === $postedRaw) {
        $in['posted_at'] = $job['posted_at'];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedRaw) && strtotime($postedRaw . ' 12:00:00 UTC')) {
        $in['posted_at'] = $postedRaw . ' 12:00:00';
    } else {
        $errors[] = 'Posted date is not valid.';
        $in['posted_at'] = $job['posted_at'] ?? null;
    }
    if ($in['status'] === 'active' && $in['posted_at'] === null) {
        $in['posted_at'] = hj_now();
    }

    if ($in['title'] === '') {
        $errors[] = 'Job title is required.';
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,29}$/', $in['ref_code'])) {
        $errors[] = 'Reference code must be 2–30 letters, numbers or dashes (e.g. TECH-06).';
    } elseif ((int)hj_db_value('SELECT COUNT(*) FROM jobs WHERE ref_code = ? AND id <> ?', [$in['ref_code'], $id]) > 0) {
        $errors[] = 'Reference code ' . $in['ref_code'] . ' is already used by another job.';
    }
    if (!in_array($in['category_id'], $categoryIds, true)) {
        $errors[] = 'Choose a category.';
    }
    $enumChecks = [
        'work_arrangement' => hj_labels('work_arrangement'), 'applicant_region' => hj_labels('applicant_region'),
        'schedule' => hj_labels('schedule'), 'pay_currency' => hj_labels('currency'), 'pay_period' => hj_labels('pay_period'),
        'status' => hj_labels('job_status'),
    ];
    foreach ($enumChecks as $field => $allowed) {
        if (!array_key_exists($in[$field], $allowed)) {
            $errors[] = 'Invalid value for ' . str_replace('_', ' ', $field) . '.';
        }
    }
    if (!preg_match('/^[a-z0-9_]{1,60}$/', $in['icon'])) {
        $errors[] = 'Icon must be a Material Symbols name such as "work" or "support_agent".';
    }

    $values = array_merge($values, $in);

    if (!$errors) {
        try {
            hj_job_save($job, $id, $in, $editable);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            error_log('[hubjob] Job save conflict: ' . $e->getMessage());
            $errors[] = 'Reference code ' . $in['ref_code'] . ' was just taken by another job, or the category was removed. Check and save again.';
        }
    }
}

$isNew = $job === null;
$selectedTypes = hj_employment_types_array((string)$values['employment_types']);
$appCount = $isNew ? 0 : (int)hj_db_value('SELECT COUNT(*) FROM applications WHERE job_id = ? AND anonymised_at IS NULL', [$id]);

hj_admin_header($isNew ? 'Add job' : 'Edit job', 'jobs');
hj_admin_page_title(
    $isNew ? 'Add a job' : 'Edit ' . $job['ref_code'],
    $isNew ? ($duplicateOf ? 'Copy of ' . $duplicateOf['ref_code'] . '. ' : '') . 'New jobs start as drafts. Set the status to Active to publish.' : 'Last updated ' . hj_format_datetime($job['updated_at'] ?? $job['created_at']) . ' · ' . $appCount . ' application(s)',
    '<a class="hj-btn hj-btn-light" href="jobs.php"><span class="material-symbols-outlined text-[18px]">arrow_back</span>All jobs</a>'
    . (!$isNew ? '<a class="hj-btn hj-btn-light" href="job-edit.php?duplicate=' . (int)$id . '"><span class="material-symbols-outlined text-[18px]">content_copy</span>Duplicate</a>' : '')
    . (!$isNew && $job['status'] === 'active' ? '<a class="hj-btn hj-btn-light" target="_blank" href="../apply.html?job=' . (int)$id . '&amp;role=' . rawurlencode($job['title']) . '"><span class="material-symbols-outlined text-[18px]">open_in_new</span>Apply page</a>' : '')
);

if ($errors): ?>
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
    <p class="font-semibold mb-1">Please fix the following:</p>
    <ul class="list-disc pl-5"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" action="job-edit.php<?= $isNew ? '' : '?id=' . (int)$id ?>" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <?= hj_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="xl:col-span-2 space-y-6">
    <section class="hj-card p-5 sm:p-6 space-y-4">
      <h2 class="font-bold text-primary">Basics</h2>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
          <label class="hj-label" for="title">Job title *</label>
          <input class="hj-input" id="title" name="title" required maxlength="160" value="<?= e($values['title']) ?>">
        </div>
        <div>
          <label class="hj-label" for="ref_code">Reference code *</label>
          <input class="hj-input uppercase" id="ref_code" name="ref_code" required maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9-]{1,29}" value="<?= e($values['ref_code']) ?>">
          <p class="hj-help">Must be unique. Shown to applicants.</p>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="hj-label" for="company_name">Company name</label>
          <input class="hj-input" id="company_name" name="company_name" maxlength="160" value="<?= e($values['company_name']) ?>" placeholder="Leave empty if not disclosed">
          <p class="hj-help">Leave empty unless the employer agreed to be named.</p>
        </div>
        <div>
          <label class="hj-label" for="category_id">Category *</label>
          <select class="hj-input" id="category_id" name="category_id" required<?= $isNew ? ' data-ref-target="ref_code"' : '' ?>>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$values['category_id'] === (int)$c['id'] ? 'selected' : '' ?><?= $isNew ? ' data-ref-suggest="' . e(hj_suggest_ref_code(hj_ref_prefix_for($c['slug']))) . '"' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hj-help">Rename or add categories on the <a class="underline" href="categories.php">Categories</a> page.</p>
        </div>
      </div>
      <div>
        <span class="hj-label">Employment type</span>
        <div class="flex flex-wrap gap-4">
          <?php foreach (hj_labels('employment_types') as $key => $label): ?>
            <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="employment_types[]" value="<?= e($key) ?>" <?= in_array($key, $selectedTypes, true) ? 'checked' : '' ?>><?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
        <p class="hj-help">Leave all unticked if the type is not known; the site will say "unspecified".</p>
      </div>
      <div>
        <label class="hj-label" for="description">Description</label>
        <textarea class="hj-input" id="description" name="description" rows="6" maxlength="5000"><?= e($values['description']) ?></textarea>
        <p class="hj-help">Plain text. Only include details confirmed by the employer.</p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="hj-label" for="skills">Skills / tools</label>
          <input class="hj-input" id="skills" name="skills" maxlength="200" value="<?= e($values['skills']) ?>" placeholder="e.g. Python, Django">
        </div>
        <div>
          <label class="hj-label" for="badge">Badge text</label>
          <input class="hj-input" id="badge" name="badge" maxlength="60" value="<?= e($values['badge']) ?>" placeholder="e.g. Urgent">
          <p class="hj-help">Optional label on the job card. Don't use "Verified" here — tick Employer verified instead.</p>
        </div>
      </div>
    </section>

    <section class="hj-card p-5 sm:p-6 space-y-4">
      <h2 class="font-bold text-primary">Where and when</h2>
      <p class="text-sm text-text-muted -mt-2">These are separate filters on the jobs page. Use "Unspecified" when the employer hasn't confirmed a value.</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="hj-label" for="work_arrangement">Work arrangement</label>
          <?= hj_admin_select('work_arrangement', hj_labels('work_arrangement'), $values['work_arrangement'], 'id="work_arrangement"') ?>
        </div>
        <div>
          <label class="hj-label" for="applicant_region">Who can apply (region)</label>
          <?= hj_admin_select('applicant_region', hj_labels('applicant_region'), $values['applicant_region'], 'id="applicant_region"') ?>
        </div>
        <div>
          <label class="hj-label" for="schedule">Schedule</label>
          <?= hj_admin_select('schedule', hj_labels('schedule'), $values['schedule'], 'id="schedule"') ?>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="hj-label" for="location_text">Location text</label>
          <input class="hj-input" id="location_text" name="location_text" maxlength="160" value="<?= e($values['location_text']) ?>" placeholder="e.g. Remote (Worldwide)">
        </div>
        <div>
          <label class="hj-label" for="schedule_note">Schedule note</label>
          <input class="hj-input" id="schedule_note" name="schedule_note" maxlength="160" value="<?= e($values['schedule_note']) ?>" placeholder="e.g. Flexible shift">
        </div>
      </div>
    </section>

    <section class="hj-card p-5 sm:p-6 space-y-4">
      <h2 class="font-bold text-primary">Pay</h2>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
          <label class="hj-label" for="pay_min">Minimum</label>
          <input class="hj-input" id="pay_min" name="pay_min" inputmode="decimal" value="<?= e($values['pay_min'] !== null ? hj_format_amount($values['pay_min']) : '') ?>">
        </div>
        <div>
          <label class="hj-label" for="pay_max">Maximum</label>
          <input class="hj-input" id="pay_max" name="pay_max" inputmode="decimal" value="<?= e($values['pay_max'] !== null ? hj_format_amount($values['pay_max']) : '') ?>">
        </div>
        <div>
          <label class="hj-label" for="pay_currency">Currency</label>
          <?= hj_admin_select('pay_currency', hj_labels('currency'), $values['pay_currency'], 'id="pay_currency"') ?>
        </div>
        <div>
          <label class="hj-label" for="pay_period">Period</label>
          <?= hj_admin_select('pay_period', hj_labels('pay_period'), $values['pay_period'], 'id="pay_period"') ?>
        </div>
      </div>
      <p class="hj-help">Leave both empty if pay is not disclosed. The pay-band filter on the jobs page only uses USD amounts.</p>
    </section>

    <?php if (!$isNew && !empty($job['source_text'])): ?>
      <section class="hj-card p-5 sm:p-6">
        <h2 class="font-bold text-primary mb-2">Original listing (imported)</h2>
        <p class="text-xs text-text-muted mb-2">Exactly what the old website showed before the move to the database. Read only.</p>
        <pre class="text-xs bg-slate-50 border border-slate-200 rounded-lg p-3 whitespace-pre-wrap"><?= e($job['source_text']) ?></pre>
      </section>
    <?php endif; ?>
  </div>

  <div class="space-y-6">
    <section class="hj-card p-5 sm:p-6 space-y-4">
      <h2 class="font-bold text-primary">Publishing</h2>
      <div>
        <label class="hj-label" for="status">Status</label>
        <?= hj_admin_select('status', hj_labels('job_status'), $values['status'], 'id="status"') ?>
        <p class="hj-help">Only Active jobs appear on the website.</p>
      </div>
      <label class="flex items-start gap-2 text-sm"><input class="mt-0.5 rounded border-slate-300 text-primary" type="checkbox" name="is_featured" value="1" <?= (int)$values['is_featured'] ? 'checked' : '' ?>><span><strong>Featured</strong><br><span class="text-text-muted">Show on the homepage (up to 6 are shown).</span></span></label>
      <label class="flex items-start gap-2 text-sm"><input class="mt-0.5 rounded border-slate-300 text-primary" type="checkbox" name="employer_verified" value="1" <?= (int)$values['employer_verified'] ? 'checked' : '' ?>><span><strong>Employer verified</strong><br><span class="text-text-muted">Tick only after you have checked this employer. Shows a "Verified" badge on the job.</span></span></label>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="hj-label" for="posted_at">Posted date</label>
          <input class="hj-input" id="posted_at" name="posted_at" type="date" value="<?= e($values['posted_at'] ? substr((string)$values['posted_at'], 0, 10) : '') ?>">
        </div>
        <div>
          <label class="hj-label" for="sort_order">Sort order</label>
          <input class="hj-input" id="sort_order" name="sort_order" type="number" value="<?= (int)$values['sort_order'] ?>">
        </div>
      </div>
      <div>
        <label class="hj-label" for="icon">Icon</label>
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-[28px] w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center" id="icon-preview">work</span>
          <input class="hj-input" id="icon" name="icon" maxlength="60" value="<?= e($values['icon']) ?>" data-icon-preview="icon-preview">
        </div>
        <p class="hj-help">Any <a class="underline" href="https://fonts.google.com/icons" target="_blank" rel="noopener">Material Symbols</a> name.</p>
      </div>
      <button class="hj-btn hj-btn-accent w-full" type="submit" name="action" value="save"><span class="material-symbols-outlined text-[18px]">save</span><?= $isNew ? 'Create job' : 'Save changes' ?></button>
    </section>

    <?php if (!$isNew && hj_is_owner($admin)): ?>
      <section class="hj-card p-5 sm:p-6">
        <h2 class="font-bold text-primary mb-2">Delete job</h2>
        <p class="text-sm text-text-muted mb-3">Usually it's better to set the status to Closed. Deleting removes the job permanently; its applications stay, with the role title.</p>
        <button class="hj-btn hj-btn-danger w-full" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete <?= e($job['ref_code']) ?> permanently?">Delete job</button>
      </section>
    <?php endif; ?>
  </div>
</form>
<?php
hj_admin_footer();
