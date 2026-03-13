<?php
/*
 * Database configuration.
 */

function envValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function envInt(string $key, int $default): int
{
    $value = envValue($key);
    return $value !== null && is_numeric($value) ? (int) $value : $default;
}

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

define('APP_ENV', envValue('APP_ENV', 'development'));
define('APP_DEBUG', envValue('APP_DEBUG', APP_ENV === 'production' ? '0' : '1') === '1');
define('APP_URL', envValue('APP_URL', 'http://localhost:5173'));
define('ALLOWED_ORIGINS', envValue('ALLOWED_ORIGINS', APP_URL));

define('DB_HOST', envValue('DB_HOST', '127.0.0.1'));
define('DB_PORT', envValue('DB_PORT', '3306'));
define('DB_NAME', envValue('DB_NAME', 'beroeps2_taskpilot'));
define('DB_USER', envValue('DB_USER', 'groepjec'));
define('DB_PASS', envValue('DB_PASS', 'Votnm2Sy#z'));
define('DB_CHARSET', 'utf8mb4');

define('PASSWORD_MIN_LENGTH', envInt('PASSWORD_MIN_LENGTH', 10));

/* Session lifetime in seconds */
define('SESSION_LIFETIME', envInt('SESSION_LIFETIME', 86400));
define('SESSION_IDLE_TIMEOUT', envInt('SESSION_IDLE_TIMEOUT', 1800));
define('SESSION_REGENERATE_INTERVAL', envInt('SESSION_REGENERATE_INTERVAL', 900));
define('SESSION_COOKIE_NAME', APP_ENV === 'production' ? '__Host-taskpilot_session' : 'taskpilot_session');

define('MAX_JSON_BYTES', envInt('MAX_JSON_BYTES', 1024 * 1024));
define('SECURITY_LOG_FILE', envValue('SECURITY_LOG_FILE', sys_get_temp_dir() . '/taskpilot-security.log'));

define('RATE_LIMIT_GENERAL', envInt('RATE_LIMIT_GENERAL', 120));
define('RATE_LIMIT_GENERAL_WINDOW', envInt('RATE_LIMIT_GENERAL_WINDOW', 60));
define('RATE_LIMIT_READ', envInt('RATE_LIMIT_READ', 240));
define('RATE_LIMIT_READ_WINDOW', envInt('RATE_LIMIT_READ_WINDOW', 60));
define('RATE_LIMIT_AUTH', envInt('RATE_LIMIT_AUTH', 10));
define('RATE_LIMIT_AUTH_WINDOW', envInt('RATE_LIMIT_AUTH_WINDOW', 600));
define('RATE_LIMIT_PUBLIC', envInt('RATE_LIMIT_PUBLIC', 60));
define('RATE_LIMIT_PUBLIC_WINDOW', envInt('RATE_LIMIT_PUBLIC_WINDOW', 60));

/* ── PDO singleton ── */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* ── Session bootstrap ── */
function clearSessionState(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function initSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    session_name(SESSION_COOKIE_NAME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isHttpsRequest() || APP_ENV === 'production',
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);

    session_start();

    $now = time();

    if (isset($_SESSION['created_at']) && ($now - (int) $_SESSION['created_at']) > SESSION_LIFETIME) {
        clearSessionState();
        session_start();
    }

    if (isset($_SESSION['last_activity_at']) && ($now - (int) $_SESSION['last_activity_at']) > SESSION_IDLE_TIMEOUT) {
        clearSessionState();
        session_start();
    }

    if (!isset($_SESSION['created_at'])) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = $now;
        $_SESSION['rotated_at'] = $now;
    } elseif (($now - (int) ($_SESSION['rotated_at'] ?? 0)) > SESSION_REGENERATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['rotated_at'] = $now;
    }

    $_SESSION['last_activity_at'] = $now;
}
