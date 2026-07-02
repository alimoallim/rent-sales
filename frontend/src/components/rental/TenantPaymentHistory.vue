<template>
  <div>
    <div
      v-if="payments.length === 0"
      class="rounded-md border border-dashed border-zinc-300 bg-zinc-50 dark:bg-zinc-900/50 px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400"
    >
      No payments recorded for this tenant.
    </div>

    <template v-else>
      <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
          <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Payments</p>
          <p class="mt-0.5 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ activePayments.length }}</p>
        </div>
        <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
          <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total collected</p>
          <p class="mt-0.5 text-lg font-semibold tabular-nums text-emerald-700">{{ formatMoney(totalCollected, 'rental') }}</p>
        </div>
        <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-2.5">
          <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Discounts</p>
          <p class="mt-0.5 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(totalDiscount, 'rental') }}</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
              <th class="px-3 py-2.5 sm:px-4">Date</th>
              <th class="px-3 py-2.5 text-right sm:px-4">Amount</th>
              <th class="hidden px-3 py-2.5 text-right sm:table-cell sm:px-4">Discount</th>
              <th class="hidden px-3 py-2.5 sm:table-cell sm:px-4">Reference</th>
              <th class="px-3 py-2.5 text-right sm:px-4">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="payment in payments"
              :key="payment.id"
              class="border-b border-zinc-100 dark:border-zinc-800 last:border-0"
              :class="payment.status === 'voided' ? 'bg-zinc-50/80 text-zinc-500 dark:text-zinc-400' : ''"
            >
              <td class="px-3 py-2.5 whitespace-nowrap sm:px-4">{{ formatDate(payment.paid_at) }}</td>
              <td class="px-3 py-2.5 text-right font-medium tabular-nums sm:px-4">
                {{ formatMoney(payment.amount, 'rental') }}
              </td>
              <td class="hidden px-3 py-2.5 text-right tabular-nums sm:table-cell sm:px-4">
                {{ Number(payment.discount) > 0 ? formatMoney(payment.discount, 'rental') : '—' }}
              </td>
              <td class="hidden max-w-[10rem] truncate px-3 py-2.5 sm:table-cell sm:px-4">
                {{ payment.invoice_reference || '—' }}
              </td>
              <td class="px-3 py-2.5 text-right sm:px-4">
                <StatusBadge
                  :variant="payment.status === 'active' ? 'success' : 'neutral'"
                  :label="payment.status"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="truncated" class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
        Showing the most recent {{ payments.length }} payments. Open the Payments screen for the full ledger.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import StatusBadge from '../ui/StatusBadge.vue'
import { formatMoney } from '../../utils/money'

const props = defineProps({
  payments: { type: Array, default: () => [] },
  truncated: { type: Boolean, default: false },
})

const activePayments = computed(() => props.payments.filter((p) => p.status === 'active'))

const totalCollected = computed(() =>
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
</script>
