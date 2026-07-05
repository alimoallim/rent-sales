export const rentalNavSections = [
  {
    id: 'dashboard',
    standalone: true,
    items: [{ to: '/rental', label: 'Dashboard', exact: true, icon: 'dashboard' }],
  },
  {
    id: 'portfolio',
    label: 'Portfolio',
    icon: 'building',
    defaultOpen: true,
    items: [
      { to: '/rental/buildings', label: 'Buildings', icon: 'building' },
      { to: '/rental/units', label: 'Units', icon: 'unit' },
    ],
  },
  {
    id: 'tenancy',
    label: 'Tenancy',
    icon: 'tenants',
    items: [{ to: '/rental/tenants', label: 'Tenants', icon: 'tenants' }],
  },
  {
    id: 'billing',
    label: 'Billing & collections',
    icon: 'payments',
    defaultOpen: true,
    items: [
      { to: '/rental/charge-batches', label: 'Charge batches', badgeKey: 'chargeBatches', icon: 'batches' },
      { to: '/rental/charges', label: 'Charges', icon: 'charges' },
      { to: '/rental/payments', label: 'Payments', icon: 'payments' },
    ],
  },
  {
    id: 'meter-readings',
    label: 'Meter readings',
    icon: 'meter',
    items: [
      { to: '/rental/bulk-meter-readings', label: 'Bulk entry', icon: 'batches' },
      { to: '/rental/water-bills', label: 'Water bills', icon: 'water' },
      { to: '/rental/electricity-bills', label: 'Electricity bills', icon: 'electricity' },
    ],
  },
  {
    id: 'building-costs',
    label: 'Building costs',
    icon: 'utilities',
    items: [{ to: '/rental/utilities', label: 'Building utilities', icon: 'utilities' }],
  },
  {
    id: 'finance',
    label: 'Finance & payroll',
    icon: 'expenses',
    items: [
      { to: '/rental/expenses', label: 'Expenses', icon: 'expenses' },
      { to: '/rental/payroll', label: 'Payroll', icon: 'payroll' },
      { to: '/rental/shareholders', label: 'Shareholders', icon: 'shareholders' },
    ],
  },
  {
    id: 'reports',
    standalone: true,
    items: [{ to: '/rental/reports', label: 'Reports', icon: 'reports' }],
  },
]

export const adminNavSections = [
  {
    id: 'administration',
    label: 'Administration',
    icon: 'users',
    separated: true,
    items: [
      { to: '/admin/users', label: 'Users', icon: 'users' },
      { to: '/admin/activity-log', label: 'Activity log', icon: 'activity' },
      { to: '/admin/recycle-bin', label: 'Recycle bin', icon: 'recycle' },
      { to: '/settings', label: 'Settings', icon: 'settings' },
    ],
  },
]

export const accountNavSection = {
  id: 'account',
  standalone: true,
  separated: true,
  items: [{ to: '/settings', label: 'Settings', icon: 'settings' }],
}

export const salesNavSections = [
  {
    id: 'dashboard',
    standalone: true,
    items: [{ to: '/sales', label: 'Dashboard', exact: true, icon: 'dashboard' }],
  },
  {
    id: 'portfolio',
    label: 'Portfolio',
    icon: 'building',
    defaultOpen: true,
    items: [
      { to: '/sales/buildings', label: 'Buildings', icon: 'building' },
      { to: '/sales/units', label: 'Units', icon: 'unit' },
    ],
  },
  {
    id: 'clients',
    label: 'Clients',
    icon: 'clients',
    items: [{ to: '/sales/clients', label: 'Clients', icon: 'clients' }],
  },
  {
    id: 'financials',
    label: 'Financials',
    icon: 'payments',
    defaultOpen: true,
    items: [
      { to: '/sales/payments', label: 'Payments', icon: 'payments' },
      { to: '/sales/expenses', label: 'Expenses', icon: 'expenses' },
    ],
  },
  {
    id: 'reports',
    standalone: true,
    items: [{ to: '/sales/reports', label: 'Reports', icon: 'reports' }],
  },
]

export const moduleLabels = {
  rental: 'Rental',
  sales: 'Sales',
  admin: 'Admin',
}

/**
 * @param {import('vue-router').RouteLocationNormalizedLoaded} route
 * @param {{ to: string, exact?: boolean }} item
 */
export function isNavItemActive(route, item) {
  if (item.exact) {
    return route.path === item.to
  }

  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

/**
 * @param {import('vue-router').RouteLocationNormalizedLoaded} route
 * @param {{ items: Array<{ to: string, exact?: boolean, badgeKey?: string }> }} section
 */
export function isNavSectionActive(route, section) {
  return section.items.some((item) => isNavItemActive(route, item))
}

/**
 * @param {{ items: Array<{ badgeKey?: string }> }} section
 * @param {Record<string, number>} badges
 */
export function sectionBadgeCount(section, badges) {
  return section.items.reduce((total, item) => {
    if (!item.badgeKey) return total
    return total + (badges[item.badgeKey] ?? 0)
  }, 0)
}

/**
 * @param {{ standalone?: boolean, label?: string, items: unknown[] }} section
 */
export function isStandaloneNavSection(section) {
  return Boolean(section.standalone)
}

/**
 * @param {{ standalone?: boolean, label?: string, items: unknown[] }} section
 */
export function isCollapsibleNavSection(section) {
  if (section.standalone) return false
  return section.items.length > 1
}
