<?php
/*
 * Shared helper functions used across all API routes.
 */

/* ── JSON responses ── */

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
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

function validColor(?string $color): bool
{
    if ($color === null || $color === '') return true;
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
}

function clampString(?string $s, int $max): ?string
{
    if ($s === null) return null;
    return mb_substr(trim($s), 0, $max);
}

/* ── Route matching ── */

function method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

function path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Strip /api prefix
    $uri = preg_replace('#^/api#', '', $uri);
    return $uri ?: '/';
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
