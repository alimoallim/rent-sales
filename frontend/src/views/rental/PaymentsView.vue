<template>
  <section>
    <PageHeader title="Payments" subtitle="Record rent, water, and service payments in one place.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Record payment</button>
      </template>
    </PageHeader>

    <TenantFilterBanner
      v-if="tenantFilter.id"
      :tenant-id="tenantFilter.id"
      :tenant-name="tenantFilter.name"
      label="payments"
      @clear="clearTenantFilter"
    />

    <div class="filter-bar">
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
      <select v-model="filters.status" class="input-field" @change="load">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="voided">Voided</option>
      </select>
    </div>

    <ResponsiveDataList
      :items="payments"
      :columns="paymentColumns"
      empty-message="No payments recorded yet."
    >
      <template #card-title-tenant_name="{ item }">
        <TenantNameMenu
          :tenant-id="item.tenant_id"
          :tenant-name="item.tenant_name"
          :building-id="item.rental_building_id"
        />
      </template>
      <template #cell-tenant_name="{ item }">
        <TenantNameMenu
          :tenant-id="item.tenant_id"
          :tenant-name="item.tenant_name"
          :building-id="item.rental_building_id"
        />
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'active' ? 'success' : 'neutral'" :label="item.status" />
      </template>
      <template #actions="{ item }">
        <button
          v-if="item.status === 'active'"
          type="button"
          class="btn-secondary w-full sm:w-auto"
          @click="openEdit(item)"
        >
          Edit
        </button>
        <button
          v-if="item.status === 'active'"
          type="button"
          class="btn-destructive w-full sm:w-auto"
          @click="voidOne(item)"
        >
          Void
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit payment' : 'Record payment'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form id="payment-form" class="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-x-8" @submit.prevent="save">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Payment details</p>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="label-field sm:col-span-2">
              Building
              <select v-model="form.rental_building_id" class="input-field" required @change="onBuildingChange">
                <option disabled value="">Select building</option>
                <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
              </select>
            </label>

            <label class="label-field sm:col-span-2">
              Tenant
              <select v-model="form.tenant_id" class="input-field" required @change="onTenantChange">
                <option disabled value="">Select tenant</option>
                <option v-for="tenant in activeTenants" :key="tenant.id" :value="tenant.id">
                  {{ tenant.name }} — {{ tenant.unit_label }}
                </option>
              </select>
            </label>

            <label class="label-field">
              Amount (KES)
              <input v-model="form.amount" type="number" min="0.01" step="0.01" class="input-field" required />
            </label>

            <label class="label-field">
              Discount (KES)
              <input v-model="form.discount" type="number" min="0" step="0.01" class="input-field" />
            </label>

            <label class="label-field">
              Paid on
              <input v-model="form.paid_at" type="date" class="input-field" required />
            </label>

            <label class="label-field">
              Invoice reference
              <input v-model="form.invoice_reference" class="input-field" />
            </label>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Balance &amp; requirements</p>

          <BalanceBreakdown
            v-if="form.tenant_id"
            :summary="summary"
            :loading="summaryLoading"
            compact
          />
          <div
            v-else
            class="rounded-md border border-dashed border-zinc-200 bg-zinc-50 px-3 py-4 text-sm text-zinc-500"
          >
            Select a tenant to view outstanding balance and metering requirements.
          </div>

          <div
            v-if="summary?.contract"
            class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-600">Agreement metering</p>
            <ul class="mt-2 grid gap-1 text-zinc-700 sm:grid-cols-2">
              <li>Water: {{ summary.contract.requires_water_metering ? 'Required monthly' : 'Not required' }}</li>
              <li>Electricity: {{ summary.contract.requires_electricity_metering ? 'Required monthly' : 'Not required' }}</li>
            </ul>
          </div>

          <MeterReadingBanner
            :reminders="summary?.meter_reading_reminders || []"
            @record-reading="goToMeterReading"
          />

          <div
            v-if="wouldOverpay"
            class="alert-warning"
          >
            <p class="font-semibold">This payment is more than the tenant owes.</p>
            <p class="mt-1">
              Total being recorded: KES {{ formatMoney(paymentTotal) }}.
              Total due: KES {{ formatMoney(summary?.total_due || 0) }}.
              The extra amount will be kept as a credit for future charges.
            </p>
            <p class="mt-2 font-medium">Only continue if you are recording this overpayment on purpose.</p>
          </div>
        </div>

        <p v-if="error" class="alert-error lg:col-span-2">{{ error }}</p>
      </form>

      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button
          type="submit"
          form="payment-form"
          class="btn-primary w-full sm:w-auto"
          :disabled="paymentBlocked"
        >
          Save payment
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import BalanceBreakdown from '../../components/rental/BalanceBreakdown.vue'
import MeterReadingBanner from '../../components/rental/MeterReadingBanner.vue'
import TenantNameMenu from '../../components/rental/TenantNameMenu.vue'
import TenantFilterBanner from '../../components/rental/TenantFilterBanner.vue'
import {
  createPayment,
  fetchBuildings,
  fetchPayments,
  fetchTenantPaymentSummary,
  fetchTenants,
  updatePayment,
  voidPayment,
} from '../../api/rental'

