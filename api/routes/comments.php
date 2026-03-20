<?php
/*
 * Comment routes: add, edit, delete, pin
 */

function handleAddComment(int $taskId): never
{
    $uid = requireAuth();

    // Verify task exists and user has project access
    $stmt = db()->prepare('SELECT project_id FROM tasks WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) jsonError('Task not found', 404);
    requireProjectAccess((int) $task['project_id'], $uid);

    $data = jsonBody();
    requireFields($data, ['text']);

    $body = clampString($data['text'], 5000);
    if ($body === '') jsonError('Comment cannot be empty', 422);

    $db = db();
    $stmt = $db->prepare('INSERT INTO comments (task_id, user_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$taskId, $uid, $body]);
    $cid = (int) $db->lastInsertId();

    // Fetch with author name
    $stmt = $db->prepare('
        SELECT c.comment_id, c.body, c.is_pinned, c.edited_at, c.created_at,
               u.user_id, u.name AS author_name
        FROM comments c JOIN users u ON u.user_id = c.user_id
        WHERE c.comment_id = ?
    ');
    $stmt->execute([$cid]);
    $r = $stmt->fetch();

    jsonResponse([
        'id'        => (int) $r['comment_id'],
        'text'      => $r['body'],
        'author'    => $r['author_name'],
        'authorId'  => (int) $r['user_id'],
        'pinned'    => (bool) $r['is_pinned'],
        'editedAt'  => $r['edited_at'],
        'createdAt' => $r['created_at'],
    ], 201);
}

function handleEditComment(int $commentId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('
        SELECT c.*, t.project_id FROM comments c
        JOIN tasks t ON t.task_id = c.task_id
        WHERE c.comment_id = ?
    ');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if (!$comment) jsonError('Comment not found', 404);

    // Only the author or an admin/owner can edit
    $role = requireProjectAccess((int) $comment['project_id'], $uid);
    if ((int)$comment['user_id'] !== $uid && $role === 'collaborator') {
        jsonError('You can only edit your own comments', 403);
    }

    $data = jsonBody();
    requireFields($data, ['text']);

    $body = clampString($data['text'], 5000);
    if ($body === '') jsonError('Comment cannot be empty', 422);
    $db->prepare('UPDATE comments SET body = ?, edited_at = NOW() WHERE comment_id = ?')
       ->execute([$body, $commentId]);

    jsonResponse(['id' => $commentId, 'text' => $body, 'editedAt' => date('Y-m-d H:i:s')]);
}

function handleDeleteComment(int $commentId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('
        SELECT c.user_id, t.project_id FROM comments c
        JOIN tasks t ON t.task_id = c.task_id
        WHERE c.comment_id = ?
    ');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if (!$comment) jsonError('Comment not found', 404);

    $role = requireProjectAccess((int) $comment['project_id'], $uid);
    if ((int)$comment['user_id'] !== $uid && $role === 'collaborator') {
        jsonError('You can only delete your own comments', 403);
    }

    $db->prepare('DELETE FROM comments WHERE comment_id = ?')->execute([$commentId]);

    jsonSuccess('Comment deleted');
}

function handlePinComment(int $commentId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('
        SELECT c.is_pinned, t.project_id FROM comments c
        JOIN tasks t ON t.task_id = c.task_id
        WHERE c.comment_id = ?
    ');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if (!$comment) jsonError('Comment not found', 404);

    requireProjectAccess((int) $comment['project_id'], $uid);

    $newPinned = $comment['is_pinned'] ? 0 : 1;
    $db->prepare('UPDATE comments SET is_pinned = ? WHERE comment_id = ?')
       ->execute([$newPinned, $commentId]);

    jsonResponse(['id' => $commentId, 'pinned' => (bool) $newPinned]);
}
