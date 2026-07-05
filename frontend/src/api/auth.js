import api from './client'

export async function forgotPassword(email) {
  const { data } = await api.post('/api/v1/auth/forgot-password', { email })
  return data
}

export async function verifyResetCode(payload) {
  const { data } = await api.post('/api/v1/auth/verify-reset-code', payload)
  return data
}

export async function resetPassword(payload) {
  const { data } = await api.post('/api/v1/auth/reset-password', payload)
  return data
}

export async function updateProfile(payload) {
  const { data } = await api.patch('/api/v1/auth/profile', payload)
  return data.data
}

export async function updatePassword(payload) {
  const { data } = await api.put('/api/v1/auth/password', payload)
  return data
}

export async function fetchSystemSettings() {
  const { data } = await api.get('/api/v1/admin/settings')
  return data.data
}

export async function sendTestEmail(email) {
  const { data } = await api.post('/api/v1/admin/settings/test-email', { email })
  return data
}
