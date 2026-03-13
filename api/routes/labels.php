<?php
/*
 * Label routes: create, update, delete
 */

function handleCreateLabel(int $projectId): never
{
    $uid = requireAuth();
    requireProjectAdmin($projectId, $uid);

    $data = jsonBody();
    requireFields($data, ['name']);

    $name  = clampString($data['name'], 50);
    $color = requireNullableColor($data['color'] ?? '#5b5bd6', 'color') ?? '#5b5bd6';

    $db = db();
    $stmt = $db->prepare('INSERT INTO labels (project_id, name, color) VALUES (?, ?, ?)');
    $stmt->execute([$projectId, $name, $color]);
    $lid = (int) $db->lastInsertId();

    jsonResponse(['id' => $lid, 'name' => $name, 'color' => $color], 201);
}

function handleUpdateLabel(int $labelId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('SELECT * FROM labels WHERE label_id = ?');
    $stmt->execute([$labelId]);
    $label = $stmt->fetch();
    if (!$label) jsonError('Label not found', 404);

    requireProjectAdmin((int) $label['project_id'], $uid);

    $data = jsonBody();
    $sets = [];
    $vals = [];

    if (isset($data['name'])) {
        $sets[] = 'name = ?';
        $vals[] = clampString($data['name'], 50);
    }
    if (array_key_exists('color', $data)) {
        $sets[] = 'color = ?';
        $vals[] = requireNullableColor($data['color'], 'color') ?? '#5b5bd6';
    }

    if ($sets) {
        $vals[] = $labelId;
        $db->prepare('UPDATE labels SET ' . implode(', ', $sets) . ' WHERE label_id = ?')
           ->execute($vals);
    }

    // Reload
    $stmt = $db->prepare('SELECT label_id, name, color FROM labels WHERE label_id = ?');
    $stmt->execute([$labelId]);
    $l = $stmt->fetch();

    jsonResponse(['id' => (int) $l['label_id'], 'name' => $l['name'], 'color' => $l['color']]);
}

function handleDeleteLabel(int $labelId): never
{
    $uid = requireAuth();

    $db = db();
    $stmt = $db->prepare('SELECT project_id FROM labels WHERE label_id = ?');
    $stmt->execute([$labelId]);
    $label = $stmt->fetch();
    if (!$label) jsonError('Label not found', 404);

    requireProjectAdmin((int) $label['project_id'], $uid);

    $db->prepare('DELETE FROM labels WHERE label_id = ?')->execute([$labelId]);

    jsonSuccess('Label deleted');
}
