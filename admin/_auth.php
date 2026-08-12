<?php
/* =========================================================
   Admin session, CSRF and login throttling.
   Every admin page includes this first.
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

const ADMIN_PASSWORD_KEY = 'admin_password_hash';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => COOKIE_SECURE,
]);
session_name('AXIONADM');
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------
   Password storage
   --------------------------------------------------------- */

function admin_password_is_set(): bool
{
    return setting_get(ADMIN_PASSWORD_KEY) !== null;
}

function admin_password_set(string $plain): void
{
    setting_set(ADMIN_PASSWORD_KEY, password_hash($plain, PASSWORD_DEFAULT));
}

function admin_password_verify(string $plain): bool
{
    $hash = setting_get(ADMIN_PASSWORD_KEY);
    return $hash !== null && password_verify($plain, $hash);
}

/* ---------------------------------------------------------
   Session state
   --------------------------------------------------------- */

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_ok']);
}

function admin_log_in(): void
{
    session_regenerate_id(true);
    $_SESSION['admin_ok'] = true;
    $_SESSION['admin_since'] = time();
}

function admin_log_out(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* Guard: call at the top of any protected page. */
function require_admin(): void
{
    if (!admin_password_is_set()) {
        header('Location: setup.php');
        exit;
    }
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/* ---------------------------------------------------------
   CSRF
   --------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $sent);
}

/* ---------------------------------------------------------
   Login throttling
   --------------------------------------------------------- */

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function login_attempts_recent(): int
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND created_at >= ?');
    $stmt->execute([client_ip(), $since]);

    return (int) ($stmt->fetch()['c'] ?? 0);
}

function login_attempt_record(): void
{
    db()->prepare('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)')
        ->execute([client_ip(), date('Y-m-d H:i:s')]);
}

function login_attempts_clear(): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
}

function login_is_locked(): bool
{
    return login_attempts_recent() >= LOGIN_MAX_ATTEMPTS;
}
