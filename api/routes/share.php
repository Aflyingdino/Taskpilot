<?php
/*
 * Share routes: generate / revoke public link, get public project view
 */

function handleGenerateShare(int $projectId): never
{
    $uid = requireAuth();
    requireProjectAdmin($projectId, $uid);

    $token = bin2hex(random_bytes(32));

    db()->prepare('UPDATE projects SET public_token = ? WHERE project_id = ?')
        ->execute([$token, $projectId]);

    logActivity($projectId, $uid, 'share_created', 'Public share link generated');

    jsonResponse(['shareId' => $token]);
}

function handleRevokeShare(int $projectId): never
{
    $uid = requireAuth();
    requireProjectAdmin($projectId, $uid);

    db()->prepare('UPDATE projects SET public_token = NULL WHERE project_id = ?')
        ->execute([$projectId]);

    logActivity($projectId, $uid, 'share_revoked', 'Public share link revoked');

    jsonSuccess('Share link revoked');
}

function handleGetPublicProject(string $token): never
{
    $db = db();
    $stmt = $db->prepare('SELECT project_id, title, description, main_color, created_at FROM projects WHERE public_token = ?');
    $stmt->execute([$token]);
    $proj = $stmt->fetch();
    if (!$proj) jsonError('Project not found or link expired', 404);

    $pid = (int) $proj['project_id'];

    // Groups (non-archived only)
    $stmt = $db->prepare('SELECT * FROM board_groups WHERE project_id = ? AND archived_at IS NULL ORDER BY position');
    $stmt->execute([$pid]);
    $groupRows = $stmt->fetchAll();

    // Tasks
    $stmt = $db->prepare('SELECT * FROM tasks WHERE project_id = ? ORDER BY position');
    $stmt->execute([$pid]);
    $taskRows = $stmt->fetchAll();

    // Labels
    $stmt = $db->prepare('SELECT label_id, name, color FROM labels WHERE project_id = ?');
    $stmt->execute([$pid]);
    $labels = array_map(fn($r) => [
        'id' => (int)$r['label_id'], 'name' => $r['name'], 'color' => $r['color']
    ], $stmt->fetchAll());

    // Task labels (bulk)
    $taskIds = array_column($taskRows, 'task_id');
    $taskLabelMap = [];
    if ($taskIds) {
        $ph = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $db->prepare("SELECT task_id, label_id FROM task_labels WHERE task_id IN ($ph)");
        $stmt->execute($taskIds);
        foreach ($stmt->fetchAll() as $r) {
            $taskLabelMap[(int)$r['task_id']][] = (int) $r['label_id'];
        }
    }

    $buildPublicTask = fn(array $r) => [
        'id'        => (int) $r['task_id'],
        'text'      => $r['title'],
        'status'    => $r['status'],
        'priority'  => $r['priority'],
        'deadline'  => $r['due_date'],
        'labelIds'  => $taskLabelMap[(int)$r['task_id']] ?? [],
        'mainColor' => $r['main_color'],
        'color'     => $r['accent_color'],
    ];

    $tasksByGroup = [];
    $backlog = [];
    foreach ($taskRows as $tr) {
        $task = $buildPublicTask($tr);
        if ($tr['group_id'] === null) $backlog[] = $task;
        else $tasksByGroup[(int)$tr['group_id']][] = $task;
    }

    $groups = array_map(fn($gr) => [
        'id'        => (int) $gr['group_id'],
        'name'      => $gr['name'],
        'status'    => $gr['status'],
        'priority'  => $gr['priority'],
        'color'     => $gr['accent_color'],
        'mainColor' => $gr['main_color'],
        'gridRow'   => (int) $gr['grid_row'],
        'gridCol'   => (int) $gr['grid_col'],
        'tasks'     => $tasksByGroup[(int)$gr['group_id']] ?? [],
    ], $groupRows);

    jsonResponse([
        'name'        => $proj['title'],
        'description' => $proj['description'] ?? '',
        'color'       => $proj['main_color'],
        'groups'      => $groups,
        'backlog'     => $backlog,
        'labels'      => $labels,
        'createdAt'   => $proj['created_at'],
    ]);
}
