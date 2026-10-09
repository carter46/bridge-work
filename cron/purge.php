<?php
/**
 * Daily retention job. Anonymises applications past their retention date (unless marked Keep)
 * and clears old rate-limit and login records.
 *
 * cPanel cron example (once a day at 03:15):
 *   15 3 * * * /usr/local/bin/php /home/ACCOUNT/public_html/cron/purge.php >/dev/null 2>&1
 *
 * Without cron the same check runs at most once a day when an admin signs in.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('HJ_CONTEXT', 'cli');
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!hj_schema_ready()) {
    fwrite(STDERR, "Database not ready yet: open /admin/ once so the database updates run.\n");
    exit(1);
}

try {
    $result = hj_run_retention_purge(true);
    echo gmdate('c') . ' retention check: ' . $result['anonymised'] . " application(s) anonymised\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Retention check failed: ' . $e->getMessage() . "\n");
    exit(1);
}
