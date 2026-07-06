<template>
  <section>
    <PageHeader
      title="Sales reports"
      subtitle="Balances, income, and cancelled records."
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: 'Reports' }]"
    >
      <template #actions>
        <button
          type="button"
          class="btn-secondary w-full sm:w-auto"
          :disabled="loading || !hasReportData"
          @click="exportCsv"
        >
          Export CSV
        </button>
        <button
          type="button"
          class="btn-secondary w-full sm:w-auto"
          :disabled="loading || !hasReportData"
          @click="printReport"
        >
          Print
        </button>
      </template>
    </PageHeader>

    <FilterBar>
      <select v-model="tab" class="input-field" @change="load">
        <option value="balance">Balance report</option>
        <option value="income">Income statement</option>
        <option value="payment-history">Payment history</option>
        <option value="cancelled-clients">Cancelled clients</option>
        <option value="cancelled-payments">Cancelled payments</option>
      </select>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />
      <template v-if="tab === 'balance'">
        <label class="flex min-h-11 items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
          <input v-model="filters.outstanding_only" type="checkbox" class="h-4 w-4 rounded border-zinc-300" @change="load" />
          Outstanding only
        </label>
      </template>
      <template v-else-if="tab === 'income' || tab === 'payment-history' || tab === 'cancelled-clients' || tab === 'cancelled-payments'">
        <DateRangeFilter
          v-model:from="filters.from"
          v-model:to="filters.to"
          @change="load"
        />
      </template>
      <label
        v-if="tab === 'payment-history'"
        class="flex min-h-11 items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400"
      >
        <input v-model="filters.include_cancelled" type="checkbox" class="h-4 w-4 rounded border-zinc-300" @change="load" />
        Include cancelled payments
      </label>
    </FilterBar>

    <div id="print-area" class="content-panel">
    <TableSkeleton v-if="loading" :rows="8" :columns="5" />

    <div v-else-if="tab === 'balance' && balanceReport" class="space-y-4 p-4 sm:p-5">
      <KpiStrip>
        <KpiCard
          label="Total sale price"
          :value="formatMoney(balanceReport.totals.agreed_sale_price, 'sales')"
          accent="neutral"
        />
        <KpiCard
          label="Total paid"
          :value="formatMoney(balanceReport.totals.paid_total, 'sales')"
          accent="success"
        />
        <KpiCard
          label="Outstanding"
          :value="formatMoney(balanceReport.totals.balance, 'sales')"
          accent="warning"
        />
      </KpiStrip>
      <DataTable
        searchable
        :items="balanceReport.rows"
        :columns="balanceColumns"
        money-module="sales"
        empty-message="No clients in this report."
      >
        <template #card-title-client_name="{ item }">
          <ClientNameLink
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
        </template>
        <template #cell-client_name="{ item }">
          <ClientNameLink
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
        </template>
        <template #cell-agreed_sale_price="{ item }">
          <MoneyCell :amount="item.agreed_sale_price" module="sales" />
        </template>
        <template #cell-paid_total="{ item }">
          <MoneyCell :amount="item.paid_total" module="sales" />
        </template>
        <template #cell-balance="{ item }">
          <MoneyCell :amount="item.balance" module="sales" />
        </template>
      </DataTable>
    </div>

    <div v-else-if="tab === 'income' && incomeReport" class="space-y-4 p-4 sm:p-5">
      <KpiStrip>
        <KpiCard
          label="Income"
          :value="formatMoney(incomeReport.income_total, 'sales')"
          accent="success"
        />
        <KpiCard
          label="Expenses"
          :value="formatMoney(incomeReport.expense_total, 'sales')"
          accent="neutral"
        />
        <KpiCard
          label="Net"
          :value="formatMoney(incomeReport.net_balance, 'sales')"
          :accent="Number(incomeReport.net_balance) >= 0 ? 'success' : 'neutral'"
        />
      </KpiStrip>
      <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Payments</h3>
      <DataTable
        searchable
        :items="incomeReport.payments"
        :columns="paymentColumns"
        money-module="sales"
        empty-message="No payments in range."
      >
        <template #card-title-client_name="{ item }">
          <ClientNameLink
            v-if="item.client_id"
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.sale_building_id"
          />
          <span v-else>{{ item.client_name }}</span>
        </template>
        <template #cell-client_name="{ item }">
          <ClientNameLink
            v-if="item.client_id"
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.sale_building_id"
          />
          <span v-else>{{ item.client_name }}</span>
        </template>
        <template #cell-paid_at="{ item }">
          <DateCell :value="item.paid_at" />
        </template>
        <template #cell-amount="{ item }">
          <MoneyCell :amount="item.amount" module="sales" />
        </template>
      </DataTable>
      <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Expenses</h3>
      <DataTable
        searchable
        :items="incomeReport.expenses"
        :columns="expenseColumns"
        money-module="sales"
        empty-message="No expenses in range."
      >
        <template #cell-expense_date="{ item }">
          <DateCell :value="item.expense_date" />
        </template>
        <template #cell-amount="{ item }">
          <MoneyCell :amount="item.amount" module="sales" />
        </template>
      </DataTable>
    </div>

    <div v-else-if="tab === 'payment-history' && paymentHistoryReport" class="space-y-4 p-4 sm:p-5">
      <KpiStrip>
        <KpiCard
          label="Payments"
          :value="String(paymentHistoryReport.totals.payments)"
          accent="neutral"
        />
        <KpiCard
          label="Amount collected"
          :value="formatMoney(paymentHistoryReport.totals.amount, 'sales')"
          accent="success"
        />
        <KpiCard
          label="Total credited"
          :value="formatMoney(paymentHistoryReport.totals.credited_total, 'sales')"
          accent="warning"
        />
      </KpiStrip>
      <DataTable
        searchable
        :items="paymentHistoryReport.rows"
        :columns="paymentHistoryColumns"
        money-module="sales"
        empty-message="No payments in this report."
      >
        <template #card-title-client_name="{ item }">
          <ClientNameLink
            v-if="item.client_id"
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
          <span v-else>{{ item.client_name }}</span>
        </template>
        <template #cell-client_name="{ item }">
          <ClientNameLink
            v-if="item.client_id"
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
          <span v-else>{{ item.client_name }}</span>
        </template>
        <template #cell-amount="{ item }">
          <MoneyCell :amount="item.amount" module="sales" />
        </template>
        <template #cell-paid_at="{ item }">
          <DateCell :value="item.paid_at" />
        </template>
      </DataTable>
    </div>

    <div v-else-if="tab === 'cancelled-clients' && cancelledClientsReport" class="space-y-4 p-4 sm:p-5">
      <KpiStrip>
        <KpiCard
          label="Disabled clients"
          :value="String(cancelledClientsReport.totals.clients)"
          accent="neutral"
        />
        <KpiCard
          label="Total sale price"
          :value="formatMoney(cancelledClientsReport.totals.agreed_sale_price, 'sales')"
          accent="neutral"
        />
        <KpiCard
          label="Historical paid"
          :value="formatMoney(cancelledClientsReport.totals.historical_paid_total, 'sales')"
          accent="warning"
        />
      </KpiStrip>
      <DataTable
        searchable
        :items="cancelledClientsReport.rows"
        :columns="cancelledClientColumns"
        money-module="sales"
        empty-message="No disabled clients in this report."
      >
        <template #card-title-client_name="{ item }">
          <ClientNameLink
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
        </template>
        <template #cell-client_name="{ item }">
          <ClientNameLink
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
        </template>
        <template #cell-agreed_sale_price="{ item }">
          <MoneyCell :amount="item.agreed_sale_price" module="sales" />
        </template>
        <template #cell-historical_paid_total="{ item }">
          <MoneyCell :amount="item.historical_paid_total" module="sales" />
        </template>
        <template #cell-registration_date="{ item }">
          <DateCell :value="item.registration_date" />
        </template>
        <template #cell-disabled_at="{ item }">
          <DateCell :value="item.disabled_at" />
        </template>
      </DataTable>
    </div>

    <div v-else-if="tab === 'cancelled-payments' && cancelledPaymentsReport" class="space-y-4 p-4 sm:p-5">
      <KpiStrip>
        <KpiCard
          label="Cancelled payments"
          :value="String(cancelledPaymentsReport.totals.payments)"
          accent="neutral"
        />
        <KpiCard
          label="Amount"
          :value="formatMoney(cancelledPaymentsReport.totals.amount, 'sales')"
          accent="warning"
        />
        <KpiCard
          label="Total credited"
          :value="formatMoney(cancelledPaymentsReport.totals.credited_total, 'sales')"
          accent="neutral"
        />
      </KpiStrip>
      <DataTable
        searchable
        :items="cancelledPaymentsReport.rows"
        :columns="cancelledPaymentColumns"
        money-module="sales"
        empty-message="No cancelled payments in this report."
      >
        <template #card-title-client_name="{ item }">
          <ClientNameLink
            v-if="item.client_id"
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
          <span v-else>{{ item.client_name }}</span>
        </template>
        <template #cell-client_name="{ item }">
          <ClientNameLink
            v-if="item.client_id"
            :client-id="item.client_id"
            :client-name="item.client_name"
            :building-id="item.building_id"
          />
          <span v-else>{{ item.client_name }}</span>
        </template>
        <template #cell-amount="{ item }">
          <MoneyCell :amount="item.amount" module="sales" />
        </template>
        <template #cell-paid_at="{ item }">
          <DateCell :value="item.paid_at" />
        </template>
        <template #cell-cancelled_at="{ item }">
          <DateCell :value="item.cancelled_at" />
        </template>
      </DataTable>
    </div>

    <div v-else-if="!loading" class="p-4 sm:p-5">
      <EmptyState
        title="No report data"
        description="Adjust filters above or try a different date range."
      />
    </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import DateRangeFilter from '../../components/ui/DateRangeFilter.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import KpiCard from '../../components/ui/KpiCard.vue'