const router = useRouter()
const route = useRoute()

const buildings = ref([])
const payments = ref([])
const activeTenants = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const summary = ref(null)
const summaryLoading = ref(false)
const filters = reactive({ building_id: '', status: '', tenant_id: '' })
const tenantFilter = reactive({ id: '', name: '' })
const form = reactive({
  tenant_id: '',
  rental_building_id: '',
  amount: 0,
  discount: 0,
  paid_at: new Date().toISOString().slice(0, 10),
  invoice_reference: '',
})

const paymentColumns = [
  {
    key: 'paid_at',
    label: 'Date',
    mobileCard: true,
    format: (row) => formatDate(row.paid_at),
  },
  { key: 'tenant_name', label: 'Tenant', cardTitle: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'discount', label: 'Discount', align: 'right', money: true, tabletCard: true },
  { key: 'status', label: 'Status', mobileCard: true },
]

const paymentTotal = computed(() => Number(form.amount || 0) + Number(form.discount || 0))

const wouldOverpay = computed(() => {
  if (!summary.value) return false
  return paymentTotal.value > Number(summary.value.total_due || 0)
})

const paymentBlocked = computed(() => Boolean(summary.value?.payment_blocked))

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE').format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE')
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

async function loadSummary() {
  if (!form.tenant_id) {
    summary.value = null
    return
  }

  summaryLoading.value = true
  try {
    const params = {}
    if (editing.value) {
      params.exclude_payment_id = editing.value.id
    }

    const paidAt = form.paid_at ? new Date(`${form.paid_at}T12:00:00`) : new Date()
    params.billing_month = paidAt.getMonth() + 1
    params.billing_year = paidAt.getFullYear()

    summary.value = await fetchTenantPaymentSummary(form.tenant_id, params)
  } catch {
    summary.value = null
  } finally {
    summaryLoading.value = false
  }
}

async function load() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id
  if (filters.status) params.status = filters.status
  if (filters.tenant_id) params.tenant_id = filters.tenant_id
  const response = await fetchPayments(params)
  payments.value = response.data
  if (tenantFilter.id && !tenantFilter.name && payments.value.length > 0) {
    tenantFilter.name = payments.value[0].tenant_name || ''
  }
}

function applyRouteQuery() {
  const tenantId = route.query.tenant_id
  if (!tenantId) {
    filters.tenant_id = ''
    tenantFilter.id = ''
    tenantFilter.name = ''
    return
  }

  filters.tenant_id = Number(tenantId)
  tenantFilter.id = Number(tenantId)
  tenantFilter.name = typeof route.query.tenant_name === 'string' ? route.query.tenant_name : ''

  if (route.query.building_id) {
    filters.building_id = Number(route.query.building_id)
  }
}

