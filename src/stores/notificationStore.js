import { reactive, computed } from 'vue'
import { projects } from './projectStore'

/* ─────────────────────────────────────────────
   Notification Store
   Handles in-app notifications with mute support,
   read state, archive/delete, and multi-select
───────────────────────────────────────────── */

let _nid = 1

const _state = reactive({
  notifications: [],        // { id, type, title, body, taskId, projectId, groupId, createdAt, read, archived }
  mutedProjectIds: new Set(),
  mutedGroupIds:   new Set(),
  mutedTaskIds:    new Set(),
  mutedLabelIds:   new Set(),
  lastChecked:     null,    // ISO string — prevents duplicate alerts on reload
})

// Restore mutes from localStorage
const _saved = localStorage.getItem('tp_notification_prefs')
if (_saved) {
  try {
    const p = JSON.parse(_saved)
    _state.mutedProjectIds = new Set(p.mutedProjectIds || [])
    _state.mutedGroupIds   = new Set(p.mutedGroupIds   || [])
    _state.mutedTaskIds    = new Set(p.mutedTaskIds    || [])
    _state.mutedLabelIds   = new Set(p.mutedLabelIds   || [])
  } catch { /* ignore */ }
}

function _savePrefs() {
  localStorage.setItem('tp_notification_prefs', JSON.stringify({
    mutedProjectIds: [..._state.mutedProjectIds],
    mutedGroupIds:   [..._state.mutedGroupIds],
    mutedTaskIds:    [..._state.mutedTaskIds],
    mutedLabelIds:   [..._state.mutedLabelIds],
  }))
}

/* ── Computed ── */
export const notifications = computed(() =>
  _state.notifications.filter(n => !n.archived).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
)
export const unreadCount = computed(() =>
  _state.notifications.filter(n => !n.read && !n.archived).length
)
export const mutedProjectIds = computed(() => _state.mutedProjectIds)
export const mutedGroupIds   = computed(() => _state.mutedGroupIds)
export const mutedTaskIds    = computed(() => _state.mutedTaskIds)
export const mutedLabelIds   = computed(() => _state.mutedLabelIds)

/* ── Add notification ── */
export function addNotification({ type, title, body, taskId = null, projectId = null, groupId = null, labelId = null }) {
  // Check mutes
  if (projectId && _state.mutedProjectIds.has(projectId)) return
  if (groupId   && _state.mutedGroupIds.has(groupId))     return
  if (taskId    && _state.mutedTaskIds.has(taskId))       return
  if (labelId   && _state.mutedLabelIds.has(labelId))     return

  // Dedup: don't add same (type+taskId) within 5 minutes
  const dedup = _state.notifications.find(n =>
    n.type === type && n.taskId === taskId &&
    (Date.now() - new Date(n.createdAt).getTime()) < 5 * 60 * 1000
  )
  if (dedup) return

  _state.notifications.push({
    id: _nid++,
    type, title, body,
    taskId, projectId, groupId, labelId,
    createdAt: new Date().toISOString(),
    read: false,
    archived: false,
  })
}

/* ── Mark read ── */
export function markRead(id) {
  const n = _state.notifications.find(n => n.id === id)
  if (n) n.read = true
}
export function markAllRead() {
  _state.notifications.forEach(n => { n.read = true })
}

/* ── Archive ── */
export function archiveNotification(id) {
  const n = _state.notifications.find(n => n.id === id)
  if (n) n.archived = true
}
export function archiveMany(ids) {
  for (const id of ids) archiveNotification(id)
}

/* ── Delete ── */
export function deleteNotification(id) {
  const idx = _state.notifications.findIndex(n => n.id === id)
  if (idx !== -1) _state.notifications.splice(idx, 1)
}
export function deleteMany(ids) {
  for (const id of ids) deleteNotification(id)
}
export function clearAll() {
  _state.notifications = []
}

/* ── Mute toggles ── */
export function toggleMuteProject(id) {
  if (_state.mutedProjectIds.has(id)) _state.mutedProjectIds.delete(id)
  else _state.mutedProjectIds.add(id)
  _savePrefs()
}
export function toggleMuteGroup(id) {
  if (_state.mutedGroupIds.has(id)) _state.mutedGroupIds.delete(id)
  else _state.mutedGroupIds.add(id)
  _savePrefs()
}
export function toggleMuteTask(id) {
  if (_state.mutedTaskIds.has(id)) _state.mutedTaskIds.delete(id)
  else _state.mutedTaskIds.add(id)
  _savePrefs()
}
export function toggleMuteLabel(id) {
  if (_state.mutedLabelIds.has(id)) _state.mutedLabelIds.delete(id)
  else _state.mutedLabelIds.add(id)
  _savePrefs()
}

/* ── Automatic: check for tasks starting soon and deadlines ── */
const _alerted = new Set()   // taskId keys already alerted this session

export function checkTaskAlerts() {
  const now = Date.now()
  const soon15  = now + 15 * 60 * 1000   // 15 minutes
  const soon60  = now + 60 * 60 * 1000   // 60 minutes
  const in24h   = now + 24 * 60 * 60 * 1000

  for (const p of projects.value) {
    const allTasks = [...p.backlog, ...p.groups.flatMap(g => g.tasks)]
    for (const task of allTasks) {
      // Task starting soon
      if (task.calendarStart) {
        const start = new Date(task.calendarStart).getTime()
        const keyStart15 = `start15-${task.id}`
        const keyStart60 = `start60-${task.id}`
        if (start > now && start <= soon15 && !_alerted.has(keyStart15)) {
          _alerted.add(keyStart15)
          addNotification({
            type: 'task_start_soon',
            title: 'Task starting in 15 minutes',
            body: task.text,
            taskId: task.id,
            projectId: p.id,
          })
        } else if (start > soon15 && start <= soon60 && !_alerted.has(keyStart60)) {
          _alerted.add(keyStart60)
          addNotification({
            type: 'task_start_hour',
            title: 'Task starting in 1 hour',
            body: task.text,
            taskId: task.id,
            projectId: p.id,
          })
        }
      }

      // Deadline soon
      if (task.deadline && task.status !== 'done') {
        const dl = new Date(task.deadline).getTime()
        const keyDL = `deadline-${task.id}`
        if (dl > now && dl <= in24h && !_alerted.has(keyDL)) {
          _alerted.add(keyDL)
          addNotification({
            type: 'deadline_soon',
            title: 'Deadline in 24 hours',
            body: task.text,
            taskId: task.id,
            projectId: p.id,
          })
        }
      }
    }
  }
}
