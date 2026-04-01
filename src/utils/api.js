const DEFAULT_API_BASE = '/api/index.php'
const envApiBase = (import.meta.env.VITE_API_BASE || '').trim()

function withLeadingSlash(value) {
  if (!value) return ''
  return value.startsWith('/') ? value : `/${value}`
}

function withoutTrailingSlash(value) {
  return value.endsWith('/') ? value.slice(0, -1) : value
}

const BASE = withoutTrailingSlash(withLeadingSlash(envApiBase || DEFAULT_API_BASE))
const FALLBACK_BASE = BASE
const CSRF_HEADER = 'X-CSRF-Token'

let csrfToken = null
let csrfPromise = null
let useFallbackRouting = false

function buildUrl(path) {
  if (useFallbackRouting) {
    return `${FALLBACK_BASE}?route=${encodeURIComponent(path)}`
  }
  return `${BASE}${path}`
}

async function parseJson(res) {
  return res.json().catch(() => ({}))
}

async function fetchCsrfToken(forceRefresh = false) {
  if (csrfToken && !forceRefresh) return csrfToken
  if (csrfPromise && !forceRefresh) return csrfPromise

  const run = async () => {
    const res = await fetch(buildUrl('/csrf'), {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    // Auto-switch to query fallback if path-info routing is unavailable.
    if (!useFallbackRouting && res.status === 404) {
      useFallbackRouting = true
      return run()
    }

    const data = await parseJson(res)
    if (!res.ok || !data.token) {
      throw new Error(data.error || `Request failed (${res.status})`)
    }
    csrfToken = data.token
    return csrfToken
  }

  csrfPromise = run().finally(() => {
    csrfPromise = null
  })

  return csrfPromise
}

async function request(path, options = {}, allowRetry = true) {
  const method = (options.method || 'GET').toUpperCase()
  const headers = { Accept: 'application/json', ...options.headers }

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers[CSRF_HEADER] = await fetchCsrfToken()
  }

  const res = await fetch(buildUrl(path), {
    headers,
    credentials: 'include',
    ...options,
  })

  if (!useFallbackRouting && res.status === 404) {
    useFallbackRouting = true
    return request(path, options, allowRetry)
  }

  if (res.status === 204) return null

  if (res.status === 419 && allowRetry) {
    await fetchCsrfToken(true)
    return request(path, options, false)
  }

  const data = await parseJson(res)

  if (data?.csrfToken) {
    csrfToken = data.csrfToken
  }

  if (!res.ok) {
    throw new Error(data.error || `Request failed (${res.status})`)
  }

  return data
}

export const api = {
  initSecurity: () => fetchCsrfToken(),
  get:    (path) => request(path),
  post:   (path, body) => request(path, { method: 'POST',   body: JSON.stringify(body) }),
  patch:  (path, body) => request(path, { method: 'PATCH',  body: JSON.stringify(body) }),
  delete: (path) => request(path, { method: 'DELETE' }),
}
