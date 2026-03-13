<?php
/*
 * Auth & authorization middleware.
 */

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
