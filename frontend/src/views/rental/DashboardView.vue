<template>
  <section>
    <PageHeader
      title="Rental dashboard"
      :subtitle="dashboardSubtitle"
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Dashboard' }]"
    >
      <template #actions>
        <button type="button" class="btn-secondary" :disabled="loading" @click="load">
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </template>
    </PageHeader>

    <p v-if="error" class="alert-error">{{ error }}</p>

    <DashboardActionPanel
      v-if="data?.action_required?.total_count > 0"
      :action-required="data.action_required"
      class="mb-5"
    />

    <template v-if="data">
      <div class="dashboard-metrics-grid">
        <DashboardMetricCard
          label="Total outstanding"
          :value="formatMoney(data.outstanding.total_balance, 'rental')"
          :hint="`${data.outstanding.tenants_with_balance} tenants owe`"
          accent="warning"
          to="/rental/tenants"
        />
        <DashboardMetricCard
          label="Collected this month"
          :value="formatMoney(data.collections.current_month, 'rental')"
          :hint="`${data.collections.payment_count_current_month} payments`"
          :trend="data.collections.change_percent"
          accent="success"
          to="/rental/payments"
        />
        <DashboardMetricCard
          label="Occupancy"
          :value="`${data.occupancy.occupancy_rate}%`"
          :hint="`${data.occupancy.occupied_units} of ${data.occupancy.total_units} units`"
          accent="info"
          to="/rental/units"
        />
        <DashboardMetricCard
          label="Active tenants"
          :value="String(data.occupancy.active_tenants)"
          :hint="`${data.occupancy.vacant_units} vacant units`"
          accent="neutral"
          to="/rental/tenants"
        />
      </div>

      <div class="dashboard-metrics-grid dashboard-metrics-grid-secondary">
        <DashboardMetricCard
          label="Pending charge batches"
          :value="String(data.operations.pending_charge_batches)"
          hint="Draft or awaiting approval"
          :accent="data.operations.pending_charge_batches > 0 ? 'warning' : 'neutral'"
          to="/rental/charge-batches"
        />
        <DashboardMetricCard
          label="Missing water readings"
          :value="String(data.utilities.missing_water_readings.count)"
          hint="Tenants without a reading this month"
          :accent="data.utilities.missing_water_readings.count > 0 ? 'warning' : 'info'"
          to="/rental/water-bills"
        />
        <DashboardMetricCard
          label="Missing electricity readings"
          :value="String(data.utilities.missing_electricity_readings.count)"
          hint="Tenants without a reading this month"
          :accent="data.utilities.missing_electricity_readings.count > 0 ? 'warning' : 'accent'"
          to="/rental/electricity-bills"
        />
        <DashboardMetricCard
          label="Move-outs (30 days)"
          :value="String(data.operations.move_outs_last_30_days)"
          :hint="`${data.operations.move_outs_last_90_days} in last 90 days`"
          accent="neutral"
          to="/rental/tenants"
        />
      </div>

      <div class="dashboard-grid-2">
        <DashboardPanel title="Outstanding breakdown" subtitle="What tenants still owe by category">
          <div class="dashboard-breakdown">
            <div v-for="item in breakdownItems" :key="item.key" class="dashboard-breakdown-row">
              <div class="dashboard-breakdown-meta">
                <span class="dashboard-breakdown-label">{{ item.label }}</span>
                <span class="dashboard-breakdown-amount tabular-nums">{{ formatMoney(item.amount, 'rental') }}</span>
              </div>
              <div class="dashboard-breakdown-track">
                <div
                  class="dashboard-breakdown-bar"
                  :class="item.barClass"
                  :style="{ width: `${item.percent}%` }"
                />
              </div>
            </div>
          </div>
          <div class="dashboard-breakdown-footer">
            <div class="dashboard-stat-chip dashboard-stat-chip-success">
              <p class="text-xs text-emerald-700 dark:text-emerald-300">Paid up</p>
              <p class="text-sm font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ data.outstanding.tenants_paid_up }}</p>
            </div>
            <div class="dashboard-stat-chip dashboard-stat-chip-info">
              <p class="text-xs text-sky-700 dark:text-sky-300">In credit</p>
              <p class="text-sm font-semibold tabular-nums text-sky-800 dark:text-sky-200">{{ data.outstanding.tenants_in_credit }}</p>
            </div>
            <div class="dashboard-stat-chip dashboard-stat-chip-warning">
              <p class="text-xs text-amber-700 dark:text-amber-300">With balance</p>
              <p class="text-sm font-semibold tabular-nums text-amber-800 dark:text-amber-200">{{ data.outstanding.tenants_with_balance }}</p>
            </div>
          </div>
        </DashboardPanel>

        <DashboardPanel
          title="Collections"
          :subtitle="`${data.collections.current_month_label} vs ${data.collections.previous_month_label}`"
          action-to="/rental/payments"
        >
          <div class="dashboard-collections">
            <div class="dashboard-collections-card dashboard-collections-card-success">
              <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">This month</p>
              <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                {{ formatMoney(data.collections.current_month, 'rental') }}
              </p>
              <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ data.collections.payment_count_current_month }} payments recorded</p>
            </div>
            <div class="dashboard-collections-card dashboard-collections-card-muted">
              <p class="text-xs font-medium uppercase tracking-wide text-zinc-600 dark:text-zinc-400">Last month</p>
              <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">
                {{ formatMoney(data.collections.previous_month, 'rental') }}
              </p>
              <p
                v-if="data.collections.change_percent !== null"
                class="mt-1 text-xs font-medium"
                :class="data.collections.change_percent >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'"
              >
                {{ data.collections.change_percent >= 0 ? '+' : '' }}{{ data.collections.change_percent }}% change
              </p>
            </div>
          </div>
          <div class="dashboard-charges-summary">
            <p class="text-xs font-medium uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Charges this month</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
              {{ formatMoney(data.charges.current_month_total, 'rental') }}
            </p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ data.charges.current_month_count }} charge lines posted</p>
          </div>
        </DashboardPanel>
      </div>

      <div class="dashboard-grid-2">
        <DashboardPanel title="Top debtors" subtitle="Highest outstanding balances" action-to="/rental/tenants">
          <EmptyState
            v-if="data.top_debtors.length === 0"
            title="All tenants are paid up"
            description="No outstanding balances at this time."
          />
          <ul v-else class="dashboard-list">
            <li v-for="debtor in data.top_debtors" :key="debtor.tenant_id" class="dashboard-list-item">
              <div class="min-w-0">
                <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ debtor.tenant_name }}</p>
                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                  {{ debtor.building_name }} · Unit {{ debtor.unit_label }}
                </p>
              </div>
              <div class="text-right">
                <p class="font-semibold tabular-nums text-amber-700">{{ formatMoney(debtor.balance, 'rental') }}</p>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                  R {{ formatCompact(debtor.rent_owed) }} · S {{ formatCompact(debtor.services_owed) }} · W {{ formatCompact(debtor.water_owed) }}
                </p>
              </div>
            </li>
          </ul>
        </DashboardPanel>

        <DashboardPanel title="Recent payments" subtitle="Latest rent collections" action-to="/rental/payments">
          <EmptyState v-if="data.recent_payments.length === 0" title="No payments yet" description="Payments will appear here once recorded." />
          <ul v-else class="dashboard-list">
            <li v-for="payment in data.recent_payments" :key="payment.payment_id" class="dashboard-list-item">
              <div class="min-w-0">
                <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ payment.tenant_name }}</p>
                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                  {{ payment.building_name }} · {{ payment.paid_at }}
                  <span v-if="payment.invoice_reference"> · {{ payment.invoice_reference }}</span>
                </p>
              </div>
              <p class="font-semibold tabular-nums text-emerald-700">{{ formatMoney(payment.amount, 'rental') }}</p>
            </li>
          </ul>
        </DashboardPanel>
      </div>

      <div class="dashboard-grid-2">
        <DashboardPanel title="Building performance" subtitle="Occupancy and outstanding by property" action-to="/rental/buildings">
          <EmptyState v-if="data.building_summary.length === 0" title="No buildings" description="Add a building to see performance here." />
          <div v-else class="overflow-x-auto">
            <table class="dashboard-table">
              <thead>
                <tr>
                  <th>Building</th>
                  <th class="text-right">Occupancy</th>
                  <th class="text-right">Tenants</th>
                  <th class="text-right">Vacant</th>
                  <th class="text-right">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="building in data.building_summary" :key="building.building_id">
                  <td class="font-medium text-zinc-900 dark:text-zinc-100">{{ building.building_name }}</td>
                  <td class="text-right tabular-nums">{{ building.occupancy_rate }}%</td>
                  <td class="text-right tabular-nums">{{ building.active_tenants }}</td>
                  <td class="text-right tabular-nums">{{ building.vacant_units }}</td>
                  <td class="text-right tabular-nums font-medium" :class="building.outstanding_balance > 0 ? 'text-amber-700' : 'text-zinc-600 dark:text-zinc-400'">
                    {{ formatMoney(building.outstanding_balance, 'rental') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </DashboardPanel>

        <DashboardPanel title="Recent move-outs" subtitle="Latest tenant departures" action-to="/rental/tenants">
          <EmptyState v-if="data.recent_move_outs.length === 0" title="No move-outs" description="Recent departures will appear here." />
          <ul v-else class="dashboard-list">
            <li v-for="moveOut in data.recent_move_outs" :key="moveOut.id" class="dashboard-list-item">
              <div class="min-w-0">
                <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ moveOut.tenant_name }}</p>
                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                  {{ moveOut.building_name }} · Unit {{ moveOut.unit_label }} · {{ moveOut.moved_out_at }}
                </p>
                <p v-if="moveOut.reason" class="truncate text-[11px] text-zinc-400">{{ moveOut.reason }}</p>
              </div>
              <p class="text-sm font-medium tabular-nums text-zinc-700 dark:text-zinc-300">
                Refund {{ formatMoney(moveOut.refund_amount, 'rental') }}
              </p>
            </li>
          </ul>
        </DashboardPanel>
      </div>
    </template>

    <DashboardSkeleton v-else-if="loading" />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import DashboardMetricCard from '../../components/dashboard/DashboardMetricCard.vue'
