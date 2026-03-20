<script setup>
import { isLoggedIn } from '@/stores/authStore'
</script>

<template>
  <header class="pub-nav">
    <router-link to="/" class="pub-brand">
      <svg width="26" height="26" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="var(--color-accent)" />
        <path d="M8 10h16M8 16h10M8 22h13" stroke="#fff" stroke-width="2.5" stroke-linecap="round" />
      </svg>
      <span>TaskPilot</span>
    </router-link>

    <nav class="pub-links">
      <router-link to="/" exact-active-class="pub-link--active" class="pub-link">Home</router-link>
      <router-link to="/about" active-class="pub-link--active" class="pub-link">About</router-link>
      <router-link to="/contact" active-class="pub-link--active" class="pub-link">Contact</router-link>

      <template v-if="!isLoggedIn">
        <router-link to="/login" class="pub-btn pub-btn--ghost">Log in</router-link>
        <router-link to="/register" class="pub-btn pub-btn--primary">Get started</router-link>
      </template>
      <template v-else>
        <router-link to="/projects" class="pub-btn pub-btn--primary">Open app</router-link>
      </template>
    </nav>
  </header>
</template>

<style scoped>
.pub-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 48px;
  height: 60px;
  border-bottom: 1px solid var(--color-border-sub);
  position: sticky;
  top: 0;
  background: var(--color-surface-0);
  z-index: 10;
  flex-shrink: 0;
}

.pub-brand {
  display: flex;
  align-items: center;
  gap: 9px;
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text-1);
  text-decoration: none;
  letter-spacing: -0.02em;
}

.pub-links {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pub-link {
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-2);
  text-decoration: none;
  transition: color 0.15s, background 0.15s;
}
.pub-link:hover {
  color: var(--color-text-1);
  background: var(--color-surface-2);
}
.pub-link--active {
  color: var(--color-text-1);
  background: var(--color-surface-2);
}

.pub-btn {
  padding: 6px 14px;
  border-radius: 7px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
  margin-left: 4px;
}
.pub-btn--ghost {
  color: var(--color-text-1);
  border: 1px solid var(--color-border);
  background: transparent;
}
.pub-btn--ghost:hover {
  background: var(--color-surface-2);
}
.pub-btn--primary {
  background: var(--color-accent);
  color: #fff;
  border: 1px solid transparent;
}
.pub-btn--primary:hover {
  background: var(--color-accent-hover, #4e4ec2);
}
</style>
