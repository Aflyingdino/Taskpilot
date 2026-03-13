const BASE = '/api'
const CSRF_HEADER = 'X-CSRF-Token'

let csrfToken = null
let csrfPromise = null

async function fetchCsrfToken(forceRefresh = false) {
  if (csrfToken && !forceRefresh) return csrfToken
  if (csrfPromise && !forceRefresh) return csrfPromise

  csrfPromise = fetch(`${BASE}/csrf`, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
    .then(async (res) => {
      const data = await res.json().catch(() => ({}))
      if (!res.ok || !data.token) {
        throw new Error(data.error || `Request failed (${res.status})`)
      }
      csrfToken = data.token
      return csrfToken
    })
    .finally(() => {
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

  const res = await fetch(BASE + path, {
    headers,
    credentials: 'include',
    ...options,
  })

  if (res.status === 204) return null

  if (res.status === 419 && allowRetry) {
    await fetchCsrfToken(true)
    return request(path, options, false)
  }

  const data = await res.json().catch(() => ({}))

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
