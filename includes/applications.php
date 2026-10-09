<?php

/** A problem the applicant can fix; the message is shown on the form. */
class HJ_UserError extends RuntimeException
{
    public $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

const HJ_CV_MAX_BYTES = 2097152;
const HJ_CV_TYPES = [
    'pdf'  => ['application/pdf', 'application/x-pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-word', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2', 'application/x-ole-storage'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'rtf'  => ['application/rtf', 'text/rtf', 'text/plain'],
    'txt'  => ['text/plain'],
];
const HJ_WORK_TYPES = ['Full-Time', 'Part-Time', 'Either'];

/**
 * Validate and store an uploaded CV. Returns stored-file details, or null when no file was sent.
 * Throws HJ_UserError for anything the applicant needs to fix.
 */
function hj_store_cv(?array $file): ?array
{
    if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (is_array($file['error'])) {
        throw new HJ_UserError('Please upload a single CV file.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new HJ_UserError('Your CV is larger than 2MB. Please upload a smaller file.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new HJ_UserError('Your CV could not be uploaded. Please try again.');
    }
    $size = (int)$file['size'];
    if ($size <= 0 || $size > HJ_CV_MAX_BYTES) {
        throw new HJ_UserError('Your CV must be a file under 2MB.');
    }

    $original = basename(str_replace('\\', '/', (string)$file['name']));
    $original = preg_replace('/[\x00-\x1F\x7F]/', '', $original);
    $parts = explode('.', strtolower($original));
    $ext = count($parts) > 1 ? array_pop($parts) : '';
    if (!array_key_exists($ext, HJ_CV_TYPES)) {
        throw new HJ_UserError('That file type isn\'t supported. Please upload a PDF, DOC, DOCX, RTF or TXT file.');
    }
    // Reject names like cv.php.pdf where an inner segment is executable.
    $dangerous = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'sh', 'exe', 'js', 'html', 'htm', 'svg', 'shtml'];
    array_shift($parts);
    foreach ($parts as $segment) {
        if (in_array($segment, $dangerous, true)) {
            throw new HJ_UserError('Please rename your CV file and try again (for example "my-cv.' . $ext . '").');
        }
    }

    $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 8);
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
        if (!in_array($mime, HJ_CV_TYPES[$ext], true)) {
            throw new HJ_UserError('That file doesn\'t look like a valid .' . $ext . ' document. Please upload a PDF, DOC, DOCX, RTF or TXT file.');
        }
    }
    $magicOk = true;
    switch ($ext) {
        case 'pdf':
            $magicOk = strncmp($head, '%PDF', 4) === 0;
            $mime = 'application/pdf';
            break;
        case 'doc':
            $magicOk = strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0;
            $mime = 'application/msword';
            break;
        case 'docx':
            $magicOk = strncmp($head, "PK\x03\x04", 4) === 0;
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
        case 'rtf':
            $magicOk = strncmp($head, '{\\rtf', 5) === 0;
            $mime = 'application/rtf';
            break;
        case 'txt':
            $magicOk = strpos((string)file_get_contents($file['tmp_name']), "\0") === false;
            $mime = 'text/plain';
            break;
    }
    if (!$magicOk) {
        throw new HJ_UserError('That file doesn\'t look like a valid .' . $ext . ' document. Please upload a PDF, DOC, DOCX, RTF or TXT file.');
    }

    $dir = hj_cv_dir();
    if (!hj_ensure_dir($dir, 0750) || !is_writable($dir)) {
        error_log('[hubjob] CV folder is not writable: ' . $dir);
        throw new HJ_UserError('We couldn\'t save your CV right now. Please try again later or email us your application.', 500);
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new HJ_UserError('We couldn\'t save your CV right now. Please try again later or email us your application.', 500);
    }
    @chmod($dir . '/' . $stored, 0640);

    return [
        'stored'   => $stored,
        'original' => hj_substr($original, 0, 255),
        'mime'     => $mime,
        'size'     => $size,
    ];
}

