import api, { ensureCsrfCookie } from './client'

export async function fetchTenantDocuments(tenantId) {
  const { data } = await api.get(`/api/v1/rental/tenants/${tenantId}/documents`)
  return data.data ?? data
}

export async function uploadTenantDocument(tenantId, kind, file) {
  await ensureCsrfCookie()

  const formData = new FormData()
  formData.append('kind', kind)
  formData.append('file', file)

  const { data } = await api.post(`/api/v1/rental/tenants/${tenantId}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  return data.data ?? data
}

export async function fetchClientDocuments(clientId) {
  const { data } = await api.get(`/api/v1/sales/clients/${clientId}/documents`)
  return data.data ?? data
}

export async function uploadClientDocument(clientId, kind, file) {
  await ensureCsrfCookie()

  const formData = new FormData()
  formData.append('kind', kind)
  formData.append('file', file)

  const { data } = await api.post(`/api/v1/sales/clients/${clientId}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  return data.data ?? data
}

export async function deleteDocument(documentId) {
  await ensureCsrfCookie()
  await api.delete(`/api/v1/documents/${documentId}`)
}

export async function fetchDocumentBlob(documentId) {
  const response = await api.get(`/api/v1/documents/${documentId}`, {
    responseType: 'blob',
  })

  return response.data
}

export function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
