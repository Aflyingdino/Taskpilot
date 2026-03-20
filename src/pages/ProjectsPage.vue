<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/components/AppLayout.vue'
import ProjectCard from '@/components/ProjectCard.vue'
import ColorPicker from '@/components/ColorPicker.vue'
import { projects, createProject, deleteProject, reorderProjects, archivedProjects, archiveProject, restoreProject, deleteArchivedProject } from '@/stores/projectStore'
import { formatLongDate } from '@/utils/dates'

const showCreate = ref(false)
const newName = ref('')
const newDesc = ref('')
const newColor = ref('#5b5bd6')
const activeTab = ref('board') // 'board' | 'archive'

const search = ref('')
const filtered = computed(() =>
  projects.value.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()))
)

async function handleCreate() {
  if (!newName.value.trim()) return
  await createProject(newName.value.trim(), newDesc.value.trim(), newColor.value)
  showCreate.value = false
  newName.value = ''
  newDesc.value = ''
  newColor.value = '#5b5bd6'
}

async function handleDelete(projectId) {
  if (confirm('Delete this project? This action cannot be undone.')) {
    await deleteProject(projectId)
  }
}

/* ── Drag-to-reorder projects ── */
const draggingProjectId = ref(null)
const dragOverProjectId = ref(null)

function onProjectDragStart(id) {
  draggingProjectId.value = id
}
function onProjectDrop(targetId) {
  if (draggingProjectId.value && draggingProjectId.value !== targetId) {
    reorderProjects(draggingProjectId.value, targetId)
  }
  draggingProjectId.value = null
  dragOverProjectId.value = null
}
</script>

<template>
  <AppLayout>
    <div class="projects-page">
      <!-- Header -->
      <div class="page-header">
        <div class="page-header__left">
          <h1 class="page-title">Projects</h1>
          <p class="page-sub">{{ activeTab === 'archive' ? archivedProjects.length + ' archived' : projects.length + ' project' + (projects.length !== 1 ? 's' : '') }}</p>
        </div>
        <div class="page-header__right">
          <div class="proj-tabs">
            <button class="proj-tab" :class="{ 'proj-tab--active': activeTab === 'board' }" @click="activeTab = 'board'">Board</button>
            <button class="proj-tab" :class="{ 'proj-tab--active': activeTab === 'archive' }" @click="activeTab = 'archive'">
              Archive
              <span v-if="archivedProjects.length" class="proj-tab-badge">{{ archivedProjects.length }}</span>
            </button>
          </div>
          <div class="search-wrap" v-if="activeTab === 'board'">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            <input v-model="search" class="search-input" placeholder="Search projects…" />
          </div>
          <button v-if="activeTab === 'board'" class="btn-create" @click="showCreate = true">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            New project
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="activeTab === 'board' && !projects.length" class="empty-state">
        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
        </svg>
        <h3>No projects yet</h3>
        <p>Create your first project to start managing tasks with your team.</p>
        <button class="btn-create btn-create--large" @click="showCreate = true">Create project</button>
      </div>

      <!-- Projects grid -->
      <div v-else-if="activeTab === 'board'" class="projects-grid">
        <div
          v-for="p in filtered"
          :key="p.id"
          class="project-drag-wrap"
          :class="{ 'project-drag-wrap--over': dragOverProjectId === p.id && draggingProjectId !== p.id }"
          draggable="true"
          @dragstart="onProjectDragStart(p.id)"
          @dragover.prevent="dragOverProjectId = p.id"
          @dragleave="dragOverProjectId = null"
          @drop.prevent="onProjectDrop(p.id)"
          @dragend="draggingProjectId = null; dragOverProjectId = null"
        >
          <ProjectCard :project="p" @delete="handleDelete" />
          <!-- Archive button overlay -->
          <button class="project-archive-btn" @click.stop="archiveProject(p.id)" title="Archive project">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
            Archive
          </button>
        </div>
        <!-- Add project card -->
        <button class="add-project-card" @click="showCreate = true">
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          <span>New project</span>
        </button>
      </div>

      <!-- ══ ARCHIVE VIEW ══ -->
      <div v-else-if="activeTab === 'archive'" class="proj-archive-view">
        <div v-if="archivedProjects.length === 0" class="empty-state">
          <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
          <h3>No archived projects</h3>
          <p>Archive a project using the Archive button on any project card.</p>
        </div>
        <div v-else class="proj-archive-list">
          <div v-for="p in archivedProjects" :key="p.id" class="proj-archive-card">
            <div class="proj-archive-bar" :style="{ background: p.color }"></div>
            <div class="proj-archive-body">
              <div class="proj-archive-info">
                <span class="proj-archive-name">{{ p.name }}</span>
                <span class="proj-archive-date">Archived {{ formatLongDate(p.archivedAt) }}</span>
                <p v-if="p.description" class="proj-archive-desc">{{ p.description }}</p>
              </div>
              <div class="proj-archive-actions">
                <button class="arch-btn arch-btn--restore" @click="restoreProject(p.id)">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                  Restore
                </button>
                <button class="arch-btn arch-btn--delete" @click="deleteArchivedProject(p.id)">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                  Delete permanently
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create project modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
          <div class="modal" role="dialog" aria-modal="true" aria-labelledby="create-proj-title">
            <div class="modal-header">
              <h2 id="create-proj-title">New project</h2>
              <button class="modal-close" @click="showCreate = false" aria-label="Close dialog">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form class="modal-form" @submit.prevent="handleCreate">
              <label class="form-label">
                Project name
                <input
                  v-model="newName"
                  type="text"
                  class="form-input"
                  placeholder="My awesome project"
                  required
                  autofocus
                />
              </label>
              <label class="form-label">
                Description <span class="optional">(optional)</span>
                <input
                  v-model="newDesc"
                  type="text"
                  class="form-input"
                  placeholder="What is this project about?"
                />
              </label>
              <div class="form-label">
                Color
                <ColorPicker v-model="newColor" />
              </div>
              <div class="modal-actions">
                <button type="button" class="btn-cancel" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-confirm" :disabled="!newName.trim()">Create project</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.projects-page {
  flex: 1;
  overflow-y: auto;
  padding: 32px 40px;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* Header */
