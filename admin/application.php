<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$app = $id > 0 ? hj_db_one('SELECT a.*, j.ref_code, j.title AS job_title, j.status AS job_status FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.id = ?', [$id]) : null;
if ($app === null) {
    hj_flash('error', 'That application no longer exists.');
    hj_redirect('applications.php');
}
$statusLabels = hj_labels('app_status');

if (hj_is_post()) {
    hj_csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $self = 'application.php?id=' . $id;

    if ($app['anonymised_at'] && $action !== 'delete') {
        hj_flash('error', 'This application has already been anonymised.');
        hj_redirect($self);
    }

    if ($action === 'update') {
        $status = (string)($_POST['status'] ?? $app['status']);
        if (!array_key_exists($status, $statusLabels)) {
            $status = $app['status'];
        }
        $notes = hj_clean_text(str_replace("\r\n", "\n", (string)($_POST['admin_notes'] ?? '')), 5000);
        $keep = !empty($_POST['keep_flag']) ? 1 : 0;
        hj_db_exec('UPDATE applications SET status = ?, admin_notes = ?, keep_flag = ? WHERE id = ?', [$status, $notes !== '' ? $notes : null, $keep, $id]);
        hj_touch_retention($id);
        $changes = [];
        if ($status !== $app['status']) {
            $changes[] = 'status ' . $app['status'] . ' → ' . $status;
        }
        if ((string)$app['admin_notes'] !== $notes) {
            $changes[] = 'notes edited';
        }
        if ((int)$app['keep_flag'] !== $keep) {
            $changes[] = $keep ? 'marked Keep' : 'Keep removed';
        }
        hj_audit('application_updated', 'application', $id, $changes ? implode('; ', $changes) : 'saved without changes');
        hj_flash('success', 'Application saved.');
        hj_redirect($self);
    }

    if ($action === 'resend_notify' || $action === 'resend_confirm') {
        $which = $action === 'resend_notify' ? 'notify' : 'confirm';
        $result = hj_dispatch_application_emails($id, $which);
        $status = $result[$which];
        hj_audit('application_email_resent', 'application', $id, $which . ': ' . $status);
        if ($status === 'sent') {
            hj_flash('success', $which === 'notify' ? 'Notification email sent to the team.' : 'Confirmation email sent to the applicant.');
        } elseif ($status === 'disabled') {
            hj_flash('warning', 'Applicant confirmation emails are switched off in Settings.');
        } else {
            hj_flash('error', 'Email failed: ' . implode(' ', $result['errors']));
        }
        hj_redirect($self);
    }

    if ($action === 'anonymise') {
        hj_anonymise_application($id, 'Removed by admin');
        hj_flash('success', 'Personal details and the CV were deleted. The anonymous record is kept for statistics.');
        hj_redirect($self);
    }

    if ($action === 'delete') {
        if (!hj_is_owner($admin)) {
            hj_flash('error', 'Only an owner can delete applications completely. Use "Delete personal data" instead.');
            hj_redirect($self);
        }
        hj_delete_cv_file($app['cv_stored_name']);
        hj_db_exec('DELETE FROM applications WHERE id = ?', [$id]);
        hj_audit('application_deleted', 'application', $id, 'Deleted completely by owner');
        hj_flash('success', 'Application #' . $id . ' deleted completely.');
        hj_redirect('applications.php');
    }

    hj_redirect($self);
}

$history = hj_db_all(
    'SELECT l.action, l.details, l.created_at, l.admin_id, ad.name AS admin_name
     FROM audit_log l LEFT JOIN admins ad ON ad.id = l.admin_id
     WHERE l.entity_type = ? AND l.entity_id = ? ORDER BY l.id DESC LIMIT 30',
    ['application', $id]
);

$anonymised = (bool)$app['anonymised_at'];
$title = $anonymised ? 'Application #' . $id . ' (anonymised)' : (string)$app['fullname'];
$rtw = hj_labels('right_to_work');

function hj_detail_row(string $label, string $valueHtml): void
{
    echo '<div class="grid grid-cols-3 gap-3 py-2.5 border-t border-slate-100 first:border-t-0 text-sm"><dt class="text-text-subtle">' . e($label) . '</dt><dd class="col-span-2 text-text-main break-words">' . $valueHtml . '</dd></div>';
}

