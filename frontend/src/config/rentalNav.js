export const rentalNavSections = [
  {
    label: 'Overview',
    items: [{ to: '/rental', label: 'Dashboard', exact: true, icon: 'dashboard' }],
  },
  {
    label: 'Properties',
    items: [
      { to: '/rental/buildings', label: 'Buildings', icon: 'building' },
      { to: '/rental/units', label: 'Units', icon: 'unit' },
    ],
  },
  {
    label: 'Tenants',
    items: [{ to: '/rental/tenants', label: 'Tenants', icon: 'tenants' }],
  },
  {
    label: 'Billing',
    items: [
      { to: '/rental/charge-batches', label: 'Charge batches', badgeKey: 'chargeBatches', icon: 'batches' },
      { to: '/rental/charges', label: 'Charges', icon: 'charges' },
      { to: '/rental/payments', label: 'Payments', icon: 'payments' },
      { to: '/rental/water-bills', label: 'Water', icon: 'water' },
      { to: '/rental/electricity-bills', label: 'Electricity', icon: 'electricity' },
    ],
  },
  {
    label: 'Building costs',
    items: [{ to: '/rental/utilities', label: 'Building utilities', icon: 'utilities' }],
  },
  {
    label: 'Operations',
    items: [
      { to: '/rental/expenses', label: 'Expenses', icon: 'expenses' },
      { to: '/rental/payroll', label: 'Payroll', icon: 'payroll' },
      { to: '/rental/shareholders', label: 'Shareholders', icon: 'shareholders' },
    ],
  },
  {
    label: 'Reports',
    items: [{ to: '/rental/reports', label: 'Reports', icon: 'reports' }],
  },
]

export const adminNavSections = [
  {
    label: 'Administration',
    items: [{ to: '/admin/users', label: 'Users', icon: 'users' }],
  },
]

export const salesNavSections = [
  {
    label: 'Overview',
    items: [{ to: '/sales', label: 'Dashboard', exact: true, icon: 'dashboard' }],
  },
  {
    label: 'Properties',
    items: [
      { to: '/sales/buildings', label: 'Buildings', icon: 'building' },
      { to: '/sales/units', label: 'Units', icon: 'unit' },
    ],
  },
  {
    label: 'Clients',
    items: [{ to: '/sales/clients', label: 'Clients', icon: 'clients' }],
  },
  {
    label: 'Financials',
    items: [
      { to: '/sales/payments', label: 'Payments', icon: 'payments' },
      { to: '/sales/expenses', label: 'Expenses', icon: 'expenses' },
    ],
  },
  {
    label: 'Reports',
    items: [{ to: '/sales/reports', label: 'Reports', icon: 'reports' }],
  },
]

export const moduleLabels = {
  rental: 'Rental',
  sales: 'Sales',
  admin: 'Admin',
}
