<?php
/**
 * Retention: applications are anonymised and their CVs deleted 12 months (setting retention_months)
 * after the last update, unless an admin ticked "Keep".
 */

/** Remove personal data from one application and delete its CV. Keeps the row for statistics. */
function hj_anonymise_application(int $id, string $reason): bool
{
    $app = hj_db_one('SELECT id, cv_stored_name, anonymised_at FROM applications WHERE id = ?', [$id]);
    if (!$app || $app['anonymised_at']) {
        return false;
    }
    hj_delete_cv_file($app['cv_stored_name']);
    hj_db_exec(
        'UPDATE applications SET fullname = NULL, email = NULL, phone = NULL, is_adult = NULL,
            residence_country = NULL, residence_country_code = NULL, right_to_work = NULL, work_type = NULL,
            cv_stored_name = NULL, cv_original_name = NULL, cv_mime = NULL, cv_size = NULL,
            admin_notes = NULL, last_mail_error = NULL, utm_source = NULL, utm_medium = NULL, utm_campaign = NULL,
            utm_term = NULL, utm_content = NULL, utm_landing = NULL, source_page = NULL, ip_hash = NULL,
            keep_flag = 0, retain_until = NULL, anonymised_at = ?, updated_at = ?
         WHERE id = ?',
        [hj_now(), hj_now(), $id]
    );
    hj_audit('application_anonymised', 'application', $id, $reason);
    return true;
}

/**
 * Anonymise everything past its retention date and clear old rate-limit and login records.
 * Runs at most once a day unless $force is true. Returns ['ran' => bool, 'anonymised' => int].
 */
function hj_run_retention_purge(bool $force = false): array
{
    if (!hj_schema_ready()) {
        return ['ran' => false, 'anonymised' => 0];
    }
    $last = hj_setting('last_purge_at');
    if (!$force && $last !== '' && strtotime($last . ' UTC') > time() - 86400) {
        return ['ran' => false, 'anonymised' => 0];
    }
    hj_setting_set('last_purge_at', hj_now());

    $count = 0;
    $due = hj_db_all(
        'SELECT id FROM applications
         WHERE anonymised_at IS NULL AND keep_flag = 0 AND retain_until IS NOT NULL AND retain_until < ?
         ORDER BY id LIMIT 500',
        [hj_now()]
    );
    foreach ($due as $row) {
        if (hj_anonymise_application((int)$row['id'], 'Retention period ended')) {
            $count++;
        }
    }

    $cutoff = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
    hj_db_exec('DELETE FROM rate_limits WHERE created_at < ?', [$cutoff]);
    hj_db_exec('DELETE FROM login_attempts WHERE attempted_at < ?', [$cutoff]);

    if ($count > 0) {
        hj_audit('retention_purge', 'application', null, $count . ' application(s) anonymised');
    }
    return ['ran' => true, 'anonymised' => $count];
}

/** Push the retention date forward after an admin changes an application. */
function hj_touch_retention(int $id): void
{
    hj_db_exec('UPDATE applications SET retain_until = ?, updated_at = ? WHERE id = ? AND anonymised_at IS NULL', [hj_retain_until(), hj_now(), $id]);
}
