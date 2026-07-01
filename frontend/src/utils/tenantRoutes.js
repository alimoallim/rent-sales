export function tenantPaymentsRoute(tenantId, buildingId = null, tenantName = null) {
  const query = { tenant_id: String(tenantId) }
  if (buildingId) query.building_id = String(buildingId)
  if (tenantName) query.tenant_name = tenantName
  return { name: 'rental.payments', query }
}

export function tenantChargesRoute(tenantId, buildingId = null, tenantName = null) {
  const query = { tenant_id: String(tenantId) }
  if (buildingId) query.building_id = String(buildingId)
  if (tenantName) query.tenant_name = tenantName
  return { name: 'rental.charges', query }
}
