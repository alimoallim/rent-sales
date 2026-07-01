<template>
  <section>
    <PageHeader title="Electricity bills" subtitle="Tenant meter readings and electricity charges.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate()">
          Record electricity bill
        </button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
    </div>

    <ResponsiveDataList :items="bills" :columns="billColumns" empty-message="No electricity bills found.">
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'paid' ? 'success' : 'warning'" :label="item.status" />
      </template>
      <template #actions="{ item }">
        <button
          v-if="item.status !== 'paid'"
          type="button"
          class="btn-secondary w-full sm:w-auto"
          @click="markPaid(item)"
        >
          Mark paid
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" title="Record electricity bill" size="md" :close-on-backdrop="false">
      <div class="grid gap-4 lg:grid-cols-2">
        <label class="label-field lg:col-span-2">
          Building
          <select v-model="form.rental_building_id" class="input-field" required @change="onBuildingChange">
            <option disabled value="">Select building</option>
            <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
          </select>
        </label>
        <label class="label-field lg:col-span-2">
          Tenant
          <select v-model="form.tenant_id" class="input-field" required>
            <option disabled value="">Select tenant</option>
            <option v-for="tenant in activeTenants" :key="tenant.id" :value="tenant.id">
              {{ tenant.name }} — {{ tenant.unit_label }}
            </option>
          </select>
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
          Previous reading
          <input v-model="form.previous_reading" type="number" min="0" class="input-field" required />
        </label>
        <label class="label-field">
          Current reading
          <input v-model="form.current_reading" type="number" min="0" class="input-field" required />
        </label>
        <label class="label-field">
          Rate (KES/unit)
          <input v-model="form.rate" type="number" min="0" step="0.01" class="input-field" required />
        </label>
        <label class="label-field">
          Fixed fee (KES)
          <input v-model="form.fixed_fee" type="number" min="0" step="0.01" class="input-field" />
        </label>
        <label class="label-field lg:col-span-2">
          Remark
          <input v-model="form.remark" class="input-field" />
        </label>
      </div>
      <p v-if="previewAmount" class="mt-3 text-sm text-slate-600">Estimated amount: KES {{ formatMoney(previewAmount) }}</p>
      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="save">Save</button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import {
  createTenantElectricityBill,
  fetchBuildings,
  fetchTenants,
  fetchTenantElectricityBills,
  markTenantElectricityBillPaid,
} from '../../api/rental'

const route = useRoute()

const buildings = ref([])
const bills = ref([])
const activeTenants = ref([])
const showForm = ref(false)
const error = ref('')
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

const billColumns = [
  { key: 'period', label: 'Period', mobileCard: true },
  { key: 'tenant_name', label: 'Tenant', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'status', label: 'Status', mobileCard: true },
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

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE').format(Number(value || 0))
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

async function load() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id
  const response = await fetchTenantElectricityBills(params)
  bills.value = response.data
}

function onBuildingChange() {
  form.tenant_id = ''
  loadTenants(form.rental_building_id)
}

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
  try {
    await createTenantElectricityBill(form)
    closeForm()
    await load()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save electricity bill.'
  }
}

async function markPaid(bill) {
  try {
    await markTenantElectricityBillPaid(bill.id)
    await load()
  } catch (e) {
    window.alert(e.response?.data?.message || 'Could not mark bill as paid.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
  openCreateFromQuery()
})
</script>
