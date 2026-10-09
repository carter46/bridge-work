<?php

const HJ_IDLE_TIMEOUT = 1800;        // 30 minutes
const HJ_LOGIN_MAX_FAILURES = 5;     // per IP
const HJ_LOGIN_WINDOW = 900;         // 15 minutes

function hj_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'))), '/') . '/';
    session_name('hj_admin');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $path,
        'secure'   => hj_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function hj_flash(string $type, string $message): void
{
    hj_session_start();
    $_SESSION['hj_flash'][] = ['type' => $type, 'message' => $message];
}

function hj_take_flashes(): array
{
    hj_session_start();
    $flashes = $_SESSION['hj_flash'] ?? [];
    unset($_SESSION['hj_flash']);
    return $flashes;
}

function hj_logout_session(): void
{
    hj_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => $p['httponly'], 'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * The signed-in admin, or null. Enforces the idle timeout and re-checks the account on every request.
 */
function hj_current_admin(): ?array
{
    static $resolved = false;
    static $admin = null;
    if ($resolved) {
        return $admin;
    }
    $resolved = true;
    hj_session_start();

    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if (time() - (int)($_SESSION['last_activity'] ?? 0) > HJ_IDLE_TIMEOUT) {
        hj_logout_session();
        hj_flash('info', 'You were signed out after 30 minutes of inactivity.');
        return null;
    }
    $_SESSION['last_activity'] = time();

    if (!hj_schema_ready()) {
        return null;
    }
    $row = hj_db_one('SELECT id, name, email, role, is_active FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
    if (!$row || !(int)$row['is_active']) {
        hj_logout_session();
        return null;
    }
    $admin = $row;
    return $admin;
}

function hj_is_owner(?array $admin = null): bool
{
    $admin = $admin ?? hj_current_admin();
    return $admin !== null && $admin['role'] === 'owner';
}

/**
 * Gate for every admin page. Applies pending database updates, then checks the role.
 */
function hj_require_admin(?string $role = null): array
{
    $admin = hj_current_admin();
    if ($admin === null) {
        $next = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        if (!empty($_SERVER['QUERY_STRING'])) {
            $next .= '?' . $_SERVER['QUERY_STRING'];
        }
        hj_redirect('login.php?next=' . rawurlencode($next));
    }

    hj_run_auto_migrations((int)$admin['id']);

    if ($role === 'owner' && $admin['role'] !== 'owner') {
        http_response_code(403);
        hj_admin_header('Not allowed', '');
        echo '<div class="bg-white rounded-xl border border-slate-200 p-8 text-center"><h1 class="text-lg font-bold text-primary mb-2">Owner access required</h1>'
            . '<p class="text-sm text-text-muted">Only owner accounts can open this page.</p></div>';
        hj_admin_footer();
        exit;
    }
    return $admin;
}

function hj_login_throttled(string $ipHash): bool
{
    $failures = (int)hj_db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND success = 0 AND attempted_at >= ?',
        [$ipHash, gmdate('Y-m-d H:i:s', time() - HJ_LOGIN_WINDOW)]
    );
    return $failures >= HJ_LOGIN_MAX_FAILURES;
}

/**
 * Returns null on success, or an error message.
 */
function hj_attempt_login(string $email, string $password): ?string
{
    $ipHash = hj_ip_hash();
    if (hj_login_throttled($ipHash)) {
        return 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
    }

    $row = hj_db_one('SELECT * FROM admins WHERE email = ?', [strtolower($email)]);
    if ($row === null) {
        // Spend the same hashing time as a real check, so timing doesn't reveal which emails exist.
        password_hash($password, PASSWORD_DEFAULT);
        $valid = false;
    } else {
        $valid = password_verify($password, $row['password_hash']) && (int)$row['is_active'] === 1;
    }

    hj_db_exec(
        'INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?, ?, ?, ?)',
        [$ipHash, hj_substr(strtolower($email), 0, 190), $valid ? 1 : 0, hj_now()]
    );

    if (!$valid) {
        return 'Incorrect email or password.';
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf']);

    $updates = ['last_login_at' => hj_now()];
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $updates['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    $set = implode(', ', array_map(function ($k) {
        return $k . ' = ?';
    }, array_keys($updates)));
    hj_db_exec('UPDATE admins SET ' . $set . ' WHERE id = ?', array_merge(array_values($updates), [(int)$row['id']]));
    hj_audit('login', 'admin', (int)$row['id']);
    return null;
}

function hj_validate_password(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'Passwords must be at least 10 characters.';
    }
    return null;
}