hj_admin_header('Application', 'applications');
hj_admin_page_title(
    $title,
    'Application #' . $id . ' · received ' . hj_format_datetime($app['created_at']),
    '<a class="hj-btn hj-btn-light" href="applications.php"><span class="material-symbols-outlined text-[18px]">arrow_back</span>All applications</a>'
);
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <div class="xl:col-span-2 space-y-6">
    <section class="hj-card p-5 sm:p-6">
      <h2 class="font-bold text-primary mb-3">Applicant</h2>
      <?php if ($anonymised): ?>
        <p class="text-sm text-text-muted">Personal details and the CV were removed on <?= e(hj_format_datetime($app['anonymised_at'])) ?>.</p>
      <?php else: ?>
      <dl>
        <?php
        hj_detail_row('Name', e($app['fullname']));
        hj_detail_row('Email', '<a class="text-primary underline" href="mailto:' . e($app['email']) . '">' . e($app['email']) . '</a>');
        hj_detail_row('Phone / WhatsApp', e($app['phone']));
        hj_detail_row('Country of residence', $app['residence_country'] ? e($app['residence_country']) . ($app['residence_country_code'] ? ' (' . e($app['residence_country_code']) . ')' : '') : '<em class="text-text-subtle">Not given</em>');
        hj_detail_row('Right to work UK/EU', $app['right_to_work'] ? e($rtw[$app['right_to_work']] ?? $app['right_to_work']) : '<em class="text-text-subtle">Prefer not to say</em>');
        hj_detail_row('18 or over', (int)$app['is_adult'] === 1 ? 'Confirmed' : '<em class="text-text-subtle">Not confirmed</em>');
        hj_detail_row('Work type', e($app['work_type']));
        hj_detail_row('Consent to privacy notice', $app['consent_at'] ? e(hj_format_datetime($app['consent_at'])) : '<em class="text-text-subtle">Not recorded (older form)</em>');
        ?>
      </dl>
      <?php endif; ?>
    </section>

    <section class="hj-card p-5 sm:p-6">
      <h2 class="font-bold text-primary mb-3">Role</h2>
      <dl>
        <?php
        hj_detail_row('Role applied for', e($app['role_title'] ?: 'General application'));
        hj_detail_row('Linked job', $app['job_id'] ? '<a class="text-primary underline" href="job-edit.php?id=' . (int)$app['job_id'] . '">' . e($app['ref_code'] . ' — ' . $app['job_title']) . '</a> ' . hj_admin_status_badge('job_status', $app['job_status']) : '<em class="text-text-subtle">None</em>');
        hj_detail_row('Form', e(($app['source_page'] ?: 'unknown page') . ' · version ' . $app['form_version']));
        $utm = array_filter([
            'source' => $app['utm_source'], 'medium' => $app['utm_medium'], 'campaign' => $app['utm_campaign'],
            'term' => $app['utm_term'], 'content' => $app['utm_content'],
        ], function ($v) { return $v !== null && $v !== ''; });
        if ($utm) {
            hj_detail_row('Campaign', e(implode(' · ', array_map(function ($k, $v) { return $k . ': ' . $v; }, array_keys($utm), $utm))));
        }
        if ($app['utm_landing']) {
            hj_detail_row('Landing page', e($app['utm_landing']));
        }
        ?>
      </dl>
    </section>

    <section class="hj-card p-5 sm:p-6">
      <h2 class="font-bold text-primary mb-3">CV</h2>
      <?php if ($app['cv_stored_name']): ?>
        <div class="flex flex-wrap items-center gap-3">
          <span class="material-symbols-outlined text-primary text-[32px]">description</span>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate"><?= e($app['cv_original_name']) ?></p>
            <p class="text-xs text-text-subtle"><?= e(hj_admin_format_bytes($app['cv_size'])) ?> · <?= e($app['cv_mime']) ?></p>
          </div>
          <a class="hj-btn hj-btn-light hj-btn-sm" href="cv.php?id=<?= $id ?>" target="_blank"><span class="material-symbols-outlined text-[18px]">visibility</span>View</a>
          <a class="hj-btn hj-btn-primary hj-btn-sm" href="cv.php?id=<?= $id ?>&amp;download=1"><span class="material-symbols-outlined text-[18px]">download</span>Download</a>
        </div>
        <p class="hj-help mt-2">PDF and text files open in the browser; Word and RTF files download. Every view is recorded.</p>
      <?php else: ?>
        <p class="text-sm text-text-muted"><?= $anonymised ? 'Deleted with the personal data.' : 'No CV was uploaded.' ?></p>
      <?php endif; ?>
    </section>

    <section class="hj-card p-5 sm:p-6">
      <h2 class="font-bold text-primary mb-3">History</h2>
      <?php if (!$history): ?>
        <p class="text-sm text-text-muted">No admin activity yet.</p>
      <?php else: ?>
        <ul class="space-y-2 text-sm">
          <?php foreach ($history as $h): ?>
            <li class="flex flex-wrap gap-x-2"><span class="text-text-subtle whitespace-nowrap"><?= e(hj_format_datetime($h['created_at'])) ?></span><span class="font-semibold"><?= e($h['admin_name'] ?: ($h['admin_id'] ? 'Removed admin #' . $h['admin_id'] : 'System')) ?></span><span><?= e(str_replace('_', ' ', $h['action'])) ?><?= $h['details'] ? ' — ' . e($h['details']) : '' ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="space-y-6">
    <?php if (!$anonymised): ?>
    <form class="hj-card p-5 sm:p-6 space-y-4" method="post" action="application.php?id=<?= $id ?>">
      <?= hj_csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <h2 class="font-bold text-primary">Review</h2>
      <div>
        <label class="hj-label" for="status">Status</label>
        <?= hj_admin_select('status', $statusLabels, $app['status'], 'id="status"') ?>
      </div>
      <div>
        <label class="hj-label" for="admin_notes">Internal notes</label>
        <textarea class="hj-input" id="admin_notes" name="admin_notes" rows="5" maxlength="5000"><?= e($app['admin_notes']) ?></textarea>
        <p class="hj-help">Never shown to the applicant, but included if they ask for a copy of their data.</p>
      </div>
      <label class="flex items-start gap-2 text-sm"><input class="mt-0.5 rounded border-slate-300 text-primary" type="checkbox" name="keep_flag" value="1" <?= (int)$app['keep_flag'] ? 'checked' : '' ?>><span><strong>Keep</strong> — don't delete automatically (e.g. active hiring process).</span></label>
      <p class="text-xs text-text-subtle">
        <?php if ((int)$app['keep_flag']): ?>
          Automatic deletion is paused while Keep is ticked.
        <?php else: ?>
          Will be deleted automatically after <?= e(hj_format_datetime($app['retain_until'], 'j M Y')) ?>. Saving pushes this date back.
        <?php endif; ?>
      </p>
      <button class="hj-btn hj-btn-accent w-full" type="submit">Save</button>
    </form>

    <section class="hj-card p-5 sm:p-6 space-y-3">
      <h2 class="font-bold text-primary">Emails</h2>
      <div class="flex items-center justify-between text-sm"><span>Team notification</span><?= hj_admin_status_badge('mail_status', $app['notify_status']) ?></div>
      <div class="flex items-center justify-between text-sm"><span>Applicant confirmation</span><?= hj_admin_status_badge('mail_status', $app['confirm_status']) ?></div>
      <?php if ($app['last_mail_error']): ?>
        <p class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg p-2"><?= e($app['last_mail_error']) ?></p>
      <?php endif; ?>
      <form class="flex flex-wrap gap-2" method="post" action="application.php?id=<?= $id ?>">
        <?= hj_csrf_field() ?>
        <button class="hj-btn hj-btn-light hj-btn-sm" name="action" value="resend_notify" type="submit">Resend notification</button>
        <button class="hj-btn hj-btn-light hj-btn-sm" name="action" value="resend_confirm" type="submit" data-confirm="Send the confirmation email to <?= e($app['email']) ?> again?">Resend confirmation</button>
      </form>
    </section>
    <?php endif; ?>

    <section class="hj-card p-5 sm:p-6 space-y-3">
      <h2 class="font-bold text-primary">Delete</h2>
      <?php if (!$anonymised): ?>
        <p class="text-sm text-text-muted">Use this for deletion requests. Removes the name, contact details, notes and CV; keeps an anonymous record (date, role, status).</p>
        <form method="post" action="application.php?id=<?= $id ?>" data-confirm="Delete this applicant's personal data and CV? This cannot be undone.">
          <?= hj_csrf_field() ?>
          <input type="hidden" name="action" value="anonymise">
          <button class="hj-btn hj-btn-danger w-full" type="submit">Delete personal data</button>
        </form>
      <?php endif; ?>
      <?php if (hj_is_owner($admin)): ?>
        <form method="post" action="application.php?id=<?= $id ?>" data-confirm="Delete application #<?= $id ?> completely? This cannot be undone.">
          <?= hj_csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="hj-btn hj-btn-danger w-full" type="submit">Delete record completely</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
hj_admin_footer();
