import { computed } from 'vue'
import {
  activeProject,
  _nextGroupId, _nextTaskId, _nextLabelId, _nextCommentId, _nextNoteId, _save,
  logActivity,
} from './projectStore'
import { user } from './authStore'
import { STATUS_META } from '../utils/constants'

function actor() {
  return user.name || 'Someone'
}

/* ─────────────────────────────────────────────
   Board Store — thin layer over the active project
   All state lives in projectStore.activeProject
───────────────────────────────────────────── */

function b() { return activeProject.value }

/* ── Getters ── */
export const groups         = computed(() => b()?.groups ?? [])
export const backlog        = computed(() => b()?.backlog ?? [])
export const projectLabels  = computed(() => b()?.labels ?? [])
export const archivedGroups = computed(() => b()?.archivedGroups ?? [])

/* ── Task lookup ── */
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

/* ── Group actions ── */
export function createGroup(name) {
  const board = b(); if (!board) return
  // Find next free row in col 0
  const usedRows = board.groups
    .filter(g => (g.gridCol ?? 0) === 0)
    .map(g => g.gridRow ?? 0)
  let nextRow = 0
  while (usedRows.includes(nextRow)) nextRow++
  const id = _nextGroupId()
  board.groups.push({
    id,
    name: name || `Group ${id}`,
    tasks: [],
    description: '',
    deadline: null,
    priority: 'medium',
    status: 'not_started',
    labelIds: [],
    color: null,
    mainColor: null,
    gridRow: nextRow,
    gridCol: 0,
  })
  _save()
}

// After any grid move, pack each column so there are no row gaps (rows start at 0, no holes).
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

export function moveGroupToGrid(fromId, toRow, toCol) {
  const board = b(); if (!board) return
  const fromGroup = board.groups.find(g => g.id === fromId)
  if (!fromGroup) return
  // Check for occupant at target cell
  const toGroup = board.groups.find(
    g => g.id !== fromId && (g.gridRow ?? 0) === toRow && (g.gridCol ?? 0) === toCol
  )
  if (toGroup) {
    // Swap: occupant takes dragged group's old position
    toGroup.gridRow = fromGroup.gridRow ?? 0
    toGroup.gridCol = fromGroup.gridCol ?? 0
  }
  fromGroup.gridRow = toRow
  fromGroup.gridCol = toCol
  // Close any gaps that opened in the source column (or any column)
  compactColumns(board)
  _save()
}

export function updateGroup(groupId, data) {
  const board = b(); if (!board) return
  const group = board.groups.find(g => g.id === groupId)
  if (group) { Object.assign(group, data); _save() }
}

export function deleteGroup(groupId) {
  const board = b(); if (!board) return
  const idx = board.groups.findIndex((g) => g.id === groupId)
  if (idx === -1) return
  board.backlog.push(...board.groups[idx].tasks)
  board.groups.splice(idx, 1)
  _save()
}

export function archiveGroup(groupId) {
  const board = b(); if (!board) return
  const idx = board.groups.findIndex(g => g.id === groupId)
  if (idx === -1) return
  if (!board.archivedGroups) board.archivedGroups = []
  const [group] = board.groups.splice(idx, 1)
  group.archivedAt = new Date().toISOString()
  board.archivedGroups.push(group)
  _save()
}

export function restoreGroup(groupId) {
  const board = b(); if (!board) return
  if (!board.archivedGroups) return
  const idx = board.archivedGroups.findIndex(g => g.id === groupId)
  if (idx === -1) return
  const [group] = board.archivedGroups.splice(idx, 1)
  delete group.archivedAt
  board.groups.push(group)
  _save()
}

export function deleteArchivedGroup(groupId) {
  const board = b(); if (!board) return
  if (!board.archivedGroups) return
  const idx = board.archivedGroups.findIndex(g => g.id === groupId)
  if (idx !== -1) { board.archivedGroups.splice(idx, 1); _save() }
}

export function reorderGroups(fromGroupId, toGroupId) {
  const board = b(); if (!board) return
  const fromIdx = board.groups.findIndex(g => g.id === fromGroupId)
  const toIdx   = board.groups.findIndex(g => g.id === toGroupId)
  if (fromIdx === -1 || toIdx === -1 || fromIdx === toIdx) return
  const [group] = board.groups.splice(fromIdx, 1)
  board.groups.splice(toIdx, 0, group)
  _save()
}

export function renameGroup(groupId, newName) {
  const group = b()?.groups.find((g) => g.id === groupId)
  if (group) { group.name = newName; _save() }
}

/* ── Task actions ── */
export function createTask(data, targetType, targetId) {
  const board = b(); if (!board) return
  const task = {
    id: _nextTaskId(),
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
    notes: data.notes || [],
    createdAt: new Date().toISOString(),
    comments: [],
  }
  if (targetType === 'group' && targetId != null) {
    const group = board.groups.find(g => g.id === targetId)
    if (group) {
      group.tasks.push(task)
      _save()
      logActivity(board.id, 'task_added', `${actor()} added task "${data.text}"`)
      return
    }
  }
  board.backlog.push(task)
  _save()
  logActivity(board.id, 'task_added', `${actor()} added task “${data.text}”`)
}

