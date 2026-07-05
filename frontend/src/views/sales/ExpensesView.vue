<template>
  <section>
    <PageHeader
      title="Sales expenses"
      subtitle="Building-level costs for the sales module."
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: 'Expenses' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add expense</button>
      </template>
    </PageHeader>

    <FilterBar>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="loadTable"
      />
      <input v-model="filters.from" type="date" class="input-field" @change="loadTable" />
      <input v-model="filters.to" type="date" class="input-field" @change="loadTable" />
    </FilterBar>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      money-module="sales"
      empty-message="No expenses found."
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #cell-expense_date="{ item }">
        <DateCell :value="item.expense_date" />
      </template>
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="sales" />
      </template>
      <template #actions="{ item }">
        <RowActionButton icon="edit" label="Edit" @click="openEdit(item)" />
        <RowActionButton icon="delete" label="Delete" variant="danger" @click="remove(item)" />
      </template>
    </DataTable>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit expense' : 'Add expense'" size="sm" :close-on-backdrop="false">
      <div class="grid gap-4">
        <FormField label="Building" required>
          <BuildingSearchSelect
            v-model="form.sale_building_id"
            :buildings="buildings"
            required
          />
        </FormField>
        <FormField label="Name" required>
          <input v-model="form.name" class="input-field" required />
        </FormField>
        <FormField :label="amountLabel('sales')" required>
          <input v-model="form.amount" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
        <FormField label="Date" required>
          <input v-model="form.expense_date" type="date" class="input-field" required />
        </FormField>
        <FormField label="Description">
          <textarea v-model="form.description" rows="2" class="input-field" />
        </FormField>
      </div>
      <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import FormField from '../../components/ui/FormField.vue'
import DataTable from '../../components/data/DataTable.vue'
import RowActionButton from '../../components/ui/RowActionButton.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import { createExpense, deleteExpense, fetchBuildings, fetchExpenses, updateExpense } from '../../api/sales'
import { amountLabel } from '../../utils/money'

const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', from: '', to: '' })
const form = reactive({
  sale_building_id: '',
  name: '',
  amount: 0,
  expense_date: new Date().toISOString().slice(0, 10),
  description: '',
})

const {
  items,
  loading,
  search,
  pagination,
  load,
  reload,
  goToPage,
  setPerPage,
  onSearchChange,
} = usePaginatedList((params) =>
  fetchExpenses({
    ...params,
    building_id: filters.building_id || undefined,
    from: filters.from || undefined,
    to: filters.to || undefined,
  }),
)

const columns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'expense_date', label: 'Date', mobileCard: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
]

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadTable() {
  await reload()
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    sale_building_id: filters.building_id || '',
    name: '',
    amount: 0,
    expense_date: new Date().toISOString().slice(0, 10),
    description: '',
  })
  error.value = ''
  showForm.value = true
}

function openEdit(expense) {
  editing.value = expense
  Object.assign(form, {
    sale_building_id: expense.sale_building_id,
    name: expense.name,
    amount: expense.amount,
    expense_date: expense.expense_date?.slice(0, 10) || '',
    description: expense.description || '',
  })
  error.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

async function save() {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...form, amount: Number(form.amount) }
    if (editing.value) {
      await updateExpense(editing.value.id, payload)
      toast.success('Expense updated.')
    } else {
      await createExpense(payload)
      toast.success('Expense added.')
    }
    closeForm()
    await reload()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save expense.'
  } finally {
    saving.value = false
  }
}

async function remove(expense) {
  const ok = await confirm({
    title: 'Delete expense',
    message: `Delete expense "${expense.name}"?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteExpense(expense.id)
    toast.success('Expense deleted.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete expense.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
