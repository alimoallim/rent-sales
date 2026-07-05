import axios from 'axios'

function appBaseUrl() {
  const configured = import.meta.env.VITE_API_BASE_URL
  if (configured) {
    return configured.replace(/\/$/, '')
  }

  // Local dev: API lives at site root (/api), not under the Vue router path (/rental/...).
  return ''
}

function xsrfTokenFromCookie() {
  if (typeof document === 'undefined') {
    return null
  }

  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

const api = axios.create({
  baseURL: appBaseUrl(),
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
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

  const token = xsrfTokenFromCookie()
  if (token) {
    config.headers['X-XSRF-TOKEN'] = token
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 419) {
      resetCsrfCookie()
    }

    return Promise.reject(error)
  },
)

export function resetCsrfCookie() {
  csrfReady = false
}

let csrfReady = false

export async function ensureCsrfCookie() {
  if (csrfReady) {
    return
  }

  await api.get('/sanctum/csrf-cookie')
  csrfReady = true
}

export default api
