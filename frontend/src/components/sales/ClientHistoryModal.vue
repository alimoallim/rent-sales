<template>
  <AppDialog
    v-model:open="open"
    :title="clientName"
    :subtitle="modalSubtitle"
    size="2xl"
    @close="onClose"
  >
    <div v-if="loading" class="py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
      Loading client account…
    </div>

    <p v-else-if="error" class="alert-error">{{ error }}</p>

    <template v-else>
      <div class="segmented-control mb-4">
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'profile' }"
          @click="tab = 'profile'"
        >
          Personal details
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'payments' }"
          @click="tab = 'payments'"
        >
          Payment history
        </button>
      </div>

      <PersonProfilePanel
        v-if="tab === 'profile' && client"
        :entity="client"
        module="sales"
      />

      <div v-else-if="tab === 'payments'">
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Agreed unit price</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
              {{ formatMoney(summary?.agreed_sale_price, 'sales') }}
            </p>
          </div>
          <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Date sold</p>
            <p class="mt-0.5 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ soldDateLabel }}</p>
          </div>
          <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total paid</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums text-emerald-700">
              {{ formatMoney(summary?.paid_total, 'sales') }}
            </p>
            <p v-if="Number(summary?.deposit) > 0" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
              incl. deposit {{ formatMoney(summary?.deposit, 'sales') }}
            </p>
          </div>
          <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Outstanding</p>
            <p
              class="mt-0.5 text-lg font-semibold tabular-nums"
              :class="Number(summary?.balance) > 0 ? 'text-amber-700' : 'text-emerald-700'"
            >
              {{ formatMoney(summary?.balance, 'sales') }}
            </p>
          </div>
        </div>

        <div v-if="paidPercent !== null" class="mb-5">
          <div class="mb-1 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-400">
            <span>Payment progress</span>
            <span class="font-medium tabular-nums">{{ paidPercent }}% of sale price</span>
          </div>
          <div class="h-2 overflow-hidden rounded-full bg-zinc-200">
            <div
              class="h-full rounded-full bg-emerald-500 transition-all"
              :style="{ width: `${Math.min(paidPercent, 100)}%` }"
            />
          </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
          <span v-if="client?.voucher_number">Voucher: {{ client.voucher_number }}</span>
          <span v-if="client?.phone">· {{ client.phone }}</span>
        </div>

        <h4 class="mb-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Payment history</h4>
        <ClientPaymentHistory
          :payments="payments"
          :truncated="paymentsTruncated"
          :client-name="clientName"
          :building-name="client?.building_name"
          :unit-label="client?.unit_label"
        />
      </div>
    </template>

    <template #footer>
      <button
        v-if="canDisable && !loading && !error && client?.status === 'active'"
        type="button"
        class="btn-destructive mr-auto w-full sm:w-auto"
        :disabled="disabling"
        @click="disableClient"
      >
        {{ disabling ? 'Disabling…' : 'Disable client' }}
      </button>
      <button type="button" class="btn-secondary w-full sm:w-auto" @click="open = false">
        Close
      </button>
      <button
        v-if="!loading && !error && tab === 'payments'"
        type="button"
        class="btn-secondary w-full sm:w-auto"
        @click="printStatement"
      >
        Print statement
      </button>
    </template>
  </AppDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppDialog from '../ui/AppDialog.vue'
import PersonProfilePanel from '../ui/PersonProfilePanel.vue'
import ClientPaymentHistory from './ClientPaymentHistory.vue'
import { fetchClient, fetchClientPaymentSummary, fetchPayments, disableClient as disableClientApi } from '../../api/sales'
import { formatMoney } from '../../utils/money'
import { escapeHtml, printHtmlDocument } from '../../utils/print'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'

const props = defineProps({
  clientId: { type: [Number, String], default: null },
  clientName: { type: String, default: '' },
  buildingId: { type: [Number, String], default: null },
  canDisable: { type: Boolean, default: false },
})

const emit = defineEmits(['disabled'])

const { confirm } = useConfirm()
const toast = useToast()

const open = defineModel('open', { type: Boolean, default: false })
const tab = defineModel('tab', { type: String, default: 'profile' })

const loading = ref(false)
const disabling = ref(false)
const error = ref('')
const client = ref(null)
const summary = ref(null)
const payments = ref([])
const paymentsTruncated = ref(false)

const modalSubtitle = computed(() => {
  if (tab.value === 'profile') return 'Contact, sale terms, and uploaded documents'
  if (!client.value) return 'Sale account summary'
  return [client.value.building_name, client.value.unit_label ? `Unit ${client.value.unit_label}` : null]
    .filter(Boolean)
    .join(' · ')
})

