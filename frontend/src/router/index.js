import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const LoginView = () => import('../views/LoginView.vue')
const ForgotPasswordView = () => import('../views/ForgotPasswordView.vue')
const ResetPasswordView = () => import('../views/ResetPasswordView.vue')
const AppLayout = () => import('../layouts/AppLayout.vue')
const RentalDashboard = () => import('../views/rental/DashboardView.vue')
const RentalBuildings = () => import('../views/rental/BuildingsView.vue')
const RentalUnits = () => import('../views/rental/UnitsView.vue')
const RentalTenants = () => import('../views/rental/TenantsView.vue')
const RentalChargeBatches = () => import('../views/rental/ChargeBatchesView.vue')
const RentalCharges = () => import('../views/rental/ChargesView.vue')
const RentalPayments = () => import('../views/rental/PaymentsView.vue')
const RentalWaterBills = () => import('../views/rental/WaterBillsView.vue')
const RentalElectricityBills = () => import('../views/rental/ElectricityBillsView.vue')
const RentalBulkMeterReadings = () => import('../views/rental/BulkMeterReadingsView.vue')
const RentalUtilities = () => import('../views/rental/UtilitiesView.vue')
const RentalReports = () => import('../views/rental/ReportsView.vue')
const RentalExpenses = () => import('../views/rental/ExpensesView.vue')
const RentalPayroll = () => import('../views/rental/PayrollView.vue')
const RentalShareholders = () => import('../views/rental/ShareholdersView.vue')
const SalesDashboard = () => import('../views/sales/DashboardView.vue')
const SalesBuildings = () => import('../views/sales/BuildingsView.vue')
const SalesUnits = () => import('../views/sales/UnitsView.vue')
const SalesClients = () => import('../views/sales/ClientsView.vue')
const SalesPayments = () => import('../views/sales/PaymentsView.vue')
const SalesExpenses = () => import('../views/sales/ExpensesView.vue')
const SalesReports = () => import('../views/sales/ReportsView.vue')
const AdminUsers = () => import('../views/admin/UsersView.vue')
const AdminActivityLog = () => import('../views/admin/ActivityLogView.vue')
const AdminRecycleBin = () => import('../views/admin/RecycleBinView.vue')
const SettingsView = () => import('../views/SettingsView.vue')

function canAccessModule(auth, module) {
  if (module === 'rental') return auth.canAccessRental
  if (module === 'sales') return auth.canAccessSales
  if (module === 'admin') return auth.isAdmin
  return true
}

function fallbackRoute(auth) {
  return auth.defaultRoute()
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPasswordView,
      meta: { guest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: ResetPasswordView,
      meta: { guest: true },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: () => fallbackRoute(useAuthStore()) },
        {
          path: 'rental',
          name: 'rental.dashboard',
          component: RentalDashboard,
          meta: { module: 'rental', title: 'Dashboard' },
        },
        {
          path: 'rental/buildings',
          name: 'rental.buildings',
          component: RentalBuildings,
          meta: { module: 'rental', title: 'Buildings' },
        },
        {
          path: 'rental/units',
          name: 'rental.units',
          component: RentalUnits,
          meta: { module: 'rental', title: 'Units' },
        },
        {
          path: 'rental/tenants',
          name: 'rental.tenants',
          component: RentalTenants,
          meta: { module: 'rental', title: 'Tenants' },
        },
        {
          path: 'rental/charge-batches',
          name: 'rental.charge-batches',
          component: RentalChargeBatches,
          meta: { module: 'rental', title: 'Charge batches' },
        },
        {
          path: 'rental/charges',
          name: 'rental.charges',
          component: RentalCharges,
          meta: { module: 'rental', title: 'Charges' },
        },
        {
          path: 'rental/payments',
          name: 'rental.payments',
          component: RentalPayments,
          meta: { module: 'rental', title: 'Payments' },
        },
        {
          path: 'rental/water-bills',
          name: 'rental.water-bills',
          component: RentalWaterBills,
          meta: { module: 'rental', title: 'Water' },
        },
        {
          path: 'rental/electricity-bills',
          name: 'rental.electricity-bills',
          component: RentalElectricityBills,
          meta: { module: 'rental', title: 'Electricity' },
        },
        {
          path: 'rental/bulk-meter-readings',
          name: 'rental.bulk-meter-readings',
          component: RentalBulkMeterReadings,
          meta: { module: 'rental', title: 'Bulk readings' },
        },
        {
          path: 'rental/utilities',
          name: 'rental.utilities',
          component: RentalUtilities,
          meta: { module: 'rental', title: 'Building utilities' },
        },
        {
          path: 'rental/reports',
          name: 'rental.reports',
          component: RentalReports,
          meta: { module: 'rental', title: 'Reports' },
        },
        {
          path: 'rental/expenses',
          name: 'rental.expenses',
          component: RentalExpenses,
          meta: { module: 'rental', title: 'Expenses' },
        },
        {
          path: 'rental/payroll',
          name: 'rental.payroll',
          component: RentalPayroll,
          meta: { module: 'rental', title: 'Payroll' },
        },
        {
          path: 'rental/shareholders',
          name: 'rental.shareholders',
          component: RentalShareholders,
          meta: { module: 'rental', title: 'Shareholders' },
        },
        {
          path: 'sales',
          name: 'sales.dashboard',
          component: SalesDashboard,
          meta: { module: 'sales', title: 'Dashboard' },
        },
        {
          path: 'sales/buildings',
          name: 'sales.buildings',
          component: SalesBuildings,
          meta: { module: 'sales', title: 'Buildings' },
        },
        {
          path: 'sales/units',
          name: 'sales.units',
          component: SalesUnits,
          meta: { module: 'sales', title: 'Units' },
        },
        {
          path: 'sales/clients',
          name: 'sales.clients',
          component: SalesClients,
          meta: { module: 'sales', title: 'Clients' },
        },
        {
          path: 'sales/payments',
          name: 'sales.payments',
          component: SalesPayments,
          meta: { module: 'sales', title: 'Payments' },
        },
        {
          path: 'sales/expenses',
          name: 'sales.expenses',
          component: SalesExpenses,
          meta: { module: 'sales', title: 'Expenses' },
        },
        {
          path: 'sales/reports',
          name: 'sales.reports',
          component: SalesReports,
          meta: { module: 'sales', title: 'Reports' },
        },
        {
          path: 'admin/users',
          name: 'admin.users',
          component: AdminUsers,
          meta: { module: 'admin', title: 'Users' },
        },
        {
          path: 'admin/activity-log',
          name: 'admin.activity-log',
          component: AdminActivityLog,
          meta: { module: 'admin', title: 'Activity log' },
        },
        {
          path: 'admin/recycle-bin',
          name: 'admin.recycle-bin',
          component: AdminRecycleBin,
          meta: { module: 'admin', title: 'Recycle bin' },
        },
        {
          path: 'settings',
          name: 'settings',
          component: SettingsView,
          meta: { title: 'Settings' },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.checked) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return fallbackRoute(auth)
  }

  if (to.meta.module && !canAccessModule(auth, to.meta.module)) {
    return fallbackRoute(auth)
  }

  if (to.meta.module === 'rental' || to.meta.module === 'sales') {
    auth.setPreferredModule(to.meta.module)
  }

  return true
})

export default router
