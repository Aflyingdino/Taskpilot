import { reactive, computed } from 'vue'
import { api } from '@/utils/api'

/* ─────────────────────────────────────────────
   Project Store — multi-project state
   All data is persisted via PHP/MySQL backend.
───────────────────────────────────────────── */

const _state = reactive({
  projects: [],
  activeProjectId: null,
  loading: false,
  error: null,
})

// ─── Getters ───────────────────────────────────
export const projects = computed(() => _state.projects.filter(p => !p.archived))
export const archivedProjects = computed(() => _state.projects.filter(p => !!p.archived))
export const activeProjectId = computed(() => _state.activeProjectId)
export const activeProject = computed(() =>
  _state.projects.find(p => p.id === _state.activeProjectId && !p.archived) ?? null
)
export const projectsLoading = computed(() => _state.loading)

/* ─── Internal helpers ──────────────────────── */

/** Replace or insert a project in the local state from server data. */
function _upsert(project) {
  const idx = _state.projects.findIndex(p => p.id === project.id)
  if (idx !== -1) {
    _state.projects[idx] = project
  } else {
    _state.projects.push(project)
  }
}

/* ─── Project actions ─────────────────────── */

export async function fetchProjects() {
  _state.loading = true
  try {
    const data = await api.get('/projects')
    _state.projects = data
  } catch (err) {
    _state.error = err.message
  } finally {
    _state.loading = false
  }
}

export async function fetchProject(projectId) {
  const data = await api.get(`/projects/${projectId}`)
  _upsert(data)
  return data
}

export async function createProject(name, description = '', color = null) {
  const data = await api.post('/projects', { name, description, color })
  _state.projects.push(data)
  return data
}

export async function updateProject(projectId, updates) {
  const data = await api.patch(`/projects/${projectId}`, updates)
  _upsert(data)
}

export async function deleteProject(projectId) {
  await api.delete(`/projects/${projectId}`)
  const idx = _state.projects.findIndex(p => p.id === projectId)
  if (idx !== -1) _state.projects.splice(idx, 1)
  if (_state.activeProjectId === projectId) _state.activeProjectId = null
}

export async function archiveProject(projectId) {
  const data = await api.post(`/projects/${projectId}/archive`)
  _upsert(data)
  if (_state.activeProjectId === projectId) _state.activeProjectId = null
}

export async function restoreProject(projectId) {
  const data = await api.post(`/projects/${projectId}/restore`)
  _upsert(data)
}

export async function deleteArchivedProject(projectId) {
  await api.delete(`/projects/${projectId}`)
  const idx = _state.projects.findIndex(p => p.id === projectId)
  if (idx !== -1) _state.projects.splice(idx, 1)
}

export function setActiveProject(projectId) {
  _state.activeProjectId = projectId
}

export function reorderProjects(fromId, toId) {
  const list = _state.projects
  const fromIdx = list.findIndex(p => p.id === fromId)
  const toIdx   = list.findIndex(p => p.id === toId)
  if (fromIdx === -1 || toIdx === -1 || fromIdx === toIdx) return
  const [removed] = list.splice(fromIdx, 1)
  list.splice(toIdx, 0, removed)
}

/* ─── Member actions ─────────────────────── */

export async function addMember(projectId, email, role = 'collaborator') {
  const member = await api.post(`/projects/${projectId}/members`, { email, role })
  const p = _state.projects.find(p => p.id === projectId)
  if (p) p.members.push(member)
}

export async function removeMember(projectId, memberId) {
  await api.delete(`/projects/${projectId}/members/${memberId}`)
  const p = _state.projects.find(p => p.id === projectId)
  if (p) {
    const idx = p.members.findIndex(m => m.id === memberId)
    if (idx !== -1) p.members.splice(idx, 1)
  }
}

export async function updateMemberRole(projectId, memberId, role) {
  await api.patch(`/projects/${projectId}/members/${memberId}`, { role })
  const p = _state.projects.find(p => p.id === projectId)
  const m = p?.members.find(m => m.id === memberId)
  if (m) m.role = role
}

/* ─── Share link ─────────────────────────── */

export async function generateShareLink(projectId) {
  const data = await api.post(`/projects/${projectId}/share`)
  const p = _state.projects.find(p => p.id === projectId)
  if (p) p.shareId = data.shareId
  return data.shareId
}

export async function revokeShareLink(projectId) {
  await api.delete(`/projects/${projectId}/share`)
  const p = _state.projects.find(p => p.id === projectId)
  if (p) p.shareId = null
}

/* ─── Public project (for share links) ───── */

export async function fetchPublicProject(shareToken) {
  return await api.get(`/public/${shareToken}`)
}

/* ─── Calendar scheduling ─────────────────── */

export async function scheduleTask(projectId, taskId, calendarStart, calendarDuration) {
  await api.patch(`/tasks/${taskId}/schedule`, { calendarStart, calendarDuration })
  const p = _state.projects.find(p => p.id === projectId)
  if (!p) return
  const all = [...p.backlog, ...p.groups.flatMap(g => g.tasks)]
  const task = all.find(t => t.id === taskId)
  if (task) {
    task.calendarStart    = calendarStart
    task.calendarDuration = calendarDuration
  }
}

export async function unscheduleTask(projectId, taskId) {
  await api.delete(`/tasks/${taskId}/schedule`)
  const p = _state.projects.find(p => p.id === projectId)
  if (!p) return
  const all = [...p.backlog, ...p.groups.flatMap(g => g.tasks)]
  const task = all.find(t => t.id === taskId)
  if (task) { task.calendarStart = null; task.calendarDuration = null }
}

/* ─── Activity log ──────────────────────────── */

export function logActivity(projectId, type, message) {
  const p = _state.projects.find(p => p.id === projectId)
  if (!p) return
  if (!p.activity) p.activity = []
  p.activity.unshift({
    id: Date.now(),
    type,
    message,
    projectId,
    projectName: p.name,
    projectColor: p.color,
    createdAt: new Date().toISOString(),
  })
  if (p.activity.length > 200) p.activity.length = 200
}

/* ─── Expose for boardStore ─── */
export function _save() { /* no-op — persistence is via API now */ }
