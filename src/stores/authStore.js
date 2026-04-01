import { reactive, computed, readonly } from 'vue'
import { api } from '@/utils/api'

/* ─────────────────────────────────────────────
   Auth Store — session-based via PHP backend
───────────────────────────────────────────── */

const _state = reactive({
  id: null,
  name: '',
  email: '',
  avatar: null,
  isLoggedIn: false,
  loading: false,
  error: null,
})

export const user = readonly(_state)
export const isLoggedIn = computed(() => _state.isLoggedIn)
export const authLoading = computed(() => _state.loading)
export const authError = computed(() => _state.error)

/**
 * Restore session from server cookie on app startup.
 * Returns true if a valid session exists.
 */
export async function checkSession() {
  try {
    const data = await api.get('/auth/me')
    Object.assign(_state, {
      id: data.id,
      name: data.name,
      email: data.email,
      avatar: null,
      isLoggedIn: true,
    })
    return true
  } catch {
    return false
  }
}

export async function login(email, password) {
  _state.loading = true
  _state.error = null
  try {
    const data = await api.post('/auth/login', { email, password })
    Object.assign(_state, {
      id: data.id,
      name: data.name,
      email: data.email,
      avatar: null,
      isLoggedIn: true,
    })
  } catch (err) {
    _state.error = err.message
    throw err
  } finally {
    _state.loading = false
  }
}

export async function register({ name, email, password }) {
  _state.loading = true
  _state.error = null
  try {
    const data = await api.post('/auth/register', { name, email, password })
    Object.assign(_state, {
      id: data.id,
      name: data.name,
      email: data.email,
      avatar: null,
      isLoggedIn: true,
    })
  } catch (err) {
    _state.error = err.message
    throw err
  } finally {
    _state.loading = false
  }
}

export async function logout() {
  try { await api.post('/auth/logout') } catch { /* ignore */ }
  Object.assign(_state, {
    id: null, name: '', email: '', avatar: null,
    isLoggedIn: false, error: null,
  })
}

export function clearAuthError() {
  _state.error = null
}
