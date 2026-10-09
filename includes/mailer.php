<?php

use PHPMailer\PHPMailer\PHPMailer;

function hj_load_phpmailer(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $dir = HJ_ROOT . '/PHPMailer-7.0.1/src/';
    require_once $dir . 'Exception.php';
    require_once $dir . 'PHPMailer.php';
    require_once $dir . 'SMTP.php';
    $loaded = true;
}

/**
 * SMTP settings. config.php 'smtp_override' (if it has a host) wins over Admin -> Settings,
 * so credentials can be kept out of the database entirely.
 */
function hj_mail_config(): array
{
    $override = hj_config('smtp_override');
    if (is_array($override) && trim((string)($override['host'] ?? '')) !== '') {
        return [
            'source'     => 'config',
            'host'       => trim((string)$override['host']),
            'port'       => (int)($override['port'] ?? 587),
            'username'   => (string)($override['username'] ?? ''),
            'password'   => (string)($override['password'] ?? ''),
            'encryption' => (string)($override['encryption'] ?? 'tls'),
            'from_email' => (string)($override['from_email'] ?? hj_setting('mail_from_email')),
            'from_name'  => (string)($override['from_name'] ?? hj_setting('mail_from_name')),
        ];
    }
    return [
        'source'     => 'settings',
        'host'       => trim(hj_setting('smtp_host')),
        'port'       => (int)hj_setting('smtp_port', '587'),
        'username'   => hj_setting('smtp_username'),
        'password'   => hj_decrypt(hj_setting('smtp_password_enc')),
        'encryption' => hj_setting('smtp_encryption', 'tls'),
        'from_email' => hj_setting('mail_from_email'),
        'from_name'  => hj_setting('mail_from_name'),
    ];
}

function hj_mail_configured(): bool
{
    return hj_mail_config()['host'] !== '';
}

