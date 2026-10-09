<?php

/** Default values; the seed migration writes these into the settings table. */
function hj_setting_defaults(): array
{
    return [
        'site_name'               => 'Hubjob Platform',
        'logo_path'               => 'static/images/logo22.png',
        'contact_email'           => 'info@hubjobplatform.com',
        'contact_phone'           => '+44 1202 958648',
        'whatsapp_number'         => '',
        'address_line1'           => '42 Windham Road',
        'address_city'            => 'Bournemouth',
        'address_region'          => 'Dorset',
        'address_postcode'        => 'BH1 2AW',
        'address_country'         => 'UK',
        'map_url'                 => 'https://www.google.com/maps/search/?api=1&query=42+Windham+Road+Bournemouth+BH1+2AW',
        'office_hours'            => 'Mon – Fri: 8:30 AM – 6:00 PM GMT',
        'response_time'           => '24 business hours',
        'response_time_short'     => '24h',
        'copyright_name'          => 'Hubjob Platform',

        'notification_email'      => '',
        'attach_cv_to_notification' => '0',
        'confirmation_enabled'    => '1',
        'confirmation_subject'    => 'We received your application — {site_name}',
        'confirmation_body'       => "Hi {name},\n\nThank you for applying{role_phrase} through {site_name}. Our team reviews every application and will be in touch within {response_time} if there is a suitable match.\n\nIf you have any questions, reply to this email or contact us at {contact_email}.\n\nKind regards,\nThe {site_name} team",

        'smtp_host'               => '',
        'smtp_port'               => '587',
        'smtp_username'           => '',
        'smtp_password_enc'       => '',
        'smtp_encryption'         => 'tls',
        'mail_from_email'         => 'noreply@hubjobplatform.com',
        'mail_from_name'          => 'Hubjob Platform',

        'rate_limit_hour'         => '5',
        'rate_limit_day'          => '20',
        'min_fill_seconds'        => '3',
        'captcha_provider'        => 'none',
        'captcha_site_key'        => '',
        'captcha_secret_enc'      => '',

        'retention_months'        => '12',
        'last_purge_at'           => '',
        'snapshot_version'        => '1',
        'legacy_form_until'       => '',
    ];
}

function hj_settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = hj_setting_defaults();
    try {
        if (hj_schema_ready()) {
            foreach (hj_db_all('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        error_log('[hubjob] Could not load settings: ' . $e->getMessage());
    }
    return $cache;
}

function hj_setting(string $key, string $default = ''): string
{
    $all = hj_settings_all();
    return array_key_exists($key, $all) ? (string)$all[$key] : $default;
}

function hj_setting_set(string $key, string $value): void
{
    hj_db_exec(
        'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
        [$key, $value, hj_now()]
    );
    hj_settings_all(true);
}

function hj_notification_email(): string
{
    $to = trim(hj_setting('notification_email'));
    return $to !== '' ? $to : trim(hj_setting('contact_email'));
}

/**
 * Settings that are safe to publish. Never add SMTP, notification or secret keys here.
 */
function hj_public_settings(): array
{
    $s = hj_settings_all();
    $phone = trim($s['contact_phone']);
    $whatsapp = preg_replace('/[^0-9]/', '', $s['whatsapp_number']);
    $addressParts = array_filter([$s['address_line1'], $s['address_city'], $s['address_region'], $s['address_postcode'], $s['address_country']], 'strlen');
    $regionLine = implode(', ', array_filter([$s['address_city'], $s['address_region'], $s['address_country']], 'strlen'));
    $shortAddress = implode(', ', array_filter([$s['address_line1'], $s['address_postcode']], 'strlen'));
    $captcha = in_array($s['captcha_provider'], ['turnstile', 'recaptcha'], true) && $s['captcha_site_key'] !== ''
        ? $s['captcha_provider'] : 'none';

    return [
        'site_name'           => $s['site_name'],
        'logo_url'            => $s['logo_path'],
        'contact_email'       => $s['contact_email'],
        'contact_phone'       => $phone,
        'phone_href'          => preg_replace('/[^0-9+]/', '', $phone),
        'whatsapp_number'     => $s['whatsapp_number'],
        'whatsapp_href'       => $whatsapp !== '' ? 'https://wa.me/' . $whatsapp : '',
        'address_line1'       => $s['address_line1'],
        'address_city'        => $s['address_city'],
        'address_region'      => $s['address_region'],
        'address_postcode'    => $s['address_postcode'],
        'address_country'     => $s['address_country'],
        'address_full'        => implode(', ', $addressParts),
        'address_short'       => $shortAddress,
        'address_region_line' => $regionLine,
        'map_url'             => $s['map_url'],
        'office_hours'        => $s['office_hours'],
        'response_time'       => $s['response_time'],
        'response_time_short' => $s['response_time_short'],
        'copyright_name'      => $s['copyright_name'],
        'retention_months'    => (string)max(1, (int)$s['retention_months']),
        'captcha_provider'    => $captcha,
        'captcha_site_key'    => $captcha !== 'none' ? $s['captcha_site_key'] : '',
        'version'             => $s['snapshot_version'],
    ];
}
