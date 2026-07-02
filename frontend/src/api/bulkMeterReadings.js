import api from './client'

export async function fetchBulkMeterReadingGrid(params) {
  const { data } = await api.get('/api/v1/rental/bulk-meter-readings', { params })
  return data.data
}

export async function storeBulkMeterReadings(payload) {
  const { data } = await api.post('/api/v1/rental/bulk-meter-readings', payload)
  return data.data
}