export function updateTask(taskId, data) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  if (!task) return
  const name = task.text
  // Detect what changed for the activity log
  if (data.labelIds && JSON.stringify(data.labelIds) !== JSON.stringify(task.labelIds))
    logActivity(board.id, 'labels_changed', `${actor()} changed labels on “${name}”`)
  if ('deadline' in data && data.deadline !== task.deadline)
    logActivity(board.id, 'deadline_changed', `${actor()} updated deadline on “${name}”`)
  if ('status' in data && data.status !== task.status) {
    logActivity(board.id, 'status_changed', `${actor()} marked "${name}" as ${STATUS_META[data.status]?.label ?? data.status}`)
  }
  Object.assign(task, data)
  _save()
}

export function deleteTask(taskId, source, groupId) {
  const board = b(); if (!board) return
  // Find task text before deleting for the log
  const task = findTask(taskId)
  const taskName = task?.text || 'a task'
  if (source === 'backlog') {
    const idx = board.backlog.findIndex((t) => t.id === taskId)
    if (idx !== -1) { board.backlog.splice(idx, 1); _save() }
  } else if (source === 'group' && groupId != null) {
    const group = board.groups.find((g) => g.id === groupId)
    if (group) {
      const idx = group.tasks.findIndex((t) => t.id === taskId)
      if (idx !== -1) { group.tasks.splice(idx, 1); _save() }
    }
  } else {
    const bi = board.backlog.findIndex((t) => t.id === taskId)
    if (bi !== -1) { board.backlog.splice(bi, 1); _save()
    } else {
      for (const g of board.groups) {
        const ti = g.tasks.findIndex((t) => t.id === taskId)
        if (ti !== -1) { g.tasks.splice(ti, 1); _save(); break }
      }
    }
  }
  logActivity(board.id, 'task_deleted', `${actor()} deleted task “${taskName}”`)
}

export function addComment(taskId, text) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  if (task) {
    task.comments.push({ id: _nextCommentId(), text, author: actor(), createdAt: new Date().toISOString(), pinned: false })
    _save()
    logActivity(board.id, 'comment_added', `${actor()} commented on "${task.text}"`)
  }
}

export function pinComment(taskId, commentId) {
  const task = findTask(taskId)
  if (!task) return
  const c = task.comments.find(c => c.id === commentId)
  if (c) { c.pinned = !c.pinned; _save() }
}

export function deleteComment(taskId, commentId) {
  const task = findTask(taskId)
  if (!task) return
  const idx = task.comments.findIndex(c => c.id === commentId)
  if (idx !== -1) { task.comments.splice(idx, 1); _save() }
}

export function editComment(taskId, commentId, newText) {
  const task = findTask(taskId)
  if (!task) return
  const c = task.comments.find(c => c.id === commentId)
  if (c) { c.text = newText; c.editedAt = new Date().toISOString(); _save() }
}
/* ── Note actions ── */
export function addNote(taskId, noteData) {
  const board = b(); if (!board) return
  const task = findTask(taskId)
  if (!task) return
  if (!task.notes) task.notes = []
  task.notes.push({
    id: _nextNoteId(),
    title:       noteData.title || 'Note',
    content:     noteData.content || '',
    contentType: noteData.contentType || 'text',   // 'text' | 'image' | 'video'
    bgColor:     noteData.bgColor || '#5b5bd6',
    textColor:   noteData.textColor || '#ffffff',
    createdBy:   user.name || 'Unknown',
    createdAt:   new Date().toISOString(),
  })
  _save()
}

export function updateNote(taskId, noteId, updates) {
  const task = findTask(taskId)
  if (!task?.notes) return
  const note = task.notes.find(n => n.id === noteId)
  if (note) { Object.assign(note, updates); _save() }
}

export function deleteNote(taskId, noteId) {
  const task = findTask(taskId)
  if (!task?.notes) return
  const idx = task.notes.findIndex(n => n.id === noteId)
  if (idx !== -1) { task.notes.splice(idx, 1); _save() }
}
/* ── Label actions ── */
export function createLabel(name, color) {
  const board = b(); if (!board) return
  board.labels.push({ id: _nextLabelId(), name, color })
  _save()
}

export function updateLabel(labelId, name, color) {
  const label = b()?.labels.find((l) => l.id === labelId)
  if (label) { label.name = name; label.color = color; _save() }
}

export function deleteLabel(labelId) {
  const board = b(); if (!board) return
  const idx = board.labels.findIndex((l) => l.id === labelId)
  if (idx !== -1) board.labels.splice(idx, 1)
  const all = [...board.backlog, ...board.groups.flatMap((g) => g.tasks)]
  for (const task of all) {
    task.labelIds = task.labelIds.filter((id) => id !== labelId)
  }
  _save()
}

/* ── Drag & Drop ── */
export function moveTaskToGroup(taskId, targetGroupId) {
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
    if (targetGroup) { targetGroup.tasks.push(task); _save() }
  }
}

export function moveTaskToBacklog(taskId) {
  const board = b(); if (!board) return
  for (const group of board.groups) {
    const idx = group.tasks.findIndex((t) => t.id === taskId)
    if (idx !== -1) {
      const task = group.tasks.splice(idx, 1)[0]
      board.backlog.push(task)
      _save()
      return
    }
  }
}
