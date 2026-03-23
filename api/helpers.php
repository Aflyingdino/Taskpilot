<?php
/*
 * Shared helper functions used across all API routes.
 */

function bootstrapAppSecurity(): void
{
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $e): void {
        securityLog('server_exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'path' => path(),
            'method' => method(),
        ]);

        if (APP_DEBUG) {
            jsonResponse([
                'error' => 'Internal server error',
                'details' => $e->getMessage(),
            ], 500);
        }

        jsonError('Internal server error', 500);
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        securityLog('fatal_error', [
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'path' => path(),
            'method' => method(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Internal server error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    });
}

function applySecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
}

function applyCorsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = array_filter(array_map('trim', explode(',', ALLOWED_ORIGINS)));

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    }
}

function securityLog(string $event, array $context = []): void
{
    $payload = [
        'ts' => gmdate('c'),
        'event' => $event,
        'ip' => clientIp(),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'context' => $context,
    ];

    error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, SECURITY_LOG_FILE);
}

function clientIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function ensureRateLimitDir(): string
{
    $dir = sys_get_temp_dir() . '/vecta-rate-limit';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

function enforceRateLimit(string $bucket, int $limit, int $windowSeconds): void
{
    $dir = ensureRateLimitDir();
    $file = $dir . '/' . sha1($bucket) . '.json';
    $now = time();
    $windowStart = $now - $windowSeconds;

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $entries = json_decode($raw ?: '[]', true);
    if (!is_array($entries)) {
        $entries = [];
    }

    $entries = array_values(array_filter($entries, static fn($ts) => is_int($ts) && $ts >= $windowStart));

    if (count($entries) >= $limit) {
        $retryAfter = max(1, $windowSeconds - ($now - (int) $entries[0]));
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($entries));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        header('Retry-After: ' . $retryAfter);
        securityLog('rate_limited', ['bucket' => $bucket, 'retryAfter' => $retryAfter]);
        jsonError('Too many requests', 429);
    }

    $entries[] = $now;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($entries));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

/* ── JSON responses ── */

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): never
{
    jsonResponse(['error' => $message], $status);
}

function jsonSuccess(string $message = 'ok'): never
{
    jsonResponse(['message' => $message]);
}

/* ── Input parsing ── */

function jsonBody(): array
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > MAX_JSON_BYTES) {
        jsonError('Request body too large', 413);
    }

    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > MAX_JSON_BYTES) {
        jsonError('Request body too large', 413);
    }

    $data = json_decode($raw, true);
    if ($raw !== '' && $data === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Malformed JSON payload', 400);
    }

    return is_array($data) ? $data : [];
}

function requireJsonRequest(): void
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($contentType === '' || !str_contains($contentType, 'application/json')) {
        jsonError('Content-Type must be application/json', 415);
    }
}

function requireFields(array $data, array $fields): void
{
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            jsonError("Missing required field: $f", 422);
        }
    }
}

/* ── Validation helpers ── */

function validEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function normalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

function validPassword(string $password): bool
{
    return mb_strlen($password) >= PASSWORD_MIN_LENGTH
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/\d/', $password);
}

function validColor(?string $color): bool
{
    if ($color === null || $color === '') return true;
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
}

function validDateString(?string $value): bool
{
    if ($value === null || $value === '') return true;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

function validDateTimeString(?string $value): bool
{
    if ($value === null || $value === '') return true;
    return strtotime($value) !== false;
}

function clampString(?string $s, int $max): ?string
{
    if ($s === null) return null;
    return mb_substr(trim($s), 0, $max);
}

function usernameBlocklist(): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $path = __DIR__ . '/username_blocklist.php';
    if (!file_exists($path)) {
        $cache = [];
        return $cache;
    }

    $raw = require $path;
    if (!is_array($raw)) {
        $cache = [];
        return $cache;
    }

    $cache = array_values(array_unique(array_filter(array_map(static function ($item): string {
        return is_string($item) ? trim($item) : '';
    }, $raw), static fn($item) => $item !== '')));

    return $cache;
}