function hj_delete_cv_file(?string $stored): void
{
    if ($stored && preg_match('/^[a-f0-9]{32}\.[a-z]{3,4}$/', $stored)) {
        $path = hj_cv_dir() . '/' . $stored;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function hj_verify_captcha(array $post): bool
{
    $provider = hj_setting('captcha_provider', 'none');
    $secret = hj_decrypt(hj_setting('captcha_secret_enc'));
    if (!in_array($provider, ['turnstile', 'recaptcha'], true) || $secret === '' || hj_setting('captcha_site_key') === '') {
        return true;
    }
    $field = $provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response';
    $response = is_string($post[$field] ?? null) ? $post[$field] : '';
    if ($response === '') {
        return false;
    }
    $url = $provider === 'turnstile'
        ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        : 'https://www.google.com/recaptcha/api/siteverify';
    $context = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['secret' => $secret, 'response' => $response, 'remoteip' => hj_client_ip()]),
        'timeout' => 8,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) && !empty($data['success']);
}

function hj_retain_until(): string
{
    $months = max(1, (int)hj_setting('retention_months', '12'));
    return gmdate('Y-m-d H:i:s', strtotime('+' . $months . ' months'));
}

/**
 * Handle a submission from the application/contact forms.
 * $legacy = true for posts from cached copies of the old form (age + nationality) via sendmail.php.
 * Returns the JSON payload; throws HJ_UserError for user-fixable problems.
 */
