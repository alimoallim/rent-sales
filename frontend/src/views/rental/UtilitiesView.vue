<template>
  <section>
    <PageHeader
      title="Building utilities"
      subtitle="Nairobi Water and electricity bills per building."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Utilities' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          Add bill
        </button>
      </template>
    </PageHeader>

    <div class="alert-info mb-3">
      These are building operating costs. They are not charged to individual tenants.
    </div>

    <FilterBar>
      <div class="segmented-control">
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'nairobi' }"
          @click="setTab('nairobi')"
        >
          Nairobi Water
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'electricity' }"
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
        @change="loadTable"
      />
    </FilterBar>

    <DataTable
      v-if="tab === 'nairobi'"
      v-model:search="waterSearch"
      server-side
      :items="waterItems"
      :columns="utilityColumns"
      :loading="waterLoading"
      :pagination="waterPagination"
      money-module="rental"
      empty-message="No utility bills found."
      @search="onWaterSearchChange"
      @page-change="waterGoToPage"
      @per-page-change="setWaterPerPage"
    >
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="rental" />
      </template>
      <template #cell-billed_at="{ item }">
        <DateCell :value="item.billed_at" />
      </template>
    </DataTable>

    <DataTable
      v-else
      v-model:search="electricitySearch"
      server-side
      :items="electricityItems"
      :columns="utilityColumns"
      :loading="electricityLoading"
      :pagination="electricityPagination"
      money-module="rental"
      empty-message="No utility bills found."
      @search="onElectricitySearchChange"
      @page-change="electricityGoToPage"
      @per-page-change="setElectricityPerPage"
    >
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="rental" />
      </template>
      <template #cell-billed_at="{ item }">
        <DateCell :value="item.billed_at" />
      </template>
    </DataTable>

    <AppDialog
      v-model:open="showForm"
      :title="`Add ${tab === 'nairobi' ? 'Nairobi Water' : 'electricity'} bill`"
      size="sm"
      :close-on-backdrop="false"
    >
      <div class="grid gap-4">
        <FormField label="Building" required>
          <BuildingSearchSelect
            v-model="form.rental_building_id"
            :buildings="buildings"
            required
          />
        </FormField>
        <FormField label="Month" required>
          <select v-model="form.billing_month" class="input-field" required>
            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
          </select>
        </FormField>
        <FormField label="Year" required>
          <input v-model="form.billing_year" type="number" min="2000" class="input-field" required />
        </FormField>
        <FormField :label="amountLabel('rental')" required>
          <input v-model="form.amount" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
        <FormField label="Billed on" required>
          <input v-model="form.billed_at" type="date" class="input-field" required />
        </FormField>
        <FormField label="Remark">
          <input v-model="form.remark" class="input-field" />
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
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import { useToast } from '../../composables/useToast'
import { usePaginatedList } from '../../composables/usePaginatedList'
import {
  createElectricityBill,
  createNairobiWaterBill,
  fetchBuildings,
  fetchElectricityBills,
  fetchNairobiWaterBills,
} from '../../api/rental'
import { amountLabel } from '../../utils/money'

const toast = useToast()

const buildings = ref([])
const saving = ref(false)
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

const {
  items: waterItems,
  loading: waterLoading,
  search: waterSearch,
  pagination: waterPagination,
  load: loadWater,
  reload: reloadWater,
  goToPage: waterGoToPage,
  setPerPage: setWaterPerPage,
  onSearchChange: onWaterSearchChange,
} = usePaginatedList((params) =>
  fetchNairobiWaterBills({
    ...params,
    building_id: filters.building_id || undefined,
  }),
)

const {
  items: electricityItems,
  loading: electricityLoading,
  search: electricitySearch,
  pagination: electricityPagination,
  load: loadElectricity,
  reload: reloadElectricity,
  goToPage: electricityGoToPage,
  setPerPage: setElectricityPerPage,
  onSearchChange: onElectricitySearchChange,
} = usePaginatedList((params) =>
  fetchElectricityBills({
    ...params,
    building_id: filters.building_id || undefined,
  }),
)

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

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadTable() {
  if (tab.value === 'nairobi') {
    await reloadWater()
  } else {
    await reloadElectricity()
  }
}

function setTab(next) {
  tab.value = next
  if (next === 'nairobi') {
    loadWater()
  } else {
    loadElectricity()
  }
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
  saving.value = true
  try {
    if (tab.value === 'nairobi') {
      await createNairobiWaterBill(form)
    } else {
      await createElectricityBill(form)
    }
    toast.success('Utility bill saved.')
    closeForm()
    if (tab.value === 'nairobi') {
      await reloadWater()
    } else {
      await reloadElectricity()
    }
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save utility bill.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadBuildings()
  await loadWater()
})
</script>
