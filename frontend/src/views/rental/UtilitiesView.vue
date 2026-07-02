<template>
  <section>
    <PageHeader title="Building utilities" subtitle="Nairobi Water and electricity bills per building.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          Add bill
        </button>
      </template>
    </PageHeader>

    <div class="alert-info mb-3">
      These are building operating costs. They are not charged to individual tenants.
    </div>

    <div class="filter-bar">
      <div class="segmented-control">
        <button
          type="button"
          class="segmented-option"
          :class="tab === 'nairobi' ? 'segmented-option-active' : 'text-slate-700'"
          @click="setTab('nairobi')"
        >
          Nairobi Water
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="tab === 'electricity' ? 'segmented-option-active' : 'text-slate-700'"
          @click="setTab('electricity')"
        >
          Electricity
        </button>
      </div>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />
    </div>

    <ResponsiveDataList :items="utilityBills" :columns="utilityColumns" empty-message="No utility bills found.">
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #cell-billed_at="{ item }">{{ formatDate(item.billed_at) }}</template>
    </ResponsiveDataList>

    <AppDialog
      v-model:open="showForm"
      :title="`Add ${tab === 'nairobi' ? 'Nairobi Water' : 'electricity'} bill`"
      size="sm"
    >
      <div class="grid gap-4">
        <label class="label-field">
          Building
          <BuildingSearchSelect
            v-model="form.rental_building_id"
            :buildings="buildings"
            required
          />
        </label>
        <label class="label-field">
          Month
          <select v-model="form.billing_month" class="input-field" required>
            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
          </select>
        </label>
        <label class="label-field">
          Year
          <input v-model="form.billing_year" type="number" min="2000" class="input-field" required />
        </label>
        <label class="label-field">
          {{ amountLabel('rental') }}
          <input v-model="form.amount" type="number" min="0" step="0.01" class="input-field" required />
        </label>
        <label class="label-field">
          Billed on
          <input v-model="form.billed_at" type="date" class="input-field" required />
        </label>
        <label class="label-field">
          Remark
          <input v-model="form.remark" class="input-field" />
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
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import {
  createElectricityBill,
  createNairobiWaterBill,
  fetchBuildings,
  fetchElectricityBills,
  fetchNairobiWaterBills,
} from '../../api/rental'
import { amountLabel } from '../../utils/money'

const buildings = ref([])
const utilityBills = ref([])
const tab = ref('nairobi')
const showForm = ref(false)
const error = ref('')
const filters = reactive({ building_id: '' })
const now = new Date()
const form = reactive({
  rental_building_id: '',
  billing_month: now.getMonth() + 1,
  billing_year: now.getFullYear(),
  amount: 0,
  billed_at: now.toISOString().slice(0, 10),
  remark: '',
})

const utilityColumns = [
  { key: 'building_name', label: 'Building', cardTitle: true },
  { key: 'period', label: 'Period', mobileCard: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'billed_at', label: 'Billed on', tabletCard: true },
  { key: 'remark', label: 'Remark', tabletCard: true },
]

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
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
  const response = tab.value === 'nairobi'
    ? await fetchNairobiWaterBills(params)
    : await fetchElectricityBills(params)
  utilityBills.value = response.data
}

function setTab(next) {
  tab.value = next
  load()
}

function openCreate() {
  Object.assign(form, {
    rental_building_id: filters.building_id || '',
    billing_month: now.getMonth() + 1,
    billing_year: now.getFullYear(),
    amount: 0,
    billed_at: now.toISOString().slice(0, 10),
    remark: '',
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
    if (tab.value === 'nairobi') {
      await createNairobiWaterBill(form)
    } else {
      await createElectricityBill(form)
    }
    closeForm()
    await load()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save utility bill.'
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
