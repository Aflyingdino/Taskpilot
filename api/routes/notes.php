<?php
/*
 * Note routes: add, update, delete
 */

function handleAddNote(int $taskId): never
{
    $uid = requireAuth();

    $stmt = db()->prepare('SELECT project_id FROM tasks WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) jsonError('Task not found', 404);
    requireProjectAccess((int) $task['project_id'], $uid);

    $data = jsonBody();

    $title       = clampString($data['title'] ?? 'Note', 150);
    $content     = clampString($data['content'] ?? '', 10000);
    $contentType = requireEnumValue($data['contentType'] ?? 'text', ['text', 'image', 'video'], 'contentType');
    $bgColor     = requireNullableColor($data['bgColor'] ?? '#5b5bd6', 'bgColor') ?? '#5b5bd6';
    $textColor   = requireNullableColor($data['textColor'] ?? '#ffffff', 'textColor') ?? '#ffffff';

    $db = db();
    $stmt = $db->prepare('
        INSERT INTO task_notes (task_id, user_id, title, content, content_type, bg_color, text_color)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$taskId, $uid, $title, $content, $contentType, $bgColor, $textColor]);
    $nid = (int) $db->lastInsertId();

    jsonResponse([
        'id'          => $nid,
        'title'       => $title,
        'content'     => $content,
        'contentType' => $contentType,
        'bgColor'     => $bgColor,
        'textColor'   => $textColor,
        'createdBy'   => $uid,
        'createdAt'   => date('Y-m-d H:i:s'),
    ], 201);
}

function handleUpdateNote(int $noteId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('
        SELECT n.*, t.project_id FROM task_notes n
        JOIN tasks t ON t.task_id = n.task_id
        WHERE n.note_id = ?
    ');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();
    if (!$note) jsonError('Note not found', 404);

    // Only the author or an admin/owner can edit
    $role = requireProjectAccess((int) $note['project_id'], $uid);
    if ((int)$note['user_id'] !== $uid && $role === 'collaborator') {
        jsonError('You can only edit your own notes', 403);
    }

    $data = jsonBody();
    $sets = [];
    $vals = [];

    if (isset($data['title']))       { $sets[] = 'title = ?';        $vals[] = clampString($data['title'], 150); }
    if (isset($data['content']))     { $sets[] = 'content = ?';      $vals[] = clampString($data['content'], 10000); }
    if (isset($data['contentType'])) { $sets[] = 'content_type = ?'; $vals[] = requireEnumValue($data['contentType'], ['text', 'image', 'video'], 'contentType'); }
    if (isset($data['bgColor']))     { $sets[] = 'bg_color = ?';     $vals[] = requireNullableColor($data['bgColor'], 'bgColor'); }
    if (isset($data['textColor']))   { $sets[] = 'text_color = ?';   $vals[] = requireNullableColor($data['textColor'], 'textColor'); }

    if ($sets) {
        $vals[] = $noteId;
        $db->prepare('UPDATE task_notes SET ' . implode(', ', $sets) . ' WHERE note_id = ?')
           ->execute($vals);
    }

    // Reload
    $stmt = $db->prepare('SELECT * FROM task_notes WHERE note_id = ?');
    $stmt->execute([$noteId]);
    $n = $stmt->fetch();

    jsonResponse([
        'id'          => (int) $n['note_id'],
        'title'       => $n['title'],
        'content'     => $n['content'],
        'contentType' => $n['content_type'],
        'bgColor'     => $n['bg_color'],
        'textColor'   => $n['text_color'],
        'createdBy'   => (int) $n['user_id'],
        'createdAt'   => $n['created_at'],
    ]);
}

function handleDeleteNote(int $noteId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('
        SELECT t.project_id FROM task_notes n
        JOIN tasks t ON t.task_id = n.task_id
        WHERE n.note_id = ?
    ');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();
    if (!$note) jsonError('Note not found', 404);

    // Only the author or an admin/owner can delete
    $role = requireProjectAccess((int) $note['project_id'], $uid);
    if ((int)$note['user_id'] !== $uid && $role === 'collaborator') {
        jsonError('You can only delete your own notes', 403);
    }

    $db->prepare('DELETE FROM task_notes WHERE note_id = ?')->execute([$noteId]);

    jsonSuccess('Note deleted');
}
