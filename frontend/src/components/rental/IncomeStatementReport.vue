<template>
  <article class="income-statement mx-auto max-w-3xl">
    <header class="border-b-2 border-zinc-900 px-5 py-6 text-center sm:px-8">
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Rental income statement</p>
      <h2 class="mt-2 text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
        {{ buildingName }}
      </h2>
      <p class="mt-1 text-sm text-zinc-600">For the month of {{ statement.period_label }}</p>
      <p v-if="statement.calculation_mode === 'legacy'" class="mt-3 inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
        Legacy calculation mode
      </p>
    </header>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
            <th class="px-5 py-3 sm:px-8">Description</th>
            <th class="px-5 py-3 text-right sm:px-8">Amount (KES)</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="section in sections" :key="section.id">
            <tr class="bg-zinc-100/80">
              <td colspan="2" class="px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-zinc-700 sm:px-8">
                {{ section.title }}
              </td>
            </tr>
            <tr
              v-for="line in section.items"
              :key="`${section.id}-${line.key}`"
              class="border-b border-zinc-100"
            >
              <td class="px-5 py-2.5 sm:px-8" :class="line.type === 'deduction' ? 'pl-8 text-zinc-600 sm:pl-12' : 'pl-8 text-zinc-800 sm:pl-12'">
                {{ line.label }}
              </td>
              <td
                class="px-5 py-2.5 text-right tabular-nums sm:px-8"
                :class="line.type === 'deduction' ? 'text-zinc-600' : 'text-zinc-900'"
              >
                {{ formatLineAmount(statement.lines[line.key], line.type) }}
              </td>
            </tr>
            <tr class="border-b-2 border-zinc-200 bg-zinc-50/60">
              <td class="px-5 py-3 pl-8 font-semibold text-zinc-900 sm:px-8 sm:pl-12">
                {{ section.subtotal.label }}
              </td>
              <td class="px-5 py-3 text-right font-semibold tabular-nums text-zinc-900 sm:px-8">
                {{ formatMoney(statement.lines[section.subtotal.key]) }}
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <section class="border-t-2 border-zinc-900 bg-zinc-50 px-5 py-5 sm:px-8">
      <p class="text-xs font-bold uppercase tracking-wide text-zinc-500">Summary</p>
      <dl class="mt-3 space-y-2">
        <div class="flex items-baseline justify-between gap-4 text-sm text-zinc-700">
          <dt>Net rent income</dt>
          <dd class="font-medium tabular-nums text-zinc-900">{{ formatMoney(statement.lines.rent_net) }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 text-sm text-zinc-700">
          <dt>Service + water net</dt>
          <dd class="font-medium tabular-nums text-zinc-900">{{ formatMoney(statement.lines.service_water_net) }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 border-t border-zinc-300 pt-3">
          <dt class="text-base font-semibold text-zinc-900">Net balance in hand</dt>
          <dd class="text-lg font-bold tabular-nums text-zinc-900">
            {{ formatMoney(statement.lines.net_balance_in_hand) }}
          </dd>
        </div>
      </dl>
    </section>

    <footer class="flex flex-col gap-3 border-t border-zinc-200 px-5 py-4 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 print:py-6">
      <p>Generated {{ generatedLabel }}</p>
      <p class="text-zinc-400">All amounts in Kenyan Shillings (KES)</p>
    </footer>

    <div v-if="showActions" class="flex flex-wrap gap-2 border-t border-zinc-200 px-5 py-4 print:hidden sm:px-8">
      <button type="button" class="btn-secondary" @click="$emit('export')">
        Export CSV
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  statement: { type: Object, required: true },
  buildingName: { type: String, default: 'Selected building' },
  showActions: { type: Boolean, default: true },
})

defineEmits(['export'])

const sections = [
  {
    id: 'rent',
    title: 'Rent collections',
    items: [
      { key: 'rent_collections', label: 'Gross rent collected' },
      { key: 'service_income', label: 'Less: service income', type: 'deduction' },
      { key: 'shareholder_deductions', label: 'Less: shareholder bills', type: 'deduction' },
    ],
    subtotal: { key: 'rent_net', label: 'Net rent income' },
  },
  {
    id: 'service-water',
    title: 'Service & water revenue',
    items: [
      { key: 'service_income', label: 'Service income' },
      { key: 'water_income', label: 'Tenant water bills' },
    ],
    subtotal: { key: 'service_water_subtotal', label: 'Service + water subtotal' },
  },
  {
    id: 'expenses',
    title: 'Operating expenses',
    items: [
      { key: 'expenses', label: 'General expenses' },
      { key: 'payroll', label: 'Payroll' },
      { key: 'electricity', label: 'Building electricity' },
      { key: 'nairobi_water', label: 'Nairobi water' },
    ],
    subtotal: { key: 'expense_subtotal', label: 'Total operating expenses' },
  },
  {
    id: 'service-water-net',
    title: 'Service & water net',
    items: [
      { key: 'service_water_subtotal', label: 'Service + water revenue' },
      { key: 'expense_subtotal', label: 'Less: operating expenses', type: 'deduction' },
    ],
    subtotal: { key: 'service_water_net', label: 'Service + water net balance' },
  },
]

const generatedLabel = computed(() => {
  if (!props.statement?.generated_at) return new Date().toLocaleString('en-KE')
  return new Date(props.statement.generated_at).toLocaleString('en-KE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
})

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))
}

function formatLineAmount(value, type) {
  const amount = Number(value || 0)
  const formatted = formatMoney(Math.abs(amount))
  if (type === 'deduction' && amount !== 0) return `(${formatted})`
  return formatted
}
</script>

<style scoped>
@media print {
  .income-statement {
    max-width: none;
    box-shadow: none;
  }
}
</style>
