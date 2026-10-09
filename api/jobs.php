<?php
/**
 * Active jobs and the categories that have them. Same shape as assets/data/jobs-snapshot.json.
 * Optional: ?featured=1 (featured jobs only), ?limit=N.
 */
define('HJ_CONTEXT', 'api');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!hj_schema_ready()) {
    hj_json(['status' => 'error', 'message' => 'Jobs are not available yet.'], 503);
}

try {
    $payload = hj_public_jobs_payload();
} catch (Throwable $e) {
    error_log('[hubjob] api/jobs.php failed: ' . $e->getMessage());
    hj_json(['status' => 'error', 'message' => 'Jobs could not be loaded.'], 500);
}

if (($_GET['featured'] ?? '') === '1') {
    $payload['jobs'] = array_values(array_filter($payload['jobs'], function ($job) {
        return $job['is_featured'];
    }));
}
$limit = (int)($_GET['limit'] ?? 0);
if ($limit > 0) {
    $payload['jobs'] = array_slice($payload['jobs'], 0, min($limit, 100));
}

// Admin changes must show on the next page load, so browsers and proxies always revalidate.
header('Cache-Control: no-cache, must-revalidate');
hj_json($payload);
