import { reactive } from 'vue'

export const ui = reactive({
  taskModalOpen: false,
  editTaskId: null,    // null = create mode, number = edit mode
  detailTaskId: null,  // open task detail panel for this id
  settingsOpen: false,
  lightMode: localStorage.getItem('theme') === 'light',
})

export function openCreateTask() {
  ui.editTaskId = null
  ui.taskModalOpen = true
}

export function openEditTask(taskId) {
  ui.editTaskId = taskId
  ui.taskModalOpen = true
}

export function closeTaskModal() {
  ui.taskModalOpen = false
  ui.editTaskId = null
}

export function openTaskDetail(taskId) {
  ui.detailTaskId = taskId
}

export function closeTaskDetail() {
  ui.detailTaskId = null
}

export function openSettings() {
  ui.settingsOpen = true
}

export function closeSettings() {
  ui.settingsOpen = false
}

export function toggleTheme() {
  ui.lightMode = !ui.lightMode
  localStorage.setItem('theme', ui.lightMode ? 'light' : 'dark')
  applyTheme()
}

export function applyTheme() {
  if (ui.lightMode) {
    document.documentElement.classList.add('light-mode')
  } else {
    document.documentElement.classList.remove('light-mode')
  }
}
