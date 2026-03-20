<script setup>
import { ref, computed, nextTick } from 'vue'
import TaskCard from './TaskCard.vue'
import ColorPicker from './ColorPicker.vue'
import {
  moveTaskToGroup, deleteTask, renameGroup, deleteGroup, archiveGroup,
  updateGroup, projectLabels, createTask,
} from '@/stores/boardStore'
import { toggleMuteGroup, mutedGroupIds } from '@/stores/notificationStore'
import { PRIORITY_OPTIONS, STATUS_OPTIONS, STATUS_META } from '@/utils/constants'
import { formatShortDate } from '@/utils/dates'

const props = defineProps({
  group: { type: Object, required: true },
})

const emit = defineEmits(['openDetail', 'groupDragStart'])

const isEditing       = ref(false)
const editName        = ref('')
const nameInput       = ref(null)
const isDragOver      = ref(false)
const dragCounter     = ref(0)
const collapsed       = ref(false)
const confirmingArchive = ref(false)
const confirmingDelete  = ref(false)

/* ── Add task inline ── */
const showTaskInput = ref(false)
const taskText      = ref('')
const taskInput     = ref(null)

async function openTaskInput() {
  showTaskInput.value = true
  taskText.value = ''
  await nextTick()
  taskInput.value?.focus()
}
function submitTask() {
  const t = taskText.value.trim()
  if (t) createTask({ text: t }, 'group', props.group.id)
  showTaskInput.value = false
  taskText.value = ''
}
function cancelTask() { showTaskInput.value = false; taskText.value = '' }

/* ── Rename ── */
async function startRename() {
  editName.value = props.group.name
  isEditing.value = true
  await nextTick()
  nameInput.value?.focus()
  nameInput.value?.select()
}
function finishRename() {
  const n = editName.value.trim().slice(0, 30)
  if (n && n !== props.group.name) renameGroup(props.group.id, n)
  isEditing.value = false
}

/* ── Metadata panel ── */
const metaOpen = ref(false)
const metaForm = ref({
  description: '', deadline: '', priority: 'medium', status: 'not_started', labelIds: [], color: null, mainColor: null,
})

function openMeta() {
  if (metaOpen.value) { metaOpen.value = false; return }
  metaForm.value = {
    description: props.group.description || '',
    deadline:    props.group.deadline || '',
    priority:    props.group.priority || 'medium',
    status:      props.group.status   || 'not_started',
    labelIds:    [...(props.group.labelIds ?? [])],
    color:       props.group.color ?? null,
    mainColor:   props.group.mainColor ?? null,
  }
  metaOpen.value = true
}
function saveMeta() {
  updateGroup(props.group.id, {
    description: metaForm.value.description,
    deadline:    metaForm.value.deadline || null,
    priority:    metaForm.value.priority,
    status:      metaForm.value.status,
    labelIds:    [...metaForm.value.labelIds],
    color:       metaForm.value.color,
    mainColor:   metaForm.value.mainColor,
  })
  metaOpen.value = false
}
function toggleLabel(id) {
  const idx = metaForm.value.labelIds.indexOf(id)
  if (idx === -1) metaForm.value.labelIds.push(id)
  else metaForm.value.labelIds.splice(idx, 1)
}

/* ── Computed ── */
const accentColor = computed(() => props.group.color ?? null)
const isGroupMuted = computed(() => mutedGroupIds.value.has(props.group.id))

const groupLabels = computed(() =>
  (props.group.labelIds ?? []).map(id => projectLabels.value.find(l => l.id === id)).filter(Boolean)
)

const PRIORITY_COLORS = {
  low: '#46a758', medium: '#5b5bd6', high: '#f76b15', urgent: '#e5484d',
}

