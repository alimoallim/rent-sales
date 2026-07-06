<template>
  <section>
    <PageHeader
      title="Reports"
      subtitle="Balances, payments, charges, and income statement."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Reports' }]"
    >
      <template #actions>
        <button
          v-if="reportType !== 'income-statement'"
          type="button"
          class="btn-secondary w-full sm:w-auto"
          :disabled="loading || !report"
          @click="exportCsv"
        >
          Export CSV
        </button>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="printReport">
          Print
        </button>
      </template>
    </PageHeader>

    <FilterBar>
      <select v-model="reportType" class="input-field" @change="load">
        <option value="tenant-balances">Tenant balances</option>
        <option value="payment-history">Payment history</option>
        <option value="charge-summary">Charge summary</option>
        <option value="arrears-aging">Arrears aging</option>
        <option value="income-statement">Income statement</option>
      </select>

      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />

      <template v-if="reportType === 'payment-history'">
        <input v-model="filters.from" type="date" class="input-field" @change="load" />
        <input v-model="filters.to" type="date" class="input-field" @change="load" />
        <label class="flex min-h-11 items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
          <input v-model="filters.include_voided" type="checkbox" class="h-4 w-4 rounded border-zinc-300" @change="load" />
          Include deleted payments
        </label>
      </template>

      <template v-if="reportType === 'tenant-balances' || reportType === 'arrears-aging'">
        <label class="flex min-h-11 items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
          <input v-model="filters.outstanding_only" type="checkbox" class="h-4 w-4 rounded border-zinc-300" @change="load" />
          Outstanding only
        </label>
      </template>

      <template v-if="reportType === 'charge-summary' || reportType === 'income-statement'">
        <select v-model="filters.billing_month" class="input-field" @change="load">
          <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
        </select>
        <input v-model="filters.billing_year" type="number" min="2000" class="input-field w-full sm:w-28" @change="load" />
      </template>

      <template v-if="reportType === 'income-statement'">
        <label class="flex min-h-11 items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
          <input v-model="filters.legacy_income_mode" type="checkbox" class="h-4 w-4 rounded border-zinc-300" @change="load" />
          Legacy calculation (match old system)
        </label>
      </template>
    </FilterBar>

    <p v-if="error" class="alert-error mb-3">{{ error }}</p>

    <div id="print-area" class="content-panel">
      <TableSkeleton v-if="loading" :rows="8" :columns="5" />

      <template v-else-if="reportType === 'income-statement'">
        <IncomeStatementReport
          v-if="incomeStatement"
          :statement="incomeStatement"
          :building-name="selectedBuildingName"
          @export="exportIncomeCsv"
        />
        <EmptyState
          v-else-if="!filters.building_id"
          title="Select a building"
          description="Choose a building above to generate the income statement for the selected period."
        />
        <EmptyState
          v-else
          title="No income statement data"
          description="No data available for the selected building and billing period."
        />
      </template>

      <template v-else>
        <KpiStrip v-if="reportType === 'arrears-aging' && report?.totals" class="mb-4">
          <KpiCard
            label="Total outstanding"
            :value="formatMoney(report.totals.total_balance, 'rental')"
            accent="warning"
          />
          <KpiCard
            label="Current (0–30 days)"
            :value="formatMoney(report.totals.current, 'rental')"
            accent="neutral"
          />
          <KpiCard
            label="31–60 days"
            :value="formatMoney(report.totals.days_31_60, 'rental')"
            accent="warning"
          />
          <KpiCard
            label="61–90 days"
            :value="formatMoney(report.totals.days_61_90, 'rental')"
            accent="danger"
          />
          <KpiCard
            label="90+ days"
            :value="formatMoney(report.totals.days_90_plus, 'rental')"
            accent="danger"
          />
        </KpiStrip>

        <DataTable
          searchable
          :items="tableRows"
          :columns="tableColumns"
          row-key="tenant_name"
          money-module="rental"
          empty-message="No data for selected filters."
          :footer-label="tableTotals ? 'Totals' : ''"
          :footer-value="tableTotals ? formatMoney(tableTotals, 'rental') : ''"
        >
        <template #cell-paid_at="{ item }">
          <DateCell :value="item.paid_at" />
        </template>
        <template #cell-balance="{ item }">
          <MoneyCell :amount="item.balance" module="rental" />
        </template>
        <template #cell-amount="{ item }">
          <MoneyCell :amount="item.amount" module="rental" />
        </template>
        <template #cell-discount="{ item }">
          <MoneyCell :amount="item.discount" module="rental" />
        </template>
        <template #cell-monthly_rent="{ item }">
          <MoneyCell :amount="item.monthly_rent" module="rental" />
        </template>
        <template #cell-service_amount="{ item }">
          <MoneyCell :amount="item.service_amount" module="rental" />
        </template>
        <template #cell-charged_amount="{ item }">
          <MoneyCell :amount="item.charged_amount" module="rental" />
        </template>
        <template #cell-paid_amount="{ item }">
          <MoneyCell :amount="item.paid_amount" module="rental" />
        </template>
        <template #cell-total_amount="{ item }">
          <MoneyCell :amount="item.total_amount" module="rental" />
        </template>
        <template #cell-total_balance="{ item }">
          <MoneyCell :amount="item.total_balance" module="rental" />
        </template>
        <template #cell-current="{ item }">
          <MoneyCell :amount="item.current" module="rental" />
        </template>
        <template #cell-days_31_60="{ item }">
          <MoneyCell :amount="item.days_31_60" module="rental" />
        </template>
        <template #cell-days_61_90="{ item }">
          <MoneyCell :amount="item.days_61_90" module="rental" />
        </template>
        <template #cell-days_90_plus="{ item }">
          <MoneyCell :amount="item.days_90_plus" module="rental" />
        </template>
        <template #cell-rent_amount="{ item }">
          <MoneyCell :amount="item.rent_amount" module="rental" />
        </template>
      </DataTable>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import KpiCard from '../../components/ui/KpiCard.vue'
