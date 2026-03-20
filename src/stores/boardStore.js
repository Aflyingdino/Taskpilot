import { computed } from 'vue'
import { activeProject, logActivity, _save } from './projectStore'
import { api } from '@/utils/api'
import { user } from './authStore'
import { STATUS_META } from '../utils/constants'

function actor() {
  return user.name || 'Someone'
}

/* ---------------------------------------------
   Board Store - thin layer over the active project.
   Mutations update local reactive state AND call
   the PHP API for persistence.
--------------------------------------------- */

function b() { return activeProject.value }

/* -- Getters -- */
export const groups         = computed(() => b()?.groups ?? [])
export const backlog        = computed(() => b()?.backlog ?? [])
export const projectLabels  = computed(() => b()?.labels ?? [])
export const archivedGroups = computed(() => b()?.archivedGroups ?? [])

/* -- Task lookup -- */
export function findTask(taskId) {
  const board = b(); if (!board) return null
  const bl = board.backlog.find((t) => t.id === taskId)
  if (bl) return bl
  for (const g of board.groups) {
    const t = g.tasks.find((t) => t.id === taskId)
    if (t) return t
  }
  return null
}

/* -- Group actions -- */
export async function createGroup(name) {
  const board = b(); if (!board) return
  const usedRows = board.groups
    .filter(g => (g.gridCol ?? 0) === 0)
    .map(g => g.gridRow ?? 0)
  let nextRow = 0
  while (usedRows.includes(nextRow)) nextRow++

  const payload = {
    name: name || 'New Group',
    gridRow: nextRow,
    gridCol: 0,
  }

  const created = await api.post(`/projects/${board.id}/groups`, payload)
  created.tasks = created.tasks || []
  board.groups.push(created)
}

function compactColumns(board) {
  const cols = {}
  for (const g of board.groups) {
    const c = g.gridCol ?? 0
    if (!cols[c]) cols[c] = []
    cols[c].push(g)
  }
  for (const colGroups of Object.values(cols)) {
    colGroups.sort((a, b) => (a.gridRow ?? 0) - (b.gridRow ?? 0))
    colGroups.forEach((g, i) => { g.gridRow = i })
  }
}

export async function moveGroupToGrid(fromId, toRow, toCol) {
  const board = b(); if (!board) return
  const fromGroup = board.groups.find(g => g.id === fromId)
  if (!fromGroup) return
  const toGroup = board.groups.find(
    g => g.id !== fromId && (g.gridRow ?? 0) === toRow && (g.gridCol ?? 0) === toCol
  )
  if (toGroup) {
    toGroup.gridRow = fromGroup.gridRow ?? 0
    toGroup.gridCol = fromGroup.gridCol ?? 0
    api.patch(`/groups/${toGroup.id}`, { gridRow: toGroup.gridRow, gridCol: toGroup.gridCol }).catch(() => {})
  }
  fromGroup.gridRow = toRow
  fromGroup.gridCol = toCol
  compactColumns(board)
  api.patch(`/groups/${fromGroup.id}`, { gridRow: fromGroup.gridRow, gridCol: fromGroup.gridCol }).catch(() => {})
}

export async function updateGroup(groupId, data) {
  const board = b(); if (!board) return
  const group = board.groups.find(g => g.id === groupId)
  if (!group) return
  Object.assign(group, data)
  await api.patch(`/groups/${groupId}`, data)
}

export async function deleteGroup(groupId) {
  const board = b(); if (!board) return
  const idx = board.groups.findIndex((g) => g.id === groupId)
  if (idx === -1) return
  board.backlog.push(...board.groups[idx].tasks)
  board.groups.splice(idx, 1)
  await api.delete(`/groups/${groupId}`)
}

export async function archiveGroup(groupId) {
  const board = b(); if (!board) return
  const idx = board.groups.findIndex(g => g.id === groupId)
  if (idx === -1) return
  if (!board.archivedGroups) board.archivedGroups = []
  const [group] = board.groups.splice(idx, 1)
  group.archivedAt = new Date().toISOString()
  board.archivedGroups.push(group)
  await api.post(`/groups/${groupId}/archive`)
}