/* ── Drag & drop tasks only ── */
function onDragEnter(e) {
  if (e.dataTransfer.types.includes('application/task-id')) {
    e.preventDefault(); dragCounter.value++; isDragOver.value = true
  }
}
function onDragOver(e) {
  if (e.dataTransfer.types.includes('application/task-id')) e.preventDefault()
}
function onDragLeave(e) {
  if (e.dataTransfer.types.includes('application/task-id')) {
    dragCounter.value--
    if (dragCounter.value <= 0) { isDragOver.value = false; dragCounter.value = 0 }
  }
}
function onDrop(e) {
  dragCounter.value = 0; isDragOver.value = false
  const taskId = Number(e.dataTransfer.getData('application/task-id'))
  if (taskId) { moveTaskToGroup(taskId, props.group.id); e.stopPropagation() }
}
function onGroupDragStart(e) {
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/group-id', String(props.group.id))
  emit('groupDragStart', props.group.id)
}
function handleDeleteTask(taskId) { deleteTask(taskId, 'group', props.group.id) }
</script>

<template>
  <div
    class="col"
    :class="{ 'col--over': isDragOver, 'col--colored': !!accentColor, 'col--tinted': !!group.mainColor }"
    :style="{ ...(accentColor ? { '--col-accent': accentColor } : {}), ...(group.mainColor ? { '--col-main': group.mainColor } : {}) }"
    @dragenter="onDragEnter"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
  >
    <!-- Column header -->
    <div class="col-header">
      <!-- Drag handle -->
      <div
        class="col-icon-btn col-drag-handle"
        draggable="true"
        @dragstart="onGroupDragStart"
        title="Drag to reorder"
        aria-label="Drag to reorder group"
      >
        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </div>

      <!-- Title area -->
      <div class="col-title-area">
        <!-- Priority dot -->
        <span
          v-if="group.priority"
          class="col-priority-dot"
          :style="{ background: PRIORITY_COLORS[group.priority] }"
          :title="group.priority"
        ></span>

        <!-- Name / rename input -->
        <h3
          v-if="!isEditing"
          class="col-name"
          @click="emit('openDetail', group.id)"
          @dblclick.stop="startRename"
          title="Click for detail · Double-click to rename"
        >{{ group.name }}</h3>
        <input
          v-else
          ref="nameInput"
          v-model="editName"
          class="col-name-input"
          maxlength="30"
          @blur="finishRename"
          @keydown.enter="finishRename"
          @keydown.escape="isEditing = false"
        />

        <!-- Task count -->
        <span class="col-count">{{ group.tasks.length }}</span>

        <!-- Status chip -->
        <span
          v-if="group.status && group.status !== 'not_started'"
          class="col-status-chip"
        >{{ STATUS_OPTIONS.find(s => s.value === group.status)?.label ?? group.status }}</span>
      </div>

      <!-- Label dots -->
      <div v-if="groupLabels.length" class="col-label-dots">
        <span
          v-for="label in groupLabels.slice(0, 4)"
          :key="label.id"
          class="col-label-dot"
          :style="{ background: label.color }"
          :title="label.name"
        ></span>
        <span v-if="groupLabels.length > 4" class="col-label-more">+{{ groupLabels.length - 4 }}</span>
      </div>

      <!-- Action buttons -->
      <div class="col-actions">
        <!-- Collapse / expand — leftmost, fastest reach -->
        <button
          class="col-icon-btn"
          :class="{ 'col-icon-btn--active': collapsed }"
          @click.stop="collapsed = !collapsed; confirmingArchive = false; confirmingDelete = false"
          :title="collapsed ? 'Expand group' : 'Collapse group'"
        >
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" :d="collapsed ? 'M19 9l-7 7-7-7' : 'M5 15l7-7 7 7'" />
          </svg>
        </button>
        <!-- Open detail -->
        <button class="col-icon-btn" @click.stop="emit('openDetail', group.id)" title="Open group detail">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
          </svg>
        </button>
        <!-- Archive (with confirm) -->
        <template v-if="confirmingArchive">
          <span class="col-confirm-text">Archive?</span>
          <button class="col-icon-btn col-icon-btn--confirm-yes" @click.stop="archiveGroup(group.id)" title="Yes, archive">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </button>
          <button class="col-icon-btn" @click.stop="confirmingArchive = false" title="Cancel">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </template>
        <button v-else class="col-icon-btn col-icon-btn--archive" @click.stop="confirmingArchive = true" title="Archive group">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
          </svg>
        </button>
        <!-- Delete (with confirm) -->
        <template v-if="confirmingDelete">
          <span class="col-confirm-text col-confirm-text--danger">Delete?</span>
          <button class="col-icon-btn col-icon-btn--danger" @click.stop="deleteGroup(group.id)" title="Yes, delete">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </button>
          <button class="col-icon-btn" @click.stop="confirmingDelete = false" title="Cancel">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </template>
        <button v-else class="col-icon-btn col-icon-btn--danger" @click.stop="confirmingDelete = true" title="Delete group">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
          </svg>
        </button>
        <!-- Meta / settings -->
        <button class="col-icon-btn" :class="{ 'col-icon-btn--active': metaOpen }" @click.stop="openMeta" title="Group settings">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path stroke-linecap="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
          </svg>
        </button>
        <!-- Mute notifications -->
        <button
          class="col-icon-btn"
          :class="{ 'col-icon-btn--muted': isGroupMuted }"
          @click.stop="toggleMuteGroup(group.id)"
          :title="isGroupMuted ? 'Unmute notifications' : 'Mute notifications'"
        >
          <svg v-if="!isGroupMuted" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <svg v-else width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            <line x1="3" y1="3" x2="21" y2="21" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Meta panel ── -->
    <Transition name="fade">
      <div v-if="metaOpen && !collapsed" class="col-meta-panel">
        <div class="meta-panel-hdr">
          <span class="meta-panel-title">Group settings</span>
          <button class="meta-back-btn" @click="metaOpen = false" title="Close settings">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
          </button>
        </div>
        <div class="meta-field">
          <label class="meta-label">Description</label>
          <textarea v-model="metaForm.description" class="meta-input meta-textarea" rows="2" placeholder="Group description…" />
        </div>
        <div class="meta-field">
          <label class="meta-label">Status</label>
          <select v-model="metaForm.status" class="meta-input meta-select">
            <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div class="meta-field">
          <label class="meta-label">Priority</label>
          <select v-model="metaForm.priority" class="meta-input meta-select">
            <option v-for="p in PRIORITY_OPTIONS" :key="p.value" :value="p.value">{{ p.label }}</option>
          </select>
        </div>
        <div class="meta-field">
          <label class="meta-label">Deadline</label>
          <input v-model="metaForm.deadline" type="date" class="meta-input" />
        </div>
        <div class="meta-field">
          <label class="meta-label">Main color (background tint)</label>
          <ColorPicker v-model="metaForm.mainColor" small />
        </div>
        <div class="meta-field">
          <label class="meta-label">Accent color (border)</label>
          <ColorPicker v-model="metaForm.color" small />
        </div>
        <div class="meta-field" v-if="projectLabels.length">
          <label class="meta-label">Labels</label>
          <div class="meta-labels">
            <button
              v-for="l in projectLabels"
              :key="l.id"
              class="meta-label-chip"
              :class="{ 'meta-label-chip--active': metaForm.labelIds.includes(l.id) }"
              :style="{ '--lc': l.color }"
              @click="toggleLabel(l.id)"
              type="button"
            >{{ l.name }}</button>
          </div>
        </div>
        <div class="meta-actions">
          <button class="meta-btn-cancel" @click="metaOpen = false">Cancel</button>
          <button class="meta-btn-save" @click="saveMeta">Save</button>
        </div>
      </div>
    </Transition>

    <!-- ── Task list ── -->
    <div v-show="!collapsed" class="col-body">
      <TransitionGroup name="task-list" tag="div" class="task-list">
        <TaskCard
          v-for="task in group.tasks"
          :key="task.id"
          :task="task"
          @delete="handleDeleteTask"
        />
      </TransitionGroup>

      <!-- Add task -->
      <div class="add-task-area">
        <Transition name="fade" mode="out-in">
          <div v-if="showTaskInput" key="form" class="add-task-form">
            <input
              ref="taskInput"
              v-model="taskText"
              class="task-input"
              placeholder="Task name…"
              @keydown.enter="submitTask"
              @keydown.escape="cancelTask"
            />
            <div class="add-task-actions">
              <button @click="submitTask" class="btn-add-confirm">Add</button>
              <button @click="cancelTask" class="btn-add-cancel">Cancel</button>
            </div>
          </div>
          <button v-else key="btn" class="add-task-btn" @click="openTaskInput">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add task
          </button>
        </Transition>
      </div>
    </div>

    <!-- Drop hint -->
    <div class="drop-hint" :class="{ 'drop-hint--over': isDragOver }">
      Drop task here
    </div>
  </div>
