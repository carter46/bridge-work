<?php
/**
 * Signed, time-stamped token for the application form (spam timing check). Not a secret.
 */
define('HJ_CONTEXT', 'api');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');
hj_json(['token' => hj_form_token()]);