import KpiStrip from '../../components/ui/KpiStrip.vue'
import DataTable from '../../components/data/DataTable.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import TableSkeleton from '../../components/data/TableSkeleton.vue'
import IncomeStatementReport from '../../components/rental/IncomeStatementReport.vue'
import { useToast } from '../../composables/useToast'
import {
  downloadReportCsv,
  fetchArrearsAgingReport,
  fetchBuildings,
  fetchChargeSummaryReport,
  fetchIncomeStatementReport,
  fetchPaymentHistoryReport,
  fetchTenantBalancesReport,
} from '../../api/rental'
import { formatMoney } from '../../utils/money'

const toast = useToast()

const buildings = ref([])
const reportType = ref('tenant-balances')
const report = ref(null)
const incomeStatement = ref(null)
const loading = ref(false)
const error = ref('')
const now = new Date()
const filters = reactive({
  building_id: '',
  from: '',
  to: '',
  outstanding_only: true,
  include_voided: false,
  legacy_income_mode: false,
  billing_month: now.getMonth() + 1,
  billing_year: now.getFullYear(),
})

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const columnSets = {
  'tenant-balances': [
    { key: 'tenant_name', label: 'Tenant', cardTitle: true },
    { key: 'balance', label: 'Balance', align: 'right', money: true, mobileCard: true },
    { key: 'building_name', label: 'Building', mobileCard: true },
    { key: 'unit_label', label: 'Unit', tabletCard: true },
    { key: 'monthly_rent', label: 'Rent', align: 'right', money: true, tabletCard: true },
    { key: 'service_amount', label: 'Service', align: 'right', money: true, tabletCard: true },
    { key: 'charge_count', label: 'Periods', align: 'right', tabletCard: true },
    { key: 'charged_amount', label: 'Charged', align: 'right', money: true, tabletCard: true },
    { key: 'paid_amount', label: 'Paid', align: 'right', money: true, tabletCard: true },
  ],
  'payment-history': [
    { key: 'paid_at', label: 'Date', mobileCard: true },
    { key: 'tenant_name', label: 'Tenant', cardTitle: true },
    { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
    { key: 'building_name', label: 'Building', tabletCard: true },
    { key: 'invoice_reference', label: 'Invoice', tabletCard: true },
    { key: 'discount', label: 'Discount', align: 'right', money: true, tabletCard: true },
    {
      key: 'status',
      label: 'Status',
      mobileCard: true,
      format: (item) => (item.status === 'voided' ? 'deleted' : item.status),
    },
  ],
  'charge-summary': [
    { key: 'tenant_name', label: 'Tenant', cardTitle: true },
    { key: 'total_amount', label: 'Total', align: 'right', money: true, mobileCard: true },
    { key: 'period', label: 'Period', mobileCard: true },
    { key: 'building_name', label: 'Building', tabletCard: true },
    { key: 'unit_label', label: 'Unit', tabletCard: true },
    { key: 'rent_amount', label: 'Rent', align: 'right', money: true, tabletCard: true },
    { key: 'service_amount', label: 'Service', align: 'right', money: true, tabletCard: true },
  ],
  'arrears-aging': [
    { key: 'tenant_name', label: 'Tenant', cardTitle: true },
    { key: 'total_balance', label: 'Total', align: 'right', money: true, mobileCard: true },
    { key: 'building_name', label: 'Building', mobileCard: true },
    { key: 'unit_label', label: 'Unit', tabletCard: true },
    { key: 'current', label: '0–30 days', align: 'right', money: true, tabletCard: true },
    { key: 'days_31_60', label: '31–60', align: 'right', money: true, tabletCard: true },
    { key: 'days_61_90', label: '61–90', align: 'right', money: true, tabletCard: true },
    { key: 'days_90_plus', label: '90+', align: 'right', money: true, mobileCard: true },
    { key: 'oldest_overdue_period', label: 'Oldest period', tabletCard: true },
    { key: 'max_days_overdue', label: 'Max days', align: 'right', tabletCard: true },
  ],
}

const tableColumns = computed(() => columnSets[reportType.value] || [])
const selectedBuildingName = computed(() => {
  if (!filters.building_id) return 'All buildings'
  return buildings.value.find((b) => String(b.id) === String(filters.building_id))?.name || 'Selected building'
})
const tableRows = computed(() => {
  if (!report.value?.rows) return []
  if (reportType.value === 'charge-summary') {
    return report.value.rows.map((row) => ({
      ...row,
      period: `${row.billing_month}/${row.billing_year}`,
    }))
  }
  return report.value.rows
})

const tableTotals = computed(() => {
  if (!report.value?.totals) return null
  if (reportType.value === 'tenant-balances') return report.value.totals.balance
  if (reportType.value === 'payment-history') return report.value.totals.amount
  if (reportType.value === 'charge-summary') return report.value.totals.total_amount
  if (reportType.value === 'arrears-aging') return report.value.totals.total_balance
  return null
})

function buildParams() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id
  if (reportType.value === 'payment-history') {
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to
    if (filters.include_voided) params.include_voided = 1
  }
  if (reportType.value === 'tenant-balances' && filters.outstanding_only) {
    params.outstanding_only = 1
  }
  if (reportType.value === 'arrears-aging' && filters.outstanding_only) {
    params.outstanding_only = 1
  }
  if (reportType.value === 'charge-summary' || reportType.value === 'income-statement') {
    params.billing_month = filters.billing_month
    params.billing_year = filters.billing_year
  }
  if (reportType.value === 'income-statement' && filters.legacy_income_mode) {
    params.mode = 'legacy'
  }
  return params
}

