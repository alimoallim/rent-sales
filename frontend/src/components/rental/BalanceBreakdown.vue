<template>
  <div class="rounded-md border p-3 transition-all duration-200" :class="panelClass">
    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-600">{{ title }}</p>
    <p v-if="loading" class="mt-2 text-sm text-zinc-500">Loading balance…</p>
    <dl
      v-else-if="summary"
      class="mt-2 text-sm"
      :class="compact ? 'grid grid-cols-2 gap-x-4 gap-y-1' : 'space-y-1.5'"
    >
      <div class="flex justify-between gap-2 border-b border-zinc-100 py-1">
        <dt class="text-zinc-600">Water</dt>
        <dd class="font-medium tabular-nums text-zinc-900">{{ formatMoney(summary.water_owed) }}</dd>
      </div>
      <div class="flex justify-between gap-2 border-b border-zinc-100 py-1">
        <dt class="text-zinc-600">Electricity</dt>
        <dd class="font-medium tabular-nums text-zinc-900">{{ formatMoney(summary.electricity_owed) }}</dd>
      </div>
      <div class="flex justify-between gap-2 border-b border-zinc-100 py-1">
        <dt class="text-zinc-600">Services</dt>
        <dd class="font-medium tabular-nums text-zinc-900">{{ formatMoney(summary.services_owed) }}</dd>
      </div>
      <div class="flex justify-between gap-2 border-b border-zinc-100 py-1">
        <dt class="text-zinc-600">Rent</dt>
        <dd class="font-medium tabular-nums text-zinc-900">{{ formatMoney(summary.rent_owed) }}</dd>
      </div>
      <div
        class="flex justify-between gap-3 border-t border-zinc-200 pt-1.5"
        :class="{ 'col-span-2': compact }"
      >
        <dt class="font-semibold text-zinc-900">Total due</dt>
        <dd class="font-semibold tabular-nums text-zinc-900">{{ formatMoney(summary.total_due) }}</dd>
      </div>
      <div
        v-if="summary.status === 'credit'"
        class="flex justify-between gap-3"
        :class="{ 'col-span-2': compact }"
      >
        <dt class="font-semibold text-emerald-800">Credit balance</dt>
        <dd class="font-semibold tabular-nums text-emerald-800">{{ formatMoney(summary.credit_balance) }}</dd>
      </div>
    </dl>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  summary: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE').format(Number(value || 0))
}

const title = computed(() => {
  if (!props.summary) return 'Outstanding balance'
  if (props.summary.status === 'paid_up') return 'Tenant is fully paid up'
  if (props.summary.status === 'credit') return 'Tenant has a credit balance'
  return 'Outstanding balance'
})

const panelClass = computed(() => {
  if (!props.summary) return 'border-zinc-200 bg-zinc-50'
  if (props.summary.status === 'paid_up' || props.summary.status === 'credit') {
    return 'border-emerald-200 bg-emerald-50/50'
  }
  return 'border-amber-200 bg-amber-50/50'
})
</script>
