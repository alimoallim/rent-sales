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
      <div id="client-history-print-area">
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
        <ClientPaymentHistory :payments="payments" :truncated="paymentsTruncated" />
      </div>
    </template>

    <template #footer>
      <button type="button" class="btn-secondary w-full sm:w-auto" @click="open = false">
        Close
      </button>
      <button
        v-if="!loading && !error"
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
import ClientPaymentHistory from './ClientPaymentHistory.vue'
import { fetchClient, fetchClientPaymentSummary, fetchPayments } from '../../api/sales'
import { formatMoney } from '../../utils/money'

const props = defineProps({
  clientId: { type: [Number, String], default: null },
  clientName: { type: String, default: '' },
  buildingId: { type: [Number, String], default: null },
})

const open = defineModel('open', { type: Boolean, default: false })

const loading = ref(false)
const error = ref('')
const client = ref(null)
const summary = ref(null)
const payments = ref([])
const paymentsTruncated = ref(false)

const modalSubtitle = computed(() => {
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

function printStatement() {
  const area = document.getElementById('client-history-print-area')
  if (!area) return

  const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=900,height=700')
  if (!printWindow) return

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${client.value?.name || 'Client'} — Sale statement</title>
        <style>
          body { font-family: system-ui, sans-serif; padding: 1.5rem; color: #18181b; }
          h1 { font-size: 1.25rem; margin: 0 0 0.25rem; }
          .meta { margin: 0 0 1rem; color: #52525b; font-size: 0.875rem; }
          .summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1.25rem; }
          .card { border: 1px solid #e4e4e7; border-radius: 0.375rem; padding: 0.75rem; }
          .label { font-size: 0.7rem; text-transform: uppercase; color: #71717a; }
          .value { font-size: 1rem; font-weight: 600; margin-top: 0.25rem; }
          table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
          th, td { border-bottom: 1px solid #e4e4e7; padding: 0.5rem 0.75rem; text-align: left; }
          th { font-size: 0.75rem; text-transform: uppercase; color: #71717a; }
          .text-right { text-align: right; }
        </style>
      </head>
      <body>
        <h1>${client.value?.name || ''}</h1>
        <p class="meta">${modalSubtitle.value} · Sold ${soldDateLabel.value}</p>
        <div class="summary">
          <div class="card"><div class="label">Agreed unit price</div><div class="value">${formatMoney(summary.value?.agreed_sale_price, 'sales')}</div></div>
          <div class="card"><div class="label">Total paid</div><div class="value">${formatMoney(summary.value?.paid_total, 'sales')}</div></div>
          <div class="card"><div class="label">Outstanding</div><div class="value">${formatMoney(summary.value?.balance, 'sales')}</div></div>
          <div class="card"><div class="label">Deposit</div><div class="value">${formatMoney(summary.value?.deposit, 'sales')}</div></div>
        </div>
        ${area.innerHTML}
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

function onClose() {
  client.value = null
  summary.value = null
  payments.value = []
  error.value = ''
}

watch(
  () => [open.value, props.clientId],
  ([isOpen, clientId]) => {
    if (!isOpen || !clientId) return
    loadClientData(Number(clientId))
  },
)
</script>
