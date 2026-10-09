<?php
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin('owner');
const HJ_DEFAULT_LOGO = 'static/images/logo22.png';
const HJ_LOGO_MAX_BYTES = 1048576;

/** Save the given key => value pairs; returns the keys whose value changed. */
function hj_save_settings(array $values): array
{
    $changed = [];
    foreach ($values as $key => $value) {
        if (hj_setting($key) !== (string)$value) {
            hj_setting_set($key, (string)$value);
            $changed[] = $key;
        }
    }
    return $changed;
}

function hj_store_logo(array $file): string
{
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || (int)$file['size'] > HJ_LOGO_MAX_BYTES) {
        throw new RuntimeException('The logo must be under 1MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The logo could not be uploaded. Please try again.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'];
    if (defined('IMAGETYPE_WEBP')) {
        $types[IMAGETYPE_WEBP] = 'webp';
    }
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('The logo must be a PNG, JPG or WebP image.');
    }
    if ($info[0] > 2000 || $info[1] > 2000) {
        throw new RuntimeException('The logo must be at most 2000×2000 pixels.');
    }
    $dir = HJ_ROOT . '/uploads';
    if (!hj_ensure_dir($dir, 0755) || !is_writable($dir)) {
        throw new RuntimeException('The uploads folder is not writable.');
    }
    $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('The logo could not be saved.');
    }
    @chmod($dir . '/' . $name, 0644);
    return 'uploads/' . $name;
}

function hj_delete_uploaded_logo(string $path): void
{
    if (preg_match('#^uploads/logo-[a-f0-9]{12}\.(png|jpg|webp)$#', $path) && is_file(HJ_ROOT . '/' . $path)) {
        @unlink(HJ_ROOT . '/' . $path);
    }
}

