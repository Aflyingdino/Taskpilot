<?php
/*
 * Project routes: list, get, create, update, delete, archive, restore, activity
 */

/* ── Helpers for assembling full project data ── */

function fetchProjectSummaries(int $userId): array
{
    $sql = '
        SELECT p.project_id, p.title, p.description, p.main_color, p.archived_at, p.created_at,
               pm.role,
               (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) AS task_count,
               (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id = p.project_id) AS member_count
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.project_id AND pm.user_id = ?
        ORDER BY p.created_at DESC
    ';
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    return array_map(fn($r) => [
        'id'          => (int) $r['project_id'],
        'name'        => $r['title'],
        'description' => $r['description'],
        'color'       => $r['main_color'],
        'role'        => $r['role'],
        'taskCount'   => (int) $r['task_count'],
        'memberCount' => (int) $r['member_count'],
        'archived'    => $r['archived_at'] !== null,
        'archivedAt'  => $r['archived_at'],
        'createdAt'   => $r['created_at'],
    ], $rows);
}

function fetchFullProject(int $projectId, string $role): array
{
    $db = db();

    // Project base
    $stmt = $db->prepare('SELECT * FROM projects WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $proj = $stmt->fetch();
    if (!$proj) jsonError('Project not found', 404);

    // Members
    $stmt = $db->prepare('
        SELECT u.user_id, u.name, u.email, pm.role
        FROM project_members pm
        JOIN users u ON u.user_id = pm.user_id
        WHERE pm.project_id = ?
    ');
    $stmt->execute([$projectId]);
    $members = array_map(fn($r) => [
        'id'    => (int) $r['user_id'],
        'name'  => $r['name'],
        'email' => $r['email'],
        'avatar' => null,
        'role'  => $r['role'],
    ], $stmt->fetchAll());

    // Labels
    $stmt = $db->prepare('SELECT label_id, name, color FROM labels WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $labels = array_map(fn($r) => [
        'id'    => (int) $r['label_id'],
        'name'  => $r['name'],
        'color' => $r['color'],
    ], $stmt->fetchAll());

    // Groups (including archived)
    $stmt = $db->prepare('SELECT * FROM board_groups WHERE project_id = ? ORDER BY position, group_id');
    $stmt->execute([$projectId]);
    $groupRows = $stmt->fetchAll();

    // All tasks for this project
    $stmt = $db->prepare('SELECT * FROM tasks WHERE project_id = ? ORDER BY position, task_id');
    $stmt->execute([$projectId]);
    $taskRows = $stmt->fetchAll();

    // Bulk-load relational data for all tasks
    $taskIds = array_column($taskRows, 'task_id');

    $taskLabelMap    = [];
    $taskAssigneeMap = [];
    $taskCommentMap  = [];
    $taskNoteMap     = [];

    if ($taskIds) {
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

        // Task labels
        $stmt = $db->prepare("SELECT task_id, label_id FROM task_labels WHERE task_id IN ($placeholders)");
        $stmt->execute($taskIds);
        foreach ($stmt->fetchAll() as $r) {
            $taskLabelMap[(int)$r['task_id']][] = (int) $r['label_id'];
        }

        // Task assignees
        $stmt = $db->prepare("SELECT task_id, user_id FROM task_assignees WHERE task_id IN ($placeholders)");
        $stmt->execute($taskIds);
        foreach ($stmt->fetchAll() as $r) {
            $taskAssigneeMap[(int)$r['task_id']][] = (int) $r['user_id'];
        }

        // Comments
        $stmt = $db->prepare("
            SELECT c.comment_id, c.task_id, c.body, c.is_pinned, c.edited_at, c.created_at,
                   u.user_id, u.name AS author_name
            FROM comments c
            JOIN users u ON u.user_id = c.user_id
            WHERE c.task_id IN ($placeholders)
            ORDER BY c.created_at
        ");
        $stmt->execute($taskIds);
        foreach ($stmt->fetchAll() as $r) {
            $taskCommentMap[(int)$r['task_id']][] = [
                'id'        => (int) $r['comment_id'],
                'text'      => $r['body'],
                'author'    => $r['author_name'],
                'authorId'  => (int) $r['user_id'],
                'pinned'    => (bool) $r['is_pinned'],
                'editedAt'  => $r['edited_at'],
                'createdAt' => $r['created_at'],
            ];
        }

        // Notes
        $stmt = $db->prepare("
            SELECT n.note_id, n.task_id, n.title, n.content, n.content_type,
                   n.bg_color, n.text_color, n.user_id, n.created_at
            FROM task_notes n
            WHERE n.task_id IN ($placeholders)
            ORDER BY n.created_at
        ");
        $stmt->execute($taskIds);
        foreach ($stmt->fetchAll() as $r) {
            $taskNoteMap[(int)$r['task_id']][] = [
                'id'          => (int) $r['note_id'],
                'title'       => $r['title'],
                'content'     => $r['content'],
                'contentType' => $r['content_type'],
                'bgColor'     => $r['bg_color'],
                'textColor'   => $r['text_color'],
                'createdBy'   => (int) $r['user_id'],
                'createdAt'   => $r['created_at'],
            ];
        }
    }

    // Group labels
    $groupIds = array_column($groupRows, 'group_id');
    $groupLabelMap = [];
    if ($groupIds) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $stmt = $db->prepare("SELECT group_id, label_id FROM group_labels WHERE group_id IN ($placeholders)");
        $stmt->execute($groupIds);
        foreach ($stmt->fetchAll() as $r) {
            $groupLabelMap[(int)$r['group_id']][] = (int) $r['label_id'];
        }
    }

    // Build task objects
    $buildTask = function(array $r) use ($taskLabelMap, $taskAssigneeMap, $taskCommentMap, $taskNoteMap): array {
        $tid = (int) $r['task_id'];
        return [
            'id'               => $tid,
            'text'             => $r['title'],
            'description'      => $r['description'] ?? '',
            'status'           => $r['status'],
            'priority'         => $r['priority'],
            'deadline'         => $r['due_date'],
            'duration'         => $r['duration_minutes'],
            'labelIds'         => $taskLabelMap[$tid] ?? [],
            'assigneeIds'      => $taskAssigneeMap[$tid] ?? [],
            'mainColor'        => $r['main_color'],
            'color'            => $r['accent_color'],
            'calendarColor'    => $r['calendar_color'],
            'calendarStart'    => $r['scheduled_start'],
            'calendarDuration' => $r['duration_minutes'],
            'notes'            => $taskNoteMap[$tid] ?? [],
            'attachments'      => [],
            'comments'         => $taskCommentMap[$tid] ?? [],
            'createdAt'        => $r['created_at'],
        ];
    };

    // Partition tasks by group
    $tasksByGroup = [];
    $backlog = [];
    foreach ($taskRows as $tr) {
        $task = $buildTask($tr);
        if ($tr['group_id'] === null) {
            $backlog[] = $task;
        } else {
            $tasksByGroup[(int)$tr['group_id']][] = $task;
        }
    }

    // Build groups
    $groups = [];
    $archivedGroups = [];
    foreach ($groupRows as $gr) {
        $gid = (int) $gr['group_id'];
        $group = [
            'id'          => $gid,
            'name'        => $gr['name'],
            'description' => $gr['description'] ?? '',
            'status'      => $gr['status'],
            'priority'    => $gr['priority'],
            'deadline'    => $gr['deadline'],
            'labelIds'    => $groupLabelMap[$gid] ?? [],
            'color'       => $gr['accent_color'],
            'mainColor'   => $gr['main_color'],
            'gridRow'     => (int) $gr['grid_row'],
            'gridCol'     => (int) $gr['grid_col'],
            'tasks'       => $tasksByGroup[$gid] ?? [],
        ];
        if ($gr['archived_at'] !== null) {
            $group['archivedAt'] = $gr['archived_at'];
            $archivedGroups[] = $group;
        } else {
            $groups[] = $group;
        }
    }

    // Activity (last 50)
    $stmt = $db->prepare('
        SELECT a.activity_id, a.type, a.message, a.created_at
        FROM activity_log a
        WHERE a.project_id = ?
        ORDER BY a.created_at DESC
        LIMIT 50
    ');
    $stmt->execute([$projectId]);
    $activity = array_map(fn($r) => [
        'id'        => (int) $r['activity_id'],
        'type'      => $r['type'],
        'message'   => $r['message'],
        'createdAt' => $r['created_at'],
    ], $stmt->fetchAll());

    // Completed tasks (tasks with status = done, for dashboard)
    $completedTasks = array_filter(
        array_merge($backlog, ...array_values($tasksByGroup)),
        fn($t) => $t['status'] === 'done'
    );

    return [
        'id'             => (int) $proj['project_id'],
        'name'           => $proj['title'],
        'description'    => $proj['description'] ?? '',
        'color'          => $proj['main_color'],
        'role'           => $role,
        'shareId'        => $proj['public_token'],
        'archived'       => $proj['archived_at'] !== null,
        'archivedAt'     => $proj['archived_at'],
        'members'        => $members,
        'groups'         => $groups,
        'archivedGroups' => $archivedGroups,
        'backlog'        => $backlog,
        'labels'         => $labels,
        'completedTasks' => array_values($completedTasks),
        'activity'       => $activity,
        'createdAt'      => $proj['created_at'],
    ];
}

/* ── Handlers ── */

function handleListProjects(): never
{
    $uid = requireAuth();

    // Return full data for all projects (needed by dashboard/calendar)
    $stmt = db()->prepare('SELECT project_id, role FROM project_members WHERE user_id = ?');
    $stmt->execute([$uid]);
    $memberships = $stmt->fetchAll();

    $result = [];
    foreach ($memberships as $m) {
        $result[] = fetchFullProject((int) $m['project_id'], $m['role']);
    }

    jsonResponse($result);
}

function handleGetProject(int $id): never
{
    $uid  = requireAuth();
    $role = requireProjectAccess($id, $uid);
    jsonResponse(fetchFullProject($id, $role));
}

function handleCreateProject(): never
{
    $uid  = requireAuth();
    $data = jsonBody();
    requireFields($data, ['name']);

    $db = db();
    $name  = clampString($data['name'], 150);
    $desc  = clampString($data['description'] ?? '', 5000);
    $color = requireNullableColor($data['color'] ?? '#5b5bd6', 'color') ?? '#5b5bd6';

    $stmt = $db->prepare('INSERT INTO projects (title, description, main_color) VALUES (?, ?, ?)');
    $stmt->execute([$name, $desc, $color]);
    $pid = (int) $db->lastInsertId();

    // Creator becomes owner
    $stmt = $db->prepare('INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)');
    $stmt->execute([$pid, $uid, 'owner']);

    logActivity($pid, $uid, 'project_created', "Project \"$name\" created");

    jsonResponse(fetchFullProject($pid, 'owner'), 201);
}

function handleUpdateProject(int $id): never
{
    $uid = requireAuth();
    requireProjectAdmin($id, $uid);

    $data = jsonBody();
    $db   = db();
    $sets = [];
    $vals = [];

    if (isset($data['name'])) {
        $sets[] = 'title = ?';
        $vals[] = clampString($data['name'], 150);
    }
    if (array_key_exists('description', $data)) {
        $sets[] = 'description = ?';
        $vals[] = clampString($data['description'], 5000);
    }
    if (isset($data['color'])) {
        $sets[] = 'main_color = ?';
        $vals[] = requireNullableColor($data['color'], 'color') ?? '#5b5bd6';
    }

    if ($sets) {
        $vals[] = $id;
        $db->prepare('UPDATE projects SET ' . implode(', ', $sets) . ' WHERE project_id = ?')
           ->execute($vals);
    }

    $role = projectRole($id, $uid);
    jsonResponse(fetchFullProject($id, $role));
}

function handleDeleteProject(int $id): never
{
    $uid = requireAuth();
    requireProjectOwner($id, $uid);

    db()->prepare('DELETE FROM projects WHERE project_id = ?')->execute([$id]);

    jsonSuccess('Project deleted');
}

function handleArchiveProject(int $id): never
{
    $uid = requireAuth();
    requireProjectAdmin($id, $uid);

    db()->prepare('UPDATE projects SET archived_at = NOW() WHERE project_id = ?')->execute([$id]);
    logActivity($id, $uid, 'project_archived', 'Project archived');

    $role = projectRole($id, $uid);
    jsonResponse(fetchFullProject($id, $role));
}

function handleRestoreProject(int $id): never
{
    $uid = requireAuth();
    requireProjectAdmin($id, $uid);

    db()->prepare('UPDATE projects SET archived_at = NULL WHERE project_id = ?')->execute([$id]);
    logActivity($id, $uid, 'project_restored', 'Project restored');

    $role = projectRole($id, $uid);
    jsonResponse(fetchFullProject($id, $role));
}

function handleGetActivity(int $id): never
{
    $uid = requireAuth();
    requireProjectAccess($id, $uid);

    $stmt = db()->prepare('
        SELECT activity_id, type, message, created_at
        FROM activity_log
        WHERE project_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ');
    $stmt->execute([$id]);

    jsonResponse(array_map(fn($r) => [
        'id'        => (int) $r['activity_id'],
        'type'      => $r['type'],
        'message'   => $r['message'],
        'createdAt' => $r['created_at'],
    ], $stmt->fetchAll()));
}
