import api from './client'

export async function fetchDashboard() {
  const { data } = await api.get('/api/v1/rental/dashboard')
  return data
}

export async function fetchBuildings(params = {}) {
  const { data } = await api.get('/api/v1/rental/buildings', { params })
  return data
}

export async function createBuilding(payload) {
  const { data } = await api.post('/api/v1/rental/buildings', payload)
  return data.data
}

export async function updateBuilding(id, payload) {
  const { data } = await api.put(`/api/v1/rental/buildings/${id}`, payload)
  return data.data
}

export async function deleteBuilding(id) {
  await api.delete(`/api/v1/rental/buildings/${id}`)
}

export async function fetchUnits(params = {}) {
  const { data } = await api.get('/api/v1/rental/units', { params })
  return data
}

export async function createUnit(payload) {
  const { data } = await api.post('/api/v1/rental/units', payload)
  return data.data
}

export async function updateUnit(id, payload) {
  const { data } = await api.put(`/api/v1/rental/units/${id}`, payload)
  return data.data
}

export async function deleteUnit(id) {
  await api.delete(`/api/v1/rental/units/${id}`)
}

export async function fetchTenants(params = {}) {
  const { data } = await api.get('/api/v1/rental/tenants', { params })
  return data
}

export async function fetchTenantPaymentSummary(tenantId, params = {}) {
  const { data } = await api.get(`/api/v1/rental/tenants/${tenantId}/payment-summary`, { params })
  return data.data
}

export async function createTenant(payload) {
  const { data } = await api.post('/api/v1/rental/tenants', payload)
  return data.data
}

export async function updateTenant(id, payload) {
  const { data } = await api.put(`/api/v1/rental/tenants/${id}`, payload)
  return data.data
}

export async function moveOutTenant(id, payload) {
  const { data } = await api.post(`/api/v1/rental/tenants/${id}/move-out`, payload)
  return data.data
}

export async function fetchMoveOuts(params = {}) {
  const { data } = await api.get('/api/v1/rental/move-outs', { params })
  return data
}

export async function fetchCharges(params = {}) {
  const { data } = await api.get('/api/v1/rental/charges', { params })
  return data
}

export async function generateCharges(payload = {}) {
  const { data } = await api.post('/api/v1/rental/charge-batches/generate', payload)
  return data
}

export async function fetchChargeBatchPendingCount() {
  const { data } = await api.get('/api/v1/rental/charge-batches/pending-count')
  return data
}

export async function fetchChargeBatch(params) {
  const { data } = await api.get('/api/v1/rental/charge-batches', { params })
  return data
}

export async function generateChargeBatch(payload) {
  const { data } = await api.post('/api/v1/rental/charge-batches/generate', payload)
  return data
}

export async function refreshChargeBatchPending(batchId) {
  const { data } = await api.post(`/api/v1/rental/charge-batches/${batchId}/refresh-pending`)
  return data
}

export async function updateChargeBatchItem(batchId, itemId, payload) {
  const { data } = await api.put(`/api/v1/rental/charge-batches/${batchId}/items/${itemId}`, payload)
  return data
}

export async function excludeChargeBatchTenant(batchId, tenantId, payload) {
  const { data } = await api.post(`/api/v1/rental/charge-batches/${batchId}/tenants/${tenantId}/exclude`, payload)
  return data
}

export async function approveChargeBatchTenant(batchId, tenantId) {
  const { data } = await api.post(`/api/v1/rental/charge-batches/${batchId}/tenants/${tenantId}/approve`)
  return data
}

export async function reopenChargeBatchTenant(batchId, tenantId) {
  const { data } = await api.post(`/api/v1/rental/charge-batches/${batchId}/tenants/${tenantId}/reopen`)
  return data
}

export async function approveAllChargeBatch(batchId) {
  const { data } = await api.post(`/api/v1/rental/charge-batches/${batchId}/approve-all`)
  return data
}

export async function updateCharge(id, payload) {
  const { data } = await api.put(`/api/v1/rental/charges/${id}`, payload)
  return data.data
}

export async function fetchPayments(params = {}) {
  const { data } = await api.get('/api/v1/rental/payments', { params })
  return data
}

export async function createPayment(payload) {
  const { data } = await api.post('/api/v1/rental/payments', payload)
  return data.data
}

export async function updatePayment(id, payload) {
  const { data } = await api.put(`/api/v1/rental/payments/${id}`, payload)
  return data.data
}

export async function voidPayment(id) {
  const { data } = await api.post(`/api/v1/rental/payments/${id}/void`)
  return data.data
}

export async function fetchWaterBills(params = {}) {
  const { data } = await api.get('/api/v1/rental/water-bills', { params })
  return data
}

export async function createWaterBill(payload) {
  const { data } = await api.post('/api/v1/rental/water-bills', payload)
  return data.data
}

