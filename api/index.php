<?php
/*
 * API entry-point / router.
 *
 * All requests to /api/* are funnelled here by .htaccess.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';

initSession();

/* ── CORS (development) ── */
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');

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

/* ── Routing ── */
$m = method();
$p = path();

/* ─ Auth ─ */
if ($p === '/auth/register'  && $m === 'POST')  handleRegister();
if ($p === '/auth/login'     && $m === 'POST')  handleLogin();
if ($p === '/auth/logout'    && $m === 'POST')  handleLogout();
if ($p === '/auth/me'        && $m === 'GET')   handleMe();

/* ─ Projects list / create ─ */
if ($p === '/projects'       && $m === 'GET')   handleListProjects();
if ($p === '/projects'       && $m === 'POST')  handleCreateProject();

/* ─ Single project ─ */
if ($params = matchRoute('/projects/{id}', $p)) {
    $id = (int) $params['id'];
    if ($m === 'GET')    handleGetProject($id);
    if ($m === 'PATCH')  handleUpdateProject($id);
    if ($m === 'DELETE') handleDeleteProject($id);
}

/* ─ Project archive / restore ─ */
if ($params = matchRoute('/projects/{id}/archive', $p)) {
    $id = (int) $params['id'];
    if ($m === 'POST')   handleArchiveProject($id);
}
if ($params = matchRoute('/projects/{id}/restore', $p)) {
    $id = (int) $params['id'];
    if ($m === 'POST')   handleRestoreProject($id);
}

/* ─ Project activity ─ */
if ($params = matchRoute('/projects/{id}/activity', $p)) {
    if ($m === 'GET') handleGetActivity((int) $params['id']);
}

/* ─ Members ─ */
if ($params = matchRoute('/projects/{id}/members', $p)) {
    $pid = (int) $params['id'];
    if ($m === 'POST') handleAddMember($pid);
}
if ($params = matchRoute('/members/{id}', $p)) {
    $mid = (int) $params['id']; // this is user_id within a project context
    // We pass project_id via query string for member routes
}
if ($params = matchRoute('/projects/{pid}/members/{uid}', $p)) {
    $pid = (int) $params['pid'];
    $uid = (int) $params['uid'];
    if ($m === 'PATCH')  handleUpdateMemberRole($pid, $uid);
    if ($m === 'DELETE') handleRemoveMember($pid, $uid);
}

/* ─ Share ─ */
if ($params = matchRoute('/projects/{id}/share', $p)) {
    $id = (int) $params['id'];
    if ($m === 'POST')   handleGenerateShare($id);
    if ($m === 'DELETE') handleRevokeShare($id);
}
if ($params = matchRoute('/public/{token}', $p)) {
    if ($m === 'GET') handleGetPublicProject($params['token']);
}

/* ─ Groups ─ */
if ($params = matchRoute('/projects/{id}/groups', $p)) {
    $pid = (int) $params['id'];
    if ($m === 'POST') handleCreateGroup($pid);
}
if ($params = matchRoute('/groups/{id}', $p)) {
    $gid = (int) $params['id'];
    if ($m === 'PATCH')  handleUpdateGroup($gid);
    if ($m === 'DELETE') handleDeleteGroup($gid);
}
if ($params = matchRoute('/groups/{id}/archive', $p)) {
    if ($m === 'POST') handleArchiveGroup((int) $params['id']);
}
if ($params = matchRoute('/groups/{id}/restore', $p)) {
    if ($m === 'POST') handleRestoreGroup((int) $params['id']);
}

/* ─ Tasks ─ */
if ($params = matchRoute('/projects/{id}/tasks', $p)) {
    if ($m === 'POST') handleCreateTask((int) $params['id']);
}
if ($params = matchRoute('/tasks/{id}', $p)) {
    $tid = (int) $params['id'];
    if ($m === 'PATCH')  handleUpdateTask($tid);
    if ($m === 'DELETE') handleDeleteTask($tid);
}
if ($params = matchRoute('/tasks/{id}/move', $p)) {
    if ($m === 'PATCH') handleMoveTask((int) $params['id']);
}
if ($params = matchRoute('/tasks/{id}/schedule', $p)) {
    $tid = (int) $params['id'];
    if ($m === 'PATCH')  handleScheduleTask($tid);
    if ($m === 'DELETE') handleUnscheduleTask($tid);
}

/* ─ Labels ─ */
if ($params = matchRoute('/projects/{id}/labels', $p)) {
    if ($m === 'POST') handleCreateLabel((int) $params['id']);
}
if ($params = matchRoute('/labels/{id}', $p)) {
    $lid = (int) $params['id'];
    if ($m === 'PATCH')  handleUpdateLabel($lid);
    if ($m === 'DELETE') handleDeleteLabel($lid);
}

/* ─ Comments ─ */
if ($params = matchRoute('/tasks/{id}/comments', $p)) {
    if ($m === 'POST') handleAddComment((int) $params['id']);
}
if ($params = matchRoute('/comments/{id}', $p)) {
    $cid = (int) $params['id'];
    if ($m === 'PATCH')  handleEditComment($cid);
    if ($m === 'DELETE') handleDeleteComment($cid);
}
if ($params = matchRoute('/comments/{id}/pin', $p)) {
    if ($m === 'PATCH') handlePinComment((int) $params['id']);
}

/* ─ Notes ─ */
if ($params = matchRoute('/tasks/{id}/notes', $p)) {
    if ($m === 'POST') handleAddNote((int) $params['id']);
}
if ($params = matchRoute('/notes/{id}', $p)) {
    $nid = (int) $params['id'];
    if ($m === 'PATCH')  handleUpdateNote($nid);
    if ($m === 'DELETE') handleDeleteNote($nid);
}

/* ── 404 fallback ── */
jsonError('Not found', 404);
