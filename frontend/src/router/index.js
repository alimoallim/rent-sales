import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import AppLayout from '../layouts/AppLayout.vue'
import RentalDashboard from '../views/rental/DashboardView.vue'
import RentalBuildings from '../views/rental/BuildingsView.vue'
import RentalUnits from '../views/rental/UnitsView.vue'
import RentalTenants from '../views/rental/TenantsView.vue'
import RentalChargeBatches from '../views/rental/ChargeBatchesView.vue'
import RentalCharges from '../views/rental/ChargesView.vue'
import RentalPayments from '../views/rental/PaymentsView.vue'
import RentalWaterBills from '../views/rental/WaterBillsView.vue'
import RentalElectricityBills from '../views/rental/ElectricityBillsView.vue'
import RentalBulkMeterReadings from '../views/rental/BulkMeterReadingsView.vue'
import RentalUtilities from '../views/rental/UtilitiesView.vue'
import RentalReports from '../views/rental/ReportsView.vue'
import RentalExpenses from '../views/rental/ExpensesView.vue'
import RentalPayroll from '../views/rental/PayrollView.vue'
import RentalShareholders from '../views/rental/ShareholdersView.vue'
import SalesDashboard from '../views/sales/DashboardView.vue'
import SalesBuildings from '../views/sales/BuildingsView.vue'
import SalesUnits from '../views/sales/UnitsView.vue'
import SalesClients from '../views/sales/ClientsView.vue'
import SalesPayments from '../views/sales/PaymentsView.vue'
import SalesExpenses from '../views/sales/ExpensesView.vue'
import SalesReports from '../views/sales/ReportsView.vue'
import AdminUsers from '../views/admin/UsersView.vue'
import AdminActivityLog from '../views/admin/ActivityLogView.vue'
import AdminRecycleBin from '../views/admin/RecycleBinView.vue'
import SettingsView from '../views/SettingsView.vue'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'

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
