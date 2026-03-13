<?php
/*
 * Member routes: add, update role, remove
 */

function handleAddMember(int $projectId): never
{
    $uid = requireAuth();
    requireProjectAdmin($projectId, $uid);

    $data = jsonBody();
    requireFields($data, ['email']);

    $email = strtolower(trim($data['email']));
    $role  = $data['role'] ?? 'collaborator';

    // Validate role
    if (!in_array($role, ['admin', 'collaborator'])) {
        jsonError('Invalid role. Use "admin" or "collaborator"', 422);
    }

    // Only owner can add admins
    if ($role === 'admin') {
        requireProjectOwner($projectId, $uid);
    }

    $db = db();

    // Find user by email
    $stmt = $db->prepare('SELECT user_id, name, email FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $target = $stmt->fetch();
    if (!$target) jsonError('No user found with that email', 404);

    $targetId = (int) $target['user_id'];

    // Check not already a member
    $existing = projectRole($projectId, $targetId);
    if ($existing) jsonError('User is already a member of this project', 409);

    $stmt = $db->prepare('INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)');
    $stmt->execute([$projectId, $targetId, $role]);

    logActivity($projectId, $uid, 'member_added', "{$target['name']} added as $role");

    jsonResponse([
        'id'     => $targetId,
        'name'   => $target['name'],
        'email'  => $target['email'],
        'avatar' => null,
        'role'   => $role,
    ], 201);
}

function handleUpdateMemberRole(int $projectId, int $targetUserId): never
{
    $uid = requireAuth();

    $data = jsonBody();
    requireFields($data, ['role']);

    $newRole = $data['role'];
    if (!in_array($newRole, ['admin', 'collaborator'])) {
        jsonError('Invalid role', 422);
    }

    // Only owner can promote to admin or change roles
    requireProjectOwner($projectId, $uid);

    // Cannot change owner's own role
    if ($targetUserId === $uid) {
        jsonError('Cannot change your own role', 422);
    }

    // Verify target is a member
    $currentRole = projectRole($projectId, $targetUserId);
    if (!$currentRole) jsonError('User is not a member', 404);
    if ($currentRole === 'owner') jsonError('Cannot change the owner role', 422);

    $db = db();
    $db->prepare('UPDATE project_members SET role = ? WHERE project_id = ? AND user_id = ?')
       ->execute([$newRole, $projectId, $targetUserId]);

    logActivity($projectId, $uid, 'role_changed', "Role changed to $newRole");

    jsonResponse(['userId' => $targetUserId, 'role' => $newRole]);
}

function handleRemoveMember(int $projectId, int $targetUserId): never
{
    $uid = requireAuth();
    requireProjectAdmin($projectId, $uid);

    // Cannot remove yourself if you're the owner
    $targetRole = projectRole($projectId, $targetUserId);
    if (!$targetRole) jsonError('User is not a member', 404);
    if ($targetRole === 'owner') jsonError('Cannot remove the project owner', 422);

    // Collaborators can only remove themselves
    $myRole = projectRole($projectId, $uid);
    if ($myRole === 'collaborator' && $targetUserId !== $uid) {
        jsonError('Insufficient permissions', 403);
    }

    db()->prepare('DELETE FROM project_members WHERE project_id = ? AND user_id = ?')
        ->execute([$projectId, $targetUserId]);

    // Also remove from task assignees in this project
    db()->prepare('
        DELETE ta FROM task_assignees ta
        JOIN tasks t ON t.task_id = ta.task_id
        WHERE t.project_id = ? AND ta.user_id = ?
    ')->execute([$projectId, $targetUserId]);

    logActivity($projectId, $uid, 'member_removed', 'Member removed');

    jsonSuccess('Member removed');
}
