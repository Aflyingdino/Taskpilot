<script setup>
import { ref, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import AppHeader from '@/components/AppHeader.vue'
import GroupBoard from '@/components/GroupBoard.vue'
import BacklogPanel from '@/components/BacklogPanel.vue'
import { setActiveProject, projects } from '@/stores/projectStore'

const route = useRoute()
const router = useRouter()
const backlogOpen = ref(false)

watchEffect(() => {
  const id = Number(route.params.id)
  const exists = projects.value.find(p => p.id === id)
  if (!exists) { router.push({ name: 'projects' }); return }
  setActiveProject(id)
})
</script>

<template>
  <AppLayout>
    <div class="board-page">
      <AppHeader />
      <div class="content">
        <main class="board-area">
          <GroupBoard />
        </main>
        <aside class="sidebar-area">
          <BacklogPanel />
        </aside>

        <!-- Mobile backlog toggle -->
        <button
          class="mobile-toggle"
          :class="{ 'mobile-toggle--open': backlogOpen }"
          @click="backlogOpen = !backlogOpen"
          aria-label="Toggle backlog"
        >
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </button>
        <Transition name="fade">
          <div v-if="backlogOpen" class="overlay" @click="backlogOpen = false" />
        </Transition>
        <Transition name="slide-right">
          <div v-if="backlogOpen" class="mobile-backlog">
            <BacklogPanel />
          </div>
        </Transition>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.board-page {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
.content {
  position: relative;
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
.board-area { flex: 1; min-width: 0; overflow-x: auto; overflow-y: hidden; display: flex; }
.sidebar-area { display: none; flex-shrink: 0; }
@media (min-width: 768px) { .sidebar-area { display: block; } }
.mobile-toggle {
  display: flex;
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 40;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  border: none;
  background: var(--color-accent);
  color: #fff;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,0.4);
  transition: background 0.15s, transform 0.25s;
}
.mobile-toggle--open { transform: rotate(45deg); }
.mobile-toggle:hover { background: var(--color-accent-hover); }
@media (min-width: 768px) { .mobile-toggle { display: none; } }
.overlay {
  position: fixed;
  inset: 0;
  z-index: 40;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(2px);
}
.mobile-backlog { position: fixed; inset-y: 0; right: 0; z-index: 50; width: 280px; }
</style>
