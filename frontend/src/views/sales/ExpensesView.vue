<template>
  <section>
    <PageHeader title="Sales expenses" subtitle="Building-level costs for the sales module.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add expense</button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
      <input v-model="filters.from" type="date" class="input-field" @change="load" />
      <input v-model="filters.to" type="date" class="input-field" @change="load" />
    </div>

    <ResponsiveDataList :items="expenses" :columns="columns" empty-message="No expenses found.">
      <template #cell-expense_date="{ item }">{{ formatDate(item.expense_date) }}</template>
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="remove(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit expense' : 'Add expense'" size="sm">
      <div class="grid gap-4">
        <label class="label-field">
          Building
          <select v-model="form.sale_building_id" class="input-field" required>
            <option disabled value="">Select building</option>
            <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
          </select>
        </label>
        <label class="label-field">
          Name
          <input v-model="form.name" class="input-field" required />
        </label>
        <label class="label-field">
          Amount (KES)
          <input v-model="form.amount" type="number" min="0" step="0.01" class="input-field" required />
        </label>
        <label class="label-field">
          Date
          <input v-model="form.expense_date" type="date" class="input-field" required />
        </label>
        <label class="label-field">
          Description
          <textarea v-model="form.description" rows="2" class="input-field" />
        </label>
      </div>
      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="save">Save</button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import { createExpense, deleteExpense, fetchBuildings, fetchExpenses, updateExpense } from '../../api/sales'

const buildings = ref([])
const expenses = ref([])
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

const columns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'expense_date', label: 'Date', mobileCard: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
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
  if (filters.from) params.from = filters.from
  if (filters.to) params.to = filters.to
  const response = await fetchExpenses(params)
  expenses.value = response.data
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
  try {
    const payload = { ...form, amount: Number(form.amount) }
    if (editing.value) {
      await updateExpense(editing.value.id, payload)
    } else {
      await createExpense(payload)
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save expense.'
  }
}

async function remove(expense) {
  if (!confirm(`Delete expense "${expense.name}"?`)) return
  try {
    await deleteExpense(expense.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not delete expense.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