function hj_new_mailer(): PHPMailer
{
    hj_load_phpmailer();
    $c = hj_mail_config();
    if ($c['host'] === '') {
        throw new RuntimeException('SMTP is not set up yet. Add the SMTP details in Admin → Settings → Email delivery.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $c['host'];
    $mail->Port = $c['port'] > 0 ? $c['port'] : 587;
    $mail->SMTPAuth = $c['username'] !== '';
    if ($mail->SMTPAuth) {
        $mail->Username = $c['username'];
        $mail->Password = $c['password'];
    }
    if ($c['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($c['encryption'] === 'none') {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Timeout = 20;
    $mail->CharSet = 'UTF-8';

    $fromEmail = trim($c['from_email']) !== '' ? trim($c['from_email']) : trim(hj_setting('contact_email'));
    $fromName = trim($c['from_name']) !== '' ? trim($c['from_name']) : hj_setting('site_name');
    $mail->setFrom($fromEmail, $fromName);
    return $mail;
}

/** Wrap content in the branded email layout. $innerHtml must already be escaped. */
function hj_mail_layout(string $heading, string $innerHtml): string
{
    $site = e(hj_setting('site_name'));
    return '<!doctype html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;line-height:1.6">'
        . '<div style="max-width:600px;margin:0 auto;padding:24px 12px">'
        . '<div style="background:#070235;color:#ffffff;padding:20px 24px;border-radius:12px 12px 0 0">'
        . '<div style="font-size:13px;opacity:.75">' . $site . '</div>'
        . '<h1 style="margin:4px 0 0;font-size:20px">' . e($heading) . '</h1></div>'
        . '<div style="background:#ffffff;padding:24px;border-radius:0 0 12px 12px;border:1px solid #e2e8f0;border-top:0">'
        . $innerHtml
        . '</div></div></body></html>';
}

/**
 * Send one email. Throws on failure.
 * $opts: reply_to => [email, name], attachments => [[path, name]]
 */
function hj_send_mail(string $to, string $subject, string $html, string $text, array $opts = []): void
{
    $mail = hj_new_mailer();
    $mail->addAddress($to);
    if (!empty($opts['reply_to'][0]) && filter_var($opts['reply_to'][0], FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($opts['reply_to'][0], (string)($opts['reply_to'][1] ?? ''));
    }
    foreach ($opts['attachments'] ?? [] as $attachment) {
        if (is_file($attachment[0])) {
            $mail->addAttachment($attachment[0], $attachment[1]);
        }
    }
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    try {
        $mail->send();
    } catch (Throwable $e) {
        $info = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        throw new RuntimeException(hj_mail_error_summary($info));
    }
}

/** Short, credential-free error text for storing on the application and showing in admin. */
function hj_mail_error_summary(string $error): string
{
    $error = preg_replace('/\s+/', ' ', $error);
    return hj_substr(trim((string)$error), 0, 300);
}

function hj_application_rows(array $app): array
{
    $rtw = hj_labels('right_to_work');
    $rows = [
        'Name'                 => $app['fullname'],
        'Email'                => $app['email'],
        'Phone'                => $app['phone'],
        'Role'                 => $app['role_title'] ?: 'Not specified',
        'Job reference'        => $app['job_ref'] ?? '',
        'Work type preference' => $app['work_type'],
        '18 or over'           => $app['is_adult'] === null ? 'Not given' : ((int)$app['is_adult'] ? 'Yes' : 'No'),
        'Country of residence' => $app['residence_country'] ?: 'Not given',
        'Right to work UK/EU'  => $app['right_to_work'] ? ($rtw[$app['right_to_work']] ?? $app['right_to_work']) : 'Not given',
        'CV'                   => $app['cv_original_name'] ?: 'No CV uploaded',
        'Source'               => trim(implode(' / ', array_filter([$app['utm_source'], $app['utm_medium'], $app['utm_campaign']]))),
        'Submitted'            => hj_format_datetime($app['created_at']) . ' (UK time)',
    ];
    return array_filter($rows, function ($v) {
        return $v !== null && $v !== '';
    });
}

/** Admin notification for a new application. Throws on failure. */
function hj_send_application_notification(array $app): void
{
    $to = hj_notification_email();
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('No valid notification email is set in Admin → Settings.');
    }

    $rowsHtml = '';
    $rowsText = '';
    foreach (hj_application_rows($app) as $label => $value) {
        $rowsHtml .= '<tr><td style="padding:6px 12px 6px 0;color:#64748b;font-size:13px;vertical-align:top;white-space:nowrap">' . e($label)
            . '</td><td style="padding:6px 0;font-size:14px">' . e($value) . '</td></tr>';
        $rowsText .= $label . ': ' . $value . "\n";
    }
    $link = hj_base_url() !== '' ? hj_base_url() . '/admin/application.php?id=' . (int)$app['id'] : '';
    $attachCv = hj_setting('attach_cv_to_notification') === '1' && $app['cv_stored_name'];

    $html = '<table style="width:100%;border-collapse:collapse">' . $rowsHtml . '</table>';
    if ($link !== '') {
        $html .= '<p style="margin-top:20px"><a href="' . e($link) . '" style="display:inline-block;background:#070235;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Open in admin</a></p>';
    }
    if ($app['cv_stored_name'] && !$attachCv) {
        $html .= '<p style="font-size:13px;color:#64748b">The CV is stored securely. Sign in to the admin area to view or download it.</p>';
    }

    $subject = 'New application: ' . $app['fullname'] . ($app['role_title'] ? ' (' . $app['role_title'] . ')' : '');
    $opts = ['reply_to' => [$app['email'], $app['fullname']]];
    if ($attachCv) {
        $opts['attachments'] = [[hj_cv_dir() . '/' . $app['cv_stored_name'], $app['cv_original_name']]];
    }
    hj_send_mail(
        $to,
        $subject,
        hj_mail_layout('New application received', $html),
        "New application received\n\n" . $rowsText . ($link !== '' ? "\nOpen in admin: " . $link . "\n" : ''),
        $opts
    );
}

function hj_confirmation_placeholders(array $app): array
{
    $role = trim((string)$app['role_title']);
    return [
        '{name}'          => $app['fullname'],
        '{role}'          => $role,
        '{role_phrase}'   => $role !== '' ? ' for the ' . $role . ' role' : '',
        '{site_name}'     => hj_setting('site_name'),
        '{response_time}' => hj_setting('response_time'),
        '{contact_email}' => hj_setting('contact_email'),
    ];
}

/** Confirmation to the applicant. Throws on failure. */
function hj_send_applicant_confirmation(array $app): void
{
    $vars = hj_confirmation_placeholders($app);
    $subject = strtr(hj_setting('confirmation_subject'), $vars);
    $body = strtr(hj_setting('confirmation_body'), $vars);
    $html = '<div style="font-size:15px">' . nl2br(e($body)) . '</div>';
    hj_send_mail(
        $app['email'],
        $subject,
        hj_mail_layout('Application received', $html),
        $body,
        ['reply_to' => [hj_setting('contact_email'), hj_setting('site_name')]]
    );
}

function hj_application_for_mail(int $id): ?array
{
    return hj_db_one(
        'SELECT a.*, j.ref_code AS job_ref FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.id = ?',
        [$id]
    );
}

/**
 * Send the notification and the confirmation independently and record each status.
 * $which: 'both', 'notify' or 'confirm'. Returns ['notify' => status|null, 'confirm' => status|null, 'errors' => []].
 */
function hj_dispatch_application_emails(int $id, string $which = 'both'): array
{
    $result = ['notify' => null, 'confirm' => null, 'errors' => []];
    $app = hj_application_for_mail($id);
    if (!$app || $app['anonymised_at']) {
        $result['errors'][] = 'Application not found or already deleted.';
        return $result;
    }

    if ($which === 'both' || $which === 'notify') {
        try {
            hj_send_application_notification($app);
            $result['notify'] = 'sent';
        } catch (Throwable $e) {
            $result['notify'] = 'failed';
            $result['errors'][] = 'Notification: ' . hj_mail_error_summary($e->getMessage());
        }
    }

    if ($which === 'both' || $which === 'confirm') {
        if (hj_setting('confirmation_enabled') !== '1') {
            $result['confirm'] = 'disabled';
        } else {
            try {
                hj_send_applicant_confirmation($app);
                $result['confirm'] = 'sent';
            } catch (Throwable $e) {
                $result['confirm'] = 'failed';
                $result['errors'][] = 'Confirmation: ' . hj_mail_error_summary($e->getMessage());
            }
        }
    }

    $sets = ['updated_at = ?'];
    $params = [hj_now()];
    if ($result['notify'] !== null) {
        $sets[] = 'notify_status = ?';
        $params[] = $result['notify'];
    }
    if ($result['confirm'] !== null) {
        $sets[] = 'confirm_status = ?';
        $params[] = $result['confirm'];
    }
    $sets[] = 'last_mail_error = ?';
    $params[] = $result['errors'] ? hj_substr(implode(' | ', $result['errors']), 0, 500) : null;
    $params[] = $id;
    hj_db_exec('UPDATE applications SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

    foreach ($result['errors'] as $error) {
        error_log('[hubjob] Application #' . $id . ' email failed: ' . $error);
    }
    return $result;
}

/** Test sends from Admin -> Settings. Returns null on success or an error message. */
function hj_send_test_notification(): ?string
{
    $fake = [
        'id' => 0, 'fullname' => 'Test Applicant', 'email' => hj_setting('contact_email'), 'phone' => '+44 0000 000000',
        'role_title' => 'Test role', 'job_ref' => 'TEST-00', 'work_type' => 'Full-Time', 'is_adult' => 1,
        'residence_country' => 'United Kingdom', 'right_to_work' => 'yes', 'cv_original_name' => null,
        'cv_stored_name' => null, 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'created_at' => hj_now(),
    ];
    try {
        hj_send_application_notification($fake);
        return null;
    } catch (Throwable $e) {
        return hj_mail_error_summary($e->getMessage());
    }
}

function hj_send_test_confirmation(string $to): ?string
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address to send the test confirmation to.';
    }
    $fake = ['fullname' => 'Test Applicant', 'email' => $to, 'role_title' => 'Content Writer'];
    try {
        hj_send_applicant_confirmation($fake);
        return null;
    } catch (Throwable $e) {
        return hj_mail_error_summary($e->getMessage());
    }
}