import KpiStrip from '../../components/ui/KpiStrip.vue'
import DataTable from '../../components/data/DataTable.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import TableSkeleton from '../../components/data/TableSkeleton.vue'
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { useToast } from '../../composables/useToast'
import { formatMoney } from '../../utils/money'
import {
  formatReportDate,
  moneyColumn,
  printSalesReport,
} from '../../utils/salesReportPrint'
import {
  downloadSalesReportCsv,
  fetchBalanceReport,
  fetchBuildings,
  fetchCancelledClientsReport,
  fetchCancelledPaymentsReport,
  fetchIncomeStatement,
  fetchPaymentHistoryReport,
} from '../../api/sales'

const toast = useToast()

const tab = ref('balance')
const buildings = ref([])
const balanceReport = ref(null)
const incomeReport = ref(null)
const cancelledClientsReport = ref(null)
const cancelledPaymentsReport = ref(null)
const paymentHistoryReport = ref(null)
const loading = ref(false)
const filters = reactive({
  building_id: '',
  outstanding_only: false,
  from: '',
  to: '',
  include_cancelled: false,
})

const reportTitles = {
  balance: 'Balance report',
  income: 'Income statement',
  'payment-history': 'Payment history',
  'cancelled-clients': 'Cancelled clients',
  'cancelled-payments': 'Cancelled payments',
}

