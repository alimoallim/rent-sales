<template>
  <section class="dashboard-page sales-dashboard-page">
    <header class="dashboard-page-header">
      <div>
        <h2 class="page-title">Sales dashboard</h2>
        <p class="page-subtitle">
          Portfolio and pipeline snapshot for {{ data?.period?.label ?? 'this month' }}.
          <span v-if="data?.generated_at" class="text-zinc-400">
            Updated {{ formatRelativeTime(data.generated_at) }}
          </span>
        </p>
      </div>
      <button type="button" class="btn-secondary" :disabled="loading" @click="load">
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </header>

    <p v-if="error" class="alert-error">{{ error }}</p>

    <template v-if="data">
      <div class="sales-dashboard-hero">
        <div class="sales-dashboard-hero-grid">
          <div>
            <p class="sales-dashboard-hero-label">Portfolio collection rate</p>
            <p class="sales-dashboard-hero-value">{{ data.portfolio.collection_rate }}%</p>
            <p class="sales-dashboard-hero-sub">
              {{ formatMoney(data.portfolio.collected_total, 'sales') }} collected of
              {{ formatMoney(data.portfolio.agreed_sale_value, 'sales') }} agreed across
              {{ data.portfolio.active_clients }} active clients
            </p>
          </div>
          <div class="sales-dashboard-pipeline">
            <div class="sales-dashboard-pipeline-step">
              <p class="sales-dashboard-hero-label">Available inventory</p>
              <p class="sales-dashboard-pipeline-value">{{ formatMoney(data.pipeline.available_list_value, 'sales') }}</p>
              <p class="text-xs text-indigo-200">{{ data.inventory.available_units }} units for sale</p>
              <div class="sales-dashboard-pipeline-bar">
                <div
                  class="sales-dashboard-pipeline-fill"
                  :style="{ width: `${pipelinePercent(data.pipeline.available_list_value)}%` }"
                />
              </div>
            </div>
            <div class="sales-dashboard-pipeline-step">
              <p class="sales-dashboard-hero-label">Agreed sales</p>
              <p class="sales-dashboard-pipeline-value">{{ formatMoney(data.pipeline.agreed_sale_value, 'sales') }}</p>
              <p class="text-xs text-indigo-200">{{ data.inventory.sold_units }} units sold</p>
              <div class="sales-dashboard-pipeline-bar">
                <div
                  class="sales-dashboard-pipeline-fill"
                  :style="{ width: `${pipelinePercent(data.pipeline.agreed_sale_value)}%` }"
                />
              </div>
            </div>
            <div class="sales-dashboard-pipeline-step">
              <p class="sales-dashboard-hero-label">Cash collected</p>
              <p class="sales-dashboard-pipeline-value">{{ formatMoney(data.pipeline.collected_total, 'sales') }}</p>
              <p class="text-xs text-indigo-200">{{ data.portfolio.clients_paid_up }} clients fully paid</p>
              <div class="sales-dashboard-pipeline-bar">
                <div
                  class="sales-dashboard-pipeline-fill"
                  :style="{ width: `${pipelinePercent(data.pipeline.collected_total)}%` }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="dashboard-metrics-grid">
        <DashboardMetricCard
          label="Outstanding"
          :value="formatMoney(data.portfolio.outstanding_total, 'sales')"
          :hint="`${data.portfolio.clients_with_balance} clients with balance`"
          accent="warning"
          to="/sales/clients"
        />
        <DashboardMetricCard
          label="Collected this month"
          :value="formatMoney(data.collections.current_month, 'sales')"
          :hint="`${data.collections.payment_count_current_month} payments`"
          :trend="data.collections.change_percent"
          accent="success"
          to="/sales/payments"
        />
        <DashboardMetricCard
          label="Sell-through"
          :value="`${data.inventory.sell_through_rate}%`"
          :hint="`${data.inventory.sold_units} of ${data.inventory.total_units} units sold`"
          accent="info"
          to="/sales/units"
        />
        <DashboardMetricCard
          label="Available inventory"
          :value="formatMoney(data.inventory.available_list_value, 'sales')"
          :hint="`${data.inventory.available_units} units ready to sell`"
          accent="success"
          to="/sales/units"
        />
      </div>

      <div class="dashboard-metrics-grid dashboard-metrics-grid-secondary">
        <DashboardMetricCard
          label="Active clients"
          :value="String(data.portfolio.active_clients)"
          :hint="`${data.portfolio.clients_paid_up} fully paid up`"
          accent="neutral"
          to="/sales/clients"
        />
        <DashboardMetricCard
          label="New clients (month)"
          :value="String(data.operations.new_clients_this_month)"
          :hint="`${data.operations.new_clients_last_month} last month`"
          accent="info"
          to="/sales/clients"
        />
        <DashboardMetricCard
          label="Sale buildings"
          :value="String(data.inventory.buildings)"
          hint="Properties in portfolio"
          accent="neutral"
          to="/sales/buildings"
        />
        <DashboardMetricCard
          label="Expenses (month)"
          :value="formatMoney(data.operations.expenses_current_month, 'sales')"
          hint="Recorded sales expenses"
          accent="warning"
          to="/sales/expenses"
        />
      </div>

      <div class="dashboard-grid-2">
        <DashboardPanel title="Collection pipeline" subtitle="From list price to cash received">
          <div class="sales-dashboard-progress-list">
            <div class="sales-dashboard-progress-item">
              <div class="sales-dashboard-progress-meta">
                <span class="font-medium text-zinc-700 dark:text-zinc-300">Available list value</span>
                <span class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                  {{ formatMoney(data.pipeline.available_list_value, 'sales') }}
                </span>
              </div>
              <div class="sales-dashboard-progress-track">
                <div
                  class="sales-dashboard-progress-bar bg-gradient-to-r from-emerald-400 to-emerald-500"
                  :style="{ width: `${pipelinePercent(data.pipeline.available_list_value)}%` }"
                />
              </div>
            </div>
            <div class="sales-dashboard-progress-item">
              <div class="sales-dashboard-progress-meta">
                <span class="font-medium text-zinc-700 dark:text-zinc-300">Agreed sale value</span>
                <span class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                  {{ formatMoney(data.pipeline.agreed_sale_value, 'sales') }}
                </span>
              </div>
              <div class="sales-dashboard-progress-track">
                <div
                  class="sales-dashboard-progress-bar"
                  :style="{ width: `${pipelinePercent(data.pipeline.agreed_sale_value)}%` }"
                />
              </div>
            </div>
            <div class="sales-dashboard-progress-item">
              <div class="sales-dashboard-progress-meta">
                <span class="font-medium text-zinc-700 dark:text-zinc-300">Collected to date</span>
                <span class="font-semibold tabular-nums text-emerald-700">
                  {{ formatMoney(data.pipeline.collected_total, 'sales') }}
                </span>
              </div>
              <div class="sales-dashboard-progress-track">
                <div
                  class="sales-dashboard-progress-bar bg-gradient-to-r from-indigo-500 to-violet-600"
                  :style="{ width: `${pipelinePercent(data.pipeline.collected_total)}%` }"
                />
              </div>
            </div>
          </div>
          <div class="dashboard-breakdown-footer">
            <div>
              <p class="text-xs text-zinc-500 dark:text-zinc-400">Outstanding</p>
              <p class="text-sm font-semibold tabular-nums text-amber-700">
                {{ formatMoney(data.portfolio.outstanding_total, 'sales') }}
              </p>
            </div>
            <div>
              <p class="text-xs text-zinc-500 dark:text-zinc-400">Collection rate</p>
              <p class="text-sm font-semibold tabular-nums text-indigo-700">{{ data.portfolio.collection_rate }}%</p>
            </div>
            <div>
              <p class="text-xs text-zinc-500 dark:text-zinc-400">Paid up</p>
              <p class="text-sm font-semibold tabular-nums text-emerald-700">{{ data.portfolio.clients_paid_up }}</p>
            </div>
          </div>
        </DashboardPanel>

        <DashboardPanel
          title="Monthly collections"
          :subtitle="`${data.collections.current_month_label} vs ${data.collections.previous_month_label}`"
          action-to="/sales/payments"
        >
          <div class="dashboard-collections">
            <div class="dashboard-collections-card border-indigo-100 bg-indigo-50/40">
              <p class="text-xs font-medium uppercase tracking-wide text-indigo-600">This month</p>
              <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                {{ formatMoney(data.collections.current_month, 'sales') }}
              </p>
              <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ data.collections.payment_count_current_month }} payments</p>
            </div>
            <div class="dashboard-collections-card dashboard-collections-card-muted">
              <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Last month</p>
              <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">
                {{ formatMoney(data.collections.previous_month, 'sales') }}
              </p>
              <p
                v-if="data.collections.change_percent !== null"
                class="mt-1 text-xs font-medium"
                :class="data.collections.change_percent >= 0 ? 'text-emerald-700' : 'text-red-700'"
              >
                {{ data.collections.change_percent >= 0 ? '+' : '' }}{{ data.collections.change_percent }}% change
              </p>
            </div>
          </div>
        </DashboardPanel>
      </div>

      <div class="dashboard-grid-2">
        <DashboardPanel title="Top outstanding clients" subtitle="Prioritize follow-up collections" action-to="/sales/clients">
          <div v-if="data.top_outstanding.length === 0" class="empty-state">All clients are fully paid up.</div>
          <ul v-else class="dashboard-list">
            <li v-for="client in data.top_outstanding" :key="client.client_id" class="dashboard-list-item">
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ client.client_name }}</p>
                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                  {{ client.building_name }} · Unit {{ client.unit_label }}
                </p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500"
                    :style="{ width: `${client.paid_percent}%` }"
                  />
                </div>
                <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ client.paid_percent }}% paid</p>
              </div>
              <div class="text-right">
                <p class="font-semibold tabular-nums text-amber-700">{{ formatMoney(client.balance, 'sales') }}</p>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">of {{ formatMoney(client.agreed_sale_price, 'sales') }}</p>
              </div>
            </li>
          </ul>
        </DashboardPanel>

        <DashboardPanel title="Recent payments" subtitle="Latest buyer collections" action-to="/sales/payments">
          <div v-if="data.recent_payments.length === 0" class="empty-state">No payments recorded yet.</div>
          <ul v-else class="dashboard-list">
            <li v-for="payment in data.recent_payments" :key="payment.payment_id" class="dashboard-list-item">
              <div class="min-w-0">
                <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ payment.client_name }}</p>
                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                  {{ payment.building_name }} · {{ payment.paid_at }}
                  <span v-if="payment.invoice_reference"> · {{ payment.invoice_reference }}</span>
                </p>
              </div>
              <p class="font-semibold tabular-nums text-emerald-700">{{ formatMoney(payment.amount, 'sales') }}</p>
            </li>
          </ul>
        </DashboardPanel>
      </div>

      <div class="dashboard-grid-2">
        <DashboardPanel title="Available inventory" subtitle="Highest-value units ready to sell" action-to="/sales/units">
          <div v-if="data.available_inventory.length === 0" class="empty-state">No available units in inventory.</div>
          <ul v-else class="space-y-2">
            <li
              v-for="unit in data.available_inventory"
              :key="unit.unit_id"
              class="sales-dashboard-inventory-card"
            >
              <div class="min-w-0">
                <p class="font-medium text-zinc-900 dark:text-zinc-100">Unit {{ unit.house_number }} · Floor {{ unit.floor }}</p>
                <p class="truncate text-xs text-zinc-600 dark:text-zinc-400">{{ unit.building_name }} · {{ unit.description }}</p>
              </div>
              <p class="shrink-0 font-semibold tabular-nums text-emerald-800">
                {{ formatMoney(unit.list_price, 'sales') }}
              </p>
            </li>
          </ul>
        </DashboardPanel>

        <DashboardPanel title="Recent registrations" subtitle="New buyers onboarded" action-to="/sales/clients">
          <div v-if="data.recent_registrations.length === 0" class="empty-state">No client registrations yet.</div>
          <ul v-else class="dashboard-list">
            <li v-for="client in data.recent_registrations" :key="client.client_id" class="dashboard-list-item">
              <div class="min-w-0">
                <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ client.client_name }}</p>
                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                  {{ client.building_name }} · Unit {{ client.unit_label }} · {{ client.registration_date }}
                </p>
              </div>
              <div class="text-right">
                <p class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                  {{ formatMoney(client.agreed_sale_price, 'sales') }}
                </p>
                <p
                  class="text-[11px] tabular-nums"
                  :class="Number(client.balance) > 0 ? 'text-amber-700' : 'text-emerald-700'"
                >
                  {{ Number(client.balance) > 0 ? formatMoney(client.balance, 'sales') + ' due' : 'Paid up' }}
                </p>
              </div>
            </li>
          </ul>
        </DashboardPanel>
      </div>

      <DashboardPanel
        title="Building performance"
        subtitle="Inventory, sales value, and collections by property"
        action-to="/sales/buildings"
        class="mb-5"
      >
        <div v-if="data.building_summary.length === 0" class="empty-state">No buildings configured.</div>
        <div v-else class="overflow-x-auto">
          <table class="dashboard-table">
            <thead>
              <tr>
                <th>Building</th>
                <th class="text-right">Available</th>
                <th class="text-right">Sold</th>
                <th class="text-right">Sell-through</th>
                <th class="text-right">Agreed value</th>
                <th class="text-right">Collected</th>
                <th class="text-right">Outstanding</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="building in data.building_summary" :key="building.building_id">
                <td class="font-medium text-zinc-900 dark:text-zinc-100">{{ building.building_name }}</td>
                <td class="text-right tabular-nums">{{ building.available_units }}</td>
                <td class="text-right tabular-nums">{{ building.sold_units }}</td>
                <td class="text-right tabular-nums">{{ building.sell_through_rate }}%</td>
                <td class="text-right tabular-nums">{{ formatMoney(building.agreed_sale_value, 'sales') }}</td>
                <td class="text-right tabular-nums text-emerald-700">
                  {{ formatMoney(building.collected_total, 'sales') }}
                </td>
                <td
                  class="text-right tabular-nums font-medium"
                  :class="Number(building.outstanding_balance) > 0 ? 'text-amber-700' : 'text-zinc-600 dark:text-zinc-400'"
                >
                  {{ formatMoney(building.outstanding_balance, 'sales') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </DashboardPanel>
    </template>

    <div v-else-if="loading" class="dashboard-loading">
      <p class="text-sm text-zinc-500 dark:text-zinc-400">Loading dashboard…</p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import DashboardMetricCard from '../../components/dashboard/DashboardMetricCard.vue'
import DashboardPanel from '../../components/dashboard/DashboardPanel.vue'
import { fetchDashboard } from '../../api/sales'
import { formatMoney } from '../../utils/money'

const data = ref(null)
const loading = ref(false)
const error = ref('')

const pipelineMax = computed(() => {
  if (!data.value?.pipeline) return 1

  return Math.max(
    Number(data.value.pipeline.available_list_value) || 0,
    Number(data.value.pipeline.agreed_sale_value) || 0,
    Number(data.value.pipeline.collected_total) || 0,
    1,
  )
})

function pipelinePercent(amount) {
  return Math.max(4, Math.round((Number(amount || 0) / pipelineMax.value) * 100))
}

function formatRelativeTime(iso) {
  const date = new Date(iso)
  const diff = Date.now() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return date.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
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
