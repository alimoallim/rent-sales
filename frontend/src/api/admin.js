import api from './client'

export async function fetchActivityLog(params = {}) {
  const { data } = await api.get('/api/v1/admin/activity-log', { params })
  return data
}

export async function fetchRecycleBinTypes() {
  const { data } = await api.get('/api/v1/admin/recycle-bin/types')
  return data
}

export async function fetchRecycleBin(params = {}) {
  const { data } = await api.get('/api/v1/admin/recycle-bin', { params })
  return data
}

export async function restoreRecycleBinItem(type, id) {
  const { data } = await api.post(`/api/v1/admin/recycle-bin/${type}/${id}/restore`)
  return data
}
