<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import { projects, setActiveProject } from '@/stores/projectStore'
import { user } from '@/stores/authStore'
import { openTaskDetail } from '@/stores/uiStore'
import { toggleMuteProject, mutedProjectIds, toggleMuteTask, mutedTaskIds } from '@/stores/notificationStore'


const router = useRouter()

const activeTab = ref('overview') // 'overview' | 'projects' | 'activity'

// Gather all tasks across all projects
const allTasks = computed(() => {
  const tasks = []
  for (const p of projects.value) {
    for (const t of [...p.backlog, ...p.groups.flatMap(g => g.tasks)]) {
      tasks.push({ ...t, projectId: p.id, projectName: p.name, projectColor: p.color })
    }
  }
  return tasks
})

const stats = computed(() => {
  const all = allTasks.value
  return {
    total: all.length,
    notStarted: all.filter(t => t.status === 'not_started').length,
    started: all.filter(t => t.status === 'started').length,
    readyForTest: all.filter(t => t.status === 'ready_for_test').length,
    done: all.filter(t => t.status === 'done').length,
  }
})

const overdueTasks = computed(() => {
  const now = new Date()
  return allTasks.value.filter(t => t.deadline && t.status !== 'done' && new Date(t.deadline) < now)
})

const recentTasks = computed(() =>
  [...allTasks.value]
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
    .slice(0, 8)
)

// ── Activity feed ──────────────────────────────────────────────────
// 1. Deadline warnings (computed, not stored)
const deadlineWarnings = computed(() => {
  const now = new Date()
  const in24h = new Date(now.getTime() + 24 * 60 * 60 * 1000)
  const warnings = []
  for (const t of allTasks.value) {
    if (!t.deadline || t.status === 'done') continue
    const dl = new Date(t.deadline)
    if (dl < now) {
      warnings.push({
        id: `warn-${t.id}-overdue`,
        type: 'deadline_expired',
        message: `Deadline on "${t.text}" has passed`,
        projectName: t.projectName,
        projectColor: t.projectColor,
        createdAt: t.deadline,
      })
    } else if (dl <= in24h) {
      warnings.push({
        id: `warn-${t.id}-soon`,
        type: 'deadline_soon',
        message: `Deadline on "${t.text}" expires within 24 hours`,
        projectName: t.projectName,
        projectColor: t.projectColor,
        createdAt: t.deadline,
      })
    }
  }
  return warnings
})

// 2. Stored activity log from all projects
const storedActivity = computed(() => {
  const entries = []
  for (const p of projects.value) {
    for (const a of (p.activity || [])) {
      entries.push(a)
    }
  }
  return entries.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 100)
})

// 3. Combined feed: warnings first, then stored activity
const activityFeed = computed(() => {
  return [...deadlineWarnings.value, ...storedActivity.value]
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
    .slice(0, 80)
})

const ACTIVITY_ICONS = {
  task_added:       { icon: 'plus',     color: '#46a758' },
  task_deleted:     { icon: 'trash',    color: '#e5484d' },
  status_changed:   { icon: 'refresh',  color: '#5b5bd6' },
  labels_changed:   { icon: 'tag',      color: '#8e4ec6' },
  deadline_changed: { icon: 'calendar', color: '#f5c842' },
  comment_added:    { icon: 'chat',     color: '#5eb1ef' },
  deadline_soon:    { icon: 'clock',    color: '#f76b15' },
  deadline_expired: { icon: 'alert',    color: '#e5484d' },
}

function activityMeta(type) {
  return ACTIVITY_ICONS[type] || { icon: 'dot', color: '#8a8a9a' }
}

