<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { register, authLoading, authError, clearAuthError } from '@/stores/authStore'
import { fetchProjects } from '@/stores/projectStore'

const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const confirm = ref('')
const localError = ref('')

async function handleRegister() {
  clearAuthError()
  localError.value = ''
  if (password.value !== confirm.value) {
    localError.value = 'Passwords do not match'
    return
  }
  if (password.value.length < 6) {
    localError.value = 'Password must be at least 6 characters'
    return
  }
  try {
    await register({ name: name.value, email: email.value, password: password.value })
    await fetchProjects()
    router.push({ name: 'projects' })
  } catch (_) { /* error shown via authError */ }
}

const displayError = () => localError.value || authError.value
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <router-link to="/" class="auth-logo">
        <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="8" fill="var(--color-accent)" />
          <path d="M8 10h16M8 16h10M8 22h13" stroke="#fff" stroke-width="2.5" stroke-linecap="round" />
        </svg>
        <span>TaskPilot</span>
      </router-link>
      <h1 class="auth-title">Create account</h1>
      <p class="auth-sub">Free forever, no credit card needed</p>

      <div v-if="localError || authError" class="auth-error">{{ localError || authError }}</div>

      <form class="auth-form" @submit.prevent="handleRegister">
        <label class="form-label">
          Full name
          <input v-model="name" type="text" class="form-input" placeholder="Jane Smith" required autocomplete="name" />
        </label>
        <label class="form-label">
          Email
          <input v-model="email" type="email" class="form-input" placeholder="you@example.com" required autocomplete="email" />
        </label>
        <label class="form-label">
          Password
          <input v-model="password" type="password" class="form-input" placeholder="Min. 6 characters" required autocomplete="new-password" />
        </label>
        <label class="form-label">
          Confirm password
          <input v-model="confirm" type="password" class="form-input" placeholder="Repeat password" required autocomplete="new-password" />
        </label>
        <button type="submit" class="btn-submit" :disabled="authLoading">
          <span v-if="authLoading" class="spinner"></span>
          {{ authLoading ? 'Creating account…' : 'Create account' }}
        </button>
      </form>

      <p class="auth-switch">
        Already have an account? <router-link to="/login">Log in</router-link>
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
.auth-title { font-size: 22px; font-weight: 700; color: var(--color-text-1); }
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
  outline: none;
  transition: border-color 0.15s;
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
