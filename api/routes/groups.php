<?php
/*
 * Group routes: create, update, delete, archive, restore
 */

/** Resolve a group row and verify the caller has access to its project. */
function resolveGroup(int $groupId, int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM board_groups WHERE group_id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    if (!$group) jsonError('Group not found', 404);

    requireProjectAccess((int) $group['project_id'], $userId);
    return $group;
}

function handleCreateGroup(int $projectId): never
{
    $uid = requireAuth();
    requireProjectAccess($projectId, $uid);

    $data = jsonBody();
    requireFields($data, ['name']);

    $db = db();

    // Next position
    $stmt = $db->prepare('SELECT COALESCE(MAX(position),0)+1 AS pos FROM board_groups WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $pos = (int) $stmt->fetch()['pos'];

    $name     = clampString($data['name'], 150);
    $desc     = clampString($data['description'] ?? '', 5000);
    $status   = $data['status'] ?? 'not_started';
    $priority = $data['priority'] ?? 'medium';
    $deadline = $data['deadline'] ?? null;
    $mainClr  = $data['mainColor'] ?? null;
    $accClr   = $data['color'] ?? null;
    $gridRow  = (int)($data['gridRow'] ?? 0);
    $gridCol  = (int)($data['gridCol'] ?? 0);

    $stmt = $db->prepare('
        INSERT INTO board_groups
            (project_id, name, description, status, priority, deadline,
             main_color, accent_color, grid_row, grid_col, position)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $projectId, $name, $desc, $status, $priority, $deadline,
        $mainClr, $accClr, $gridRow, $gridCol, $pos,
    ]);
    $gid = (int) $db->lastInsertId();

    // Sync group labels
    if (!empty($data['labelIds'])) {
        syncGroupLabels($gid, $data['labelIds']);
    }

    logActivity($projectId, $uid, 'group_created', "Group \"$name\" created");

    jsonResponse(buildGroupResponse($gid), 201);
}

function handleUpdateGroup(int $groupId): never
{
    $uid   = requireAuth();
    $group = resolveGroup($groupId, $uid);
    $pid   = (int) $group['project_id'];
    $data  = jsonBody();
    $db    = db();

    $sets = [];
    $vals = [];

    $fieldMap = [
        'name'        => ['col' => 'name',         'max' => 150],
        'description' => ['col' => 'description',   'max' => 5000],
        'status'      => ['col' => 'status',         'max' => null],
        'priority'    => ['col' => 'priority',       'max' => null],
        'deadline'    => ['col' => 'deadline',       'max' => null],
        'mainColor'   => ['col' => 'main_color',    'max' => null],
        'color'       => ['col' => 'accent_color',  'max' => null],
        'gridRow'     => ['col' => 'grid_row',       'max' => null],
        'gridCol'     => ['col' => 'grid_col',       'max' => null],
    ];

    foreach ($fieldMap as $key => $cfg) {
        if (array_key_exists($key, $data)) {
            $val = $data[$key];
            if ($cfg['max'] && is_string($val)) $val = clampString($val, $cfg['max']);
            if (in_array($key, ['gridRow', 'gridCol'])) $val = (int) $val;
            $sets[] = $cfg['col'] . ' = ?';
            $vals[] = $val;
        }
    }

    if ($sets) {
        $vals[] = $groupId;
        $db->prepare('UPDATE board_groups SET ' . implode(', ', $sets) . ' WHERE group_id = ?')
           ->execute($vals);
    }

    if (array_key_exists('labelIds', $data)) {
        syncGroupLabels($groupId, $data['labelIds'] ?? []);
    }

    jsonResponse(buildGroupResponse($groupId));
}

function handleDeleteGroup(int $groupId): never
{
    $uid   = requireAuth();
    $group = resolveGroup($groupId, $uid);
    $pid   = (int) $group['project_id'];

    $db = db();

    // Move tasks in this group to backlog
    $db->prepare('UPDATE tasks SET group_id = NULL WHERE group_id = ?')->execute([$groupId]);

    $db->prepare('DELETE FROM board_groups WHERE group_id = ?')->execute([$groupId]);

    logActivity($pid, $uid, 'group_deleted', "Group \"{$group['name']}\" deleted");

    jsonSuccess('Group deleted');
}

function handleArchiveGroup(int $groupId): never
{
    $uid   = requireAuth();
    $group = resolveGroup($groupId, $uid);

    db()->prepare('UPDATE board_groups SET archived_at = NOW() WHERE group_id = ?')
        ->execute([$groupId]);

    logActivity((int)$group['project_id'], $uid, 'group_archived', "Group \"{$group['name']}\" archived");

    jsonResponse(buildGroupResponse($groupId));
}

function handleRestoreGroup(int $groupId): never
{
    $uid   = requireAuth();
    $group = resolveGroup($groupId, $uid);

    db()->prepare('UPDATE board_groups SET archived_at = NULL WHERE group_id = ?')
        ->execute([$groupId]);

    logActivity((int)$group['project_id'], $uid, 'group_restored', "Group \"{$group['name']}\" restored");

    jsonResponse(buildGroupResponse($groupId));
}

/* ── Internal helpers ── */

function syncGroupLabels(int $groupId, array $labelIds): void
{
    $db = db();
    $db->prepare('DELETE FROM group_labels WHERE group_id = ?')->execute([$groupId]);
    $stmt = $db->prepare('INSERT INTO group_labels (group_id, label_id) VALUES (?, ?)');
    foreach ($labelIds as $lid) {
        $stmt->execute([$groupId, (int) $lid]);
    }
}

function buildGroupResponse(int $groupId): array
{
    $db = db();
    $stmt = $db->prepare('SELECT * FROM board_groups WHERE group_id = ?');
    $stmt->execute([$groupId]);
    $g = $stmt->fetch();
    if (!$g) jsonError('Group not found', 404);

    // Label IDs
    $stmt = $db->prepare('SELECT label_id FROM group_labels WHERE group_id = ?');
    $stmt->execute([$groupId]);
    $labelIds = array_map(fn($r) => (int) $r['label_id'], $stmt->fetchAll());

    return [
        'id'          => (int) $g['group_id'],
        'name'        => $g['name'],
        'description' => $g['description'] ?? '',
        'status'      => $g['status'],
        'priority'    => $g['priority'],
        'deadline'    => $g['deadline'],
        'labelIds'    => $labelIds,
        'color'       => $g['accent_color'],
        'mainColor'   => $g['main_color'],
        'gridRow'     => (int) $g['grid_row'],
        'gridCol'     => (int) $g['grid_col'],
        'archivedAt'  => $g['archived_at'],
        'tasks'       => [],
    ];
}
