<template>
  <AppDialog
    v-model:open="open"
    :title="tenantName"
    :subtitle="modalSubtitle"
    size="2xl"
    :close-on-backdrop="!showChargeEdit"
    @close="onClose"
  >
    <div v-if="balanceSummary" class="mb-4 flex flex-wrap items-center gap-2">
      <span
        class="badge"
        :class="Number(balanceSummary.total_due) > 0 ? 'badge-warning' : 'badge-success'"
      >
        Balance: {{ formatMoney(balanceSummary.total_due, 'rental') }}
      </span>
      <span v-if="metaLabel" class="text-xs text-zinc-500 dark:text-zinc-400">{{ metaLabel }}</span>
    </div>

    <div class="segmented-control mb-4">
      <button
        type="button"
        class="segmented-option"
        :class="{ 'segmented-option-active': tab === 'payments' }"
        @click="setTab('payments')"
      >
        Payment history
      </button>
      <button
        type="button"
        class="segmented-option"
        :class="{ 'segmented-option-active': tab === 'charges' }"
        @click="setTab('charges')"
      >
        Charge history
      </button>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
      Loading {{ tab === 'payments' ? 'payments' : 'charges' }}…
    </div>

    <p v-else-if="error" class="alert-error">{{ error }}</p>

    <TenantPaymentHistory
      v-else-if="tab === 'payments'"
      :payments="payments"
      :truncated="paymentsTruncated"
    />

    <div v-else-if="tab === 'charges'" id="tenant-charge-print-area">
      <TenantChargeStatement
        :charges="charges"
        :tenant-name="tenantName"
        compact
        @edit="openChargeEdit"
      />
    </div>

    <template #footer>
      <button type="button" class="btn-secondary w-full sm:w-auto" @click="open = false">
        Close
      </button>
      <button
        v-if="tab === 'charges' && charges.length > 0"
        type="button"
        class="btn-secondary w-full sm:w-auto"
        @click="printCharges"
      >
        Print charges
      </button>
    </template>
  </AppDialog>

  <AppDialog v-model:open="showChargeEdit" title="Edit charge" size="sm" :close-on-backdrop="false">
    <p class="text-sm text-zinc-600 dark:text-zinc-400">
      {{ tenantName }} — {{ editingCharge?.billing_month }}/{{ editingCharge?.billing_year }}
    </p>
    <div class="mt-4 grid gap-4">
      <label class="label-field">
        {{ moneyLabel('Rent', 'rental') }}
        <input v-model="chargeForm.rent_amount" type="number" min="0" step="0.01" class="input-field" required />
      </label>
      <label class="label-field">
        {{ moneyLabel('Service', 'rental') }}
        <input v-model="chargeForm.service_amount" type="number" min="0" step="0.01" class="input-field" required />
      </label>
      <label class="label-field">
        Purpose
        <input v-model="chargeForm.purpose" class="input-field" />
      </label>
    </div>
    <p v-if="chargeEditError" class="mt-3 text-sm text-red-600">{{ chargeEditError }}</p>
    <template #footer>
      <button type="button" class="btn-secondary w-full sm:w-auto" @click="showChargeEdit = false">Cancel</button>
      <button type="button" class="btn-primary w-full sm:w-auto" :disabled="chargeSaving" @click="saveChargeEdit">
        {{ chargeSaving ? 'Saving…' : 'Save' }}
      </button>
    </template>
  </AppDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import AppDialog from '../ui/AppDialog.vue'
import TenantChargeStatement from './TenantChargeStatement.vue'
import TenantPaymentHistory from './TenantPaymentHistory.vue'
import {
  fetchCharges,
  fetchPayments,
  fetchTenantPaymentSummary,
  updateCharge,
} from '../../api/rental'
import { formatMoney, moneyLabel } from '../../utils/money'

const props = defineProps({
  tenantId: { type: [Number, String], default: null },
  tenantName: { type: String, default: '' },
  buildingId: { type: [Number, String], default: null },
})

const open = defineModel('open', { type: Boolean, default: false })
const tab = defineModel('tab', { type: String, default: 'payments' })

