<?php
/*
 * API entry-point.
 *
 * Provides centralized routing/dispatch for all API handlers.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';

bootstrapAppSecurity();
initSession();

applyCorsHeaders();
applySecurityHeaders();

if (method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ── Load route handlers ── */
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/projects.php';
require_once __DIR__ . '/routes/groups.php';
require_once __DIR__ . '/routes/tasks.php';
require_once __DIR__ . '/routes/labels.php';
require_once __DIR__ . '/routes/comments.php';
require_once __DIR__ . '/routes/notes.php';
require_once __DIR__ . '/routes/members.php';
require_once __DIR__ . '/routes/share.php';

/**
 * @param array<string,string> $params
 */
function route(string $method, string $pattern, callable $handler): array
{
    return [
        'method' => strtoupper($method),
        'pattern' => $pattern,
        'handler' => $handler,
    ];
}

/**
 * @return array<int,array{method:string,pattern:string,handler:callable}>
 */
function defineRoutes(): array
{
    return [
        route('GET', '/csrf', static fn() => jsonResponse(['token' => csrfToken()])),

        // Auth
        route('POST', '/auth/register', static fn() => handleRegister()),
        route('POST', '/auth/login', static fn() => handleLogin()),
        route('POST', '/auth/logout', static fn() => handleLogout()),
        route('GET', '/auth/me', static fn() => handleMe()),

        // Projects
        route('GET', '/projects', static fn() => handleListProjects()),
        route('POST', '/projects', static fn() => handleCreateProject()),
        route('GET', '/projects/{id}', static fn(array $p) => handleGetProject((int) $p['id'])),
        route('PATCH', '/projects/{id}', static fn(array $p) => handleUpdateProject((int) $p['id'])),
        route('DELETE', '/projects/{id}', static fn(array $p) => handleDeleteProject((int) $p['id'])),
        route('POST', '/projects/{id}/archive', static fn(array $p) => handleArchiveProject((int) $p['id'])),
        route('POST', '/projects/{id}/restore', static fn(array $p) => handleRestoreProject((int) $p['id'])),
        route('GET', '/projects/{id}/activity', static fn(array $p) => handleGetActivity((int) $p['id'])),

        // Members
        route('POST', '/projects/{id}/members', static fn(array $p) => handleAddMember((int) $p['id'])),
        route('PATCH', '/projects/{pid}/members/{uid}', static fn(array $p) => handleUpdateMemberRole((int) $p['pid'], (int) $p['uid'])),
        route('DELETE', '/projects/{pid}/members/{uid}', static fn(array $p) => handleRemoveMember((int) $p['pid'], (int) $p['uid'])),

        // Share
        route('POST', '/projects/{id}/share', static fn(array $p) => handleGenerateShare((int) $p['id'])),
        route('DELETE', '/projects/{id}/share', static fn(array $p) => handleRevokeShare((int) $p['id'])),
        route('GET', '/public/{token}', static fn(array $p) => handleGetPublicProject($p['token'])),

        // Groups
        route('POST', '/projects/{id}/groups', static fn(array $p) => handleCreateGroup((int) $p['id'])),
        route('PATCH', '/groups/{id}', static fn(array $p) => handleUpdateGroup((int) $p['id'])),
        route('DELETE', '/groups/{id}', static fn(array $p) => handleDeleteGroup((int) $p['id'])),
        route('POST', '/groups/{id}/archive', static fn(array $p) => handleArchiveGroup((int) $p['id'])),
        route('POST', '/groups/{id}/restore', static fn(array $p) => handleRestoreGroup((int) $p['id'])),

        // Tasks
        route('POST', '/projects/{id}/tasks', static fn(array $p) => handleCreateTask((int) $p['id'])),
        route('PATCH', '/tasks/{id}', static fn(array $p) => handleUpdateTask((int) $p['id'])),
        route('DELETE', '/tasks/{id}', static fn(array $p) => handleDeleteTask((int) $p['id'])),
        route('PATCH', '/tasks/{id}/move', static fn(array $p) => handleMoveTask((int) $p['id'])),
        route('PATCH', '/tasks/{id}/schedule', static fn(array $p) => handleScheduleTask((int) $p['id'])),
        route('DELETE', '/tasks/{id}/schedule', static fn(array $p) => handleUnscheduleTask((int) $p['id'])),

        // Labels
        route('POST', '/projects/{id}/labels', static fn(array $p) => handleCreateLabel((int) $p['id'])),
        route('PATCH', '/labels/{id}', static fn(array $p) => handleUpdateLabel((int) $p['id'])),
        route('DELETE', '/labels/{id}', static fn(array $p) => handleDeleteLabel((int) $p['id'])),

        // Comments
        route('POST', '/tasks/{id}/comments', static fn(array $p) => handleAddComment((int) $p['id'])),
        route('PATCH', '/comments/{id}', static fn(array $p) => handleEditComment((int) $p['id'])),
        route('DELETE', '/comments/{id}', static fn(array $p) => handleDeleteComment((int) $p['id'])),
        route('PATCH', '/comments/{id}/pin', static fn(array $p) => handlePinComment((int) $p['id'])),

        // Notes
        route('POST', '/tasks/{id}/notes', static fn(array $p) => handleAddNote((int) $p['id'])),
        route('PATCH', '/notes/{id}', static fn(array $p) => handleUpdateNote((int) $p['id'])),
        route('DELETE', '/notes/{id}', static fn(array $p) => handleDeleteNote((int) $p['id'])),
    ];
}

/**
 * @param array<int,array{method:string,pattern:string,handler:callable}> $routes
 */
function dispatch(array $routes, string $requestMethod, string $requestPath): void
{
    $allowedMethods = [];

    foreach ($routes as $route) {
        $params = matchRoute($route['pattern'], $requestPath);
        if ($params === null) {
            continue;
        }

        if ($route['method'] !== $requestMethod) {
            $allowedMethods[] = $route['method'];
            continue;
        }

        ($route['handler'])($params);
        return;
    }

    if ($allowedMethods !== []) {
        header('Allow: ' . implode(', ', array_values(array_unique($allowedMethods))));
        jsonError('Method not allowed', 405);
    }

    jsonError('Not found', 404);
}

$m = method();
$p = path();

applyRequestGuards($m, $p);
dispatch(defineRoutes(), $m, $p);