</template>

<style scoped>
/* ── Column shell ── */
.col {
  flex-shrink: 0;
  width: 100%;
  max-height: calc(100vh - 130px);
  display: flex;
  flex-direction: column;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 0.15s, max-height 0.25s ease, box-shadow 0.15s;
  position: relative;
}
.col--colored { border-left: 4px solid var(--col-accent); }
.col--tinted { background: color-mix(in srgb, var(--col-main) 10%, var(--color-surface-2)); }
.col--over {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-accent) 14%, transparent);
}

/* ── Header ── */
.col-header {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 12px 12px 10px;
  border-bottom: 1px solid var(--color-border-sub);
  flex-shrink: 0;
  overflow: hidden;
}

/* ── Drag handle ── */
.col-drag-handle { cursor: grab; touch-action: none; }
.col-drag-handle:active { cursor: grabbing; }

/* ── Title area ── */
.col-title-area {
  display: flex;
  align-items: center;
  gap: 7px;
  flex: 1;
  min-width: 0;
  overflow: hidden;
}
.col-priority-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.col-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-1);
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
  transition: color 0.12s;
}
.col-name:hover { color: var(--color-accent); }
.col-name-input {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-1);
  background: var(--color-surface-0);
  border: 1px solid var(--color-accent);
  border-radius: 5px;
  padding: 2px 7px;
  outline: none;
  min-width: 0;
  flex: 1;
}
.col-count {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-3);
  border: 1px solid var(--color-border);
  flex-shrink: 0;
}
.col-status-chip {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-2);
  border: 1px solid var(--color-border);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

