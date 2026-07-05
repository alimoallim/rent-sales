/**
 * Map API records to SearchableSelect option objects.
 */

export function buildingOptions(buildings, { includeAll = false, allLabel = 'All buildings' } = {}) {
  const options = (buildings ?? []).map((building) => ({
    value: building.id,
    label: building.name,
  }))

  if (includeAll) {
    return [{ value: '', label: allLabel }, ...options]
  }

  return options
}

export function tenantOptions(tenants) {
  return (tenants ?? []).map((tenant) => ({
    value: tenant.id,
    label: tenant.name,
    hint: tenant.unit_label || undefined,
    keywords: [tenant.name, tenant.unit_label, tenant.phone].filter(Boolean).join(' '),
  }))
}

export function rentalUnitOptions(units) {
  return (units ?? []).map((unit) => ({
    value: unit.id,
    label: `Unit ${unit.house_number}`,
    hint: [unit.description, unit.floor ? `Floor ${unit.floor}` : ''].filter(Boolean).join(' · ') || undefined,
    keywords: [unit.house_number, unit.description, unit.floor].filter(Boolean).join(' '),
  }))
}

export function saleUnitOptions(units) {
  return (units ?? []).map((unit) => ({
    value: unit.id,
    label: `Unit ${unit.house_number}`,
    hint: unit.description || undefined,
    keywords: [unit.house_number, unit.description, unit.floor].filter(Boolean).join(' '),
  }))
}

export function clientOptions(clients) {
  return (clients ?? []).map((client) => ({
    value: client.id,
    label: client.name,
    hint: client.unit_label || client.house_number || undefined,
    keywords: [client.name, client.unit_label, client.house_number, client.phone].filter(Boolean).join(' '),
  }))
}

export function employeeOptions(employees, salaryFormatter = null) {
  return (employees ?? []).map((employee) => ({
    value: employee.id,
    label: employee.name,
    hint: salaryFormatter ? salaryFormatter(employee.salary) : employee.position || undefined,
    keywords: [employee.name, employee.position, employee.phone].filter(Boolean).join(' '),
  }))
}

export function shareholderOptions(shareholders) {
  return (shareholders ?? []).map((shareholder) => ({
    value: shareholder.id,
    label: shareholder.name,
    hint: shareholder.phone || undefined,
    keywords: [shareholder.name, shareholder.phone].filter(Boolean).join(' '),
  }))
}

export function monthOptions(months) {
  return (months ?? []).map((month) => ({
    value: month.value,
    label: month.label,
  }))
}
