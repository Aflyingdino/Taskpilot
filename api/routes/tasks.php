<?php
/*
 * Task routes: create, update, delete, move, schedule
 */

/** Resolve a task row and verify the caller has project access. */
function resolveTask(int $taskId, int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM tasks WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) jsonError('Task not found', 404);

    requireProjectAccess((int) $task['project_id'], $userId);
    return $task;
}

function handleCreateTask(int $projectId): never
{
    $uid = requireAuth();
    requireProjectAccess($projectId, $uid);

    $data = jsonBody();
    requireFields($data, ['text']);

    $db = db();

    $groupId  = isset($data['groupId']) ? (int) $data['groupId'] : null;
    $title    = clampString($data['text'], 150);
    $desc     = clampString($data['description'] ?? '', 10000);
    $status   = $data['status'] ?? 'not_started';
    $priority = $data['priority'] ?? 'medium';
    $deadline = $data['deadline'] ?? null;
    $mainClr  = $data['mainColor'] ?? null;
    $accClr   = $data['color'] ?? null;
    $calClr   = $data['calendarColor'] ?? null;

    // Next position within the group (or backlog)
    if ($groupId) {
        $stmt = $db->prepare('SELECT COALESCE(MAX(position),0)+1 AS pos FROM tasks WHERE group_id = ?');
        $stmt->execute([$groupId]);
    } else {
        $stmt = $db->prepare('SELECT COALESCE(MAX(position),0)+1 AS pos FROM tasks WHERE project_id = ? AND group_id IS NULL');
        $stmt->execute([$projectId]);
    }
    $pos = (int) $stmt->fetch()['pos'];

    $stmt = $db->prepare('
        INSERT INTO tasks
            (project_id, group_id, title, description, status, priority, due_date,
             main_color, accent_color, calendar_color, position)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $projectId, $groupId, $title, $desc, $status, $priority, $deadline,
        $mainClr, $accClr, $calClr, $pos,
    ]);
    $tid = (int) $db->lastInsertId();

    // Sync labels
    if (!empty($data['labelIds'])) {
        syncTaskLabels($tid, $data['labelIds']);
    }

    // Sync assignees
    if (!empty($data['assigneeIds'])) {
        syncTaskAssignees($tid, $data['assigneeIds']);
    }

    logActivity($projectId, $uid, 'task_created', "Task \"$title\" created");

    jsonResponse(buildTaskResponse($tid), 201);
}

function handleUpdateTask(int $taskId): never
{
    $uid  = requireAuth();
    $task = resolveTask($taskId, $uid);
    $pid  = (int) $task['project_id'];
    $data = jsonBody();
    $db   = db();

    $sets = [];
    $vals = [];

    $fieldMap = [
        'text'          => ['col' => 'title',           'max' => 150],
        'description'   => ['col' => 'description',     'max' => 10000],
        'status'        => ['col' => 'status',           'max' => null],
        'priority'      => ['col' => 'priority',         'max' => null],
        'deadline'      => ['col' => 'due_date',         'max' => null],
        'mainColor'     => ['col' => 'main_color',      'max' => null],
        'color'         => ['col' => 'accent_color',    'max' => null],
        'calendarColor' => ['col' => 'calendar_color',  'max' => null],
        'duration'      => ['col' => 'duration_minutes', 'max' => null],
    ];

    foreach ($fieldMap as $key => $cfg) {
        if (array_key_exists($key, $data)) {
            $val = $data[$key];
            if ($cfg['max'] && is_string($val)) $val = clampString($val, $cfg['max']);
            if ($key === 'duration' && $val !== null) $val = (int) $val;
            $sets[] = $cfg['col'] . ' = ?';
            $vals[] = $val;
        }
    }

    if ($sets) {
        $vals[] = $taskId;
        $db->prepare('UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE task_id = ?')
           ->execute($vals);
    }

    if (array_key_exists('labelIds', $data)) {
        syncTaskLabels($taskId, $data['labelIds'] ?? []);
    }

    if (array_key_exists('assigneeIds', $data)) {
        syncTaskAssignees($taskId, $data['assigneeIds'] ?? []);
    }

    jsonResponse(buildTaskResponse($taskId));
}

function handleDeleteTask(int $taskId): never
{
    $uid  = requireAuth();
    $task = resolveTask($taskId, $uid);

    db()->prepare('DELETE FROM tasks WHERE task_id = ?')->execute([$taskId]);

    logActivity((int)$task['project_id'], $uid, 'task_deleted', "Task \"{$task['title']}\" deleted");

    jsonSuccess('Task deleted');
}

function handleMoveTask(int $taskId): never
{
    $uid  = requireAuth();
    $task = resolveTask($taskId, $uid);
    $data = jsonBody();

    $groupId = array_key_exists('groupId', $data) ? ($data['groupId'] !== null ? (int) $data['groupId'] : null) : false;
    if ($groupId === false) jsonError('groupId is required (use null for backlog)', 422);

    db()->prepare('UPDATE tasks SET group_id = ? WHERE task_id = ?')
        ->execute([$groupId, $taskId]);

    jsonResponse(buildTaskResponse($taskId));
}

