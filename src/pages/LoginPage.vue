<script setup>
import { useRouter, useRoute } from 'vue-router'
import { ref } from 'vue'
import { login, authLoading, authError, clearAuthError } from '@/stores/authStore'
import { fetchProjects } from '@/stores/projectStore'

const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')

async function handleLogin() {
  clearAuthError()
  try {
    await login(email.value, password.value)
    await fetchProjects()
    const redirect = route.query.redirect || '/dashboard'
    router.push(redirect)
  } catch (_) { /* error shown via authError */ }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <router-link to="/" class="auth-logo">
        <img src="/logo.png" alt="TaskPilot logo" width="28" height="28" />
        <span>TaskPilot</span>
      </router-link>
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Log in to your account</p>

      <div v-if="authError" class="auth-error">{{ authError }}</div>

      <form class="auth-form" @submit.prevent="handleLogin">
        <label class="form-label">
          Email
          <input v-model="email" type="email" class="form-input" placeholder="you@example.com" autocomplete="email" />
        </label>
        <label class="form-label">
          Password
          <input v-model="password" type="password" class="form-input" placeholder="••••••••" autocomplete="current-password" />
        </label>
        <button type="submit" class="btn-submit" :disabled="authLoading">
          <span v-if="authLoading" class="spinner"></span>
          {{ authLoading ? 'Logging in…' : 'Log in' }}
        </button>
      </form>

      <p class="auth-switch">
        Don't have an account? <router-link to="/register">Sign up</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-0);
  padding: 24px;
}
.auth-card {
  width: 100%;
  max-width: 380px;
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  padding: 36px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.auth-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--color-text-1);
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 4px;
}
.auth-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-1);
}
.auth-sub { font-size: 13px; color: var(--color-text-2); margin-top: -10px; }
.auth-error {
  padding: 10px 14px;
  border-radius: 7px;
  background: var(--color-danger-bg);
  color: var(--color-danger);
  font-size: 13px;
  border: 1px solid color-mix(in srgb, var(--color-danger) 30%, transparent);
}
.auth-form { display: flex; flex-direction: column; gap: 14px; }
.form-label {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-2);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.form-input {
  padding: 9px 12px;
  border-radius: 7px;
  border: 1px solid var(--color-border);
  background: var(--color-surface-2);
  color: var(--color-text-1);
  font-size: 14px;
  transition: border-color 0.15s;
  outline: none;
}
.form-input:focus { border-color: var(--color-accent); }
.btn-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 11px;
  border: none;
  border-radius: 8px;
  background: var(--color-accent);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  margin-top: 4px;
}
.btn-submit:hover { background: var(--color-accent-hover); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.auth-switch { font-size: 13px; color: var(--color-text-2); text-align: center; }
.auth-switch a { color: var(--color-accent); text-decoration: none; font-weight: 600; }
.auth-switch a:hover { text-decoration: underline; }
</style>
