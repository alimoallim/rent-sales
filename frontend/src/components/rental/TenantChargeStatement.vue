<template>
  <div class="charge-statement-report" :class="{ 'charge-statement-compact': compact }">
    <div
      v-if="statements.length === 0"
      class="rounded-md border border-dashed border-zinc-300 bg-zinc-50 dark:bg-zinc-900/50 px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400"
    >
      No charges found for this tenant.
    </div>

    <div
      v-for="(statement, index) in statements"
      :key="`${statement.billing_year}-${statement.billing_month}`"
      class="charge-statement-month content-panel mb-4 overflow-hidden"
      :class="{ 'print-page-break': index < statements.length - 1 }"
    >
      <div class="border-b border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-4 py-4 sm:px-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Monthly charge statement</p>
            <h3 class="mt-1 text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ statement.periodLabel }}</h3>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Invoice date: {{ statement.invoiceDate }}</p>
          </div>
          <div class="text-sm text-zinc-700 dark:text-zinc-300 sm:text-right">
            <p><span class="font-medium text-zinc-900 dark:text-zinc-100">Tenant:</span> {{ tenantName }}</p>
            <p v-if="statement.building_name">
              <span class="font-medium text-zinc-900 dark:text-zinc-100">Building:</span> {{ statement.building_name }}
            </p>
            <p v-if="statement.unit_label">
              <span class="font-medium text-zinc-900 dark:text-zinc-100">Unit:</span> {{ statement.unit_label }}
            </p>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-zinc-200 bg-white dark:bg-zinc-900 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
              <th class="px-4 py-2.5 sm:px-5">#</th>
              <th class="px-4 py-2.5 sm:px-5">Description</th>
              <th class="px-4 py-2.5 sm:px-5">Charged on</th>
              <th class="px-4 py-2.5 text-right sm:px-5">{{ amountLabel('rental') }}</th>
              <th class="print-hidden px-4 py-2.5 text-right sm:px-5" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(line, lineIndex) in statement.lines"
              :key="`${line.chargeId}-${line.description}-${lineIndex}`"
              class="border-b border-zinc-100 dark:border-zinc-800 last:border-0"
            >
              <td class="px-4 py-2.5 tabular-nums text-zinc-500 dark:text-zinc-400 sm:px-5">{{ lineIndex + 1 }}</td>
              <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100 sm:px-5">{{ line.description }}</td>
              <td class="px-4 py-2.5 text-zinc-700 dark:text-zinc-300 sm:px-5">{{ line.chargedOn }}</td>
              <td class="px-4 py-2.5 text-right font-medium tabular-nums text-zinc-900 dark:text-zinc-100 sm:px-5">
                {{ formatMoney(line.amount, 'rental') }}
              </td>
              <td class="print-hidden px-4 py-2.5 text-right sm:px-5">
                <button
                  v-if="line.editable"
                  type="button"
                  class="btn-ghost !min-h-8 !py-1 text-xs"
                  @click="$emit('edit', line.charge)"
                >
                  Edit
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-zinc-50 dark:bg-zinc-900/50">
              <td colspan="3" class="px-4 py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100 sm:px-5">
                {{ statement.periodLabel }} total
              </td>
              <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100 sm:px-5">
                {{ formatMoney(statement.monthTotal, 'rental') }}
              </td>
              <td class="print-hidden sm:px-5" />
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div
      v-if="statements.length > 0"
      class="content-panel flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5"
    >
      <p class="text-sm text-zinc-600 dark:text-zinc-400">
        {{ statements.length }} billing period{{ statements.length === 1 ? '' : 's' }} ·
        {{ totalLineCount }} line item{{ totalLineCount === 1 ? '' : 's' }}
      </p>
      <p class="text-base font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
        Grand total: {{ formatMoney(grandTotal, 'rental') }}
      </p>
    </div>

    <p v-if="!compact" class="mt-3 text-center text-xs text-zinc-400 print:mt-6">
      Generated {{ generatedLabel }} · For billing disputes, refer to the invoice period and charged-on date on each line.
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatMoney, amountLabel, moneyLabel, currencyCode } from '../../utils/money'

