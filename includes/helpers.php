<?php

function hj_config(string $key, $default = null)
{
    $value = $GLOBALS['HJ_CONFIG'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hj_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function hj_strlen(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function hj_substr(string $s, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($s, $start, $length, 'UTF-8') : substr($s, $start, $length);
}

/**
 * Trimmed string input with a maximum length; control characters stripped.
 */
function hj_clean_text($value, int $max): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    $value = trim((string)$value);
    if (hj_strlen($value) > $max) {
        $value = hj_substr($value, 0, $max);
    }
    return $value;
}

function hj_input(array $source, string $key, int $max = 255): string
{
    return hj_clean_text($source[$key] ?? '', $max);
}

function hj_json($data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hj_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/**
 * Site root URL without trailing slash, e.g. https://example.com or https://example.com/sub.
 */
function hj_base_url(): string
{
    $configured = trim((string)hj_config('app_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return '';
    }
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)$_SERVER['HTTP_HOST']);
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    // Scripts live at /<root>/<section>/file.php or /<root>/file.php (sendmail.php).
    $dir = rtrim(dirname($script), '/');
    $section = basename($dir);
    if (in_array($section, ['admin', 'api', 'cron'], true)) {
        $dir = rtrim(dirname($dir), '/');
    }
    return (hj_is_https() ? 'https://' : 'http://') . $host . ($dir === '.' ? '' : $dir);
}

function hj_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function hj_ip_hash(?string $ip = null): string
{
    return hash_hmac('sha256', $ip ?? hj_client_ip(), hj_app_key());
}

function hj_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function hj_format_datetime(?string $utc, string $format = 'j M Y, H:i'): string
{
    if (!$utc) {
        return '—';
    }
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/London'));
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$utc;
    }
}

function hj_currency_symbol(string $currency): string
{
    $map = ['USD' => '$', 'GBP' => '£', 'EUR' => '€'];
    return $map[$currency] ?? ($currency . ' ');
}

function hj_format_amount($amount): string
{
    $amount = (float)$amount;
    return floor($amount) == $amount ? number_format($amount, 0) : number_format($amount, 2);
}

/**
 * Human pay label, e.g. "$35–$90/hr" or "£38,000–£48,000/yr".
 */
function hj_format_pay($min, $max, string $currency, string $period): string
{
    if ($min === null && $max === null) {
        return '';
    }
    $symbol = hj_currency_symbol($currency);
    $suffix = ['hour' => '/hr', 'month' => '/mo', 'year' => '/yr'][$period] ?? '';
    if ($min !== null && $max !== null && (float)$min !== (float)$max) {
        return $symbol . hj_format_amount($min) . '–' . $symbol . hj_format_amount($max) . $suffix;
    }
    $single = $min !== null ? $min : $max;
    return $symbol . hj_format_amount($single) . $suffix;
}

/**
 * Hourly equivalent used by the pay-band filter (2080 working hours a year, ~173 a month).
 */
function hj_hourly_equivalent($amount, string $period): ?float
{
    if ($amount === null || $amount === '') {
        return null;
    }
    $amount = (float)$amount;
    if ($period === 'year') {
        return round($amount / 2080, 2);
    }
    if ($period === 'month') {
        return round($amount / 173, 2);
    }
    return $amount;
}

function hj_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string)$text, '-');
}

function hj_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

function hj_ensure_dir(string $path, int $mode = 0750): bool
{
    if (is_dir($path)) {
        return true;
    }
    return @mkdir($path, $mode, true) || is_dir($path);
}

function hj_cv_dir(): string
{
    $configured = trim((string)hj_config('cv_storage_path', ''));
    return rtrim($configured !== '' ? $configured : HJ_ROOT . '/storage/cvs', '/\\');
}

function hj_backup_dir(): string
{
    $configured = trim((string)hj_config('backup_storage_path', ''));
    return rtrim($configured !== '' ? $configured : HJ_ROOT . '/storage/backups', '/\\');
}

/** Labels used across admin and API. */
function hj_labels(string $group): array
{
    $labels = [
        'employment_types' => ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'temporary' => 'Temporary'],
        'work_arrangement' => ['remote' => 'Remote', 'hybrid' => 'Hybrid', 'onsite' => 'On-site', 'unspecified' => 'Unspecified'],
        'applicant_region' => ['worldwide' => 'Worldwide', 'uk_europe' => 'UK & Europe', 'uk_only' => 'UK only', 'unspecified' => 'Unspecified'],
        'schedule'         => ['fixed' => 'Fixed hours', 'flexible' => 'Flexible hours', 'unspecified' => 'Unspecified'],
        'job_status'       => ['active' => 'Active', 'draft' => 'Draft', 'closed' => 'Closed'],
        'pay_period'       => ['hour' => 'Per hour', 'month' => 'Per month', 'year' => 'Per year'],
        'currency'         => ['USD' => 'USD ($)', 'GBP' => 'GBP (£)', 'EUR' => 'EUR (€)'],
        'app_status'       => ['new' => 'New', 'reviewed' => 'Reviewed', 'shortlisted' => 'Shortlisted', 'rejected' => 'Rejected', 'hired' => 'Hired'],
        'right_to_work'    => ['yes' => 'Yes', 'no' => 'No', 'not_sure' => 'Not sure'],
        'mail_status'      => ['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'disabled' => 'Disabled'],
    ];
    return $labels[$group] ?? [];
}
