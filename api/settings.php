<?php
/**
 * Public site settings (contact details, logo, address). Allowlisted keys only:
 * SMTP, notification email and secrets are never included. Same shape as assets/data/site-settings.json.
 */
define('HJ_CONTEXT', 'api');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!hj_schema_ready()) {
    hj_json(['status' => 'error', 'message' => 'Settings are not available yet.'], 503);
}
// Admin changes must show on the next page load, so browsers and proxies always revalidate.
header('Cache-Control: no-cache, must-revalidate');
hj_json(hj_public_settings());
