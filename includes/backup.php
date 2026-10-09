<?php

/** Tables owned by this application, in restore-safe order. */
function hj_app_tables(): array
{
    return ['admins', 'settings', 'categories', 'jobs', 'applications', 'audit_log', 'rate_limits', 'login_attempts', 'auto_migrations'];
}

/** True when there is real data worth backing up (anything beyond the migration tracker). */
function hj_has_app_data(): bool
{
    foreach (hj_app_tables() as $table) {
        if ($table !== 'auto_migrations' && hj_table_exists($table)) {
            return true;
        }
    }
    return false;
}

/**
 * Write a SQL dump of the app tables to the private backup folder.
 * One statement per line so restore can replay it line by line.
 * Returns the file name, or null when there is nothing to back up.
 */
function hj_create_backup(string $reason = 'manual'): ?string
{
    $tables = array_values(array_filter(hj_app_tables(), 'hj_table_exists'));
    if (!$tables) {
        return null;
    }
    $dir = hj_backup_dir();
    if (!hj_ensure_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Backup folder is not writable: ' . $dir);
    }
    $reason = preg_replace('/[^a-z0-9-]/', '', strtolower($reason)) ?: 'manual';
    $name = 'backup-' . gmdate('Ymd-His') . '-' . $reason . '.sql';
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    $fh = @fopen($path, 'wb');
    if (!$fh) {
        throw new RuntimeException('Could not create backup file ' . $name);
    }

    $pdo = hj_db();
    fwrite($fh, '-- Hubjob Platform backup (' . $reason . ') created ' . hj_now() . " UTC\n");
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        fwrite($fh, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
        fwrite($fh, preg_replace('/\s*\R\s*/', ' ', $create[1]) . ";\n");

        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        $batch = [];
        $columns = null;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
            }
            $values = [];
            foreach ($row as $value) {
                $values[] = $value === null ? 'NULL' : $pdo->quote((string)$value);
            }
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) >= 100) {
                fwrite($fh, 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES ' . implode(', ', $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($fh, 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES ' . implode(', ', $batch) . ";\n");
        }
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
    @chmod($path, 0640);

    hj_rotate_backups(10);
    return $name;
}

function hj_list_backups(): array
{
    $files = glob(hj_backup_dir() . DIRECTORY_SEPARATOR . 'backup-*.sql') ?: [];
    $list = [];
    foreach ($files as $file) {
        $list[] = ['name' => basename($file), 'size' => filesize($file), 'mtime' => filemtime($file)];
    }
    usort($list, function ($a, $b) {
        return strcmp($b['name'], $a['name']);
    });
    return $list;
}

function hj_rotate_backups(int $keep): void
{
    foreach (array_slice(hj_list_backups(), $keep) as $old) {
        @unlink(hj_backup_dir() . DIRECTORY_SEPARATOR . $old['name']);
    }
}

function hj_backup_path(string $name): ?string
{
    if (!preg_match('/^backup-\d{8}-\d{6}-[a-z0-9-]+\.sql$/', $name)) {
        return null;
    }
    $path = hj_backup_dir() . DIRECTORY_SEPARATOR . $name;
    return is_file($path) ? $path : null;
}

/**
 * Replay a backup file. A fresh "pre-restore" backup is taken first.
 */
function hj_restore_backup(string $name): void
{
    $path = hj_backup_path($name);
    if ($path === null) {
        throw new RuntimeException('Backup file not found.');
    }
    hj_create_backup('pre-restore');

    $pdo = hj_db();
    $fh = fopen($path, 'rb');
    if (!$fh) {
        throw new RuntimeException('Could not read backup file.');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '' || strpos($line, '--') === 0) {
                continue;
            }
            $pdo->exec($line);
        }
    } finally {
        fclose($fh);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
