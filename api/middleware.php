<?php
/*
 * Auth & authorization middleware.
 */

function requireCsrfProtection(): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        securityLog('csrf_rejected');
        jsonError('Invalid CSRF token', 419);
    }
}

function applyRequestGuards(string $method, string $path): void
{
    if ($method === 'POST' || $method === 'PATCH') {
        requireJsonRequest();
    }

    $ip = clientIp();

    if (str_starts_with($path, '/public/')) {
        enforceRateLimit('public:' . $ip, RATE_LIMIT_PUBLIC, RATE_LIMIT_PUBLIC_WINDOW);
        return;
    }

    if ($path === '/auth/me') {
        enforceRateLimit('auth-me:' . $ip, RATE_LIMIT_READ, RATE_LIMIT_READ_WINDOW);
        return;
    }

    if ($path === '/auth/login' || $path === '/auth/register') {
        enforceRateLimit('auth-route:' . $ip . ':' . $path, RATE_LIMIT_AUTH, RATE_LIMIT_AUTH_WINDOW);
    } elseif (isSafeMethod($method)) {
        enforceRateLimit('read:' . $ip, RATE_LIMIT_READ, RATE_LIMIT_READ_WINDOW);
    } else {
        enforceRateLimit('write:' . $ip, RATE_LIMIT_GENERAL, RATE_LIMIT_GENERAL_WINDOW);
    }

    if (!isSafeMethod($method)) {
        requireCsrfProtection();
    }
}

/**
 * Return current user_id from session, or null if not logged in.
 */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Require an authenticated user. Sends 401 and exits if not logged in.
 */
function requireAuth(): int
{
    $uid = currentUserId();
    if (!$uid) jsonError('Authentication required', 401);
    return $uid;
}

/**
 * Get the current user's role in a project.
 * Returns null if the user is not a member.
 */
function projectRole(int $projectId, int $userId): ?string
{
    $stmt = db()->prepare('SELECT role FROM project_members WHERE project_id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : null;
}

/**
 * Require that the current user is a member of the project.
 * Returns the role string. Sends 403 if not a member.
 */
function requireProjectAccess(int $projectId, int $userId): string
{
    $role = projectRole($projectId, $userId);
    if (!$role) jsonError('You do not have access to this project', 403);
    return $role;
}

/**
 * Require owner or admin role. Returns role string.
 */
function requireProjectAdmin(int $projectId, int $userId): string
{
    $role = requireProjectAccess($projectId, $userId);
    if ($role === 'collaborator') {
        jsonError('Insufficient permissions — admin or owner required', 403);
    }
    return $role;
}

/**
 * Require owner role.
 */
function requireProjectOwner(int $projectId, int $userId): void
{
    $role = requireProjectAccess($projectId, $userId);
    if ($role !== 'owner') {
        jsonError('Insufficient permissions — owner required', 403);
    }
}

/**
 * Log an activity entry for a project.
 */
function logActivity(int $projectId, ?int $userId, string $type, string $message): void
{
    $stmt = db()->prepare(
        'INSERT INTO activity_log (project_id, user_id, type, message) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$projectId, $userId, $type, $message]);
}
