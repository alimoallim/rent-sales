<template>
  <section>
    <PageHeader title="Sales reports" subtitle="Balance outstanding and income vs expenses." />

    <div class="mb-4 flex flex-wrap gap-2">
      <button
        type="button"
        class="segmented-option"
        :class="{ 'segmented-option-active': tab === 'balance' }"
        @click="tab = 'balance'"
      >
        Balance report
      </button>
      <button
        type="button"
        class="segmented-option"
        :class="{ 'segmented-option-active': tab === 'income' }"
        @click="tab = 'income'"
      >
        Income statement
      </button>
    </div>

    <div class="filter-bar">
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />
      <template v-if="tab === 'balance'">
        <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
          <input v-model="filters.outstanding_only" type="checkbox" class="rounded border-zinc-300" @change="load" />
          Outstanding only
        </label>
      </template>
      <template v-else>
        <input v-model="filters.from" type="date" class="input-field" @change="load" />
        <input v-model="filters.to" type="date" class="input-field" @change="load" />
      </template>
    </div>

    <div v-if="tab === 'balance' && balanceReport" class="space-y-4">
      <div class="grid gap-2 sm:grid-cols-3">
        <div class="stat-card">
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Total sale price</p>
          <p class="text-lg font-semibold tabular-nums">{{ formatMoney(balanceReport.totals.agreed_sale_price, 'sales') }}</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Total paid</p>
          <p class="text-lg font-semibold tabular-nums">{{ formatMoney(balanceReport.totals.paid_total, 'sales') }}</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Outstanding</p>
          <p class="text-lg font-semibold tabular-nums text-amber-700">{{ formatMoney(balanceReport.totals.balance, 'sales') }}</p>
        </div>
      </div>
      <ResponsiveDataList :items="balanceReport.rows" :columns="balanceColumns" money-module="sales" empty-message="No clients in this report.">
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
      </ResponsiveDataList>
    </div>

    <div v-if="tab === 'income' && incomeReport" class="space-y-4">
      <div class="grid gap-2 sm:grid-cols-3">
        <div class="stat-card">
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Income</p>
          <p class="text-lg font-semibold tabular-nums text-emerald-700">{{ formatMoney(incomeReport.income_total, 'sales') }}</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Expenses</p>
          <p class="text-lg font-semibold tabular-nums text-red-700">{{ formatMoney(incomeReport.expense_total, 'sales') }}</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Net</p>
          <p class="text-lg font-semibold tabular-nums">{{ formatMoney(incomeReport.net_balance, 'sales') }}</p>
        </div>
      </div>
      <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Payments</h3>
      <ResponsiveDataList :items="incomeReport.payments" :columns="paymentColumns" money-module="sales" empty-message="No payments in range.">
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
      </ResponsiveDataList>
      <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Expenses</h3>
      <ResponsiveDataList :items="incomeReport.expenses" :columns="expenseColumns" money-module="sales" empty-message="No expenses in range." />
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { formatMoney } from '../../utils/money'
import { fetchBalanceReport, fetchBuildings, fetchIncomeStatement } from '../../api/sales'

const tab = ref('balance')
const buildings = ref([])
const balanceReport = ref(null)
const incomeReport = ref(null)
const filters = reactive({
  building_id: '',
  outstanding_only: false,
  from: '',
  to: '',
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
  { key: 'paid_at', label: 'Date', format: (row) => formatDate(row.paid_at) },
]

const expenseColumns = [
  { key: 'name', label: 'Expense', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true },
  { key: 'expense_date', label: 'Date', format: (row) => formatDate(row.expense_date) },
]

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE')
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function load() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id

  if (tab.value === 'balance') {
    if (filters.outstanding_only) params.outstanding_only = 1
    balanceReport.value = await fetchBalanceReport(params)
  } else {
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to
    incomeReport.value = await fetchIncomeStatement(params)
  }
}

watch(tab, load)

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