const reportTitle = computed(() => reportTitles[tab.value] || 'Sales report')

const reportSubtitle = computed(() => {
  const parts = []
  if (filters.building_id) {
    const building = buildings.value.find((b) => String(b.id) === String(filters.building_id))
    if (building) parts.push(building.name)
  }
  if (filters.from || filters.to) {
    const range = [filters.from, filters.to].filter(Boolean).join(' – ')
    parts.push(range)
  }
  return parts.join(' · ')
})

const balanceColumns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'unit_label', label: 'Unit', mobileCard: true },
  { key: 'agreed_sale_price', label: 'Sale price', align: 'right', money: true },
  { key: 'paid_total', label: 'Paid', align: 'right', money: true, mobileCard: true },
  { key: 'balance', label: 'Balance', align: 'right', money: true, mobileCard: true },
]

const paymentColumns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true },
  { key: 'paid_at', label: 'Date' },
]

const expenseColumns = [
  { key: 'name', label: 'Expense', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true },
  { key: 'expense_date', label: 'Date' },
]

const paymentHistoryColumns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'unit_label', label: 'Unit', mobileCard: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true },
  { key: 'invoice_reference', label: 'Reference', tabletCard: true },
  { key: 'bank', label: 'Bank' },
  { key: 'paid_at', label: 'Date' },
  { key: 'status', label: 'Status', mobileCard: true },
]

