import axios from 'axios'

function appBaseUrl() {
  const configured = import.meta.env.VITE_API_BASE_URL
  if (configured) {
    return configured.replace(/\/$/, '')
  }

  // Local dev: API lives at site root (/api), not under the Vue router path (/rental/...).
  return ''
}

const api = axios.create({
  baseURL: appBaseUrl(),
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

api.interceptors.request.use((config) => {
  const base = appBaseUrl()

  // Subdirectory deploy (e.g. /app): join base + path without a double slash.
  if (base && config.url?.startsWith('/')) {
    config.url = config.url.slice(1)
  }

  return config
})

let csrfReady = false

export async function ensureCsrfCookie() {
  if (csrfReady) {
    return
  }

  await api.get('/sanctum/csrf-cookie')
  csrfReady = true
}

export default api
