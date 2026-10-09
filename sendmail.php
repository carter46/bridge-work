<?php
/**
 * Compatibility endpoint for the old application form.
 *
 * The current forms post to api/apply.php. Browsers with a cached copy of the old page still post
 * here (with age + nationality); those are accepted for 30 days after deployment (setting
 * legacy_form_until), saved like any other application and visible in Admin -> Applications.
 */
define('HJ_CONTEXT', 'api');
require_once __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: no-store');

$isCurrentForm = isset($_POST['form_token']) || isset($_POST['residence_country']);
if (!$isCurrentForm) {
    $until = hj_setting('legacy_form_until');
    if ($until !== '' && strtotime($until . ' UTC') < time()) {
        hj_json(['status' => 'error', 'message' => 'This form has been updated. Please reload the page and submit your application again.'], 410);
    }
}

hj_application_endpoint(!$isCurrentForm);
