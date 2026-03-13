<script setup>
import { ref, computed } from 'vue'
import { projectLabels } from '@/stores/boardStore'
import { openEditTask, openTaskDetail } from '@/stores/uiStore'
import { STATUS_META } from '@/utils/constants'
import { formatShortDate } from '@/utils/dates'

const props = defineProps({
  task: { type: Object, required: true },
  source: { type: String, default: 'backlog' },
})

const emit = defineEmits(['delete'])

const taskLabels = computed(() =>
  (props.task.labelIds ?? []).map((id) => projectLabels.value.find((l) => l.id === id)).filter(Boolean)
)

const isOverdue = computed(() => {
  if (!props.task.deadline || props.task.status === 'done') return false
  return new Date(props.task.deadline) < new Date()
})

function onDragStart(e) {
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/task-id', String(e.currentTarget.dataset.taskId))
  e.currentTarget.classList.add('dragging')
}

function onDragEnd(e) {
  e.currentTarget.classList.remove('dragging')
}

/* Context menu — Teleport-based so it escapes overflow:hidden containers */
const menuOpen    = ref(false)
const dropdownPos = ref({ top: 0, right: 0 })

function toggleMenu(e) {
  if (!menuOpen.value) {
    const rect = e.currentTarget.getBoundingClientRect()
    dropdownPos.value = {
      top  : rect.bottom + 4,
      right: window.innerWidth - rect.right,
    }
  }
  menuOpen.value = !menuOpen.value
}

function closeMenu() {
  menuOpen.value = false
}
</script>

<template>
  <div
    class="task"
    :class="{ 'task--accented': task.color, 'task--tinted': task.mainColor }"
    :style="{
      ...(task.color ? { '--task-accent': task.color } : {}),
      ...(task.mainColor ? { '--task-main': task.mainColor } : {}),
    }"
    :data-task-id="task.id"
    draggable="true"
    tabindex="0"
    role="article"
    :aria-label="task.text"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @click="openTaskDetail(task.id)"
    @keydown.enter.self="openTaskDetail(task.id)"
  >
    <!-- Top row: text + menu -->
    <div class="task-top">
      <p class="task-text">{{ task.text }}</p>
      <div class="task-menu-wrap">
        <button class="task-menu-btn" @click.stop="toggleMenu" aria-label="Task options">
          <!-- 4-directional move icon -->
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Teleported dropdown — renders at body level, never clipped -->
    <Teleport to="body">
      <template v-if="menuOpen">
        <div class="task-dropdown-backdrop" @click="closeMenu"></div>
        <div class="task-dropdown" :style="{ top: dropdownPos.top + 'px', right: dropdownPos.right + 'px' }">
          <button class="dd-item" @click.stop="openTaskDetail(task.id); closeMenu()">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            View details
          </button>
          <button class="dd-item" @click.stop="openEditTask(task.id); closeMenu()">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
            </svg>
            Edit
          </button>
          <div class="dd-divider"></div>
          <button class="dd-item dd-item--danger" @click.stop="emit('delete', task.id); closeMenu()">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            Delete
          </button>
        </div>
      </template>
    </Teleport>

    <!-- Meta row: status, deadline, labels -->
    <div class="task-meta" v-if="task.status !== 'not_started' || task.deadline || taskLabels.length">
      <span
        v-if="task.status && task.status !== 'not_started'"
        class="status-chip"
        :style="{ '--sc': STATUS_META[task.status]?.color ?? '#52525f' }"
      >
        <span class="status-dot"></span>
        {{ STATUS_META[task.status]?.label }}
      </span>
      <span
        v-if="task.deadline"
        class="deadline-chip"
        :class="{ 'deadline-chip--overdue': isOverdue }"
      >
        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        {{ formatShortDate(task.deadline) }}
      </span>
      <span
        v-for="label in taskLabels"
        :key="label.id"
        class="label-chip"
        :style="{ '--lc': label.color }"
      >
        <span class="label-dot"></span>
        {{ label.name }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.task {
  position: relative;
  background: var(--color-surface-3);
  border: 1px solid var(--color-border);
  border-radius: 7px;
  cursor: grab;
  transition: background 0.12s, border-color 0.12s;
  user-select: none;
}
.task:hover {
  background: var(--color-surface-0);
  border-color: var(--color-accent);
}
.task:active, .task.dragging {
  cursor: grabbing;
}
.task:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  border-radius: 7px;
}
.task--accented {
  border-left: 3px solid var(--task-accent);
}
.task--tinted {
  background: color-mix(in srgb, var(--task-main) 12%, var(--color-surface-3));
}
.task-top {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  padding: 9px 6px 9px 11px;
}
.task-text {
  flex: 1;
  font-size: 13px;
  line-height: 1.5;
  color: var(--color-text-1);
  word-break: break-word;
  min-width: 0;
}

/* Context menu */
.task-menu-wrap {
  position: relative;
  flex-shrink: 0;
}
.task-menu-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.1s, background 0.1s, color 0.1s;
}
.task:hover .task-menu-btn {
  opacity: 1;
}
.task-menu-btn:hover {
  background: var(--color-surface-2);
  color: var(--color-text-1);
}
/* Backdrop — closes menu on outside click */
.task-dropdown-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
}
.task-dropdown {
  position: fixed;
  z-index: 1000;
  min-width: 150px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 4px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.4);
  display: flex;
  flex-direction: column;
}
.dd-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: 5px;
  border: none;
  background: transparent;
  color: var(--color-text-1);
  font-size: 13px;
  text-align: left;
  cursor: pointer;
  transition: background 0.1s;
  white-space: nowrap;
}
.dd-item:hover {
  background: var(--color-surface-3);
}
.dd-item--danger {
  color: var(--color-danger);
}
.dd-item--danger:hover {
  background: var(--color-danger-bg);
}
.dd-divider {
  height: 1px;
  background: var(--color-border-sub);
  margin: 3px 0;
}

/* Meta chips */
.task-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 0 11px 9px;
}
.status-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  color: var(--sc, #8a8a9a);
  padding: 2px 7px;
  border-radius: 99px;
  background: color-mix(in srgb, var(--sc, #8a8a9a) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--sc, #8a8a9a) 30%, transparent);
}
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--sc, #8a8a9a);
  flex-shrink: 0;
}
.deadline-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-2);
  padding: 2px 7px;
  border-radius: 99px;
  background: var(--color-surface-0);
  border: 1px solid var(--color-border);
}
.deadline-chip--overdue {
  color: var(--color-danger);
  border-color: color-mix(in srgb, var(--color-danger) 40%, transparent);
  background: var(--color-danger-bg);
}
.label-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-1);
  padding: 2px 7px;
  border-radius: 99px;
  background: color-mix(in srgb, var(--lc, #8a8a9a) 18%, transparent);
  border: 1px solid color-mix(in srgb, var(--lc, #8a8a9a) 35%, transparent);
}
.label-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--lc, #8a8a9a);
  flex-shrink: 0;
}
</style>