function clearTenantFilter() {
  filters.tenant_id = ''
  tenantFilter.id = ''
  tenantFilter.name = ''
  router.replace({ name: 'rental.payments' })
  load()
}

function onBuildingChange() {
  form.tenant_id = ''
  summary.value = null
  loadTenants(form.rental_building_id)
}

function onTenantChange() {
  loadSummary()
}

function goToMeterReading(reminder) {
  showForm.value = false
  router.push({
    name: reminder.utility === 'electricity' ? 'rental.electricity-bills' : 'rental.water-bills',
    query: {
      tenant_id: reminder.tenant_id,
      building_id: reminder.rental_building_id,
      billing_month: reminder.billing_month,
      billing_year: reminder.billing_year,
    },
  })
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    tenant_id: '',
    rental_building_id: filters.building_id || '',
    amount: '',
    discount: '',
    paid_at: new Date().toISOString().slice(0, 10),
    invoice_reference: '',
  })
  error.value = ''
  summary.value = null
  showForm.value = true
  loadTenants(form.rental_building_id)
}

function openEdit(payment) {
  editing.value = payment
  Object.assign(form, {
    tenant_id: payment.tenant_id,
    rental_building_id: payment.rental_building_id,
    amount: payment.amount,
    discount: payment.discount || 0,
    paid_at: payment.paid_at?.slice(0, 10) || '',
    invoice_reference: payment.invoice_reference || '',
  })
  error.value = ''
  summary.value = null
  showForm.value = true
  loadTenants(form.rental_building_id)
  loadSummary()
}

function closeForm() {
  showForm.value = false
}

function validateForm() {
  const errors = []
  if (!form.rental_building_id) errors.push('Select a building.')
  if (!form.tenant_id) errors.push('Select a tenant.')
  if (paymentTotal.value <= 0) errors.push('Enter an amount greater than zero.')
  if (!form.paid_at) errors.push('Enter the payment date.')
  return errors
}

async function save() {
  error.value = ''

  const validationErrors = validateForm()
  if (validationErrors.length > 0) {
    error.value = validationErrors.join(' ')
    return
  }

  if (paymentBlocked.value) {
    error.value = 'Enter the required meter reading(s) for this billing period before recording payment.'
    return
  }

  if (wouldOverpay.value) {
    const confirmed = window.confirm(
      `This payment (KES ${formatMoney(paymentTotal.value)}) is more than the tenant owes (KES ${formatMoney(summary.value?.total_due || 0)}). `
        + 'The extra will be kept as credit. Only continue if this overpayment is intentional.',
    )
    if (!confirmed) return
  }

  const payload = {
    tenant_id: form.tenant_id,
    rental_building_id: form.rental_building_id,
    amount: Number(form.amount),
    discount: Number(form.discount) || 0,
    paid_at: form.paid_at,
    invoice_reference: form.invoice_reference || null,
    overpayment_acknowledged: wouldOverpay.value,
  }

  try {
    if (editing.value) {
      await updatePayment(editing.value.id, payload)
    } else {
      await createPayment(payload)
    }
    closeForm()
    await load()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save payment.'
  }
}

async function voidOne(payment) {
  if (!window.confirm(`Void payment of KES ${formatMoney(payment.amount)} for ${payment.tenant_name}?`)) return

  try {
    await voidPayment(payment.id)
    await load()
  } catch (e) {
    window.alert(e.response?.data?.message || 'Could not void payment.')
  }
}

watch(
  () => [form.amount, form.discount],
  () => {
    error.value = ''
  },
)

watch(
  () => form.paid_at,
  () => {
    if (form.tenant_id && showForm.value) {
      loadSummary()
    }
  },
)

onMounted(async () => {
  await loadBuildings()
  applyRouteQuery()
  await load()
})

watch(
  () => route.query.tenant_id,
  async () => {
    applyRouteQuery()
    await load()
  },
)
</script>
