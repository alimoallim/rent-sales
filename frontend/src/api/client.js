import axios from 'axios'

function appBaseUrl() {
  const configured = import.meta.env.VITE_API_BASE_URL || import.meta.env.BASE_URL || '/'
  return configured.replace(/\/$/, '')
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
  if (config.url?.startsWith('/')) {
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