async function load() {
  error.value = ''
  incomeStatement.value = null
  report.value = null

  if (reportType.value === 'income-statement' && !filters.building_id) {
    return
  }

  loading.value = true
  try {
    if (reportType.value === 'income-statement') {
      incomeStatement.value = await fetchIncomeStatementReport(buildParams())
      return
    }

    if (reportType.value === 'tenant-balances') {
      report.value = await fetchTenantBalancesReport(buildParams())
    } else if (reportType.value === 'payment-history') {
      report.value = await fetchPaymentHistoryReport(buildParams())
    } else if (reportType.value === 'arrears-aging') {
      report.value = await fetchArrearsAgingReport(buildParams())
    } else {
      report.value = await fetchChargeSummaryReport(buildParams())
    }
  } catch (e) {
    const message = e.response?.data?.message || 'Could not load report.'
    error.value = message
    toast.error(message)
  } finally {
    loading.value = false
  }
}

async function exportCsv() {
  try {
    await downloadReportCsv(reportType.value, buildParams(), `${reportType.value}.csv`)
    toast.success('Report exported.')
  } catch {
    toast.error('Could not export report.')
  }
}

async function exportIncomeCsv() {
  try {
    await downloadReportCsv('income-statement', buildParams(), 'income-statement.csv')
    toast.success('Income statement exported.')
  } catch {
    toast.error('Could not export income statement.')
  }
}

function printReport() {
  window.print()
}

onMounted(async () => {
  const response = await fetchBuildings()
  buildings.value = response.data
  await load()
})
</script>

<style>
@media print {
  header,
  nav,
  button,
  .filter-bar {
    display: none !important;
  }

  #print-area.content-panel {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>
