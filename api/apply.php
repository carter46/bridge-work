<?php
/**
 * Application and contact form submissions (multipart/form-data). Returns {status, message}.
 */
define('HJ_CONTEXT', 'api');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');
hj_application_endpoint(false);
