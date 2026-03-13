<script setup>
import { computed } from 'vue'
import { toggleMuteProject, mutedProjectIds } from '@/stores/notificationStore'

const props = defineProps({
  project: { type: Object, required: true },
})

const emit = defineEmits(['delete'])

const totalTasks = computed(() => {
  const p = props.project
  return p.backlog.length + p.groups.reduce((s, g) => s + g.tasks.length, 0)
})

const doneTasks = computed(() => {
  const p = props.project
  const all = [...p.backlog, ...p.groups.flatMap(g => g.tasks)]
  return all.filter(t => t.status === 'done').length
})

const progress = computed(() => {
  if (!totalTasks.value) return 0
  return Math.round((doneTasks.value / totalTasks.value) * 100)
})

const ROLE_LABEL = { owner: 'Owner', admin: 'Admin', collaborator: 'Member' }

const isProjectMuted = computed(() => mutedProjectIds.value.has(props.project.id))
</script>

<template>
  <router-link :to="{ name: 'board', params: { id: project.id } }" class="project-card" draggable="false">
    <!-- Color bar -->
    <div class="card-bar" :style="{ background: project.color }"></div>

    <div class="card-body">
      <div class="card-top">
        <div class="card-icon" :style="{ background: project.color }">
          {{ project.name[0].toUpperCase() }}
        </div>
        <div class="card-info">
          <h3 class="card-name">{{ project.name }}</h3>
          <p class="card-role" :class="`card-role--${project.role}`">{{ ROLE_LABEL[project.role] }}</p>
        </div>
        <button
          class="card-delete"
          @click.prevent="emit('delete', project.id)"
          title="Delete project"
        >
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <button
          class="card-mute"
          :class="{ 'card-mute--active': isProjectMuted }"
          @click.prevent="toggleMuteProject(project.id)"
          :title="isProjectMuted ? 'Unmute notifications' : 'Mute notifications'"
        >
          <svg v-if="!isProjectMuted" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <svg v-else width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            <line x1="3" y1="3" x2="21" y2="21" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <p class="card-desc">{{ project.description || 'No description' }}</p>

      <div class="card-stats">
        <span class="stat">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          {{ totalTasks }} task{{ totalTasks !== 1 ? 's' : '' }}
        </span>
        <span class="stat">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          {{ project.members.length }} member{{ project.members.length !== 1 ? 's' : '' }}
        </span>
      </div>

      <!-- Progress bar -->
      <div class="card-progress" v-if="totalTasks > 0">
        <div class="progress-bar">
          <div class="progress-fill" :style="{ width: progress + '%', background: project.color }"></div>
        </div>
        <span class="progress-label">{{ progress }}% done</span>
      </div>
    </div>
  </router-link>
</template>

<style scoped>
.project-card {
  position: relative;
  display: flex;
  flex-direction: column;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
  text-decoration: none;
  transition: border-color 0.15s, transform 0.1s, box-shadow 0.15s;
  cursor: pointer;
}
.project-card:hover {
  border-color: var(--color-accent);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}
.card-bar { height: 4px; flex-shrink: 0; }
.card-body { padding: 18px; display: flex; flex-direction: column; gap: 10px; }
.card-top { display: flex; align-items: flex-start; gap: 10px; }
.card-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
}
.card-info { flex: 1; min-width: 0; }
.card-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.card-role {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 99px;
  display: inline-block;
  margin-top: 3px;
}
.card-role--owner { background: color-mix(in srgb, #f5c842 15%, transparent); color: #f5c842; }
.card-role--admin  { background: color-mix(in srgb, #5b5bd6 15%, transparent); color: #5b5bd6; }
.card-role--user   { background: var(--color-surface-3); color: var(--color-text-2); }

.card-delete {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.1s, background 0.1s, color 0.1s;
}
.project-card:hover .card-delete { opacity: 1; }
.card-delete:hover { background: var(--color-danger-bg); color: var(--color-danger); }

.card-mute {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.1s, background 0.1s, color 0.1s;
}
.project-card:hover .card-mute { opacity: 1; }
.card-mute--active { opacity: 1; color: #f5c842; }
.card-mute:hover { background: color-mix(in srgb, #f5c842 15%, transparent); color: #f5c842; }

.card-desc {
  font-size: 12px;
  color: var(--color-text-3);
  line-height: 1.5;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.card-stats { display: flex; gap: 14px; }
.stat {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--color-text-3);
  font-weight: 500;
}

/* Progress */
.card-progress { display: flex; align-items: center; gap: 10px; }
.progress-bar {
  flex: 1;
  height: 4px;
  background: var(--color-surface-0);
  border-radius: 99px;
  overflow: hidden;
}
.progress-fill { height: 100%; border-radius: 99px; transition: width 0.4s; }
.progress-label { font-size: 11px; color: var(--color-text-3); white-space: nowrap; }
</style>