export async function restoreGroup(groupId) {
  const board = b(); if (!board) return
  if (!board.archivedGroups) return
  const idx = board.archivedGroups.findIndex(g => g.id === groupId)
  if (idx === -1) return
  const [group] = board.archivedGroups.splice(idx, 1)
  delete group.archivedAt
  board.groups.push(group)
  await api.post(`/groups/${groupId}/restore`)
}

export async function deleteArchivedGroup(groupId) {
  const board = b(); if (!board) return
  if (!board.archivedGroups) return
  const idx = board.archivedGroups.findIndex(g => g.id === groupId)
  if (idx !== -1) {
    board.archivedGroups.splice(idx, 1)
    await api.delete(`/groups/${groupId}`)
  }
}

export function reorderGroups(fromGroupId, toGroupId) {
  const board = b(); if (!board) return
  const fromIdx = board.groups.findIndex(g => g.id === fromGroupId)
  const toIdx   = board.groups.findIndex(g => g.id === toGroupId)
  if (fromIdx === -1 || toIdx === -1 || fromIdx === toIdx) return
  const [group] = board.groups.splice(fromIdx, 1)
  board.groups.splice(toIdx, 0, group)
}

export async function renameGroup(groupId, newName) {
  const group = b()?.groups.find((g) => g.id === groupId)
  if (group) {
    group.name = newName
    await api.patch(`/groups/${groupId}`, { name: newName })
  }
}

/* -- Task actions -- */
export async function createTask(data, targetType, targetId) {
  const board = b(); if (!board) return

  const payload = {
    text: data.text,
    description: data.description || '',
    status: data.status || 'not_started',
    priority: data.priority || 'medium',
    deadline: data.deadline || null,
    duration: data.duration || null,
    labelIds: data.labelIds || [],
    assigneeIds: data.assigneeIds || [],
    mainColor: data.mainColor || null,
    color: data.color || null,
    calendarColor: data.calendarColor || null,
    groupId: (targetType === 'group' && targetId != null) ? targetId : null,
  }

  const task = await api.post(`/projects/${board.id}/tasks`, payload)
  task.notes = task.notes || []
  task.comments = task.comments || []
  task.attachments = task.attachments || []

  if (targetType === 'group' && targetId != null) {
    const group = board.groups.find(g => g.id === targetId)
    if (group) {
      group.tasks.push(task)
      logActivity(board.id, 'task_added', `${actor()} added task "${data.text}"`)
      return
    }
  }
  board.backlog.push(task)
  logActivity(board.id, 'task_added', `${actor()} added task "${data.text}"`)
}

export async function updateTask(taskId, data) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  if (!task) return
  const name = task.text
  if (data.labelIds && JSON.stringify(data.labelIds) !== JSON.stringify(task.labelIds))
    logActivity(board.id, 'labels_changed', `${actor()} changed labels on "${name}"`)
  if ('deadline' in data && data.deadline !== task.deadline)
    logActivity(board.id, 'deadline_changed', `${actor()} updated deadline on "${name}"`)
  if ('status' in data && data.status !== task.status) {
    logActivity(board.id, 'status_changed', `${actor()} marked "${name}" as ${STATUS_META[data.status]?.label ?? data.status}`)
  }
  Object.assign(task, data)
  await api.patch(`/tasks/${taskId}`, data)
}

export async function deleteTask(taskId, source, groupId) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  const taskName = task?.text || 'a task'
  if (source === 'backlog') {
    const idx = board.backlog.findIndex((t) => t.id === taskId)
    if (idx !== -1) board.backlog.splice(idx, 1)
  } else if (source === 'group' && groupId != null) {
    const group = board.groups.find((g) => g.id === groupId)
    if (group) {
      const idx = group.tasks.findIndex((t) => t.id === taskId)
      if (idx !== -1) group.tasks.splice(idx, 1)
    }
  } else {
    const bi = board.backlog.findIndex((t) => t.id === taskId)
    if (bi !== -1) {
      board.backlog.splice(bi, 1)
    } else {
      for (const g of board.groups) {
        const ti = g.tasks.findIndex((t) => t.id === taskId)
        if (ti !== -1) { g.tasks.splice(ti, 1); break }
      }
    }
  }
  logActivity(board.id, 'task_deleted', `${actor()} deleted task "${taskName}"`)
  await api.delete(`/tasks/${taskId}`)
}

