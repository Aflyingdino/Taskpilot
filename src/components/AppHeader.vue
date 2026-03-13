<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { openSettings } from '@/stores/uiStore'
import { activeProject } from '@/stores/projectStore'

const route = useRoute()
const isBoard = computed(() => route.name === 'board')
</script>

<template>
  <header class="header">
    <div class="header-inner">
      <div class="header-left">
        <template v-if="isBoard && activeProject">
          <router-link to="/projects" class="breadcrumb-link">Projects</router-link>
          <span class="breadcrumb-sep">/</span>
          <div class="project-badge" :style="{ background: activeProject.color }">
            {{ activeProject.name[0].toUpperCase() }}
          </div>
          <span class="project-title">{{ activeProject.name }}</span>
        </template>
        <template v-else>
          <span class="page-title-text">{{ route.meta?.title || '' }}</span>
        </template>
      </div>

      <div class="header-right">
        <button v-if="isBoard" class="settings-btn" @click="openSettings">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Project Settings
        </button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.header {
  position: sticky;
  top: 0;
  z-index: 50;
  width: 100%;
  background: var(--color-surface-1);
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}
.header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 52px;
  padding: 0 20px;
  gap: 16px;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.breadcrumb-link {
  font-size: 13px;
  color: var(--color-text-3);
  text-decoration: none;
  transition: color 0.15s;
}
.breadcrumb-link:hover { color: var(--color-text-1); }
.breadcrumb-sep { color: var(--color-text-3); font-size: 13px; }
.project-badge {
  width: 22px;
  height: 22px;
  border-radius: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
}
.project-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.page-title-text { font-size: 14px; font-weight: 600; color: var(--color-text-1); }
.header-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.settings-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.settings-btn:hover { background: var(--color-surface-2); color: var(--color-text-1); }
</style>