function formatRelative(iso) {
  if (!iso) return ''
  const diff = Date.now() - new Date(iso).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return 'just now'
  if (m < 60) return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h ago`
  const d = Math.floor(h / 24)
  if (d < 7) return `${d}d ago`
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}

const STATUS_META = {
  not_started:    { label: 'Not started', color: '#52525f' },
  started:        { label: 'Started',     color: '#5b5bd6' },
  ready_for_test: { label: 'Ready for test', color: '#f5c842' },
  done:           { label: 'Done',         color: '#46a758' },
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function goToProject(task) {
  setActiveProject(task.projectId)
  router.push({ name: 'board', params: { id: task.projectId } })
}

/* ── Clickable stat filter ── */
const statFilter = ref(null) // null | 'total' | 'started' | 'overdue' | 'done'

const filteredStatTasks = computed(() => {
  if (!statFilter.value) return []
  if (statFilter.value === 'total') return allTasks.value
  if (statFilter.value === 'started') return allTasks.value.filter(t => t.status === 'started')
  if (statFilter.value === 'overdue') return overdueTasks.value
  if (statFilter.value === 'done') return allTasks.value.filter(t => t.status === 'done')
  return []
})

const statFilterLabel = computed(() => ({
  total: 'All tasks', started: 'In progress', overdue: 'Overdue', done: 'Completed'
})[statFilter.value])

function toggleStatFilter(key) {
  statFilter.value = statFilter.value === key ? null : key
}

// ── Activity: read state ──
const readActivityIds = ref(new Set(JSON.parse(localStorage.getItem('tp_read_activity') || '[]')))

function persistRead() {
  localStorage.setItem('tp_read_activity', JSON.stringify([...readActivityIds.value]))
}
function markRead(id) {
  readActivityIds.value.add(id)
  persistRead()
}
function markAllRead() {
  for (const e of activityFeed.value) readActivityIds.value.add(e.id)
  persistRead()
}
function isRead(id) { return readActivityIds.value.has(id) }

// ── Activity: detail modal ──
const activityDetail = ref(null)

function openActivityDetail(entry) {
  markRead(entry.id)
  activityDetail.value = entry
}
function closeActivityDetail() { activityDetail.value = null }

function formatFull(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ── Activity: shift-select ──
const selectedActivityIds = ref(new Set())
const lastSelectedIdx = ref(-1)

function toggleSelect(entry, idx, e) {
  const id = entry.id
  if (e.shiftKey && lastSelectedIdx.value >= 0) {
    const lo = Math.min(lastSelectedIdx.value, idx)
    const hi = Math.max(lastSelectedIdx.value, idx)
    for (let i = lo; i <= hi; i++) {
      selectedActivityIds.value.add(activityFeed.value[i].id)
    }
  } else {
    if (selectedActivityIds.value.has(id)) {
      selectedActivityIds.value.delete(id)
    } else {
      selectedActivityIds.value.add(id)
      lastSelectedIdx.value = idx
    }
  }
}
function clearSelection() { selectedActivityIds.value = new Set(); lastSelectedIdx.value = -1 }
function markSelectedRead() {
  for (const id of selectedActivityIds.value) readActivityIds.value.add(id)
  persistRead()
  clearSelection()
}

// ── Navigate to task from activity detail ──
function goToActivityTask(entry) {
  if (!entry.projectId) return
  setActiveProject(entry.projectId)
  router.push({ name: 'board', params: { id: entry.projectId } })
  if (entry.taskId) setTimeout(() => openTaskDetail(entry.taskId), 350)
  closeActivityDetail()
}
</script>

<template>
  <AppLayout>
    <div class="dashboard-page">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">Welcome back, {{ user.name }}</p>
      </div>

      <!-- Stats row (always visible) -->
      <div class="stats-grid">
        <button class="stat-card" :class="{ 'stat-card--active': statFilter === 'total' }" @click="toggleStatFilter('total')">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total tasks</div>
        </button>
        <button class="stat-card stat-card--accent" :class="{ 'stat-card--active': statFilter === 'started' }" @click="toggleStatFilter('started')">
          <div class="stat-value">{{ stats.started }}</div>
          <div class="stat-label">In progress</div>
        </button>
        <button class="stat-card stat-card--warn" :class="{ 'stat-card--active': statFilter === 'overdue' }" @click="toggleStatFilter('overdue')">
          <div class="stat-value">{{ overdueTasks.length }}</div>
          <div class="stat-label">Overdue</div>
        </button>
        <button class="stat-card stat-card--done" :class="{ 'stat-card--active': statFilter === 'done' }" @click="toggleStatFilter('done')">
          <div class="stat-value">{{ stats.done }}</div>
          <div class="stat-label">Completed</div>
        </button>
      </div>

      <!-- Stat drill-down panel -->
      <Transition name="stat-panel">
        <div v-if="statFilter" class="stat-panel">
          <div class="stat-panel-header">
            <span class="stat-panel-title">{{ statFilterLabel }} <span class="stat-panel-count">{{ filteredStatTasks.length }}</span></span>
            <button class="stat-panel-close" @click="statFilter = null" aria-label="Close">
              <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div v-if="filteredStatTasks.length === 0" class="stat-panel-empty">No tasks in this category.</div>
          <div class="stat-panel-list" v-else>
            <div
              v-for="t in filteredStatTasks"
              :key="t.id"
              class="stat-task-row"
              @click="setActiveProject(t.projectId); openTaskDetail(t.id)"
            >
              <span class="stat-task-dot" :style="{ background: t.projectColor }"></span>
              <span class="stat-task-text">{{ t.text }}</span>
              <span class="stat-task-project">{{ t.projectName }}</span>
              <span v-if="t.deadline" class="stat-task-date">{{ formatDate(t.deadline) }}</span>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Tabs -->
      <div class="dash-tabs">
        <button
          class="dash-tab"
          :class="{ 'dash-tab--active': activeTab === 'overview' }"
          @click="activeTab = 'overview'"
        >Overview</button>
        <button
          class="dash-tab"
          :class="{ 'dash-tab--active': activeTab === 'projects' }"
          @click="activeTab = 'projects'"
        >
          Projects
          <span class="tab-badge" v-if="projects.length">{{ projects.length }}</span>
        </button>
        <button
          class="dash-tab"
          :class="{ 'dash-tab--active': activeTab === 'activity' }"
          @click="activeTab = 'activity'"
        >
          Activity
          <span class="tab-badge tab-badge--warn" v-if="deadlineWarnings.length">{{ deadlineWarnings.length }}</span>
        </button>
      </div>

      <!-- ── Overview tab ─────────────────────────── -->
      <template v-if="activeTab === 'overview'">
        <div class="dashboard-grid">
          <!-- Overdue tasks -->
          <section class="dash-section" v-if="overdueTasks.length">
            <h2 class="section-title">
              <span class="dot dot--red"></span>
              Overdue ({{ overdueTasks.length }})
            </h2>
            <div class="task-list">
              <div
                v-for="t in overdueTasks"
                :key="t.id + '-' + t.projectId"
                class="task-row task-row--overdue"
                @click="goToProject(t)"
              >
                <div class="task-row__info">
                  <span class="task-row__text">{{ t.text }}</span>
                  <span class="task-row__project" :style="{ color: t.projectColor }">{{ t.projectName }}</span>
                </div>
                <span class="task-row__date overdue">{{ formatDate(t.deadline) }}</span>
              </div>
            </div>
          </section>

          <!-- Recent tasks -->
          <section class="dash-section">
            <h2 class="section-title">
              <span class="dot dot--blue"></span>
              Recent tasks
            </h2>
            <div class="task-list" v-if="recentTasks.length">
              <div
                v-for="t in recentTasks"
                :key="t.id + '-' + t.projectId"
                class="task-row"
                @click="goToProject(t)"
              >
                <div class="task-row__info">
                  <span class="task-row__text">{{ t.text }}</span>
                  <span class="task-row__project" :style="{ color: t.projectColor }">{{ t.projectName }}</span>
                </div>
                <span
                  class="status-chip"
                  :style="{ '--sc': STATUS_META[t.status]?.color }"
                >{{ STATUS_META[t.status]?.label }}</span>
              </div>
            </div>
            <div v-else class="empty-section">
              <p>No tasks yet. <router-link to="/projects">Create a project</router-link> to get started.</p>
            </div>
          </section>
        </div>
      </template>

      <!-- ── Projects tab ─────────────────────────── -->
      <template v-if="activeTab === 'projects'">
        <div class="tab-panel">
          <section class="dash-section">
            <h2 class="section-title">
              <span class="dot dot--green"></span>
              Projects ({{ projects.length }})
            </h2>
            <div class="project-list" v-if="projects.length">
              <router-link
                v-for="p in projects"
                :key="p.id"
                :to="{ name: 'board', params: { id: p.id } }"
                class="project-row"
                @click="setActiveProject(p.id)"
              >
                <div class="project-row__icon" :style="{ background: p.color }">{{ p.name[0] }}</div>
                <div class="project-row__info">
                  <span class="project-row__name">{{ p.name }}</span>
                  <span class="project-row__count">
                    {{ p.backlog.length + p.groups.reduce((s, g) => s + g.tasks.length, 0) }} tasks
                  </span>
                </div>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="arrow">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
              </router-link>
            </div>
            <div v-else class="empty-section">
              <p><router-link to="/projects">Create your first project</router-link></p>
            </div>
          </section>
        </div>
      </template>

      <!-- ── Activity tab ─────────────────────────── -->
      <template v-if="activeTab === 'activity'">
        <div class="tab-panel">
          <section class="dash-section">
            <h2 class="section-title">
              <span class="dot dot--orange"></span>
              Recent activity
              <span class="activity-unread-count" v-if="activityFeed.filter(e => !isRead(e.id)).length">
                {{ activityFeed.filter(e => !isRead(e.id)).length }} unread
              </span>
              <button v-if="activityFeed.length" class="activity-mark-all-btn" @click="markAllRead">Mark all read</button>
            </h2>

            <!-- Selection toolbar -->
            <Transition name="fade">
              <div v-if="selectedActivityIds.size > 0" class="activity-selection-bar">
                <span>{{ selectedActivityIds.size }} selected</span>
                <button class="activity-sel-btn" @click="markSelectedRead">Mark read</button>
                <button class="activity-sel-btn activity-sel-btn--ghost" @click="clearSelection">Clear</button>
              </div>
            </Transition>

            <div class="activity-list" v-if="activityFeed.length">
              <div
                v-for="(entry, idx) in activityFeed"
                :key="entry.id"
                class="activity-row"
                :class="{
                  'activity-row--unread': !isRead(entry.id),
                  'activity-row--selected': selectedActivityIds.has(entry.id),
                }"
                :style="{ '--ac': activityMeta(entry.type).color }"
                @click="openActivityDetail(entry)"
              >
                <!-- Checkbox -->
                <div class="activity-checkbox" @click.stop="toggleSelect(entry, idx, $event)">
                  <div class="activity-check-box" :class="{ 'activity-check-box--checked': selectedActivityIds.has(entry.id) }">
                    <svg v-if="selectedActivityIds.has(entry.id)" width="8" height="8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>

                <div class="activity-icon">
                  <svg v-if="activityMeta(entry.type).icon === 'plus'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                  </svg>
                  <svg v-else-if="activityMeta(entry.type).icon === 'trash'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                  </svg>
                  <svg v-else-if="activityMeta(entry.type).icon === 'refresh'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4v5h5M20 20v-5h-5M4.5 9A9 9 0 0 1 19.5 15M19.5 15A9 9 0 0 1 4.5 9"/>
                  </svg>
                  <svg v-else-if="activityMeta(entry.type).icon === 'tag'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                    <circle cx="7" cy="7" r="1.5" fill="currentColor" stroke="none"/>
                  </svg>
                  <svg v-else-if="activityMeta(entry.type).icon === 'calendar'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                  </svg>
                  <svg v-else-if="activityMeta(entry.type).icon === 'chat'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                  </svg>
                  <svg v-else-if="activityMeta(entry.type).icon === 'clock'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                  </svg>
                  <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                  </svg>
                </div>

                <div class="activity-body">
                  <span class="activity-message">{{ entry.message }}</span>
                  <div class="activity-meta">
                    <span class="activity-project" :style="{ color: entry.projectColor }">{{ entry.projectName }}</span>
                    <span class="activity-time">{{ formatRelative(entry.createdAt) }}</span>
                    <span v-if="!isRead(entry.id)" class="activity-unread-dot"></span>
                  </div>
                </div>

                <!-- Mute button (task if available, else project) -->
                <button
                  v-if="entry.taskId || entry.projectId"
                  class="activity-mute-btn"
                  :class="{ 'activity-mute-btn--active': entry.taskId ? mutedTaskIds.has(entry.taskId) : mutedProjectIds.has(entry.projectId) }"
                  :title="(entry.taskId ? mutedTaskIds.has(entry.taskId) : mutedProjectIds.has(entry.projectId)) ? 'Unmute notifications' : 'Mute notifications'"
                  @click.stop="entry.taskId ? toggleMuteTask(entry.taskId) : toggleMuteProject(entry.projectId)"
                >
                  <svg v-if="!(entry.taskId ? mutedTaskIds.has(entry.taskId) : mutedProjectIds.has(entry.projectId))" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                  </svg>
                  <svg v-else width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    <line x1="3" y1="3" x2="21" y2="21" stroke-linecap="round"/>
                  </svg>
                </button>
              </div>
            </div>
            <div v-else class="empty-section">
              <p>No activity yet. Changes to tasks will appear here.</p>
            </div>
          </section>
        </div>

        <!-- Activity detail modal -->
        <Teleport to="body">
          <Transition name="modal-fade">
            <div v-if="activityDetail" class="activity-modal-overlay" @click.self="closeActivityDetail">
              <div class="activity-modal">
                <div class="activity-modal-hdr">
                  <div class="activity-modal-icon" :style="{ '--ac': activityMeta(activityDetail.type).color }">
                    <svg v-if="activityMeta(activityDetail.type).icon === 'plus'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    <svg v-else-if="activityMeta(activityDetail.type).icon === 'trash'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                    <svg v-else-if="activityMeta(activityDetail.type).icon === 'refresh'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v5h5M20 20v-5h-5M4.5 9A9 9 0 0 1 19.5 15M19.5 15A9 9 0 0 1 4.5 9"/></svg>
                    <svg v-else-if="activityMeta(activityDetail.type).icon === 'clock'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                  </div>
                  <span class="activity-modal-type">{{ activityDetail.type.replace(/_/g, ' ') }}</span>
                  <button class="activity-modal-close" @click="closeActivityDetail">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
                <div class="activity-modal-body">
                  <p class="activity-modal-message">{{ activityDetail.message }}</p>
                  <div class="activity-modal-meta">
                    <span class="activity-modal-project" :style="{ color: activityDetail.projectColor }">
                      {{ activityDetail.projectName }}
                    </span>
                    <span class="activity-modal-time">{{ formatFull(activityDetail.createdAt) }}</span>
                  </div>
                </div>
                <div class="activity-modal-actions">
                  <button v-if="activityDetail.projectId" class="activity-modal-goto" @click="goToActivityTask(activityDetail)">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    Go to project
                  </button>
                  <button class="activity-modal-dismiss" @click="closeActivityDetail">Close</button>
                </div>
              </div>
            </div>
          </Transition>
        </Teleport>
      </template>

    </div>
  </AppLayout>
</template>

<style scoped>
.dashboard-page {
  flex: 1;
  overflow-y: auto;
  padding: 32px 40px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.page-header { display: flex; flex-direction: column; gap: 4px; }
.page-title { font-size: 26px; font-weight: 800; color: var(--color-text-1); letter-spacing: -0.02em; }
.page-sub { font-size: 14px; color: var(--color-text-2); }

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 14px;
}
.stat-card {
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s, border-color 0.12s, box-shadow 0.12s;
}
.stat-card:hover { background: var(--color-surface-3); }
.stat-card--active { box-shadow: 0 0 0 2px var(--color-accent); }
.stat-card--accent.stat-card--active { box-shadow: 0 0 0 2px #5b5bd6; }
.stat-card--warn.stat-card--active { box-shadow: 0 0 0 2px #e5484d; }
.stat-card--done.stat-card--active { box-shadow: 0 0 0 2px #46a758; }
.stat-card--accent { border-color: color-mix(in srgb, #5b5bd6 40%, transparent); }
.stat-card--warn { border-color: color-mix(in srgb, #e5484d 40%, transparent); }
.stat-card--done { border-color: color-mix(in srgb, #46a758 40%, transparent); }
.stat-value { font-size: 32px; font-weight: 800; color: var(--color-text-1); line-height: 1; }
.stat-label { font-size: 12px; color: var(--color-text-3); font-weight: 500; }

/* Stat drill-down panel */
.stat-panel {
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  overflow: hidden;
}
.stat-panel-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-border-sub);
  background: var(--color-surface-1);
}
.stat-panel-title { font-size: 13px; font-weight: 600; color: var(--color-text-1); display: flex; align-items: center; gap: 8px; }
.stat-panel-count {
  font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 99px;
  background: var(--color-surface-3); color: var(--color-text-2); border: 1px solid var(--color-border);
}
.stat-panel-close {
  display: flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; border-radius: 4px;
  border: none; background: transparent; color: var(--color-text-3); cursor: pointer;
  transition: background 0.1s;
}
.stat-panel-close:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.stat-panel-empty { padding: 16px; font-size: 13px; color: var(--color-text-3); }
.stat-panel-list { max-height: 320px; overflow-y: auto; }
.stat-task-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 16px;
  border-bottom: 1px solid var(--color-border-sub);
  cursor: pointer; transition: background 0.1s;
}
.stat-task-row:last-child { border-bottom: none; }
.stat-task-row:hover { background: var(--color-surface-3); }
.stat-task-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.stat-task-text { flex: 1; font-size: 13px; color: var(--color-text-1); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stat-task-project { font-size: 11px; color: var(--color-text-3); flex-shrink: 0; }
.stat-task-date { font-size: 11px; color: var(--color-text-3); flex-shrink: 0; }
.stat-panel-enter-active, .stat-panel-leave-active { transition: all 0.2s ease; }
.stat-panel-enter-from { opacity: 0; transform: translateY(-6px); }
.stat-panel-leave-to { opacity: 0; transform: translateY(-6px); }

/* Tabs */
.dash-tabs {
  display: flex;
  gap: 2px;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 0;
}
.dash-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-2);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  cursor: pointer;
  border-radius: 6px 6px 0 0;
  transition: color 0.15s, background 0.15s;
}
.dash-tab:hover { color: var(--color-text-1); background: var(--color-surface-2); }
.dash-tab--active {
  color: var(--color-accent);
  border-bottom-color: var(--color-accent);
  font-weight: 600;
}
.tab-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-2);
}
.tab-badge--warn {
  background: color-mix(in srgb, #f76b15 20%, transparent);
  color: #f76b15;
}

/* Tab panels */
.tab-panel { display: flex; flex-direction: column; gap: 16px; }

/* Dashboard grid */
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
  align-items: start;
}

.dash-section {
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 18px 12px;
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-1);
  border-bottom: 1px solid var(--color-border-sub);
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot--red    { background: #e5484d; }
.dot--blue   { background: #5b5bd6; }
.dot--green  { background: #46a758; }
.dot--orange { background: #f76b15; }

/* Task rows */
.task-list { display: flex; flex-direction: column; }
.task-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 18px;
  border-bottom: 1px solid var(--color-border-sub);
  cursor: pointer;
  transition: background 0.1s;
}
.task-row:last-child { border-bottom: none; }
.task-row:hover { background: var(--color-surface-2); }
.task-row--overdue { border-left: 3px solid var(--color-danger); }
.task-row__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.task-row__text {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.task-row__project { font-size: 11px; font-weight: 500; }
.task-row__date { font-size: 11px; color: var(--color-text-3); white-space: nowrap; }
.task-row__date.overdue { color: var(--color-danger); font-weight: 600; }

.status-chip {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 99px;
  white-space: nowrap;
  color: var(--sc, #52525f);
  background: color-mix(in srgb, var(--sc, #52525f) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--sc, #52525f) 30%, transparent);
}

/* Project rows */
.project-list { display: flex; flex-direction: column; }
.project-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 18px;
  border-bottom: 1px solid var(--color-border-sub);
  text-decoration: none;
  transition: background 0.1s;
}
.project-row:last-child { border-bottom: none; }
.project-row:hover { background: var(--color-surface-2); }
.project-row__icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
}
.project-row__info { flex: 1; min-width: 0; }
.project-row__name { font-size: 13px; font-weight: 600; color: var(--color-text-1); display: block; }
.project-row__count { font-size: 11px; color: var(--color-text-3); }
.arrow { color: var(--color-text-3); }

/* Activity feed — base shared styles */
.activity-list { display: flex; flex-direction: column; }
.activity-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--ac, #8a8a9a) 18%, transparent);
  color: var(--ac, #8a8a9a);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 1px;
}
.activity-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.activity-message {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-1);
  line-height: 1.4;
}
.activity-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}
.activity-project {
  font-size: 11px;
  font-weight: 600;
}
.activity-time {
  font-size: 11px;
  color: var(--color-text-3);
}

.empty-section {
  padding: 24px 18px;
  font-size: 13px;
  color: var(--color-text-3);
}
.empty-section a { color: var(--color-accent); text-decoration: none; }
.empty-section a:hover { text-decoration: underline; }

/* Activity enhancements */
.activity-unread-count {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 99px;
  background: color-mix(in srgb, #f76b15 20%, transparent);
  color: #f76b15;
  margin-left: auto;
}
.activity-mark-all-btn {
  font-size: 11px;
  font-weight: 500;
  padding: 3px 9px;
  border-radius: 5px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
  margin-left: 8px;
  flex-shrink: 0;
}
.activity-mark-all-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }

.activity-selection-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  background: color-mix(in srgb, var(--color-accent) 10%, transparent);
  border-bottom: 1px solid color-mix(in srgb, var(--color-accent) 30%, transparent);
  font-size: 12px;
  color: var(--color-text-2);
}
.activity-sel-btn {
  padding: 3px 10px;
  border-radius: 5px;
  border: none;
  background: var(--color-accent);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}
.activity-sel-btn--ghost {
  background: transparent;
  border: 1px solid var(--color-border);
  color: var(--color-text-2);
}
.activity-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 18px;
  border-bottom: 1px solid var(--color-border-sub);
  border-left: 3px solid var(--ac, #8a8a9a);
  transition: background 0.1s;
  cursor: pointer;
}
.activity-row:last-child { border-bottom: none; }
.activity-row:hover { background: var(--color-surface-2); }
.activity-row--unread { background: color-mix(in srgb, var(--ac, #8a8a9a) 5%, transparent); }
.activity-row--selected { background: color-mix(in srgb, var(--color-accent) 10%, transparent); outline: 1px solid color-mix(in srgb, var(--color-accent) 30%, transparent); }
.activity-checkbox {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  margin-top: 3px;
  cursor: pointer;
}
.activity-check-box {
  width: 13px;
  height: 13px;
  border-radius: 3px;
  border: 1.5px solid var(--color-border);
  background: var(--color-surface-0);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.1s, border-color 0.1s;
  flex-shrink: 0;
}
.activity-check-box--checked {
  background: var(--color-accent);
  border-color: var(--color-accent);
  color: #fff;
}
.activity-unread-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--color-accent);
  flex-shrink: 0;
}
.activity-mute-btn {
  flex-shrink: 0;
  align-self: center;
  width: 24px;
  height: 24px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.1s, background 0.1s, color 0.1s;
}
.activity-row:hover .activity-mute-btn { opacity: 1; }
.activity-mute-btn--active { opacity: 1; color: #f5c842; }
.activity-mute-btn:hover { background: color-mix(in srgb, #f5c842 15%, transparent); color: #f5c842; }

/* Activity detail modal */
.activity-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 300;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.activity-modal {
  width: 100%;
  max-width: 420px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.activity-modal-hdr {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--color-border-sub);
  background: var(--color-surface-1);
}
.activity-modal-icon {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--ac) 20%, transparent);
  color: var(--ac, #8a8a9a);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.activity-modal-type {
  flex: 1;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-3);
  text-transform: capitalize;
}
.activity-modal-close {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 6px; border: none;
  background: transparent; color: var(--color-text-3); cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.activity-modal-close:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.activity-modal-body {
  padding: 20px 18px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.activity-modal-message {
  font-size: 15px;
  font-weight: 500;
  color: var(--color-text-1);
  line-height: 1.5;
}
.activity-modal-meta {
  display: flex;
  align-items: center;
  gap: 10px;
}
.activity-modal-project { font-size: 12px; font-weight: 600; }
.activity-modal-time { font-size: 12px; color: var(--color-text-3); }
.activity-modal-actions {
  display: flex;
  gap: 8px;
  padding: 12px 18px;
  border-top: 1px solid var(--color-border-sub);
  justify-content: flex-end;
}
.activity-modal-goto {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: 6px; border: none;
  background: var(--color-accent); color: #fff;
  font-size: 12px; font-weight: 600; cursor: pointer;
  transition: background 0.15s;
}
.activity-modal-goto:hover { background: var(--color-accent-hover); }
.activity-modal-dismiss {
  padding: 6px 14px; border-radius: 6px;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-2); font-size: 12px; cursor: pointer;
  transition: background 0.1s;
}
.activity-modal-dismiss:hover { background: var(--color-surface-3); }
</style>