const cancelledClientColumns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'unit_label', label: 'Unit', mobileCard: true },
  { key: 'agreed_sale_price', label: 'Sale price', align: 'right', money: true },
  { key: 'historical_paid_total', label: 'Historical paid', align: 'right', money: true, mobileCard: true },
  { key: 'cancelled_payment_count', label: 'Cancelled payments', align: 'right' },
  { key: 'disabled_at', label: 'Disabled' },
]

const cancelledPaymentColumns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true },
  { key: 'invoice_reference', label: 'Reference', mobileCard: true },
  { key: 'paid_at', label: 'Paid' },
  { key: 'cancelled_at', label: 'Cancelled' },
  { key: 'cancelled_by_name', label: 'By' },
]

const reportPaths = {
  balance: 'balance',
  income: 'income-statement',
  'payment-history': 'payment-history',
  'cancelled-clients': 'cancelled-clients',
  'cancelled-payments': 'cancelled-payments',
}

const reportFilenames = {
  balance: 'sales-balance.csv',
  income: 'sales-income-statement.csv',
  'payment-history': 'sales-payment-history.csv',
  'cancelled-clients': 'sales-cancelled-clients.csv',
  'cancelled-payments': 'sales-cancelled-payments.csv',
}

const hasReportData = computed(() => {
  if (tab.value === 'balance') {
    return Boolean(balanceReport.value?.rows?.length)
  }

  if (tab.value === 'income') {
    return Boolean(
      incomeReport.value?.payments?.length || incomeReport.value?.expenses?.length,
    )
  }

  if (tab.value === 'cancelled-clients') {
    return Boolean(cancelledClientsReport.value?.rows?.length)
  }

  if (tab.value === 'payment-history') {
    return Boolean(paymentHistoryReport.value?.rows?.length)
  }

  return Boolean(cancelledPaymentsReport.value?.rows?.length)
})

function buildParams() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id

  if (tab.value === 'balance') {
    if (filters.outstanding_only) params.outstanding_only = 1
  } else {
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to
    if (tab.value === 'payment-history' && filters.include_cancelled) {
      params.include_cancelled = 1
    }
  }

  return params
}

async function exportCsv() {
  try {
    await downloadSalesReportCsv(reportPaths[tab.value], buildParams(), reportFilenames[tab.value])
  } catch {
    toast.error('Could not export report.')
  }
}

