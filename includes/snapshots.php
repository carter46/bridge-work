<?php
/**
 * Static JSON copies of the public data (assets/data/*.json).
 * The public pages fall back to these when the PHP API is unreachable, so they are rewritten
 * every time an admin saves settings, jobs or categories.
 */

function hj_snapshot_dir(): string
{
    return HJ_ROOT . '/assets/data';
}

function hj_write_json_atomic(string $path, $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Could not encode ' . basename($path));
    }
    if (!hj_ensure_dir(dirname($path), 0755)) {
        throw new RuntimeException('Folder ' . dirname($path) . ' is not writable');
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write ' . basename($path) . ' (check folder permissions on assets/data)');
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $path)) {
        // Windows can't rename over an existing file.
        @unlink($path);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Could not replace ' . basename($path));
        }
    }
}

/** Rewrite both snapshot files. Throws on failure. */
function hj_regenerate_snapshots(): void
{
    if (!hj_schema_ready()) {
        return;
    }
    hj_setting_set('snapshot_version', gmdate('YmdHis'));
    hj_write_json_atomic(hj_snapshot_dir() . '/site-settings.json', hj_public_settings());
    hj_write_json_atomic(hj_snapshot_dir() . '/jobs-snapshot.json', hj_public_jobs_payload());
}

/** For admin save handlers: regenerate and flash a warning instead of failing the save. */
function hj_refresh_public_data(): void
{
    try {
        hj_regenerate_snapshots();
    } catch (Throwable $e) {
        error_log('[hubjob] Snapshot regeneration failed: ' . $e->getMessage());
        if (function_exists('hj_flash')) {
            hj_flash('warning', 'Saved, but the backup copy of the public data could not be updated: ' . $e->getMessage());
        }
    }
}