/* ── Label dots ── */
.col-label-dots { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.col-label-dot { width: 8px; height: 8px; border-radius: 50%; }
.col-label-more { font-size: 10px; color: var(--color-text-3); }

/* ── Icon buttons (unified) ── */
.col-actions { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
.col-icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 7px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  transition: background 0.13s, color 0.13s, transform 0.1s;
  flex-shrink: 0;
}
.col-icon-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); transform: scale(1.08); }
.col-icon-btn:active { transform: scale(0.94); }
.col-icon-btn--active { color: var(--color-accent); }
/* Archive — amber/yellow on hover */
.col-icon-btn--archive { color: color-mix(in srgb, #f5c842 70%, var(--color-text-3)); }
.col-icon-btn--archive:hover { color: #f5c842; background: color-mix(in srgb, #f5c842 15%, transparent); }
/* Delete — red on hover */
.col-icon-btn--danger { color: color-mix(in srgb, var(--color-danger) 60%, var(--color-text-3)); }
.col-icon-btn--danger:hover { color: var(--color-danger); background: var(--color-danger-bg); }
/* Muted — amber when active */
.col-icon-btn--muted { color: #f5c842; }
.col-icon-btn--muted:hover { color: #f5c842; background: color-mix(in srgb, #f5c842 15%, transparent); }
/* Confirm yes (green) */
.col-icon-btn--confirm-yes { color: #46a758; }
.col-icon-btn--confirm-yes:hover { color: #46a758; background: color-mix(in srgb, #46a758 15%, transparent); }

/* ── Confirm inline labels ── */
.col-confirm-text {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-2);
  padding: 0 3px;
  white-space: nowrap;
}
.col-confirm-text--danger { color: var(--color-danger); }

/* ── Meta panel ── */
.col-meta-panel {
  padding: 0 12px 12px;
  background: var(--color-surface-1);
  border-bottom: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.meta-panel-hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0 4px;
  border-bottom: 1px solid var(--color-border-sub);
  margin-bottom: 2px;
}
.meta-panel-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-3);
}
.meta-back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.meta-back-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.meta-field { display: flex; flex-direction: column; gap: 4px; }
.meta-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-3);
}
.meta-input {
  background: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: 5px;
  padding: 5px 8px;
  font-size: 12px;
  color: var(--color-text-1);
  font-family: inherit;
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
  box-sizing: border-box;
}
.meta-input:focus { border-color: var(--color-accent); }
.meta-textarea { resize: vertical; min-height: 48px; }
.meta-select { cursor: pointer; }
.meta-labels { display: flex; flex-wrap: wrap; gap: 5px; }
.meta-label-chip {
  padding: 2px 8px;
  border-radius: 99px;
  border: 1px solid var(--color-border);
  font-size: 11px;
  background: transparent;
  color: var(--color-text-2);
  cursor: pointer;
  transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.meta-label-chip--active {
  background: color-mix(in srgb, var(--lc) 20%, transparent);
  color: var(--lc);
  border-color: var(--lc);
}
.meta-actions { display: flex; gap: 6px; justify-content: flex-end; padding-top: 2px; }
.meta-btn-cancel {
  padding: 5px 12px; border-radius: 5px; border: 1px solid var(--color-border);
  background: transparent; color: var(--color-text-2); font-size: 12px; cursor: pointer;
}
.meta-btn-cancel:hover { background: var(--color-surface-3); }
.meta-btn-save {
  padding: 5px 12px; border-radius: 5px; border: none;
  background: var(--color-accent); color: #fff; font-size: 12px; font-weight: 600; cursor: pointer;
}
.meta-btn-save:hover { background: var(--color-accent-hover); }

/* ── Task list body ── */
.col-body {
  flex: 1;
  overflow-y: auto;
  padding: 8px 8px 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-height: 0;
}
.task-list { display: contents; }

/* ── Add task ── */
.add-task-area { padding-bottom: 8px; }
.add-task-form { display: flex; flex-direction: column; gap: 6px; }
.task-input {
  width: 100%; padding: 6px 8px;
  background: var(--color-surface-0);
  border: 1px solid var(--color-accent);
  border-radius: 6px;
  font-size: 12px; color: var(--color-text-1); font-family: inherit;
  outline: none;
}
.add-task-actions { display: flex; gap: 5px; }
.btn-add-confirm {
  flex: 1; padding: 4px; border-radius: 5px; border: none;
  background: var(--color-accent); color: #fff; font-size: 11px; font-weight: 600; cursor: pointer;
}
.btn-add-confirm:hover { background: var(--color-accent-hover); }
.btn-add-cancel {
  padding: 4px 8px; border-radius: 5px;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-2); font-size: 11px; cursor: pointer;
}
.btn-add-cancel:hover { background: var(--color-surface-3); }
.add-task-btn {
  display: flex; align-items: center; gap: 6px; width: 100%;
  padding: 6px 8px; border-radius: 6px;
  border: 1px dashed var(--color-border); background: transparent;
  color: var(--color-text-3); font-size: 12px; cursor: pointer;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.add-task-btn:hover {
  border-color: var(--color-accent); color: var(--color-accent); background: var(--color-surface-3);
}

/* ── Drop hint overlay ── */
.drop-hint {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 600; color: var(--color-accent);
  border-radius: 10px;
  border: 2px dashed transparent;
  pointer-events: none;
  transition: border-color 0.15s, background 0.15s, opacity 0.15s;
  opacity: 0;
}
.drop-hint--over {
  opacity: 1;
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 8%, transparent);
}

/* ── Transitions ── */
.task-list-move { transition: transform 0.2s; }
.task-list-enter-active, .task-list-leave-active { transition: all 0.2s ease; }
.task-list-enter-from { opacity: 0; transform: translateY(-6px); }
.task-list-leave-to   { opacity: 0; transform: translateY(6px); }
</style>