function hj_valid_url(string $url): bool
{
    return (bool)preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

$mailConfig = hj_mail_config();
$smtpFromConfig = $mailConfig['source'] === 'config';
$errors = [];

if (hj_is_post()) {
    hj_csrf_verify();
    $section = (string)($_POST['section'] ?? '');
    $changed = [];
    $publicChanged = false;

    if ($section === 'site') {
        $v = [];
        foreach (['site_name' => 120, 'contact_phone' => 40, 'whatsapp_number' => 40, 'address_line1' => 160, 'address_city' => 80,
                  'address_region' => 80, 'address_postcode' => 20, 'address_country' => 80, 'office_hours' => 120,
                  'response_time' => 60, 'response_time_short' => 20, 'copyright_name' => 120] as $key => $max) {
            $v[$key] = hj_input($_POST, $key, $max);
        }
        $v['contact_email'] = strtolower(hj_input($_POST, 'contact_email', 190));
        $v['map_url'] = hj_input($_POST, 'map_url', 500);
        if ($v['site_name'] === '') {
            $errors[] = 'Site name is required.';
        }
        if (!filter_var($v['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid public contact email.';
        }
        if ($v['map_url'] !== '' && !hj_valid_url($v['map_url'])) {
            $errors[] = 'The map link must start with https://';
        }
        if ($v['whatsapp_number'] !== '' && !preg_match('/^\+?[0-9 ()-]{6,40}$/', $v['whatsapp_number'])) {
            $errors[] = 'WhatsApp number may only contain digits, spaces and +.';
        }

        $oldLogo = hj_setting('logo_path');
        if (!$errors && !empty($_POST['logo_reset'])) {
            $v['logo_path'] = HJ_DEFAULT_LOGO;
        } elseif (!$errors && isset($_FILES['logo']) && is_array($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $v['logo_path'] = hj_store_logo($_FILES['logo']);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!$errors) {
            $changed = hj_save_settings($v);
            if (isset($v['logo_path']) && $v['logo_path'] !== $oldLogo) {
                hj_delete_uploaded_logo($oldLogo);
            }
            $publicChanged = true;
        }
    } elseif ($section === 'email') {
        $v = [
            'notification_email'        => strtolower(hj_input($_POST, 'notification_email', 190)),
            'attach_cv_to_notification' => !empty($_POST['attach_cv_to_notification']) ? '1' : '0',
        ];
        if ($v['notification_email'] !== '' && !filter_var($v['notification_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid notification email, or leave it empty to use the public contact email.';
        }
        if (!$smtpFromConfig) {
            $v['smtp_host'] = hj_input($_POST, 'smtp_host', 190);
            $v['smtp_port'] = (string)(int)($_POST['smtp_port'] ?? 587);
            $v['smtp_username'] = hj_input($_POST, 'smtp_username', 190);
            $v['smtp_encryption'] = in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls';
            $v['mail_from_email'] = strtolower(hj_input($_POST, 'mail_from_email', 190));
            $v['mail_from_name'] = hj_input($_POST, 'mail_from_name', 120);
            if ($v['smtp_host'] !== '' && !preg_match('/^[A-Za-z0-9.-]+$/', $v['smtp_host'])) {
                $errors[] = 'SMTP host should be a server name like smtp.example.com.';
            }
            if ((int)$v['smtp_port'] < 1 || (int)$v['smtp_port'] > 65535) {
                $errors[] = 'SMTP port must be between 1 and 65535.';
            }
            if (!filter_var($v['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid "from" email address.';
            }
            $newPassword = is_string($_POST['smtp_password'] ?? null) ? $_POST['smtp_password'] : '';
            if (!empty($_POST['smtp_password_clear'])) {
                $v['smtp_password_enc'] = '';
            } elseif ($newPassword !== '') {
                try {
                    $v['smtp_password_enc'] = hj_encrypt($newPassword);
                } catch (Throwable $e) {
                    $errors[] = 'The password could not be encrypted: ' . $e->getMessage();
                }
            }
        }
        if (!$errors) {
            $changed = hj_save_settings($v);
        }
    } elseif ($section === 'confirmation') {
        $v = [
            'confirmation_enabled' => !empty($_POST['confirmation_enabled']) ? '1' : '0',
            'confirmation_subject' => hj_input($_POST, 'confirmation_subject', 200),
            'confirmation_body'    => hj_clean_text(str_replace("\r\n", "\n", (string)($_POST['confirmation_body'] ?? '')), 5000),
        ];
        if ($v['confirmation_subject'] === '' || $v['confirmation_body'] === '') {
            $errors[] = 'The confirmation email needs a subject and a message.';
        }
        if (!$errors) {
            $changed = hj_save_settings($v);
        }
    } elseif ($section === 'spam') {
        $v = [
            'rate_limit_hour'  => (string)max(1, min(100, (int)($_POST['rate_limit_hour'] ?? 5))),
            'rate_limit_day'   => (string)max(1, min(500, (int)($_POST['rate_limit_day'] ?? 20))),
            'min_fill_seconds' => (string)max(0, min(60, (int)($_POST['min_fill_seconds'] ?? 3))),
            'captcha_provider' => in_array($_POST['captcha_provider'] ?? '', ['none', 'turnstile', 'recaptcha'], true) ? $_POST['captcha_provider'] : 'none',
            'captcha_site_key' => hj_input($_POST, 'captcha_site_key', 200),
        ];
        $secret = is_string($_POST['captcha_secret'] ?? null) ? trim($_POST['captcha_secret']) : '';
        if ($secret !== '') {
            try {
                $v['captcha_secret_enc'] = hj_encrypt($secret);
            } catch (Throwable $e) {
                $errors[] = 'The secret key could not be encrypted: ' . $e->getMessage();
            }
        }
        if ($v['captcha_provider'] !== 'none') {
            $hasSecret = $secret !== '' || hj_setting('captcha_secret_enc') !== '';
            if ($v['captcha_site_key'] === '' || !$hasSecret) {
                $errors[] = 'A security check needs both the site key and the secret key from the provider.';
            }
        }
        if (!$errors) {
            $changed = hj_save_settings($v);
            $publicChanged = true;
        }
    } elseif ($section === 'retention') {
        $v = ['retention_months' => (string)max(1, min(60, (int)($_POST['retention_months'] ?? 12)))];
        $changed = hj_save_settings($v);
        $publicChanged = true;
    } elseif ($section === 'test_notify') {
        $error = hj_send_test_notification();
        hj_audit('mail_test', 'settings', null, 'Test notification: ' . ($error === null ? 'sent' : 'failed'));
        $error === null
            ? hj_flash('success', 'Test notification sent to ' . hj_notification_email() . '.')
            : hj_flash('error', 'Test notification failed: ' . $error);
        hj_redirect('settings.php#email');
    } elseif ($section === 'test_confirm') {
        $to = strtolower(hj_input($_POST, 'test_to', 190));
        $error = hj_send_test_confirmation($to);
        hj_audit('mail_test', 'settings', null, 'Test confirmation: ' . ($error === null ? 'sent' : 'failed'));
        $error === null
            ? hj_flash('success', 'Test confirmation sent to ' . $to . '.')
            : hj_flash('error', 'Test confirmation failed: ' . $error);
        hj_redirect('settings.php#confirmation');
    } elseif ($section === 'purge_now') {
        $result = hj_run_retention_purge(true);
        hj_flash('success', 'Retention check finished: ' . $result['anonymised'] . ' application(s) anonymised.');
        hj_redirect('settings.php#retention');
    }

    if (!$errors) {
        if ($changed) {
            $safe = array_map(function ($k) { return preg_replace('/_enc$/', ' (encrypted, value not logged)', $k); }, $changed);
            hj_audit('settings_updated', 'settings', null, $section . ': ' . implode(', ', $safe));
            if ($publicChanged) {
                hj_refresh_public_data();
            }
            hj_flash('success', 'Settings saved.');
        } else {
            hj_flash('info', 'No changes to save.');
        }
        hj_redirect('settings.php#' . $section);
    }
}

$s = hj_settings_all(true);
$mailConfig = hj_mail_config();
$passwordSet = $s['smtp_password_enc'] !== '';
$captchaSecretSet = $s['captcha_secret_enc'] !== '';
$logoPreview = '../' . ($s['logo_path'] ?: HJ_DEFAULT_LOGO);

function hj_text_field(string $name, string $label, string $value, string $attrs = '', string $help = ''): void
{
    echo '<div><label class="hj-label" for="f-' . e($name) . '">' . e($label) . '</label>'
        . '<input class="hj-input" id="f-' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '" ' . $attrs . '>'
        . ($help !== '' ? '<p class="hj-help">' . $help . '</p>' : '') . '</div>';
}

hj_admin_header('Settings', 'settings');
hj_admin_page_title('Settings', 'Changes to contact details appear on the website straight away.');

if ($errors): ?>
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
    <ul class="list-disc pl-5"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<nav class="flex flex-wrap gap-2 mb-6 text-sm">
  <?php foreach (['site' => 'Site & contact', 'email' => 'Email delivery', 'confirmation' => 'Applicant confirmation', 'spam' => 'Spam protection', 'retention' => 'Data retention'] as $anchor => $label): ?>
    <a class="hj-btn hj-btn-light hj-btn-sm" href="#<?= $anchor ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="space-y-6">

<form class="hj-card p-5 sm:p-6 space-y-4 scroll-mt-6" id="site" method="post" action="settings.php" enctype="multipart/form-data">
  <?= hj_csrf_field() ?>
  <input type="hidden" name="section" value="site">
  <h2 class="text-lg font-bold text-primary">Site & contact</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php
    hj_text_field('site_name', 'Site name', $s['site_name'], 'required maxlength="120"');
    hj_text_field('copyright_name', 'Copyright name (footer)', $s['copyright_name'], 'maxlength="120"');
    hj_text_field('contact_email', 'Public contact email', $s['contact_email'], 'type="email" required maxlength="190"', 'Shown on the website and used as the reply-to address. Notifications go to the notification email below if set.');
    hj_text_field('contact_phone', 'Phone', $s['contact_phone'], 'maxlength="40"');
    hj_text_field('whatsapp_number', 'WhatsApp number', $s['whatsapp_number'], 'maxlength="40"', 'Optional, international format, e.g. +44 7123 456789.');
    hj_text_field('office_hours', 'Office hours', $s['office_hours'], 'maxlength="120"');
    hj_text_field('address_line1', 'Address line', $s['address_line1'], 'maxlength="160"');
    hj_text_field('address_city', 'Town / city', $s['address_city'], 'maxlength="80"');
    hj_text_field('address_region', 'County / region', $s['address_region'], 'maxlength="80"');
    hj_text_field('address_postcode', 'Postcode', $s['address_postcode'], 'maxlength="20"');
    hj_text_field('address_country', 'Country', $s['address_country'], 'maxlength="80"');
    hj_text_field('map_url', 'Map link', $s['map_url'], 'type="url" maxlength="500"', 'Google Maps link for the "find us" button.');
    hj_text_field('response_time', 'Response time (long)', $s['response_time'], 'maxlength="60"', 'e.g. "24 business hours".');
    hj_text_field('response_time_short', 'Response time (short)', $s['response_time_short'], 'maxlength="20"', 'e.g. "24h".');
    ?>
  </div>
  <div class="border-t border-slate-100 pt-4">
    <span class="hj-label">Logo</span>
    <div class="flex flex-wrap items-center gap-4">
      <img alt="Current logo" class="h-12 w-auto max-w-[220px] object-contain bg-slate-50 border border-slate-200 rounded-lg p-1" src="<?= e($logoPreview) ?>">
      <input accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="text-sm" name="logo" type="file">
      <?php if ($s['logo_path'] !== HJ_DEFAULT_LOGO): ?>
        <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="logo_reset" value="1"> Go back to the original logo</label>
      <?php endif; ?>
    </div>
    <p class="hj-help">PNG, JPG or WebP, under 1MB. A wide logo about 300×80 pixels works best.</p>
  </div>
  <button class="hj-btn hj-btn-accent" type="submit">Save site & contact</button>
</form>

<div class="hj-card p-5 sm:p-6 space-y-4 scroll-mt-6" id="email">
  <h2 class="text-lg font-bold text-primary">Email delivery</h2>
  <form class="space-y-4" method="post" action="settings.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="section" value="email">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <?php hj_text_field('notification_email', 'Send new applications to', $s['notification_email'], 'type="email" maxlength="190" placeholder="' . e($s['contact_email']) . '"', 'Leave empty to use the public contact email.'); ?>
      <div class="flex items-end pb-2">
        <label class="inline-flex items-start gap-2 text-sm"><input class="mt-0.5 rounded border-slate-300 text-primary" type="checkbox" name="attach_cv_to_notification" value="1" <?= $s['attach_cv_to_notification'] === '1' ? 'checked' : '' ?>><span>Attach the CV to the notification email<br><span class="text-text-subtle text-xs">Off is safer: open CVs from the admin instead.</span></span></label>
      </div>
    </div>

    <?php if ($smtpFromConfig): ?>
      <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        SMTP is set in <code>config/config.php</code> (<code>smtp_override</code>) on the server, so it can't be changed here. Server: <strong><?= e($mailConfig['host']) ?>:<?= (int)$mailConfig['port'] ?></strong>, from <strong><?= e($mailConfig['from_email']) ?></strong>.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <?php
        hj_text_field('smtp_host', 'SMTP server', $s['smtp_host'], 'maxlength="190" placeholder="smtp.example.com" autocomplete="off"');
        hj_text_field('smtp_port', 'Port', $s['smtp_port'], 'type="number" min="1" max="65535"', 'Usually 587 (TLS) or 465 (SSL).');
        ?>
        <div>
          <label class="hj-label" for="f-smtp_encryption">Encryption</label>
          <?= hj_admin_select('smtp_encryption', ['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'none' => 'None'], $s['smtp_encryption'], 'id="f-smtp_encryption"') ?>
        </div>
        <?php hj_text_field('smtp_username', 'Username', $s['smtp_username'], 'maxlength="190" autocomplete="off"'); ?>
        <div>
          <label class="hj-label" for="f-smtp_password">Password</label>
          <input class="hj-input" id="f-smtp_password" name="smtp_password" type="password" autocomplete="new-password" placeholder="<?= $passwordSet ? '•••••••• (saved)' : 'Not set' ?>">
          <p class="hj-help"><?= $passwordSet ? 'Saved and encrypted. Leave empty to keep it.' : 'Stored encrypted. Never shown again after saving.' ?></p>
          <?php if ($passwordSet): ?>
            <label class="inline-flex items-center gap-2 text-xs mt-1"><input class="rounded border-slate-300 text-primary" type="checkbox" name="smtp_password_clear" value="1"> Remove saved password</label>
          <?php endif; ?>
        </div>
        <div></div>
        <?php
        hj_text_field('mail_from_email', 'Send from (email)', $s['mail_from_email'], 'type="email" required maxlength="190"', 'Must be an address your SMTP account may send from.');
        hj_text_field('mail_from_name', 'Send from (name)', $s['mail_from_name'], 'maxlength="120"');
        ?>
      </div>
    <?php endif; ?>
    <button class="hj-btn hj-btn-accent" type="submit">Save email delivery</button>
  </form>

  <form class="border-t border-slate-100 pt-4 flex flex-wrap items-center gap-3" method="post" action="settings.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="section" value="test_notify">
    <button class="hj-btn hj-btn-light" type="submit" <?= $mailConfig['host'] === '' ? 'disabled' : '' ?>><span class="material-symbols-outlined text-[18px]">send</span>Send test notification</button>
    <span class="text-sm text-text-muted">Sends a sample "new application" email to <strong><?= e(hj_notification_email()) ?></strong>.</span>
  </form>
</div>

<div class="hj-card p-5 sm:p-6 space-y-4 scroll-mt-6" id="confirmation">
  <h2 class="text-lg font-bold text-primary">Applicant confirmation email</h2>
  <form class="space-y-4" method="post" action="settings.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="section" value="confirmation">
    <label class="inline-flex items-center gap-2 text-sm"><input class="rounded border-slate-300 text-primary" type="checkbox" name="confirmation_enabled" value="1" <?= $s['confirmation_enabled'] === '1' ? 'checked' : '' ?>> Send applicants a confirmation email</label>
    <?php hj_text_field('confirmation_subject', 'Subject', $s['confirmation_subject'], 'maxlength="200" required'); ?>
    <div>
      <label class="hj-label" for="f-confirmation_body">Message</label>
      <textarea class="hj-input font-mono text-[13px]" id="f-confirmation_body" name="confirmation_body" rows="9" maxlength="5000" required><?= e($s['confirmation_body']) ?></textarea>
      <p class="hj-help">Placeholders: <code>{name}</code> <code>{role}</code> <code>{role_phrase}</code> (e.g. " for Content Writer") <code>{site_name}</code> <code>{response_time}</code> <code>{contact_email}</code></p>
    </div>
    <button class="hj-btn hj-btn-accent" type="submit">Save confirmation email</button>
  </form>
  <form class="border-t border-slate-100 pt-4 flex flex-wrap items-end gap-3" method="post" action="settings.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="section" value="test_confirm">
    <div class="min-w-[240px]">
      <label class="hj-label" for="test_to">Send a test confirmation to</label>
      <input class="hj-input" id="test_to" name="test_to" type="email" required value="<?= e($admin['email']) ?>">
    </div>
    <button class="hj-btn hj-btn-light" type="submit" <?= $mailConfig['host'] === '' ? 'disabled' : '' ?>><span class="material-symbols-outlined text-[18px]">send</span>Send test confirmation</button>
  </form>
  <?php if ($mailConfig['host'] === ''): ?><p class="text-sm text-amber-800">Set up the SMTP server above before sending tests.</p><?php endif; ?>
</div>

<form class="hj-card p-5 sm:p-6 space-y-4 scroll-mt-6" id="spam" method="post" action="settings.php">
  <?= hj_csrf_field() ?>
  <input type="hidden" name="section" value="spam">
  <h2 class="text-lg font-bold text-primary">Spam protection</h2>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <?php
    hj_text_field('rate_limit_hour', 'Applications per hour (per connection)', $s['rate_limit_hour'], 'type="number" min="1" max="100"');
    hj_text_field('rate_limit_day', 'Applications per day (per connection)', $s['rate_limit_day'], 'type="number" min="1" max="500"');
    hj_text_field('min_fill_seconds', 'Minimum seconds to fill the form', $s['min_fill_seconds'], 'type="number" min="0" max="60"', 'Faster submissions are treated as bots.');
    ?>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-slate-100 pt-4">
    <div>
      <label class="hj-label" for="f-captcha_provider">Security check (captcha)</label>
      <?= hj_admin_select('captcha_provider', ['none' => 'Off', 'turnstile' => 'Cloudflare Turnstile', 'recaptcha' => 'Google reCAPTCHA v2'], $s['captcha_provider'], 'id="f-captcha_provider"') ?>
    </div>
    <?php hj_text_field('captcha_site_key', 'Site key (public)', $s['captcha_site_key'], 'maxlength="200" autocomplete="off"'); ?>
    <div>
      <label class="hj-label" for="f-captcha_secret">Secret key</label>
      <input class="hj-input" id="f-captcha_secret" name="captcha_secret" type="password" autocomplete="new-password" placeholder="<?= $captchaSecretSet ? '•••••••• (saved)' : 'Not set' ?>">
      <p class="hj-help">Stored encrypted. Leave empty to keep the saved key.</p>
    </div>
  </div>
  <button class="hj-btn hj-btn-accent" type="submit">Save spam protection</button>
</form>

<div class="hj-card p-5 sm:p-6 space-y-4 scroll-mt-6" id="retention">
  <h2 class="text-lg font-bold text-primary">Data retention</h2>
  <form class="flex flex-wrap items-end gap-3" method="post" action="settings.php">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="section" value="retention">
    <div>
      <label class="hj-label" for="f-retention_months">Delete applications after (months since last update)</label>
      <input class="hj-input !w-32" id="f-retention_months" name="retention_months" type="number" min="1" max="60" value="<?= (int)$s['retention_months'] ?>">
    </div>
    <button class="hj-btn hj-btn-accent" type="submit">Save</button>
  </form>
  <p class="text-sm text-text-muted">Applications marked <strong>Keep</strong> are never deleted automatically. The check runs once a day when an admin signs in, and from the server cron job if one is set up (see the deployment guide). Last run: <strong><?= e(hj_format_datetime($s['last_purge_at'] ?: null)) ?></strong>.</p>
  <form method="post" action="settings.php" data-confirm="Anonymise every application past its retention date now?">
    <?= hj_csrf_field() ?>
    <input type="hidden" name="section" value="purge_now">
    <button class="hj-btn hj-btn-light" type="submit"><span class="material-symbols-outlined text-[18px]">auto_delete</span>Run the retention check now</button>
  </form>
</div>

</div>
<?php
hj_admin_footer();
