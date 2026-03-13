<script setup>
import { ref } from 'vue'
import TaskCard from './TaskCard.vue'
import { backlog, deleteTask, moveTaskToBacklog } from '@/stores/boardStore'
import { openCreateTask } from '@/stores/uiStore'

function handleDelete(taskId) {
  deleteTask(taskId, 'backlog')
}

const isDragOver = ref(false)
const dragCounter = ref(0)
const collapsed = ref(false)

function onDragEnter(e) {
  // Ignore group reorder drags — only respond to task drags
  if (e.dataTransfer.types.includes('application/group-id')) return
  e.preventDefault()
  dragCounter.value++
  isDragOver.value = true
}

function onDragOver(e) {
  if (e.dataTransfer.types.includes('application/group-id')) return
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'
}

function onDragLeave() {
  dragCounter.value--
  if (dragCounter.value === 0) isDragOver.value = false
}

function onDrop(e) {
  if (e.dataTransfer.types.includes('application/group-id')) return
  e.preventDefault()
  dragCounter.value = 0
  isDragOver.value = false
  const taskId = Number(e.dataTransfer.getData('application/task-id'))
  if (taskId) moveTaskToBacklog(taskId)
}
</script>

<template>
  <!-- Collapsed: thin strip with expand button -->
  <aside
    v-if="collapsed"
    class="panel panel--mini"
    @dragenter="onDragEnter"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
    :class="{ 'panel--over': isDragOver }"
  >
    <button class="mini-expand-btn" @click="collapsed = false" title="Expand backlog" aria-label="Expand backlog">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
    </button>
    <span class="mini-label">Backlog</span>
    <span class="mini-badge">{{ backlog.length }}</span>
  </aside>

  <!-- Expanded -->
  <aside
    v-else
    class="panel"
    :class="{ 'panel--over': isDragOver }"
    @dragenter="onDragEnter"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
  >
    <!-- Header -->
    <div class="panel-header">
      <div class="panel-title-row">
        <span class="panel-title">Backlog</span>
        <div class="panel-title-right">
          <span class="badge">{{ backlog.length }}</span>
          <button class="collapse-btn" @click="collapsed = true" title="Minimize backlog" aria-label="Minimize backlog">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
      <p class="panel-subtitle">Drag tasks onto a group</p>
    </div>

    <!-- Add task -->
    <div class="panel-input-area">
      <button class="add-task-btn" @click="openCreateTask">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        New task
      </button>
    </div>

    <!-- Task list -->
    <div class="panel-body">
      <TransitionGroup name="task-list" tag="div" class="task-list">
        <TaskCard
          v-for="task in backlog"
          :key="task.id"
          :task="task"
          source="backlog"
          @delete="handleDelete"
        />
      </TransitionGroup>

      <div v-if="backlog.length === 0" class="empty">
        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h4.5M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
        <p>No tasks yet</p>
        <span>Click "New task" to get started</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--color-surface-1);
  border-left: 1px solid var(--color-border);
  transition: background 0.2s;
  width: 280px;
  flex-shrink: 0;
}
.panel--over {
  background: var(--color-accent-muted);
}

/* Mini / collapsed strip */
.panel--mini {
  width: 36px;
  min-width: 36px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px 0;
  gap: 10px;
  cursor: default;
}
.mini-expand-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.mini-expand-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.mini-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--color-text-3);
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  white-space: nowrap;
}
.mini-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 5px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-2);
  border: 1px solid var(--color-border);
}
.panel-header {
  padding: 20px 16px 14px;
  border-bottom: 1px solid var(--color-border-sub);
}
.panel-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
.panel-title-right {
  display: flex;
  align-items: center;
  gap: 6px;
}
.collapse-btn {
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
.collapse-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.panel-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-1);
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.panel-subtitle {
  font-size: 12px;
  color: var(--color-text-3);
}
.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-2);
  border: 1px solid var(--color-border);
}
.panel-input-area {
  padding: 10px 16px;
  border-bottom: 1px solid var(--color-border-sub);
}
.add-task-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  height: 34px;
  padding: 0 12px;
  border-radius: 6px;
  border: 1px dashed var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.add-task-btn:hover {
  border-color: var(--color-accent);
  background: var(--color-surface-2);
  color: var(--color-text-1);
}
.panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
}
.task-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding-top: 48px;
  text-align: center;
  color: var(--color-text-3);
}
.empty p {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-2);
}
.empty span {
  font-size: 12px;
}
</style>