.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.page-title {
  font-size: 26px;
  font-weight: 800;
  color: var(--color-text-1);
  letter-spacing: -0.02em;
}
.page-sub { font-size: 13px; color: var(--color-text-3); margin-top: 2px; }
.page-header__right { display: flex; align-items: center; gap: 10px; }

.search-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 36px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  color: var(--color-text-3);
}
.search-input {
  border: none;
  background: transparent;
  color: var(--color-text-1);
  font-size: 13px;
  outline: none;
  width: 180px;
}
.search-input::placeholder { color: var(--color-text-3); }

.btn-create {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 0 16px;
  height: 36px;
  border-radius: 8px;
  border: none;
  background: var(--color-accent);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  white-space: nowrap;
}
.btn-create:hover { background: var(--color-accent-hover); }
.btn-create--large { padding: 12px 24px; height: auto; font-size: 14px; }

/* Tabs */
.proj-tabs {
  display: flex; gap: 4px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 8px; padding: 3px;
}
.proj-tab {
  display: flex; align-items: center; gap: 5px;
  padding: 5px 12px; border-radius: 5px;
  border: none; background: transparent;
  color: var(--color-text-2); font-size: 12px; font-weight: 500;
  cursor: pointer; transition: background 0.12s, color 0.12s;
}
.proj-tab--active { background: var(--color-surface-0); color: var(--color-text-1); }
.proj-tab-badge {
  font-size: 10px; font-weight: 700; padding: 1px 5px;
  border-radius: 99px; background: color-mix(in srgb, #f5c842 20%, transparent);
  color: #f5c842; border: 1px solid color-mix(in srgb, #f5c842 30%, transparent);
}

/* Archive button overlay on project card */
.project-drag-wrap { position: relative; }
.project-archive-btn {
  display: none; align-items: center; gap: 5px;
  position: absolute; top: 8px; right: 8px;
  padding: 4px 10px; border-radius: 6px;
  border: 1px solid var(--color-border);
  background: var(--color-surface-1); color: var(--color-text-2);
  font-size: 11px; font-weight: 500; cursor: pointer;
  transition: background 0.1s, color 0.1s; z-index: 5;
}
.project-drag-wrap:hover .project-archive-btn { display: flex; }
.project-archive-btn:hover { background: color-mix(in srgb, var(--color-accent) 12%, transparent); color: var(--color-accent); border-color: var(--color-accent); }

/* Archive view */
.proj-archive-view { display: flex; flex-direction: column; gap: 12px; }
.proj-archive-list { display: flex; flex-direction: column; gap: 10px; max-width: 760px; }
.proj-archive-card {
  display: flex; background: var(--color-surface-2);
  border: 1px solid var(--color-border); border-radius: 10px;
  overflow: hidden; transition: border-color 0.15s;
}
.proj-archive-card:hover { border-color: var(--color-text-3); }
.proj-archive-bar { width: 4px; flex-shrink: 0; }
.proj-archive-body { flex: 1; padding: 16px 18px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.proj-archive-info { display: flex; flex-direction: column; gap: 3px; }
.proj-archive-name { font-size: 14px; font-weight: 700; color: var(--color-text-1); }
.proj-archive-date { font-size: 11px; color: var(--color-text-3); }
.proj-archive-desc { font-size: 12px; color: var(--color-text-3); }
.proj-archive-actions { display: flex; gap: 8px; flex-shrink: 0; }
.arch-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 13px; border-radius: 6px;
  font-size: 12px; font-weight: 500; cursor: pointer;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-2); transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.arch-btn--restore:hover { background: color-mix(in srgb, var(--color-accent) 12%, transparent); color: var(--color-accent); border-color: var(--color-accent); }
.arch-btn--delete:hover { background: var(--color-danger-bg); color: var(--color-danger); border-color: var(--color-danger); }

/* Grid */
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 18px;
}

