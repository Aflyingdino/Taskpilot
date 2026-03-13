<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchPublicProject } from '@/stores/projectStore'

const route = useRoute()
const project = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    project.value = await fetchPublicProject(route.params.shareId)
  } catch { /* not found */ }
  loading.value = false
})
</script>

<template>
  <div class="public-board-page">
    <header class="pub-header">
      <div class="pub-brand">
        <svg width="22" height="22" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="8" fill="var(--color-accent)" />
          <path d="M8 10h16M8 16h10M8 22h13" stroke="#fff" stroke-width="2.5" stroke-linecap="round" />
        </svg>
        <span>TaskPilot</span>
      </div>
      <nav class="pub-nav-links">
        <router-link to="/login">Log in</router-link>
        <router-link to="/register" class="btn-cta">Sign up free</router-link>
      </nav>
    </header>

    <div v-if="!project" class="not-found">
      <h2>Project not found</h2>
      <p>This share link is invalid or the project has been made private.</p>
      <router-link to="/">Go home</router-link>
    </div>

    <template v-else>
      <div class="board-meta">
        <div class="board-meta__icon" :style="{ background: project.color }">{{ project.name[0] }}</div>
        <div>
          <h1 class="board-meta__name">{{ project.name }}</h1>
          <p class="board-meta__desc">{{ project.description || 'Shared project' }}</p>
        </div>
        <span class="readonly-badge">Read-only</span>
      </div>

      <div class="board-scroll">
        <div class="board-columns">
          <div v-for="group in project.groups" :key="group.id" class="column">
            <div class="col-header">
              <span class="col-name">{{ group.name }}</span>
              <span class="col-count">{{ group.tasks.length }}</span>
            </div>
            <div class="col-tasks">
              <div v-for="task in group.tasks" :key="task.id" class="pub-task">
                <p class="pub-task__text">{{ task.text }}</p>
                <div class="pub-task__meta" v-if="task.deadline || task.status !== 'not_started'">
                  <span v-if="task.deadline" class="pub-deadline">
                    {{ new Date(task.deadline).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) }}
                  </span>
                </div>
              </div>
              <div v-if="!group.tasks.length" class="col-empty">No tasks</div>
            </div>
          </div>

          <div class="column" v-if="project.backlog.length">
            <div class="col-header">
              <span class="col-name">Backlog</span>
              <span class="col-count">{{ project.backlog.length }}</span>
            </div>
            <div class="col-tasks">
              <div v-for="task in project.backlog" :key="task.id" class="pub-task">
                <p class="pub-task__text">{{ task.text }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.public-board-page {
  min-height: 100vh;
  background: var(--color-surface-0);
  color: var(--color-text-1);
  display: flex;
  flex-direction: column;
}
.pub-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 32px;
  height: 56px;
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}
.pub-brand { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; }
.pub-nav-links { display: flex; align-items: center; gap: 12px; }
.pub-nav-links a { font-size: 13px; color: var(--color-text-2); text-decoration: none; }
.pub-nav-links a:hover { color: var(--color-text-1); }
.btn-cta {
  padding: 7px 16px;
  border-radius: 7px;
  background: var(--color-accent) !important;
  color: #fff !important;
  font-weight: 600;
}

.not-found {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--color-text-2);
}
.not-found h2 { font-size: 24px; font-weight: 700; color: var(--color-text-1); }
.not-found a { color: var(--color-accent); text-decoration: none; }

.board-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 24px 32px;
  border-bottom: 1px solid var(--color-border-sub);
  flex-shrink: 0;
}
.board-meta__icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 800;
  color: #fff;
}
.board-meta__name { font-size: 20px; font-weight: 800; }
.board-meta__desc { font-size: 13px; color: var(--color-text-3); margin-top: 2px; }
.readonly-badge {
  margin-left: auto;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  font-size: 11px;
  color: var(--color-text-3);
  font-weight: 600;
}

.board-scroll { flex: 1; overflow-x: auto; padding: 24px 32px; }
.board-columns { display: flex; gap: 18px; align-items: flex-start; min-width: max-content; }
.column {
  width: 260px;
  flex-shrink: 0;
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  overflow: hidden;
}
.col-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid var(--color-border);
}
.col-name { font-size: 13px; font-weight: 600; color: var(--color-text-1); }
.col-count {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-2);
}
.col-tasks { padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.pub-task {
  background: var(--color-surface-3);
  border: 1px solid var(--color-border);
  border-radius: 7px;
  padding: 10px 12px;
}
.pub-task__text { font-size: 13px; color: var(--color-text-1); line-height: 1.5; }
.pub-task__meta { margin-top: 6px; }
.pub-deadline { font-size: 11px; color: var(--color-text-3); }
.col-empty { font-size: 12px; color: var(--color-text-3); text-align: center; padding: 16px 0; }
</style>