function normalizeModerationText(string $value): string
{
    $value = mb_strtolower($value);
    $value = strtr($value, [
        '0' => 'o',
        '1' => 'i',
        '3' => 'e',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
        '8' => 'b',
        '@' => 'a',
        '$' => 's',
        '!' => 'i',
    ]);

    $value = preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    return preg_replace('/(.)\1+/', '$1', $value) ?? '';
}

function hasBlockedUsernameContent(string $name): bool
{
    $normalizedName = normalizeModerationText($name);
    if ($normalizedName === '') {
        return false;
    }

    foreach (usernameBlocklist() as $blockedWord) {
        $normalizedBlockedWord = normalizeModerationText($blockedWord);
        if ($normalizedBlockedWord !== '' && str_contains($normalizedName, $normalizedBlockedWord)) {
            return true;
        }
    }

    return false;
}

function validateUsername(string $name): void
{
    if (hasBlockedUsernameContent($name)) {
        jsonError('Username contains blocked language', 422);
    }
}

function requireEnumValue(mixed $value, array $allowed, string $field): string
{
    if (!is_string($value) || !in_array($value, $allowed, true)) {
        jsonError("Invalid $field", 422);
    }
    return $value;
}

function requireNullableColor(mixed $value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || !validColor($value)) {
        jsonError("Invalid $field", 422);
    }
    return $value;
}

function requireNullableDate(mixed $value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || !validDateString($value)) {
        jsonError("Invalid $field", 422);
    }
    return $value;
}

function requireNullableDateTime(mixed $value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || !validDateTimeString($value)) {
        jsonError("Invalid $field", 422);
    }
    return $value;
}

function requireIntArray(mixed $value, string $field, int $maxItems = 100): array
{
    if ($value === null) {
        return [];
    }
    if (!is_array($value)) {
        jsonError("Invalid $field", 422);
    }

    $result = [];
    foreach ($value as $item) {
        if (!is_numeric($item)) {
            jsonError("Invalid $field", 422);
        }
        $result[] = (int) $item;
    }

    $result = array_values(array_unique(array_filter($result, static fn($id) => $id > 0)));

    if (count($result) > $maxItems) {
        jsonError("Too many items in $field", 422);
    }

    return $result;
}

function requireBoundedInt(mixed $value, string $field, int $min, int $max): int
{
    if (!is_numeric($value)) {
        jsonError("Invalid $field", 422);
    }

    $int = (int) $value;
    if ($int < $min || $int > $max) {
        jsonError("Invalid $field", 422);
    }

    return $int;
}

function isSafeMethod(?string $method = null): bool
{
    $method ??= method();
    return in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function rotateCsrfToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/* ── Route matching ── */

function method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

function path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

    // When deployed under a subdirectory (e.g. /myapp/api), strip script dir first.
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
        if (str_starts_with($uri, $scriptDir . '/')) {
            $uri = (string) substr($uri, strlen($scriptDir));
        } elseif ($uri === $scriptDir) {
            $uri = '/';
        }
    }

    $uri = '/' . ltrim($uri, '/');

    // Keep dev-router compatibility where requests arrive as /api/*.
    if ($uri === '/api') {
        return '/';
    }
    if (str_starts_with($uri, '/api/')) {
        return (string) substr($uri, 4);
    }

    return $uri;
}

/**
 * Match a route pattern like '/projects/{id}/groups'.
 * Returns an associative array of matched params, or null if no match.
 */
function matchRoute(string $pattern, string $path): ?array
{
    $patternParts = explode('/', trim($pattern, '/'));
    $pathParts    = explode('/', trim($path, '/'));

    if (count($patternParts) !== count($pathParts)) return null;

    $params = [];
    for ($i = 0; $i < count($patternParts); $i++) {
        $pp = $patternParts[$i];
        $vp = $pathParts[$i];
        if (str_starts_with($pp, '{') && str_ends_with($pp, '}')) {
            $key = trim($pp, '{}');
            $params[$key] = $vp;
        } elseif ($pp !== $vp) {
            return null;
        }
    }
    return $params;
}
