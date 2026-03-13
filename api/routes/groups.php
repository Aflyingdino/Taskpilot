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
    $status   = requireEnumValue($data['status'] ?? 'not_started', ['not_started', 'started', 'ready_for_test', 'done'], 'status');
    $priority = requireEnumValue($data['priority'] ?? 'medium', ['low', 'medium', 'high', 'urgent'], 'priority');
    $deadline = requireNullableDate($data['deadline'] ?? null, 'deadline');
    $mainClr  = requireNullableColor($data['mainColor'] ?? null, 'mainColor');
    $accClr   = requireNullableColor($data['color'] ?? null, 'color');
    $gridRow  = requireBoundedInt($data['gridRow'] ?? 0, 'gridRow', 0, 1000);
    $gridCol  = requireBoundedInt($data['gridCol'] ?? 0, 'gridCol', 0, 1000);
    $labelIds = requireIntArray($data['labelIds'] ?? [], 'labelIds');

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
    if ($labelIds) {
        syncGroupLabels($gid, $projectId, $labelIds);
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
            if ($key === 'status') $val = requireEnumValue($val, ['not_started', 'started', 'ready_for_test', 'done'], 'status');
            if ($key === 'priority') $val = requireEnumValue($val, ['low', 'medium', 'high', 'urgent'], 'priority');
            if ($key === 'deadline') $val = requireNullableDate($val, 'deadline');
            if ($key === 'mainColor') $val = requireNullableColor($val, 'mainColor');
            if ($key === 'color') $val = requireNullableColor($val, 'color');
            if ($key === 'gridRow') $val = requireBoundedInt($val, 'gridRow', 0, 1000);
            if ($key === 'gridCol') $val = requireBoundedInt($val, 'gridCol', 0, 1000);
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
        syncGroupLabels($groupId, $pid, requireIntArray($data['labelIds'] ?? [], 'labelIds'));
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

function syncGroupLabels(int $groupId, int $projectId, array $labelIds): void
{
    $db = db();
    $db->prepare('DELETE FROM group_labels WHERE group_id = ?')->execute([$groupId]);
    if (!$labelIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($labelIds), '?'));
    $params = array_merge([$projectId], $labelIds);
    $stmt = $db->prepare("SELECT label_id FROM labels WHERE project_id = ? AND label_id IN ($placeholders)");
    $stmt->execute($params);
    $validIds = array_map(static fn($row) => (int) $row['label_id'], $stmt->fetchAll());
    sort($validIds);
    $expected = $labelIds;
    sort($expected);
    if ($validIds !== $expected) {
        jsonError('Invalid labelIds', 422);
    }

    $stmt = $db->prepare('INSERT INTO group_labels (group_id, label_id) VALUES (?, ?)');
    foreach ($validIds as $lid) {
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
