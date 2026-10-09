<?php

/**
 * Count events in a bucket for a key within the last $seconds.
 */
function hj_rate_count(string $bucket, string $keyHash, int $seconds): int
{
    return (int)hj_db_value(
        'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND key_hash = ? AND created_at >= ?',
        [$bucket, $keyHash, gmdate('Y-m-d H:i:s', time() - $seconds)]
    );
}

function hj_rate_hit(string $bucket, string $keyHash): void
{
    hj_db_exec(
        'INSERT INTO rate_limits (bucket, key_hash, created_at) VALUES (?, ?, ?)',
        [$bucket, $keyHash, hj_now()]
    );
}

/**
 * Returns an error message if the IP has hit the application limits, otherwise null.
 */
function hj_application_rate_error(string $ipHash): ?string
{
    $perHour = max(1, (int)hj_setting('rate_limit_hour', '5'));
    $perDay = max($perHour, (int)hj_setting('rate_limit_day', '20'));
    if (hj_rate_count('apply', $ipHash, 3600) >= $perHour || hj_rate_count('apply', $ipHash, 86400) >= $perDay) {
        return 'We have received several applications from your connection recently. Please try again later, or email us directly.';
    }
    return null;
}