function handleScheduleTask(int $taskId): never
{
    $uid  = requireAuth();
    $task = resolveTask($taskId, $uid);
    $data = jsonBody();

    $start    = $data['calendarStart'] ?? null;
    $duration = isset($data['calendarDuration']) ? (int) $data['calendarDuration'] : null;

    db()->prepare('UPDATE tasks SET scheduled_start = ?, duration_minutes = ? WHERE task_id = ?')
        ->execute([$start, $duration, $taskId]);

    jsonResponse(buildTaskResponse($taskId));
}

function handleUnscheduleTask(int $taskId): never
{
    $uid  = requireAuth();
    resolveTask($taskId, $uid);

    db()->prepare('UPDATE tasks SET scheduled_start = NULL, duration_minutes = NULL WHERE task_id = ?')
        ->execute([$taskId]);

    jsonResponse(buildTaskResponse($taskId));
}

/* ── Internal helpers ── */

function syncTaskLabels(int $taskId, array $labelIds): void
{
    $db = db();
    $db->prepare('DELETE FROM task_labels WHERE task_id = ?')->execute([$taskId]);
    $stmt = $db->prepare('INSERT INTO task_labels (task_id, label_id) VALUES (?, ?)');
    foreach ($labelIds as $lid) {
        $stmt->execute([$taskId, (int) $lid]);
    }
}

function syncTaskAssignees(int $taskId, array $userIds): void
{
    $db = db();
    $db->prepare('DELETE FROM task_assignees WHERE task_id = ?')->execute([$taskId]);
    $stmt = $db->prepare('INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)');
    foreach ($userIds as $uid) {
        $stmt->execute([$taskId, (int) $uid]);
    }
}

function buildTaskResponse(int $taskId): array
{
    $db = db();
    $stmt = $db->prepare('SELECT * FROM tasks WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $t = $stmt->fetch();
    if (!$t) jsonError('Task not found', 404);

    $tid = (int) $t['task_id'];

    // Labels
    $stmt = $db->prepare('SELECT label_id FROM task_labels WHERE task_id = ?');
    $stmt->execute([$tid]);
    $labelIds = array_map(fn($r) => (int) $r['label_id'], $stmt->fetchAll());

    // Assignees
    $stmt = $db->prepare('SELECT user_id FROM task_assignees WHERE task_id = ?');
    $stmt->execute([$tid]);
    $assigneeIds = array_map(fn($r) => (int) $r['user_id'], $stmt->fetchAll());

    // Comments
    $stmt = $db->prepare('
        SELECT c.comment_id, c.body, c.is_pinned, c.edited_at, c.created_at,
               u.user_id, u.name AS author_name
        FROM comments c JOIN users u ON u.user_id = c.user_id
        WHERE c.task_id = ? ORDER BY c.created_at
    ');
    $stmt->execute([$tid]);
    $comments = array_map(fn($r) => [
        'id'        => (int) $r['comment_id'],
        'text'      => $r['body'],
        'author'    => $r['author_name'],
        'authorId'  => (int) $r['user_id'],
        'pinned'    => (bool) $r['is_pinned'],
        'editedAt'  => $r['edited_at'],
        'createdAt' => $r['created_at'],
    ], $stmt->fetchAll());

    // Notes
    $stmt = $db->prepare('SELECT * FROM task_notes WHERE task_id = ? ORDER BY created_at');
    $stmt->execute([$tid]);
    $notes = array_map(fn($r) => [
        'id'          => (int) $r['note_id'],
        'title'       => $r['title'],
        'content'     => $r['content'],
        'contentType' => $r['content_type'],
        'bgColor'     => $r['bg_color'],
        'textColor'   => $r['text_color'],
        'createdBy'   => (int) $r['user_id'],
        'createdAt'   => $r['created_at'],
    ], $stmt->fetchAll());

    return [
        'id'               => $tid,
        'text'             => $t['title'],
        'description'      => $t['description'] ?? '',
        'status'           => $t['status'],
        'priority'         => $t['priority'],
        'deadline'         => $t['due_date'],
        'duration'         => $t['duration_minutes'],
        'labelIds'         => $labelIds,
        'assigneeIds'      => $assigneeIds,
        'mainColor'        => $t['main_color'],
        'color'            => $t['accent_color'],
        'calendarColor'    => $t['calendar_color'],
        'calendarStart'    => $t['scheduled_start'],
        'calendarDuration' => $t['duration_minutes'],
        'notes'            => $notes,
        'attachments'      => [],
        'comments'         => $comments,
        'createdAt'        => $t['created_at'],
    ];
}