const props = defineProps({
  charges: { type: Array, default: () => [] },
  tenantName: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

defineEmits(['edit'])

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const generatedLabel = computed(() => {
  return new Date().toLocaleString('en-KE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
})


function formatChargeDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatPeriod(month, year) {
  return `${monthNames[month - 1]} ${year}`
}

function formatInvoiceDate(month, year) {
  return new Date(year, month - 1, 1).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function lineItemsForCharge(charge) {
  const chargedOn = formatChargeDate(charge.charged_at)
  const lines = []

  if (charge.is_editable || charge.charge_type === 'Rent + service') {
    if (Number(charge.rent_amount) > 0) {
      lines.push({
        description: 'Monthly rent',
        chargedOn,
        amount: charge.rent_amount,
        editable: charge.is_editable,
        chargeId: charge.id,
        charge,
      })
    }
    if (Number(charge.service_amount) > 0) {
      lines.push({
        description: 'Service charge',
        chargedOn,
        amount: charge.service_amount,
        editable: charge.is_editable,
        chargeId: charge.id,
        charge,
      })
    }
    if (lines.length === 0) {
      lines.push({
        description: 'Rent & service',
        chargedOn,
        amount: charge.total_amount,
        editable: charge.is_editable,
        chargeId: charge.id,
        charge,
      })
    }
    return lines
  }

  if (charge.charge_type === 'Water') {
    lines.push({
      description: 'Water charge',
      chargedOn,
      amount: charge.total_amount,
      editable: false,
      chargeId: charge.id,
      charge,
    })
    return lines
  }

  if (charge.charge_type === 'Electricity') {
    lines.push({
      description: 'Electricity charge',
      chargedOn,
      amount: charge.total_amount,
      editable: false,
      chargeId: charge.id,
      charge,
    })
    return lines
  }

  lines.push({
    description: charge.charge_type || charge.purpose || 'Charge',
    chargedOn,
    amount: charge.total_amount,
    editable: false,
    chargeId: charge.id,
    charge,
  })

  return lines
}

const statements = computed(() => {
  const groups = new Map()

  for (const charge of props.charges) {
    const key = `${charge.billing_year}-${charge.billing_month}`
    if (!groups.has(key)) {
      groups.set(key, {
        billing_year: charge.billing_year,
        billing_month: charge.billing_month,
        periodLabel: formatPeriod(charge.billing_month, charge.billing_year),
        invoiceDate: formatInvoiceDate(charge.billing_month, charge.billing_year),
        building_name: charge.building_name,
        unit_label: charge.unit_label,
        lines: [],
      })
    }

    groups.get(key).lines.push(...lineItemsForCharge(charge))
  }

  return [...groups.values()]
    .map((statement) => ({
      ...statement,
      monthTotal: statement.lines.reduce((sum, line) => sum + Number(line.amount || 0), 0),
    }))
    .sort((a, b) => {
      if (a.billing_year !== b.billing_year) return b.billing_year - a.billing_year
      return b.billing_month - a.billing_month
    })
})

const grandTotal = computed(() =>
  statements.value.reduce((sum, statement) => sum + statement.monthTotal, 0),
)

const totalLineCount = computed(() =>
  statements.value.reduce((sum, statement) => sum + statement.lines.length, 0),
)
</script>

<style scoped>
@media print {
  .charge-statement-month {
    break-inside: avoid-page;
    page-break-inside: avoid;
    border: 1px solid #d4d4d8 !important;
    box-shadow: none !important;
    margin-bottom: 1rem;
  }

  .print-page-break {
    break-after: page;
    page-break-after: always;
  }

  .print-hidden {
    display: none !important;
  }
}
.charge-statement-compact .charge-statement-month {
  margin-bottom: 0.75rem;
}

.charge-statement-compact .charge-statement-month:last-child {
  margin-bottom: 0;
}
</style>