function printReport() {
  const title = reportTitle.value
  const subtitle = reportSubtitle.value

  if (tab.value === 'balance' && balanceReport.value) {
    printSalesReport({
      title,
      subtitle,
      summaries: [
        { label: 'Total sale price', value: formatMoney(balanceReport.value.totals.agreed_sale_price, 'sales') },
        { label: 'Total paid', value: formatMoney(balanceReport.value.totals.paid_total, 'sales') },
        { label: 'Outstanding', value: formatMoney(balanceReport.value.totals.balance, 'sales') },
      ],
      sections: [{
        columns: [
          { key: 'client_name', label: 'Client' },
          { key: 'unit_label', label: 'Unit' },
          moneyColumn('agreed_sale_price', 'Sale price'),
          moneyColumn('paid_total', 'Paid'),
          moneyColumn('balance', 'Balance'),
        ],
        rows: balanceReport.value.rows,
      }],
    })
    return
  }

  if (tab.value === 'income' && incomeReport.value) {
    printSalesReport({
      title,
      subtitle,
      summaries: [
        { label: 'Income', value: formatMoney(incomeReport.value.income_total, 'sales') },
        { label: 'Expenses', value: formatMoney(incomeReport.value.expense_total, 'sales') },
        { label: 'Net', value: formatMoney(incomeReport.value.net_balance, 'sales') },
      ],
      sections: [
        {
          title: 'Payments',
          columns: [
            { key: 'client_name', label: 'Client' },
            moneyColumn('amount', 'Amount'),
            { key: 'paid_at', label: 'Date', format: (row) => formatReportDate(row.paid_at) },
          ],
          rows: incomeReport.value.payments,
        },
        {
          title: 'Expenses',
          columns: [
            { key: 'name', label: 'Expense' },
            moneyColumn('amount', 'Amount'),
            { key: 'expense_date', label: 'Date', format: (row) => formatReportDate(row.expense_date) },
          ],
          rows: incomeReport.value.expenses,
        },
      ],
    })
    return
  }

  if (tab.value === 'payment-history' && paymentHistoryReport.value) {
    printSalesReport({
      title,
      subtitle,
      summaries: [
        { label: 'Payments', value: String(paymentHistoryReport.value.totals.payments) },
        { label: 'Amount collected', value: formatMoney(paymentHistoryReport.value.totals.amount, 'sales') },
        { label: 'Total credited', value: formatMoney(paymentHistoryReport.value.totals.credited_total, 'sales') },
      ],
      sections: [{
        columns: [
          { key: 'client_name', label: 'Client' },
          { key: 'unit_label', label: 'Unit' },
          moneyColumn('amount', 'Amount'),
          { key: 'invoice_reference', label: 'Reference' },
          { key: 'bank', label: 'Bank' },
          { key: 'paid_at', label: 'Date', format: (row) => formatReportDate(row.paid_at) },
          { key: 'status', label: 'Status' },
        ],
        rows: paymentHistoryReport.value.rows,
      }],
    })
    return
  }

  if (tab.value === 'cancelled-clients' && cancelledClientsReport.value) {
    printSalesReport({
      title,
      subtitle,
      summaries: [
        { label: 'Disabled clients', value: String(cancelledClientsReport.value.totals.clients) },
        { label: 'Total sale price', value: formatMoney(cancelledClientsReport.value.totals.agreed_sale_price, 'sales') },
        { label: 'Historical paid', value: formatMoney(cancelledClientsReport.value.totals.historical_paid_total, 'sales') },
      ],
      sections: [{
        columns: [
          { key: 'client_name', label: 'Client' },
          { key: 'unit_label', label: 'Unit' },
          moneyColumn('agreed_sale_price', 'Sale price'),
          moneyColumn('historical_paid_total', 'Historical paid'),
          { key: 'cancelled_payment_count', label: 'Cancelled payments', align: 'right' },
          { key: 'disabled_at', label: 'Disabled', format: (row) => formatReportDate(row.disabled_at) },
        ],
        rows: cancelledClientsReport.value.rows,
      }],
    })
    return
  }

  if (tab.value === 'cancelled-payments' && cancelledPaymentsReport.value) {
    printSalesReport({
      title,
      subtitle,
      summaries: [
        { label: 'Cancelled payments', value: String(cancelledPaymentsReport.value.totals.payments) },
        { label: 'Amount', value: formatMoney(cancelledPaymentsReport.value.totals.amount, 'sales') },
        { label: 'Total credited', value: formatMoney(cancelledPaymentsReport.value.totals.credited_total, 'sales') },
      ],
      sections: [{
        columns: [
          { key: 'client_name', label: 'Client' },
          moneyColumn('amount', 'Amount'),
          { key: 'invoice_reference', label: 'Reference' },
          { key: 'paid_at', label: 'Paid', format: (row) => formatReportDate(row.paid_at) },
          { key: 'cancelled_at', label: 'Cancelled', format: (row) => formatReportDate(row.cancelled_at) },
          { key: 'cancelled_by_name', label: 'By' },
        ],
        rows: cancelledPaymentsReport.value.rows,
      }],
    })
  }
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function load() {
  loading.value = true
  balanceReport.value = null
  incomeReport.value = null
  cancelledClientsReport.value = null
  cancelledPaymentsReport.value = null
  paymentHistoryReport.value = null
  try {
    const params = buildParams()

    if (tab.value === 'balance') {
      balanceReport.value = await fetchBalanceReport(params)
    } else if (tab.value === 'income') {
      incomeReport.value = await fetchIncomeStatement(params)
    } else if (tab.value === 'payment-history') {
      paymentHistoryReport.value = await fetchPaymentHistoryReport(params)
    } else if (tab.value === 'cancelled-clients') {
      cancelledClientsReport.value = await fetchCancelledClientsReport(params)
    } else {
      cancelledPaymentsReport.value = await fetchCancelledPaymentsReport(params)
    }
  } catch {
    toast.error('Could not load report.')
  } finally {
    loading.value = false
  }
}

watch(tab, load)

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
