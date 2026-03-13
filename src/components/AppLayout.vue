<script setup>
import AppSidebar from './AppSidebar.vue'
import TaskModal from './TaskModal.vue'
import TaskDetail from './TaskDetail.vue'
import ProjectSettings from './ProjectSettings.vue'
</script>

<template>
  <div class="app-layout">
    <AppSidebar />
    <div class="app-content">
      <slot />
    </div>
    <!-- Global modals (Teleport to body internally) -->
    <TaskModal />
    <TaskDetail />
    <ProjectSettings />
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: var(--color-surface-0);
}
.app-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
</style>
