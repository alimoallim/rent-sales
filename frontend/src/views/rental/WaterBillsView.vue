<template>
  <section>
    <PageHeader
      title="Water bills"
      subtitle="Record tenant meter readings for monthly billing. Collect payments through Rent Payments."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Water' }]"
    >
      <template #actions>
        <router-link to="/rental/bulk-meter-readings" class="btn-secondary w-full sm:w-auto">
          Bulk readings
        </router-link>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate()">
          Record water reading
        </button>
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
    </FilterBar>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="billColumns"
      :loading="loading"
      :pagination="pagination"
      money-module="rental"
      empty-message="No water readings found."
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="rental" />
      </template>
      <template #cell-billing_status="{ item }">
        <StatusBadge :variant="billingStatusVariant(item)" :label="item.status_label" />
      </template>
    </DataTable>

    <AppDialog v-model:open="showForm" title="Record water reading" size="md" :close-on-backdrop="false">
      <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
        Enter the meter reading for the billing period. After the monthly charge batch is approved, this amount is added to the tenant balance. Payments are recorded in Rent Payments.
      </p>
      <div class="grid gap-4 lg:grid-cols-2">
        <FormField label="Building" class="lg:col-span-2" required>
          <BuildingSearchSelect
            v-model="form.rental_building_id"
            :buildings="buildings"
            required
            @change="onBuildingChange"
          />
        </FormField>
        <FormField label="Tenant" class="lg:col-span-2" required>
          <TenantSearchSelect
            v-model="form.tenant_id"
            :tenants="activeTenants"
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
        <FormField
          :label="readingContext?.is_first_reading ? 'Opening reading' : 'Previous reading'"
          :hint="readingContext?.previous_reading_locked ? 'Carried forward from last month — cannot be changed.' : 'Enter the meter reading at the start of this tenant\'s billing history.'"
          required
        >
          <input
            v-model="form.previous_reading"
            type="number"
            min="0"
            class="input-field"
            :readonly="readingContext?.previous_reading_locked"
            :class="{ 'bg-zinc-50 text-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-400': readingContext?.previous_reading_locked }"
            required
          />
        </FormField>
        <FormField label="Current reading" required>
          <input v-model="form.current_reading" type="number" min="0" class="input-field" required />
        </FormField>
        <FormField :label="`${moneyLabel('Rate', 'rental')}/unit`" required>
          <input v-model="form.rate" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
        <FormField :label="moneyLabel('Fixed fee', 'rental')">
          <input v-model="form.fixed_fee" type="number" min="0" step="0.01" class="input-field" />
        </FormField>
        <FormField label="Remark" class="lg:col-span-2">
          <input v-model="form.remark" class="input-field" />
        </FormField>
      </div>
      <p v-if="previewAmount" class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
        Estimated charge: <MoneyCell :amount="previewAmount" module="rental" align="left" />
      </p>
      <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save reading' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import TenantSearchSelect from '../../components/ui/TenantSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import FormField from '../../components/ui/FormField.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DataTable from '../../components/data/DataTable.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import { useToast } from '../../composables/useToast'
import { usePaginatedList } from '../../composables/usePaginatedList'
import {
  createWaterBill,
  fetchBuildings,
  fetchMeterReadingContext,
  fetchTenants,
  fetchWaterBills,
} from '../../api/rental'
import { moneyLabel } from '../../utils/money'

const route = useRoute()
const toast = useToast()

const buildings = ref([])
const activeTenants = ref([])
const saving = ref(false)
const showForm = ref(false)
const error = ref('')
const readingContext = ref(null)
const loadingReadingContext = ref(false)
const filters = reactive({ building_id: '' })
const now = new Date()
const form = reactive({
  tenant_id: '',
  rental_building_id: '',
  billing_month: now.getMonth() + 1,
  billing_year: now.getFullYear(),
  previous_reading: 0,
  current_reading: 0,
  rate: 50,
  fixed_fee: 0,
  remark: '',
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
  fetchWaterBills({
    ...params,
    building_id: filters.building_id || undefined,
  }),
)

const billColumns = [
  { key: 'period', label: 'Period', mobileCard: true },
  { key: 'tenant_name', label: 'Tenant', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'billing_status', label: 'Billing status', mobileCard: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
  { key: 'consumption', label: 'Consumption', align: 'right', tabletCard: true },
]

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const previewAmount = computed(() => {
  const consumption = Math.max(0, Number(form.current_reading) - Number(form.previous_reading))
  return consumption * Number(form.rate || 0) + Number(form.fixed_fee || 0)
})

function billingStatusVariant(item) {
  if (item.status === 'paid') return 'success'
  if (item.charge_posted) return 'info'
  return 'neutral'
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadTenants(buildingId) {
  if (!buildingId) {
    activeTenants.value = []
    return
  }
  const response = await fetchTenants({ status: 'active', building_id: buildingId })
  activeTenants.value = response.data
}

async function loadTable() {
  await reload()
}

function onBuildingChange() {
  form.tenant_id = ''
  loadTenants(form.rental_building_id)
}

async function loadReadingContext() {
  if (!form.tenant_id || !form.billing_month || !form.billing_year) {
    readingContext.value = null
    return
  }

  loadingReadingContext.value = true
  try {
    readingContext.value = await fetchMeterReadingContext({
      utility: 'water',
      tenant_id: form.tenant_id,
      billing_month: form.billing_month,
      billing_year: form.billing_year,
    })
    form.previous_reading = readingContext.value.previous_reading
    form.rate = Number(readingContext.value.default_rate)
    form.fixed_fee = Number(readingContext.value.default_fixed_fee)
  } catch {
    readingContext.value = null
  } finally {
    loadingReadingContext.value = false
  }
}

watch(
  () => [form.tenant_id, form.billing_month, form.billing_year],
  () => {
    if (showForm.value) {
      loadReadingContext()
    }
  },
)

function openCreate(prefill = {}) {
  Object.assign(form, {
    tenant_id: '',
    rental_building_id: filters.building_id || '',
    billing_month: now.getMonth() + 1,
    billing_year: now.getFullYear(),
    previous_reading: 0,
    current_reading: 0,
    rate: 50,
    fixed_fee: 0,
    remark: '',
    ...prefill,
  })
  error.value = ''
  showForm.value = true
  loadTenants(form.rental_building_id)
  loadReadingContext()
}

function openCreateFromQuery() {
  const tenantId = route.query.tenant_id
  if (!tenantId) return

  openCreate({
    tenant_id: Number(tenantId),
    rental_building_id: route.query.building_id ? Number(route.query.building_id) : '',
    billing_month: route.query.billing_month ? Number(route.query.billing_month) : now.getMonth() + 1,
    billing_year: route.query.billing_year ? Number(route.query.billing_year) : now.getFullYear(),
  })
}

function closeForm() {
  showForm.value = false
}

async function save() {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...form }
    if (readingContext.value?.previous_reading_locked) {
      delete payload.previous_reading
    }
    await createWaterBill(payload)
    toast.success('Water reading saved.')
    closeForm()
    await reload()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save water reading.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
  openCreateFromQuery()
})
</script>
