import api from './client'

export async function fetchUsers(params = {}) {
  const { data } = await api.get('/api/v1/admin/users', { params })
  return data
}

export async function createUser(payload) {
  const { data } = await api.post('/api/v1/admin/users', payload)
  return data
}

export async function updateUser(id, payload) {
  const { data } = await api.put(`/api/v1/admin/users/${id}`, payload)
  return data
}

export async function deleteUser(id) {
  const { data } = await api.delete(`/api/v1/admin/users/${id}`)
  return data
}
