<?php
/**
 * Streams a CV from private storage to a signed-in admin. Files are looked up by application id only.
 */
define('HJ_CONTEXT', 'admin');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = hj_require_admin();
$id = (int)($_GET['id'] ?? 0);
$app = $id > 0 ? hj_db_one('SELECT id, fullname, cv_stored_name, cv_original_name FROM applications WHERE id = ? AND anonymised_at IS NULL', [$id]) : null;

$stored = $app['cv_stored_name'] ?? null;
$path = ($stored && preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx|rtf|txt)$/', $stored)) ? hj_cv_dir() . '/' . $stored : null;
if ($path === null || !is_file($path)) {
    http_response_code(404);
    hj_admin_header('CV not found', 'applications');
    echo '<div class="hj-card p-8 text-center"><h1 class="text-lg font-bold text-primary mb-2">CV not found</h1>'
        . '<p class="text-sm text-text-muted">The file may have been deleted under the retention policy.</p>'
        . '<a class="hj-btn hj-btn-light mt-4" href="applications.php">Back to applications</a></div>';
    hj_admin_footer();
    exit;
}

$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$types = [
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain; charset=utf-8',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'rtf'  => 'application/rtf',
];
$inline = in_array($ext, ['pdf', 'txt'], true) && empty($_GET['download']);

$name = (string)($app['cv_original_name'] ?: 'cv.' . $ext);
$asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
if (strtolower(pathinfo($asciiName, PATHINFO_EXTENSION)) !== $ext) {
    $asciiName .= '.' . $ext;
}

hj_audit($inline ? 'cv_viewed' : 'cv_downloaded', 'application', $id, $name);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if ($ext !== 'pdf') {
    // Chrome refuses to render PDFs under a sandbox policy, so only lock down the other types.
    header("Content-Security-Policy: default-src 'none'; sandbox");
}
readfile($path);
exit;