const soldDateLabel = computed(() => {
  const date = client.value?.registration_date
  if (!date) return '—'
  return new Date(`${date}T12:00:00`).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

const paidPercent = computed(() => {
  const price = Number(summary.value?.agreed_sale_price || 0)
  const paid = Number(summary.value?.paid_total || 0)
  if (price <= 0) return null
  return Math.round((paid / price) * 100)
})

async function loadClientData(clientId) {
  if (!clientId) return

  loading.value = true
  error.value = ''
  try {
    const [clientData, summaryData, paymentsResponse] = await Promise.all([
      fetchClient(clientId),
      fetchClientPaymentSummary(clientId),
      fetchPayments({ client_id: clientId, per_page: 100 }),
    ])
    client.value = clientData
    summary.value = summaryData
    payments.value = paymentsResponse.data
    paymentsTruncated.value = paymentsResponse.meta?.last_page > 1
  } catch {
    error.value = 'Could not load client payment history.'
    client.value = null
    summary.value = null
    payments.value = []
  } finally {
    loading.value = false
  }
}

function formatPaymentDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function buildPaymentTableHtml() {
  const active = payments.value.filter((payment) => payment.status === 'active')

  if (active.length === 0) {
    return '<div class="empty">No installment payments recorded yet.</div>'
  }

  const rows = active
    .map(
      (payment, index) => `
        <tr>
          <td class="muted">${index + 1}</td>
          <td>${escapeHtml(formatPaymentDate(payment.paid_at))}</td>
          <td class="text-right">${escapeHtml(formatMoney(payment.amount, 'sales'))}</td>
          <td class="text-right">${Number(payment.discount) > 0 ? escapeHtml(formatMoney(payment.discount, 'sales')) : '—'}</td>
          <td>${escapeHtml(payment.invoice_reference || '—')}</td>
          <td>${escapeHtml(payment.bank || '—')}</td>
          <td class="text-right">${escapeHtml(payment.status)}</td>
        </tr>
      `,
    )
    .join('')

  const totalAmount = active.reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
  const totalDiscount = active.reduce((sum, payment) => sum + Number(payment.discount || 0), 0)

  return `
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th class="text-right">Amount</th>
          <th class="text-right">Discount</th>
          <th>Reference</th>
          <th>Bank</th>
          <th class="text-right">Status</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr>
          <td colspan="2" class="text-right">Installment total</td>
          <td class="text-right">${escapeHtml(formatMoney(totalAmount, 'sales'))}</td>
          <td class="text-right">${totalDiscount > 0 ? escapeHtml(formatMoney(totalDiscount, 'sales')) : '—'}</td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
    ${paymentsTruncated.value ? `<p class="footer-note">Showing the most recent ${payments.value.length} payments.</p>` : ''}
  `
}

function printStatement() {
  if (!client.value || !summary.value) return

  const balance = Number(summary.value.balance || 0)
  const progress =
    paidPercent.value !== null
      ? `
        <div class="progress">
          <div class="progress-meta">
            <span>Payment progress</span>
            <span>${paidPercent.value}% of sale price</span>
          </div>
          <div class="progress-track">
            <div class="progress-fill" style="width: ${Math.min(paidPercent.value, 100)}%"></div>
          </div>
        </div>
      `
      : ''

  const metaParts = [
    client.value.building_name,
    client.value.unit_label ? `Unit ${client.value.unit_label}` : null,
    client.value.voucher_number ? `Voucher ${client.value.voucher_number}` : null,
    client.value.phone || null,
  ].filter(Boolean)

  const body = `
    <h1>${escapeHtml(client.value.name || props.clientName)}</h1>
    <p class="meta">${escapeHtml(metaParts.join(' · '))} · Sold ${escapeHtml(soldDateLabel.value)}</p>
    <div class="summary">
      <div class="card">
        <div class="label">Agreed unit price</div>
        <div class="value">${escapeHtml(formatMoney(summary.value.agreed_sale_price, 'sales'))}</div>
      </div>
      <div class="card">
        <div class="label">Total paid</div>
        <div class="value value-success">${escapeHtml(formatMoney(summary.value.paid_total, 'sales'))}</div>
      </div>
      <div class="card">
        <div class="label">Outstanding</div>
        <div class="value ${balance > 0 ? 'value-warning' : 'value-success'}">${escapeHtml(formatMoney(summary.value.balance, 'sales'))}</div>
      </div>
      <div class="card">
        <div class="label">Deposit</div>
        <div class="value">${escapeHtml(formatMoney(summary.value.deposit, 'sales'))}</div>
      </div>
    </div>
    ${progress}
    <h2>Payment history</h2>
    ${buildPaymentTableHtml()}
  `

  printHtmlDocument({
    title: `${client.value.name || props.clientName} — Sale statement`,
    body,
  })
}

function onClose() {
  client.value = null
  summary.value = null
  payments.value = []
  error.value = ''
}

async function disableClient() {
  if (!client.value) return
  const ok = await confirm({
    title: 'Disable client',
    message: `Disable ${client.value.name || props.clientName}? The unit will be marked available.`,
    confirmLabel: 'Disable',
    variant: 'danger',
  })
  if (!ok) return
  disabling.value = true
  try {
    await disableClientApi(client.value.id)
    toast.success('Client disabled.')
    open.value = false
    emit('disabled')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not disable client.')
  } finally {
    disabling.value = false
  }
}

watch(
  () => [open.value, props.clientId],
  ([isOpen, clientId]) => {
    if (!isOpen || !clientId) return
    loadClientData(Number(clientId))
  },
)
</script>
