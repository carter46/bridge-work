<?php
/**
 * Idempotent database auto-migrator (same approach as axion-trust-bank's DatabaseAutoMigrate).
 *
 * Pending migrations in database/auto-migrations/ are applied when an admin loads any admin page,
 * and on the login and setup pages (before an admin exists). No phpMyAdmin imports needed.
 *
 * Add a new file named like 2026_10_09_000005_short_name.php that returns:
 *   ['id' => '2026_10_09_000005_short_name', 'description' => '...', 'up' => function (PDO $db) { ... }]
 * Never edit a migration that has already been applied; add a new one instead.
 */

class HJ_AutoMigrate
{
    const LOCK_NAME = 'hubjob_migrations';

    private static $ran = false;
    private static $lastResult = null;

    public static function directory(): string
    {
        return HJ_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'auto-migrations';
    }

    /**
     * Apply pending migrations once per request.
     */
    public static function run(?int $adminId = null): array
    {
        if (self::$ran) {
            return self::$lastResult;
        }
        self::$ran = true;

        $result = ['applied' => [], 'failed' => [], 'skipped' => 0, 'errors' => [], 'backup' => null];
        $pdo = hj_db();
        $locked = false;

        try {
            self::ensureTrackingTable($pdo);
            $migrations = self::discover();
            $pending = self::pending($pdo, $migrations);
            $result['skipped'] = count($migrations) - count($pending);

            if ($pending) {
                $locked = (int)$pdo->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 10)")->fetchColumn() === 1;
                if (!$locked) {
                    $result['errors'][] = 'Another database update is already running. Reload the page in a moment.';
                } else {
                    // Another request may have applied them while we waited for the lock.
                    $pending = self::pending($pdo, $migrations);
                    if ($pending && hj_has_app_data()) {
                        $result['backup'] = hj_create_backup('pre-migration');
                    }
                    foreach ($pending as $migration) {
                        $id = $migration['id'];
                        $description = $migration['description'] ?? $id;
                        try {
                            call_user_func($migration['up'], $pdo);
                            self::record($pdo, $id, $description, 'success', null, $adminId);
                            $result['applied'][] = ['id' => $id, 'description' => $description];
                        } catch (Throwable $e) {
                            $message = $e->getMessage();
                            self::record($pdo, $id, $description, 'failed', $message, $adminId);
                            $result['failed'][] = ['id' => $id, 'description' => $description, 'error' => $message];
                            $result['errors'][] = $id . ': ' . $message;
                            error_log('[hubjob] Migration failed [' . $id . ']: ' . $message);
                            // Later migrations may depend on this one; stop and retry on the next page load.
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Database update runner failed: ' . $e->getMessage();
            error_log('[hubjob] Auto-migrate error: ' . $e->getMessage());
        } finally {
            if ($locked) {
                $pdo->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
            }
        }

        if ($result['applied']) {
            hj_schema_ready(true);
            hj_settings_all(true);
            try {
                hj_regenerate_snapshots();
            } catch (Throwable $e) {
                $result['errors'][] = 'Public snapshot files could not be written: ' . $e->getMessage();
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($result['applied']) {
                $_SESSION['hj_mig_applied'] = array_map(function ($m) {
                    return $m['description'] . ' (' . $m['id'] . ')';
                }, $result['applied']);
            }
            if ($result['errors']) {
                $_SESSION['hj_mig_errors'] = $result['errors'];
            } else {
                unset($_SESSION['hj_mig_errors']);
            }
        }

        self::$lastResult = $result;
        return $result;
    }

    /** All migration files, sorted by file name. */
    public static function discover(): array
    {
        $files = glob(self::directory() . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $migrations = [];
        foreach ($files as $file) {
            if (basename($file) === 'index.php') {
                continue;
            }
            $data = include $file;
            if (!is_array($data) || empty($data['id']) || !isset($data['up']) || !is_callable($data['up'])) {
                throw new RuntimeException('Invalid migration file: ' . basename($file));
            }
            $data['file'] = basename($file);
            $migrations[] = $data;
        }
        return $migrations;
    }

    /** Status rows for the Database Updates page. */
    public static function status(): array
    {
        $pdo = hj_db();
        self::ensureTrackingTable($pdo);
        $records = [];
        foreach ($pdo->query('SELECT * FROM auto_migrations')->fetchAll() as $row) {
            $records[$row['id']] = $row;
        }
        $rows = [];
        foreach (self::discover() as $migration) {
            $record = $records[$migration['id']] ?? null;
            $rows[] = [
                'id'          => $migration['id'],
                'description' => $migration['description'] ?? $migration['id'],
                'status'      => $record['status'] ?? 'pending',
                'error'       => $record['error_message'] ?? null,
                'applied_at'  => $record['applied_at'] ?? null,
                'updated_at'  => $record['updated_at'] ?? null,
            ];
        }
        return $rows;
    }

    private static function pending(PDO $pdo, array $migrations): array
    {
        $applied = [];
        foreach ($pdo->query("SELECT id FROM auto_migrations WHERE status = 'success'")->fetchAll() as $row) {
            $applied[$row['id']] = true;
        }
        return array_values(array_filter($migrations, function ($m) use ($applied) {
            return !isset($applied[$m['id']]);
        }));
    }

    private static function ensureTrackingTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `auto_migrations` (
            `id` VARCHAR(191) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('success','failed') NOT NULL DEFAULT 'success',
            `error_message` TEXT DEFAULT NULL,
            `applied_by` INT UNSIGNED DEFAULT NULL,
            `applied_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function record(PDO $pdo, string $id, string $description, string $status, ?string $error, ?int $adminId): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO auto_migrations (id, description, status, error_message, applied_by, applied_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE description = VALUES(description), status = VALUES(status),
                error_message = VALUES(error_message), applied_by = VALUES(applied_by),
                applied_at = IF(VALUES(status) = \'success\', VALUES(applied_at), applied_at), updated_at = VALUES(updated_at)'
        );
        $now = hj_now();
        $stmt->execute([$id, hj_substr($description, 0, 255), $status, $error !== null ? hj_substr($error, 0, 2000) : null, $adminId, $now, $now]);
    }

    /* ---------- Idempotent helpers for migration files ---------- */

    public static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** CREATE TABLE IF NOT EXISTS wrapper; $createSql must start with "CREATE TABLE IF NOT EXISTS". */
    public static function ensureTable(PDO $db, string $createSql): void
    {
        $db->exec($createSql);
    }

    /** Add a column if missing. Returns true if it was added. */
    public static function ensureColumn(PDO $db, string $table, string $column, string $definitionSql): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }
        $db->exec('ALTER TABLE `' . preg_replace('/[^A-Za-z0-9_]/', '', $table) . '` ADD COLUMN ' . $definitionSql);
        return true;
    }

    /** Add an index if missing. $definitionSql example: "INDEX `idx_name` (`col`)". */
    public static function ensureIndex(PDO $db, string $table, string $index, string $definitionSql): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $stmt->execute([$table, $index]);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }
        $db->exec('ALTER TABLE `' . preg_replace('/[^A-Za-z0-9_]/', '', $table) . '` ADD ' . $definitionSql);
        return true;
    }

    /** Insert a setting only if it doesn't exist yet (never overwrites admin changes). */
    public static function ensureSetting(PDO $db, string $key, string $value): bool
    {
        $stmt = $db->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)');
        $stmt->execute([$key, $value, hj_now()]);
        return $stmt->rowCount() > 0;
    }
}

/**
 * Run pending migrations for an admin request (wrapped so a failure never blocks the admin area).
 */
function hj_run_auto_migrations(?int $adminId = null): array
{
    try {
        return HJ_AutoMigrate::run($adminId);
    } catch (Throwable $e) {
        error_log('[hubjob] Auto-migrate bootstrap error: ' . $e->getMessage());
        $errors = ['Database update runner failed: ' . $e->getMessage()];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['hj_mig_errors'] = $errors;
        }
        return ['applied' => [], 'failed' => [], 'skipped' => 0, 'errors' => $errors, 'backup' => null];
    }
}
