import api from './client'

export async function fetchDashboard() {
  const { data } = await api.get('/api/v1/sales/dashboard')
  return data
}

export async function fetchBuildings(params = {}) {
  const { data } = await api.get('/api/v1/sales/buildings', { params })
  return data
}

export async function createBuilding(payload) {
  const { data } = await api.post('/api/v1/sales/buildings', payload)
  return data.data
}

export async function updateBuilding(id, payload) {
  const { data } = await api.put(`/api/v1/sales/buildings/${id}`, payload)
  return data.data
}

export async function deleteBuilding(id) {
  await api.delete(`/api/v1/sales/buildings/${id}`)
}

export async function fetchUnits(params = {}) {
  const { data } = await api.get('/api/v1/sales/units', { params })
  return data
}

export async function createUnit(payload) {
  const { data } = await api.post('/api/v1/sales/units', payload)
  return data.data
}

export async function updateUnit(id, payload) {
  const { data } = await api.put(`/api/v1/sales/units/${id}`, payload)
  return data.data
}

export async function deleteUnit(id) {
  await api.delete(`/api/v1/sales/units/${id}`)
}

export async function fetchClients(params = {}) {
  const { data } = await api.get('/api/v1/sales/clients', { params })
  return data
}

export async function fetchClient(clientId) {
  const { data } = await api.get(`/api/v1/sales/clients/${clientId}`)
  return data.data
}

export async function fetchClientPaymentSummary(clientId) {
  const { data } = await api.get(`/api/v1/sales/clients/${clientId}/payment-summary`)
  return data.data
}

export async function createClient(payload) {
  const { data } = await api.post('/api/v1/sales/clients', payload)
  return data.data
}

export async function updateClient(id, payload) {
  const { data } = await api.put(`/api/v1/sales/clients/${id}`, payload)
  return data.data
}

export async function disableClient(id) {
  const { data } = await api.post(`/api/v1/sales/clients/${id}/disable`)
  return data.data
}

export async function fetchPayments(params = {}) {
  const { data } = await api.get('/api/v1/sales/payments', { params })
  return data
}

export async function createPayment(payload) {
  const { data } = await api.post('/api/v1/sales/payments', payload)
  return data.data
}

export async function updatePayment(id, payload) {
  const { data } = await api.put(`/api/v1/sales/payments/${id}`, payload)
  return data.data
}

export async function cancelPayment(id) {
  const { data } = await api.post(`/api/v1/sales/payments/${id}/cancel`)
  return data.data
}

export async function fetchExpenses(params = {}) {
  const { data } = await api.get('/api/v1/sales/expenses', { params })
  return data
}

export async function createExpense(payload) {
  const { data } = await api.post('/api/v1/sales/expenses', payload)
  return data.data
}

export async function updateExpense(id, payload) {
  const { data } = await api.put(`/api/v1/sales/expenses/${id}`, payload)
  return data.data
}

export async function deleteExpense(id) {
  await api.delete(`/api/v1/sales/expenses/${id}`)
}

export async function fetchBalanceReport(params = {}) {
  const { data } = await api.get('/api/v1/sales/reports/balance', { params })
  return data
}

export async function fetchIncomeStatement(params = {}) {
  const { data } = await api.get('/api/v1/sales/reports/income-statement', { params })
  return data
}