function hj_handle_application(array $post, array $files, bool $legacy = false): array
{
    if (!hj_schema_ready()) {
        throw new HJ_UserError('Applications are temporarily unavailable. Please try again shortly or email us.', 503);
    }

    // Honeypot: bots fill every field. Pretend success so they don't retry.
    if (trim((string)($post['website'] ?? '')) !== '') {
        return ['status' => 'success', 'message' => 'Thank you! Your application has been submitted.'];
    }

    $ipHash = hj_ip_hash();
    $rateError = hj_application_rate_error($ipHash);
    if ($rateError === null && hj_rate_count('apply_attempt', $ipHash, 3600) >= 30) {
        $rateError = 'Too many attempts from your connection. Please try again in an hour, or email us directly.';
    }
    if ($rateError !== null) {
        throw new HJ_UserError($rateError, 429);
    }
    hj_rate_hit('apply_attempt', $ipHash);

    if (!$legacy) {
        $issued = hj_form_token_time(is_string($post['form_token'] ?? null) ? $post['form_token'] : '');
        if ($issued === null || $issued > time() + 60 || $issued < time() - 86400) {
            throw new HJ_UserError('Your form session expired. Please reload the page and submit again.', 400);
        }
        if (time() - $issued < max(0, (int)hj_setting('min_fill_seconds', '3'))) {
            throw new HJ_UserError('That was very fast! Please check your details and submit again.', 400);
        }
        if (!hj_verify_captcha($post)) {
            throw new HJ_UserError('Please complete the security check and submit again.', 400);
        }
    }

    $fullname = hj_input($post, 'fullname', 160);
    $email = strtolower(hj_input($post, 'email', 190));
    $phone = hj_input($post, 'phone', 60);
    $workType = hj_input($post, 'work_type', 30);
    $role = hj_input($post, 'role', 160);

    if ($fullname === '' || $email === '' || $phone === '' || $workType === '') {
        throw new HJ_UserError('Please fill in all required fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HJ_UserError('Please enter a valid email address.');
    }
    if (!preg_match('/\d{5,}/', preg_replace('/[^0-9]/', '', $phone))) {
        throw new HJ_UserError('Please enter a valid phone number.');
    }
    if (!in_array($workType, HJ_WORK_TYPES, true)) {
        throw new HJ_UserError('Please choose a work type.');
    }

    $notes = null;
    if ($legacy) {
        $age = (int)hj_input($post, 'age', 3);
        if ($age < 18) {
            throw new HJ_UserError('You must be 18 or over to apply.');
        }
        $isAdult = 1;
        $country = null;
        $countryCode = null;
        $rightToWork = null;
        $consentAt = null;
        $nationality = hj_input($post, 'nationality', 100);
        if ($nationality !== '') {
            $notes = 'Submitted through the previous version of the form. Nationality given: ' . $nationality
                . (hj_input($post, 'nationality_code', 2) !== '' ? ' (' . strtoupper(hj_input($post, 'nationality_code', 2)) . ')' : '') . '.';
        }
    } else {
        if (($post['is_adult'] ?? '') !== '1') {
            throw new HJ_UserError('Please confirm you are 18 or over.');
        }
        $isAdult = 1;
        $country = hj_input($post, 'residence_country', 100);
        $countryCode = strtoupper(hj_input($post, 'residence_country_code', 2));
        if ($country === '' || !preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new HJ_UserError('Please select your country of residence.');
        }
        $rightToWork = hj_input($post, 'right_to_work', 10);
        if ($rightToWork === '') {
            $rightToWork = null;
        } elseif (!in_array($rightToWork, ['yes', 'no', 'not_sure'], true)) {
            throw new HJ_UserError('Please choose an answer for right to work, or leave it blank.');
        }
        if (($post['consent'] ?? '') !== '1') {
            throw new HJ_UserError('Please agree to the privacy notice so we can process your application.');
        }
        $consentAt = hj_now();
    }

    // Link to a job when the form came from an Apply button.
    $jobId = null;
    $jobParam = (int)($post['job_id'] ?? 0);
    if ($jobParam > 0) {
        $job = hj_db_one('SELECT id, title FROM jobs WHERE id = ?', [$jobParam]);
        if ($job) {
            $jobId = (int)$job['id'];
            if ($role === '') {
                $role = $job['title'];
            }
        }
    }

    $recent = (int)hj_db_value(
        'SELECT COUNT(*) FROM applications WHERE email = ? AND created_at >= ? AND ' . ($jobId ? 'job_id = ?' : 'job_id IS NULL AND role_title <=> ?'),
        [$email, gmdate('Y-m-d H:i:s', time() - 86400), $jobId ?: ($role !== '' ? $role : null)]
    );
    if ($recent > 0) {
        throw new HJ_UserError('We already received an application from this email for this role in the last 24 hours. We\'ll be in touch soon.', 409);
    }

    $cv = hj_store_cv(isset($files['cv']) && is_array($files['cv']) ? $files['cv'] : null);

    $utm = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_landing'] as $key) {
        $value = hj_input($post, $key, 255);
        $utm[$key] = $value !== '' ? $value : null;
    }
    $sourcePage = hj_input($post, 'source_page', 255);

    try {
        $now = hj_now();
        hj_db_exec(
            'INSERT INTO applications (job_id, role_title, fullname, email, phone, is_adult, residence_country,
                residence_country_code, right_to_work, work_type, cv_stored_name, cv_original_name, cv_mime, cv_size,
                consent_at, status, admin_notes, keep_flag, retain_until, notify_status, confirm_status,
                utm_source, utm_medium, utm_campaign, utm_term, utm_content, utm_landing, source_page, ip_hash,
                form_version, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'new\', ?, 0, ?, \'pending\', \'pending\',
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $jobId, $role !== '' ? $role : null, $fullname, $email, $phone, $isAdult, $country,
                $countryCode, $rightToWork, $workType,
                $cv['stored'] ?? null, $cv['original'] ?? null, $cv['mime'] ?? null, $cv['size'] ?? null,
                $consentAt, $notes, hj_retain_until(),
                $utm['utm_source'], $utm['utm_medium'], $utm['utm_campaign'], $utm['utm_term'], $utm['utm_content'], $utm['utm_landing'],
                $sourcePage !== '' ? $sourcePage : null, $ipHash, $legacy ? 'legacy' : 'v2', $now, $now,
            ]
        );
        $id = (int)hj_db()->lastInsertId();
    } catch (Throwable $e) {
        hj_delete_cv_file($cv['stored'] ?? null);
        throw $e;
    }

    hj_rate_hit('apply', $ipHash);

    // The application is saved; email problems are recorded on it and shown in admin, not to the applicant.
    hj_dispatch_application_emails($id);

    return [
        'status'  => 'success',
        'message' => 'Thank you! Your application has been submitted. We\'ll be in touch within ' . hj_setting('response_time') . ' if there\'s a suitable match.',
    ];
}

/** JSON response wrapper shared by api/apply.php and sendmail.php. */
function hj_application_endpoint(bool $legacy): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        hj_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
    }
    // post_max_size exceeded: PHP drops the whole body.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        hj_json(['status' => 'error', 'message' => 'Your upload was too large. Please attach a CV under 2MB.'], 413);
    }
    try {
        hj_json(hj_handle_application($_POST, $_FILES, $legacy));
    } catch (HJ_UserError $e) {
        hj_json(['status' => 'error', 'message' => $e->getMessage()], $e->status);
    } catch (Throwable $e) {
        error_log('[hubjob] Application submission failed: ' . $e->getMessage());
        $email = hj_setting('contact_email');
        hj_json(['status' => 'error', 'message' => 'Sorry, something went wrong saving your application. Please try again later or email us at ' . $email . '.'], 500);
    }
}
