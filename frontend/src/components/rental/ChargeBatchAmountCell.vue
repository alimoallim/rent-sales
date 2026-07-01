<template>
  <div class="tabular-nums leading-tight">
    <button
      v-if="editable && canEdit"
      type="button"
      class="group inline-block max-w-full text-right"
      :title="pendingLabel"
      @click="$emit('edit', item)"
    >
      <span class="block font-medium text-zinc-900 group-hover:text-indigo-700">{{ displayText }}</span>
      <span v-if="showHint" class="block text-[10px] leading-3 text-zinc-500">{{ hintText }}</span>
    </button>
    <div v-else class="inline-block max-w-full text-right" :title="pendingLabel">
      <span class="block" :class="amountClass">{{ displayText }}</span>
      <span v-if="showHint" class="block text-[10px] leading-3 text-zinc-500">{{ hintText }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  item: { type: Object, default: null },
  editable: { type: Boolean, default: false },
})

defineEmits(['edit'])

const isPending = computed(() => {
  if (!props.item) return false
  return props.item.item_status === 'pending' || props.item.amount === null
})

const canEdit = computed(() => {
  if (!props.item) return false
  return props.item.item_status === 'draft' || props.item.item_status === 'pending'
})

const displayText = computed(() => {
  if (!props.item) return '—'
  if (props.item.item_status === 'excluded') return '—'
  if (isPending.value) return 'Pending'
  return formatMoney(props.item.amount)
})

const amountClass = computed(() => {
  if (!props.item || props.item.item_status === 'excluded') return 'text-zinc-400'
  if (isPending.value) return 'text-amber-700 font-medium'
  if (props.item.item_status === 'approved') return 'text-zinc-900 font-medium'
  return 'text-zinc-900'
})

const pendingLabel = computed(() => {
  if (!props.item?.pending_reason) return ''
  return ({
    missing_water_reading: 'Water meter reading not recorded',
    missing_electricity_reading: 'Electricity meter reading not recorded',
  })[props.item.pending_reason] || ''
})

const showHint = computed(() => props.item?.manually_adjusted || isPending.value)

const hintText = computed(() => {
  if (props.item?.manually_adjusted) return 'Adjusted'
  if (isPending.value) return pendingLabel.value || 'Missing reading'
  return ''
})

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))
}
</script>
