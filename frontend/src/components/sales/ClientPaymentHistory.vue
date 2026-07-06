<template>
  <div>
    <div
      v-if="payments.length === 0"
      class="rounded-md border border-dashed border-zinc-300 bg-zinc-50 dark:bg-zinc-900/50 px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400"
    >
      No installment payments recorded yet.
    </div>

    <template v-else>
      <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
          <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Installments</p>
          <p class="mt-0.5 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ activePayments.length }}</p>
        </div>
        <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
          <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Amount paid</p>
          <p class="mt-0.5 text-lg font-semibold tabular-nums text-emerald-700">{{ formatMoney(totalAmount, 'sales') }}</p>
        </div>
        <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
          <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Discounts</p>
          <p class="mt-0.5 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(totalDiscount, 'sales') }}</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
              <th class="px-3 py-2.5 sm:px-4">#</th>
              <th class="px-3 py-2.5 sm:px-4">Date</th>
              <th class="px-3 py-2.5 text-right sm:px-4">Amount</th>
              <th class="hidden px-3 py-2.5 text-right sm:table-cell sm:px-4">Discount</th>
              <th class="hidden px-3 py-2.5 sm:table-cell sm:px-4">Reference</th>
              <th class="hidden px-3 py-2.5 sm:table-cell sm:px-4">Bank</th>
              <th class="px-3 py-2.5 text-right sm:px-4">Status</th>
              <th class="px-3 py-2.5 text-right sm:px-4 w-10" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(payment, index) in payments"
              :key="payment.id"
              class="border-b border-zinc-100 dark:border-zinc-800 last:border-0"
              :class="payment.status === 'cancelled' ? 'bg-zinc-50/80 text-zinc-500 dark:text-zinc-400' : ''"
            >
              <td class="px-3 py-2.5 tabular-nums text-zinc-500 dark:text-zinc-400 sm:px-4">{{ index + 1 }}</td>
              <td class="px-3 py-2.5 whitespace-nowrap sm:px-4">{{ formatDate(payment.paid_at) }}</td>
              <td class="px-3 py-2.5 text-right font-medium tabular-nums sm:px-4">
                {{ formatMoney(payment.amount, 'sales') }}
              </td>
              <td class="hidden px-3 py-2.5 text-right tabular-nums sm:table-cell sm:px-4">
                {{ Number(payment.discount) > 0 ? formatMoney(payment.discount, 'sales') : '—' }}
              </td>
              <td class="hidden max-w-[8rem] truncate px-3 py-2.5 sm:table-cell sm:px-4">
                {{ payment.invoice_reference || '—' }}
              </td>
              <td class="hidden max-w-[8rem] truncate px-3 py-2.5 sm:table-cell sm:px-4">
                {{ payment.bank || '—' }}
              </td>
              <td class="px-3 py-2.5 text-right sm:px-4">
                <StatusBadge
                  :variant="payment.status === 'active' ? 'success' : 'neutral'"
                  :label="payment.status"
                />
              </td>
              <td class="px-3 py-2.5 text-right sm:px-4">
                <RowActionButton
                  v-if="canPrintPaymentReceipt(payment, 'sales')"
                  icon="print"
                  label="Print receipt"
                  @click="printReceipt(payment)"
                />
              </td>
            </tr>
          </tbody>
          <tfoot v-if="activePayments.length > 0">
            <tr class="bg-zinc-50 dark:bg-zinc-900/50">
              <td colspan="2" class="px-3 py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100 sm:px-4">
                Installment total
              </td>
              <td class="px-3 py-3 text-right text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100 sm:px-4">
                {{ formatMoney(totalAmount, 'sales') }}
              </td>
              <td class="hidden px-3 py-3 text-right text-sm font-semibold tabular-nums sm:table-cell sm:px-4">
                {{ totalDiscount > 0 ? formatMoney(totalDiscount, 'sales') : '—' }}
              </td>
              <td colspan="3" class="hidden sm:table-cell" />
            </tr>
          </tfoot>
        </table>
      </div>

      <p v-if="truncated" class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
        Showing the most recent {{ payments.length }} payments.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import StatusBadge from '../ui/StatusBadge.vue'
import RowActionButton from '../ui/RowActionButton.vue'
import { formatMoney } from '../../utils/money'
import { canPrintPaymentReceipt, printSalesPaymentReceipt } from '../../utils/paymentReceipt'

const props = defineProps({
  payments: { type: Array, default: () => [] },
  truncated: { type: Boolean, default: false },
  clientName: { type: String, default: '' },
  buildingName: { type: String, default: '' },
  unitLabel: { type: String, default: '' },
})

const activePayments = computed(() => props.payments.filter((p) => p.status === 'active'))

const totalAmount = computed(() =>
  activePayments.value.reduce((sum, p) => sum + Number(p.amount || 0), 0),
)

const totalDiscount = computed(() =>
  activePayments.value.reduce((sum, p) => sum + Number(p.discount || 0), 0),
)

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function printReceipt(payment) {
  printSalesPaymentReceipt(payment, {
    clientName: props.clientName,
    buildingName: props.buildingName,
    unitLabel: props.unitLabel,
  })
}
</script>