export async function fetchMeterReadingContext(params) {
  const { data } = await api.get('/api/v1/rental/meter-readings/context', { params })
  return data.data
}

export async function fetchTenantElectricityBills(params = {}) {
  const { data } = await api.get('/api/v1/rental/electricity-bills', { params })
  return data
}

export async function createTenantElectricityBill(payload) {
  const { data } = await api.post('/api/v1/rental/electricity-bills', payload)
  return data.data
}

export async function fetchNairobiWaterBills(params = {}) {
  const { data } = await api.get('/api/v1/rental/utilities/nairobi-water', { params })
  return data
}

export async function createNairobiWaterBill(payload) {
  const { data } = await api.post('/api/v1/rental/utilities/nairobi-water', payload)
  return data.data
}

export async function fetchElectricityBills(params = {}) {
  const { data } = await api.get('/api/v1/rental/utilities/electricity', { params })
  return data
}

export async function createElectricityBill(payload) {
  const { data } = await api.post('/api/v1/rental/utilities/electricity', payload)
  return data.data
}

export async function fetchTenantBalancesReport(params = {}) {
  const { data } = await api.get('/api/v1/rental/reports/tenant-balances', { params })
  return data
}

export async function fetchPaymentHistoryReport(params = {}) {
  const { data } = await api.get('/api/v1/rental/reports/payment-history', { params })
  return data
}

export async function fetchChargeSummaryReport(params = {}) {
  const { data } = await api.get('/api/v1/rental/reports/charge-summary', { params })
  return data
}

export async function fetchIncomeStatementReport(params = {}) {
  const { data } = await api.get('/api/v1/rental/reports/income-statement', { params })
  return data
}

export function reportCsvUrl(path, params = {}) {
  const search = new URLSearchParams({ ...params, format: 'csv' })
  return `/api/v1/rental/reports/${path}?${search.toString()}`
}

export async function downloadReportCsv(path, params = {}, filename = 'report.csv') {
  const response = await api.get(`/api/v1/rental/reports/${path}`, {
    params: { ...params, format: 'csv' },
    responseType: 'blob',
  })
  const blob = new Blob([response.data], { type: 'text/csv' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}

export async function fetchExpenses(params = {}) {
  const { data } = await api.get('/api/v1/rental/expenses', { params })
  return data
}

export async function createExpense(payload) {
  const { data } = await api.post('/api/v1/rental/expenses', payload)
  return data.data
}

export async function updateExpense(id, payload) {
  const { data } = await api.put(`/api/v1/rental/expenses/${id}`, payload)
  return data.data
}

export async function deleteExpense(id) {
  await api.delete(`/api/v1/rental/expenses/${id}`)
}

export async function fetchEmployees(params = {}) {
  const { data } = await api.get('/api/v1/rental/employees', { params })
  return data
}

export async function createEmployee(payload) {
  const { data } = await api.post('/api/v1/rental/employees', payload)
  return data.data
}

export async function updateEmployee(id, payload) {
  const { data } = await api.put(`/api/v1/rental/employees/${id}`, payload)
  return data.data
}

export async function deleteEmployee(id) {
  await api.delete(`/api/v1/rental/employees/${id}`)
}

export async function fetchPayroll(params = {}) {
  const { data } = await api.get('/api/v1/rental/payroll', { params })
  return data
}

export async function createPayrollEntry(payload) {
  const { data } = await api.post('/api/v1/rental/payroll', payload)
  return data.data
}

export async function updatePayrollEntry(id, payload) {
  const { data } = await api.put(`/api/v1/rental/payroll/${id}`, payload)
  return data.data
}

export async function deletePayrollEntry(id) {
  await api.delete(`/api/v1/rental/payroll/${id}`)
}

export async function fetchShareholders(params = {}) {
  const { data } = await api.get('/api/v1/rental/shareholders', { params })
  return data
}

export async function createShareholder(payload) {
  const { data } = await api.post('/api/v1/rental/shareholders', payload)
  return data.data
}

export async function updateShareholder(id, payload) {
  const { data } = await api.put(`/api/v1/rental/shareholders/${id}`, payload)
  return data.data
}

export async function deleteShareholder(id) {
  await api.delete(`/api/v1/rental/shareholders/${id}`)
}

export async function fetchShareholderBills(params = {}) {
  const { data } = await api.get('/api/v1/rental/shareholder-bills', { params })
  return data
}

export async function createShareholderBill(payload) {
  const { data } = await api.post('/api/v1/rental/shareholder-bills', payload)
  return data.data
}

export async function updateShareholderBill(id, payload) {
  const { data } = await api.put(`/api/v1/rental/shareholder-bills/${id}`, payload)
  return data.data
}

export async function deleteShareholderBill(id) {
  await api.delete(`/api/v1/rental/shareholder-bills/${id}`)
}
