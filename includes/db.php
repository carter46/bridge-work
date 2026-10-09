<?php

function hj_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = hj_config('db', []);
    if (strpos((string)($db['name'] ?? ''), 'CHANGE_ME') === 0 || strpos((string)($db['user'] ?? ''), 'CHANGE_ME') === 0) {
        hj_bootstrap_fail('Open config/config.php and replace the CHANGE_ME values with your database name, user and password.');
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'] ?? 'localhost',
        (int)($db['port'] ?? 3306),
        $db['name'] ?? ''
    );
    try {
        $pdo = new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        error_log('[hubjob] Database connection failed: ' . $e->getMessage());
        hj_bootstrap_fail('Could not connect to the database. Check the details in config/config.php.');
    }
    return $pdo;
}

function hj_db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = hj_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function hj_db_all(string $sql, array $params = []): array
{
    return hj_db_query($sql, $params)->fetchAll();
}

function hj_db_one(string $sql, array $params = []): ?array
{
    $row = hj_db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function hj_db_value(string $sql, array $params = [])
{
    $value = hj_db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function hj_db_exec(string $sql, array $params = []): int
{
    return hj_db_query($sql, $params)->rowCount();
}

function hj_table_exists(string $table): bool
{
    $count = hj_db_value(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    );
    return (int)$count > 0;
}

/** True once the core schema migration has created the main tables. */
function hj_schema_ready(bool $refresh = false): bool
{
    static $ready = null;
    if ($ready === null || $refresh) {
        try {
            $ready = hj_table_exists('jobs') && hj_table_exists('settings') && hj_table_exists('categories');
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}
