<template>
  <time
    v-if="iso"
    :datetime="iso"
    class="text-sm text-zinc-700 dark:text-zinc-300"
    :title="fullLabel"
  >
    {{ display }}
  </time>
  <span v-else class="text-zinc-400">—</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: [String, Date, Number], default: null },
  format: {
    type: String,
    default: 'medium',
    validator: (v) => ['short', 'medium', 'long', 'datetime'].includes(v),
  },
})

const date = computed(() => {
  if (!props.value) return null
  const d = props.value instanceof Date ? props.value : new Date(props.value)
  return Number.isNaN(d.getTime()) ? null : d
})

const iso = computed(() => date.value?.toISOString() ?? null)

const display = computed(() => {
  if (!date.value) return '—'
  if (props.format === 'datetime') {
    return date.value.toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' })
  }
  return date.value.toLocaleDateString('en-KE', { dateStyle: props.format })
})

const fullLabel = computed(() => {
  if (!date.value) return ''
  return date.value.toLocaleString('en-KE', { dateStyle: 'full', timeStyle: 'short' })
})
</script>
