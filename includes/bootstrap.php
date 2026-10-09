<?php
/**
 * Shared bootstrap for api/, admin/, cron/ and sendmail.php.
 * Define HJ_CONTEXT ('api', 'admin' or 'cli') before including this file.
 */

if (defined('HJ_BOOTSTRAPPED')) {
    return;
}
define('HJ_BOOTSTRAPPED', true);
define('HJ_ROOT', dirname(__DIR__));
if (!defined('HJ_CONTEXT')) {
    define('HJ_CONTEXT', PHP_SAPI === 'cli' ? 'cli' : 'api');
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (PHP_VERSION_ID < 70400) {
    hj_bootstrap_fail('This site needs PHP 7.4 or newer (running ' . PHP_VERSION . ').');
}

$hjConfigFile = HJ_ROOT . '/config/config.php';
if (!is_file($hjConfigFile)) {
    hj_bootstrap_fail('Missing config/config.php. Copy config/config.sample.php to config/config.php and fill in the database details.');
}
$GLOBALS['HJ_CONFIG'] = require $hjConfigFile;
if (!is_array($GLOBALS['HJ_CONFIG'])) {
    hj_bootstrap_fail('config/config.php must return an array.');
}
if (!empty($GLOBALS['HJ_CONFIG']['debug'])) {
    ini_set('display_errors', '1');
}
set_exception_handler('hj_unexpected_error');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/rate-limit.php';
require_once __DIR__ . '/jobs-repo.php';
require_once __DIR__ . '/snapshots.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/applications.php';
require_once __DIR__ . '/retention.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/auto-migrate.php';

if (HJ_CONTEXT === 'admin') {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/csrf.php';
    require_once HJ_ROOT . '/admin/partials/layout.php';
    hj_send_security_headers();
}

/**
 * Stop early with a message suited to the current context.
 */
function hj_bootstrap_fail(string $message): void
{
    error_log('[hubjob] ' . $message);
    if (HJ_CONTEXT === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(503);
    }
    if (HJ_CONTEXT === 'admin') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Setup required</title>'
            . '<div style="font-family:sans-serif;max-width:640px;margin:4rem auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:12px">'
            . '<h1 style="font-size:1.25rem;color:#070235">Setup required</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'The service is temporarily unavailable. Please try again later.']);
    }
    exit;
}

/**
 * Last-resort handler for exceptions nobody caught (e.g. a database error mid-request).
 * Logs the details and shows a short message instead of a blank page or broken JSON.
 */
function hj_unexpected_error(Throwable $e): void
{
    error_log('[hubjob] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $debug = !empty($GLOBALS['HJ_CONFIG']['debug']);
    if (HJ_CONTEXT === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (HJ_CONTEXT !== 'admin') {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['status' => 'error', 'message' => 'Something went wrong on our side. Please try again later.']);
        exit;
    }
    $detail = $debug ? '<pre style="white-space:pre-wrap;font-size:12px;background:#f8fafc;padding:.75rem;border-radius:8px">'
        . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>' : '';
    $box = '<div style="font-family:sans-serif;max-width:640px;margin:2rem auto;padding:1.5rem;border:1px solid #fecaca;border-radius:12px;background:#fff">'
        . '<h1 style="font-size:1.15rem;color:#070235;margin:0 0 .5rem">Something went wrong</h1>'
        . '<p style="margin:0 0 .75rem">The action could not be completed. Check whether your change was saved before trying again. The details have been written to the server error log.</p>'
        . $detail . '<p style="margin:0"><a href="javascript:history.back()">Go back</a> · <a href="index.php">Dashboard</a></p></div>';
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Something went wrong</title>' . $box;
    } else {
        echo $box;
    }
    exit;
}