import DashboardActionPanel from '../../components/dashboard/DashboardActionPanel.vue'
import DashboardPanel from '../../components/dashboard/DashboardPanel.vue'
import DashboardSkeleton from '../../components/dashboard/DashboardSkeleton.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import { fetchDashboard } from '../../api/rental'
import { formatMoney } from '../../utils/money'

const data = ref(null)
const loading = ref(false)
const error = ref('')

const dashboardSubtitle = computed(() => {
  const period = data.value?.period?.label ?? 'this month'
  const updated = data.value?.generated_at ? ` · Updated ${formatRelativeTime(data.value.generated_at)}` : ''
  return `Operational snapshot for ${period}${updated}`
})

const breakdownItems = computed(() => {
  if (!data.value) return []

  const outstanding = data.value.outstanding
  const total = Math.max(parseFloat(outstanding.total_balance) || 0, 1)

  const items = [
    { key: 'rent', label: 'Rent', amount: outstanding.rent_owed, barClass: 'bg-indigo-500' },
    { key: 'services', label: 'Services', amount: outstanding.services_owed, barClass: 'bg-violet-500' },
    { key: 'water', label: 'Water', amount: outstanding.water_owed, barClass: 'bg-sky-500' },
    { key: 'electricity', label: 'Electricity', amount: outstanding.electricity_owed, barClass: 'bg-amber-500' },
  ]

  return items.map((item) => ({
    ...item,
    percent: Math.max(4, Math.round((parseFloat(item.amount) / total) * 100)),
  }))
})



function formatCompact(value) {
  const amount = Number(value ?? 0)
  if (amount >= 1000) return `${(amount / 1000).toFixed(1)}k`
  return amount.toFixed(0)
}

function formatRelativeTime(iso) {
  const date = new Date(iso)
  const diff = Date.now() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return date.toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchDashboard()
  } catch {
    error.value = 'Could not load dashboard data.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
