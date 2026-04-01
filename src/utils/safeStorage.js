const hasWindow = typeof window !== 'undefined'

function getStorage(kind = 'local') {
  if (!hasWindow) return null

  try {
    return kind === 'session' ? window.sessionStorage : window.localStorage
  } catch {
    return null
  }
}

export function readJson(key, fallback, kind = 'local') {
  const storage = getStorage(kind)
  if (!storage) return fallback

  try {
    const raw = storage.getItem(key)
    return raw ? JSON.parse(raw) : fallback
  } catch {
    return fallback
  }
}

export function writeJson(key, value, kind = 'local') {
  const storage = getStorage(kind)
  if (!storage) return

  try {
    storage.setItem(key, JSON.stringify(value))
  } catch {
    // Ignore quota/security errors.
  }
}

export function readString(key, fallback = '', kind = 'local') {
  const storage = getStorage(kind)
  if (!storage) return fallback

  try {
    const value = storage.getItem(key)
    return value ?? fallback
  } catch {
    return fallback
  }
}

export function writeString(key, value, kind = 'local') {
  const storage = getStorage(kind)
  if (!storage) return

  try {
    storage.setItem(key, value)
  } catch {
    // Ignore quota/security errors.
  }
}
