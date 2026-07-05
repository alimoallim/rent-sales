<template>
  <span
    class="tabular-nums"
    :class="[
      alignClass,
      toneClass,
    ]"
  >
    <slot>{{ formatted }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { formatMoney } from '../../utils/money'

const props = defineProps({
  amount: { type: [Number, String], default: 0 },
  module: { type: String, default: 'rental' },
  align: { type: String, default: 'right' },
  showSign: { type: Boolean, default: false },
  empty: { type: String, default: '—' },
})

const numeric = computed(() => Number(props.amount ?? 0))

const formatted = computed(() => {
  if (props.amount === null || props.amount === undefined || props.amount === '') {
    return props.empty
  }
  const value = formatMoney(Math.abs(numeric.value), props.module)
  if (props.showSign && numeric.value < 0) return `−${value}`
  if (props.showSign && numeric.value > 0) return value
  return numeric.value < 0 ? `−${formatMoney(Math.abs(numeric.value), props.module)}` : value
})

const alignClass = computed(() => ({
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
}[props.align] ?? 'text-right'))

const toneClass = computed(() => {
  if (props.amount === null || props.amount === undefined || props.amount === '') {
    return 'text-zinc-400'
  }
  if (numeric.value < 0) return 'font-medium text-red-600 dark:text-red-400'
  if (numeric.value === 0) return 'text-zinc-400 dark:text-zinc-500'
  return 'font-medium text-zinc-900 dark:text-zinc-100'
})
</script>
