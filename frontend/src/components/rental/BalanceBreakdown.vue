<template>
  <div class="rounded-md border p-3 transition-all duration-200" :class="panelClass">
    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">{{ title }}</p>
    <p v-if="loading" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Loading balance…</p>
    <dl
      v-else-if="summary"
      class="mt-2 text-sm"
      :class="compact ? 'grid grid-cols-2 gap-x-4 gap-y-1' : 'space-y-1.5'"
    >
      <div class="flex justify-between gap-2 border-b border-zinc-100 dark:border-zinc-800 py-1">
        <dt class="text-zinc-600 dark:text-zinc-400">Water</dt>
        <dd class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(summary.water_owed, 'rental') }}</dd>
      </div>
      <div class="flex justify-between gap-2 border-b border-zinc-100 dark:border-zinc-800 py-1">
        <dt class="text-zinc-600 dark:text-zinc-400">Electricity</dt>
        <dd class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(summary.electricity_owed, 'rental') }}</dd>
      </div>
      <div class="flex justify-between gap-2 border-b border-zinc-100 dark:border-zinc-800 py-1">
        <dt class="text-zinc-600 dark:text-zinc-400">Services</dt>
        <dd class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(summary.services_owed, 'rental') }}</dd>
      </div>
      <div class="flex justify-between gap-2 border-b border-zinc-100 dark:border-zinc-800 py-1">
        <dt class="text-zinc-600 dark:text-zinc-400">Rent</dt>
        <dd class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(summary.rent_owed, 'rental') }}</dd>
      </div>
      <div
        class="flex justify-between gap-3 border-t border-zinc-200 dark:border-zinc-700 pt-1.5"
        :class="{ 'col-span-2': compact }"
      >
        <dt class="font-semibold text-zinc-900 dark:text-zinc-100">Total due</dt>
        <dd class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(summary.total_due, 'rental') }}</dd>
      </div>
      <div
        v-if="summary.status === 'credit'"
        class="flex justify-between gap-3"
        :class="{ 'col-span-2': compact }"
      >
        <dt class="font-semibold text-emerald-800">Credit balance</dt>
        <dd class="font-semibold tabular-nums text-emerald-800">{{ formatMoney(summary.credit_balance, 'rental') }}</dd>
      </div>
    </dl>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatMoney } from '../../utils/money'

const props = defineProps({
  summary: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})


const title = computed(() => {
  if (!props.summary) return 'Outstanding balance'
  if (props.summary.status === 'paid_up') return 'Tenant is fully paid up'
  if (props.summary.status === 'credit') return 'Tenant has a credit balance'
  return 'Outstanding balance'
})

const panelClass = computed(() => {
  if (!props.summary) return 'border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50'
  if (props.summary.status === 'paid_up' || props.summary.status === 'credit') {
    return 'border-emerald-200 bg-emerald-50/50'
  }
  return 'border-amber-200 bg-amber-50/50'
})
</script>
