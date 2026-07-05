<template>
  <section>
    <PageHeader
      title="Payments"
      subtitle="Record rent, water, and service payments in one place."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Payments' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Record payment</button>
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
      <select v-model="filters.status" class="input-field" @change="loadTable">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="voided">Deleted</option>
      </select>
    </FilterBar>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="paymentColumns"
      :loading="loading"
      :pagination="pagination"
      money-module="rental"
      empty-message="No payments recorded yet."
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
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
      <template #cell-paid_at="{ item }">
        <DateCell :value="item.paid_at" />
      </template>
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="rental" />
      </template>
      <template #cell-discount="{ item }">
        <MoneyCell :amount="item.discount" module="rental" />
      </template>
      <template #cell-status="{ item }">
        <StatusBadge
          :variant="item.status === 'active' ? 'success' : 'neutral'"
          :label="item.status === 'voided' ? 'deleted' : item.status"
        />
      </template>
      <template #actions="{ item }">
        <RowActionButton
          v-if="item.status === 'active'"
          icon="edit"
          label="Edit"
          @click="openEdit(item)"
        />
        <RowActionButton
          v-if="item.status === 'active'"
          icon="delete"
          label="Delete"
          variant="danger"
          @click="voidOne(item)"
        />
      </template>
    </DataTable>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit payment' : 'Record payment'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form id="payment-form" class="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-x-8" @submit.prevent="save">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Payment details</p>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="label-field sm:col-span-2">
              Building
              <BuildingSearchSelect
                v-model="form.rental_building_id"
                :buildings="buildings"
                required
                @change="onBuildingChange"
              />
            </label>

            <label class="label-field sm:col-span-2">
              Tenant
              <TenantSearchSelect
                v-model="form.tenant_id"
                :tenants="activeTenants"
                required
                @change="onTenantChange"
              />
            </label>

            <label class="label-field">
              {{ amountLabel('rental') }}
              <input v-model="form.amount" type="number" min="0.01" step="0.01" class="input-field" required />
            </label>

            <label class="label-field">
              {{ moneyLabel('Discount', 'rental') }}
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
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Balance &amp; requirements</p>

          <BalanceBreakdown
            v-if="form.tenant_id"
            :summary="summary"
            :loading="summaryLoading"
            compact
          />
          <div
            v-else
            class="rounded-md border border-dashed border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-4 text-sm text-zinc-500 dark:text-zinc-400"
          >
            Select a tenant to view outstanding balance and metering requirements.
          </div>

          <div
            v-if="summary?.contract"
            class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 p-3 text-sm"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">Agreement metering</p>
            <ul class="mt-2 grid gap-1 text-zinc-700 dark:text-zinc-300 sm:grid-cols-2">
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
              Total being recorded: {{ formatMoney(paymentTotal, 'rental') }}.
              Total due: {{ formatMoney(summary?.total_due || 0, 'rental') }}.
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
          :disabled="paymentBlocked || saving"
        >
          {{ saving ? 'Saving…' : 'Save payment' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import TenantSearchSelect from '../../components/ui/TenantSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DataTable from '../../components/data/DataTable.vue'
import RowActionButton from '../../components/ui/RowActionButton.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import BalanceBreakdown from '../../components/rental/BalanceBreakdown.vue'
import MeterReadingBanner from '../../components/rental/MeterReadingBanner.vue'
import TenantNameMenu from '../../components/rental/TenantNameMenu.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import {
  createPayment,
  fetchBuildings,
  fetchPayments,
  fetchTenantPaymentSummary,
  fetchTenants,
  updatePayment,
  voidPayment,
} from '../../api/rental'
import { formatMoney, amountLabel, moneyLabel } from '../../utils/money'

const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const saving = ref(false)
const activeTenants = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const summary = ref(null)
const summaryLoading = ref(false)
const filters = reactive({ building_id: '', status: '' })
const form = reactive({
  tenant_id: '',
  rental_building_id: '',
  amount: 0,
  discount: 0,
  paid_at: new Date().toISOString().slice(0, 10),
  invoice_reference: '',
})

const paymentColumns = [
  { key: 'paid_at', label: 'Date', mobileCard: true },
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
  fetchPayments({
    ...params,
    building_id: filters.building_id || undefined,
    status: filters.status || undefined,
  }),
)

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

async function loadTable() {
  await reload()
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

function openCreate(prefill = {}) {
  editing.value = null
  Object.assign(form, {
    tenant_id: prefill.tenant_id ? String(prefill.tenant_id) : '',
    rental_building_id: prefill.rental_building_id ? String(prefill.rental_building_id) : (filters.building_id || ''),
    amount: '',
    discount: '',
    paid_at: new Date().toISOString().slice(0, 10),
    invoice_reference: '',
  })
  error.value = ''
  summary.value = null
  showForm.value = true
  loadTenants(form.rental_building_id).then(() => {
    if (form.tenant_id) {
      loadSummary()
    }
  })
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
    const ok = await confirm({
      title: 'Record overpayment?',
      message:
        `This payment (${formatMoney(paymentTotal.value, 'rental')}) is more than the tenant owes (${formatMoney(summary.value?.total_due || 0, 'rental')}). `
        + 'The extra will be kept as credit. Only continue if this overpayment is intentional.',
      confirmLabel: 'Record payment',
      variant: 'warning',
    })
    if (!ok) return
  }

  saving.value = true
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
      toast.success('Payment updated.')
    } else {
      await createPayment(payload)
      toast.success('Payment recorded.')
    }
    closeForm()
    await reload()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save payment.'
  } finally {
    saving.value = false
  }
}

async function voidOne(payment) {
  const ok = await confirm({
    title: 'Delete payment',
    message: `Delete payment of ${formatMoney(payment.amount, 'rental')} for ${payment.tenant_name}? The payment will be removed from balances but kept in records as deleted.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await voidPayment(payment.id)
    toast.success('Payment deleted.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete payment.')
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
  await load()
  const query = router.currentRoute.value.query
  if (query.action === 'new' && query.tenant_id) {
    openCreate({
      tenant_id: query.tenant_id,
      rental_building_id: query.building_id,
    })
  }
})
</script>
