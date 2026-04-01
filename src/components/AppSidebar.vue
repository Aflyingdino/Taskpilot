<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { projects, activeProjectId } from '@/stores/projectStore'
import { isLoggedIn, user, logout } from '@/stores/authStore'
import { openSettings } from '@/stores/uiStore'

const router = useRouter()
const route = useRoute()

const collapsed = ref(localStorage.getItem('tp_sidebar_collapsed') === '1')

function toggleCollapse() {
  collapsed.value = !collapsed.value
  localStorage.setItem('tp_sidebar_collapsed', collapsed.value ? '1' : '0')
}

const currentProjectId = computed(() =>
  route.params.id ? Number(route.params.id) : null
)

const navLinks = [
  { name: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
  { name: 'projects',  label: 'Projects',  icon: 'grid' },
  { name: 'calendar',  label: 'Calendar',  icon: 'calendar' },
]

function isActive(name) {
  return route.name === name
}

function isBoardActive(projectId) {
  return route.name === 'board' && Number(route.params.id) === projectId
}

async function handleLogout() {
  await logout()
  router.push({ name: 'home' })
}

const userInitials = computed(() => {
  if (!user.name) return '?'
  return user.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
})
</script>

<template>
  <nav class="sidebar" :class="{ 'sidebar--collapsed': collapsed }">
    <!-- Logo -->
    <div class="sidebar-logo" :class="{ 'sidebar-logo--collapsed': collapsed }">
      <router-link v-if="!collapsed" to="/" class="logo-link">
        <img src="/logo.png" alt="TaskPilot logo" width="28" height="28" />
        <span class="logo-text">TaskPilot</span>
      </router-link>
      <button class="collapse-btn" @click="toggleCollapse" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" :d="collapsed ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7'" />
        </svg>
      </button>
    </div>

    <!-- Main nav -->
    <div class="sidebar-section">
      <p class="section-label" v-if="!collapsed">Menu</p>
      <router-link
        v-for="link in navLinks"
        :key="link.name"
        :to="{ name: link.name }"
        class="nav-item"
        :class="{ 'nav-item--active': isActive(link.name) }"
        :title="collapsed ? link.label : ''"
      >
        <!-- Grid icon -->
        <svg v-if="link.icon === 'grid'" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
        </svg>
        <!-- Dashboard icon -->
        <svg v-if="link.icon === 'dashboard'" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-9v9m3-12v12M3 12l18-9-9 18 9-9-18-9z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12z" />
        </svg>
        <!-- Calendar icon -->
        <svg v-if="link.icon === 'calendar'" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        <span v-if="!collapsed">{{ link.label }}</span>
      </router-link>
    </div>

    <!-- Projects list -->
    <div class="sidebar-section sidebar-section--projects" v-if="projects.length">
      <p class="section-label" v-if="!collapsed">Projects</p>
      <router-link
        v-for="project in projects"
        :key="project.id"
        :to="{ name: 'board', params: { id: project.id } }"
        class="nav-item nav-item--project"
        :class="{ 'nav-item--active': isBoardActive(project.id) }"
        :title="project.name"
      >
        <span class="project-dot" :style="{ background: project.color }"></span>
        <span v-if="!collapsed" class="project-name">{{ project.name }}</span>
        <span v-else class="project-collapsed-hint">···</span>
      </router-link>
    </div>

    <!-- Bottom: user -->
    <div class="sidebar-bottom">
      <div class="user-row" :class="{ 'user-row--collapsed': collapsed }">
        <div class="user-avatar">{{ userInitials }}</div>
        <div class="user-info" v-if="!collapsed">
          <p class="user-name">{{ user.name }}</p>
          <p class="user-email">{{ user.email }}</p>
        </div>
        <button v-if="!collapsed" class="settings-btn" @click="openSettings" title="Settings">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m-9.75 0h9.75" />
          </svg>
        </button>
        <button v-if="!collapsed" class="logout-btn" @click="handleLogout" title="Log out">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
          </svg>
        </button>
      </div>
    </div>
  </nav>


</template>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  width: 220px;
  min-width: 220px;
  height: 100vh;
  background: var(--color-surface-1);
  border-right: 1px solid var(--color-border);
  transition: width 0.2s, min-width 0.2s;
  overflow: hidden;
  position: sticky;
  top: 0;
  flex-shrink: 0;
}
.sidebar--collapsed {
  width: 56px;
  min-width: 56px;
}

/* Logo */
.sidebar-logo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 12px;
  border-bottom: 1px solid var(--color-border-sub);
  flex-shrink: 0;
}
.sidebar-logo--collapsed {
  justify-content: center;
  padding: 14px 0;
}
.logo-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  min-width: 0;
}
.logo-text {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-1);
  white-space: nowrap;
  overflow: hidden;
}
.collapse-btn {
  flex-shrink: 0;
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
  transition: background 0.1s, color 0.1s;
}
.collapse-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }

/* Sections */
.sidebar-section {
  padding: 10px 8px 4px;
}
.sidebar-section--projects {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 0;
}
.section-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-3);
  padding: 0 6px;
  margin-bottom: 4px;
}

/* Nav items */
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 8px;
  border-radius: 6px;
  text-decoration: none;
  color: var(--color-text-2);
  font-size: 13px;
  font-weight: 500;
  transition: background 0.1s, color 0.1s;
  white-space: nowrap;
  overflow: hidden;
}
.nav-item:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.nav-item--active { background: var(--color-accent-muted); color: var(--color-accent); }
.nav-item--active:hover { background: var(--color-accent-muted); }

/* Project items */
.nav-item--project { font-size: 13px; }
.project-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.project-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.project-collapsed-hint {
  font-size: 10px;
  color: var(--color-text-3);
  letter-spacing: 0.05em;
  font-weight: 700;
}

/* Bottom user */
.sidebar-bottom {
  padding: 10px 8px;
  border-top: 1px solid var(--color-border-sub);
  flex-shrink: 0;
}
.user-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 6px;
  min-width: 0;
}
.user-row--collapsed { padding: 6px 0; justify-content: center; }
.user-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--color-accent);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.user-info {
  flex: 1;
  min-width: 0;
}
.user-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-email {
  font-size: 11px;
  color: var(--color-text-3);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.logout-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.logout-btn:hover { background: var(--color-danger-bg); color: var(--color-danger); }
.settings-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.settings-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
</style>