const loading = ref(false)
const error = ref('')
const payments = ref([])
const charges = ref([])
const paymentsTruncated = ref(false)
const balanceSummary = ref(null)
const metaLabel = ref('')

const showChargeEdit = ref(false)
const editingCharge = ref(null)
const chargeEditError = ref('')
const chargeSaving = ref(false)
const chargeForm = reactive({ rent_amount: 0, service_amount: 0, purpose: '' })

const modalSubtitle = computed(() => {
  if (metaLabel.value) return metaLabel.value
  return tab.value === 'payments' ? 'Recorded rent and utility payments' : 'Monthly charges by billing period'
})



function setTab(next) {
  tab.value = next
}

async function loadBalance() {
  if (!props.tenantId) {
    balanceSummary.value = null
    return
  }

  try {
    balanceSummary.value = await fetchTenantPaymentSummary(props.tenantId)
  } catch {
    balanceSummary.value = null
  }
}

async function loadPayments() {
  const response = await fetchPayments({
    tenant_id: props.tenantId,
    per_page: 100,
  })
  payments.value = response.data
  paymentsTruncated.value = response.meta?.last_page > 1
}

async function loadCharges() {
  const response = await fetchCharges({
    tenant_id: props.tenantId,
    per_page: 100,
  })
  charges.value = response.data

  const first = charges.value[0]
  if (first) {
    metaLabel.value = [first.building_name, first.unit_label ? `Unit ${first.unit_label}` : null]
      .filter(Boolean)
      .join(' · ')
  }
}

async function loadTab() {
  if (!props.tenantId) return

  loading.value = true
  error.value = ''
  try {
    if (tab.value === 'payments') {
      await loadPayments()
    } else {
      await loadCharges()
    }
  } catch {
    error.value = `Could not load ${tab.value === 'payments' ? 'payment' : 'charge'} history.`
  } finally {
    loading.value = false
  }
}

function openChargeEdit(charge) {
  editingCharge.value = charge
  Object.assign(chargeForm, {
    rent_amount: charge.rent_amount,
    service_amount: charge.service_amount,
    purpose: charge.purpose || '',
  })
  chargeEditError.value = ''
  showChargeEdit.value = true
}

async function saveChargeEdit() {
  if (!editingCharge.value) return

  chargeSaving.value = true
  chargeEditError.value = ''
  try {
    await updateCharge(editingCharge.value.id, chargeForm)
    showChargeEdit.value = false
    await Promise.all([loadCharges(), loadBalance()])
  } catch (e) {
    const validation = e.response?.data?.errors
    chargeEditError.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save charge.'
  } finally {
    chargeSaving.value = false
  }
}

function printCharges() {
  const area = document.getElementById('tenant-charge-print-area')
  if (!area) return

  const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=900,height=700')
  if (!printWindow) return

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${props.tenantName} — Charge history</title>
        <style>
          body { font-family: system-ui, sans-serif; padding: 1.5rem; color: #18181b; }
          h1 { font-size: 1.25rem; margin: 0 0 0.25rem; }
          p { margin: 0 0 1rem; color: #52525b; font-size: 0.875rem; }
          table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
          th, td { border-bottom: 1px solid #e4e4e7; padding: 0.5rem 0.75rem; text-align: left; }
          th { font-size: 0.75rem; text-transform: uppercase; color: #71717a; }
          .text-right { text-align: right; }
          .month-block { margin-bottom: 1.5rem; break-inside: avoid; }
          .month-title { font-weight: 600; margin: 0 0 0.5rem; }
        </style>
      </head>
      <body>
        <h1>${props.tenantName}</h1>
        <p>Charge history · ${metaLabel.value || 'Tenant statement'}</p>
        ${area.innerHTML}
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

function onClose() {
  payments.value = []
  charges.value = []
  error.value = ''
  metaLabel.value = ''
}

watch(
  () => [open.value, props.tenantId],
  async ([isOpen]) => {
    if (!isOpen || !props.tenantId) return
    await loadBalance()
    await loadTab()
  },
)

watch(tab, async () => {
  if (!open.value || !props.tenantId) return
  await loadTab()
})
</script>
