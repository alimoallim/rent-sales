<template>
  <section>
    <PageHeader
      title="Sales reports"
      subtitle="Balance outstanding and income vs expenses."
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: 'Reports' }]"
    />

    <FilterBar>
      <div class="segmented-control">
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
      <template v-else>
        <input v-model="filters.from" type="date" class="input-field" @change="load" />
        <input v-model="filters.to" type="date" class="input-field" @change="load" />
      </template>
    </FilterBar>

    <TableSkeleton v-if="loading" :rows="8" :columns="5" />

    <div v-else-if="tab === 'balance' && balanceReport" class="space-y-4">
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

    <div v-else-if="tab === 'income' && incomeReport" class="space-y-4">
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

    <div v-else-if="!loading" class="content-panel">
      <EmptyState
        title="No report data"
        description="Adjust filters above or try a different date range."
      />
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
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
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { useToast } from '../../composables/useToast'
import { formatMoney } from '../../utils/money'
import { fetchBalanceReport, fetchBuildings, fetchIncomeStatement } from '../../api/sales'

const toast = useToast()

const tab = ref('balance')
const buildings = ref([])
const balanceReport = ref(null)
const incomeReport = ref(null)
const loading = ref(false)
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
  { key: 'paid_at', label: 'Date' },
]

const expenseColumns = [
  { key: 'name', label: 'Expense', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true },
  { key: 'expense_date', label: 'Date' },
]

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function load() {
  loading.value = true
  balanceReport.value = null
  incomeReport.value = null
  try {
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