.project-drag-wrap {
  border-radius: 12px;
  transition: opacity 0.15s, outline 0.15s;
}
.project-drag-wrap--over {
  outline: 2px dashed var(--color-accent);
  outline-offset: 2px;
}

.add-project-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 180px;
  border-radius: 12px;
  border: 2px dashed var(--color-border);
  background: transparent;
  color: var(--color-text-3);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.add-project-card:hover {
  border-color: var(--color-accent);
  color: var(--color-accent);
  background: var(--color-accent-muted);
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding-top: 80px;
  text-align: center;
  color: var(--color-text-3);
}
.empty-state h3 { font-size: 18px; font-weight: 700; color: var(--color-text-2); }
.empty-state p { font-size: 14px; max-width: 400px; }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.65);
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.modal {
  width: 100%;
  max-width: 440px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(0,0,0,0.5);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--color-border);
}
.modal-header h2 { font-size: 16px; font-weight: 700; color: var(--color-text-1); }
.modal-close {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-close:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.modal-form { padding: 24px; display: flex; flex-direction: column; gap: 18px; }
.form-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-2);
}
.optional { font-weight: 400; text-transform: none; letter-spacing: 0; font-size: 11px; color: var(--color-text-3); }
.form-input {
  padding: 9px 12px;
  border-radius: 7px;
  border: 1px solid var(--color-border);
  background: var(--color-surface-1);
  color: var(--color-text-1);
  font-size: 14px;
  outline: none;
  transition: border-color 0.15s;
}
.form-input:focus { border-color: var(--color-accent); }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; }
.btn-cancel {
  padding: 8px 18px;
  border-radius: 7px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.btn-cancel:hover { background: var(--color-surface-3); }
.btn-confirm {
  padding: 8px 18px;
  border-radius: 7px;
  border: none;
  background: var(--color-accent);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-confirm:hover { background: var(--color-accent-hover); }
.btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

/* Transition */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-active .modal, .modal-fade-leave-active .modal { transition: transform 0.2s; }
.modal-fade-enter-from .modal { transform: scale(0.96) translateY(-8px); }
.modal-fade-leave-to .modal { transform: scale(0.96) translateY(-8px); }
</style>