export async function addComment(taskId, text) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  if (!task) return
  const comment = await api.post(`/tasks/${taskId}/comments`, { text })
  task.comments.push(comment)
  logActivity(board.id, 'comment_added', `${actor()} commented on "${task.text}"`)
}

export async function pinComment(taskId, commentId) {
  const task = findTask(taskId)
  if (!task) return
  const c = task.comments.find(c => c.id === commentId)
  if (!c) return
  const res = await api.patch(`/comments/${commentId}/pin`)
  c.pinned = res.pinned
}

export async function deleteComment(taskId, commentId) {
  const task = findTask(taskId)
  if (!task) return
  const idx = task.comments.findIndex(c => c.id === commentId)
  if (idx !== -1) task.comments.splice(idx, 1)
  await api.delete(`/comments/${commentId}`)
}

export async function editComment(taskId, commentId, newText) {
  const task = findTask(taskId)
  if (!task) return
  const c = task.comments.find(c => c.id === commentId)
  if (!c) return
  const res = await api.patch(`/comments/${commentId}`, { text: newText })
  c.text = res.text
  c.editedAt = res.editedAt
}

/* -- Note actions -- */
export async function addNote(taskId, noteData) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  if (!task) return
  if (!task.notes) task.notes = []
  const note = await api.post(`/tasks/${taskId}/notes`, noteData)
  task.notes.push(note)
}

export async function updateNote(taskId, noteId, updates) {
  const task = findTask(taskId)
  if (!task?.notes) return
  const note = task.notes.find(n => n.id === noteId)
  if (!note) return
  const res = await api.patch(`/notes/${noteId}`, updates)
  Object.assign(note, res)
}

export async function deleteNote(taskId, noteId) {
  const task = findTask(taskId)
  if (!task?.notes) return
  const idx = task.notes.findIndex(n => n.id === noteId)
  if (idx !== -1) task.notes.splice(idx, 1)
  await api.delete(`/notes/${noteId}`)
}

/* -- Label actions -- */
export async function createLabel(name, color) {
  const board = b(); if (!board) return
  const label = await api.post(`/projects/${board.id}/labels`, { name, color })
  board.labels.push(label)
}

export async function updateLabel(labelId, name, color) {
  const board = b(); if (!board) return
  const label = board.labels.find((l) => l.id === labelId)
  if (!label) return
  const res = await api.patch(`/labels/${labelId}`, { name, color })
  label.name = res.name
  label.color = res.color
}

export async function deleteLabel(labelId) {
  const board = b(); if (!board) return
  const idx = board.labels.findIndex((l) => l.id === labelId)
  if (idx !== -1) board.labels.splice(idx, 1)
  const all = [...board.backlog, ...board.groups.flatMap((g) => g.tasks)]
  for (const task of all) {
    task.labelIds = task.labelIds.filter((id) => id !== labelId)
  }
  await api.delete(`/labels/${labelId}`)
}

/* -- Drag & Drop -- */
export async function moveTaskToGroup(taskId, targetGroupId) {
  const board = b(); if (!board) return
  let task = null
  const backlogIdx = board.backlog.findIndex((t) => t.id === taskId)
  if (backlogIdx !== -1) {
    task = board.backlog.splice(backlogIdx, 1)[0]
  } else {
    for (const group of board.groups) {
      const idx = group.tasks.findIndex((t) => t.id === taskId)
      if (idx !== -1) { task = group.tasks.splice(idx, 1)[0]; break }
    }
  }
  if (task) {
    const targetGroup = board.groups.find((g) => g.id === targetGroupId)
    if (targetGroup) {
      targetGroup.tasks.push(task)
      await api.patch(`/tasks/${taskId}/move`, { groupId: targetGroupId })
    }
  }
}

export async function moveTaskToBacklog(taskId) {
  const board = b(); if (!board) return
  for (const group of board.groups) {
    const idx = group.tasks.findIndex((t) => t.id === taskId)
    if (idx !== -1) {
      const task = group.tasks.splice(idx, 1)[0]
      board.backlog.push(task)
      await api.patch(`/tasks/${taskId}/move`, { groupId: null })
      return
    }
  }
}
