<template>
  <span class="badge" :class="variantClass">
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: (v) => ['success', 'warning', 'neutral', 'credit', 'danger', 'info', 'accent'].includes(v),
  },
  label: { type: String, default: '' },
})

const variantClass = computed(() => {
  const map = {
    success: 'badge-success',
    warning: 'badge-warning',
    neutral: 'badge-neutral',
    credit: 'badge-success',
    danger: 'badge-danger',
    info: 'badge-info',
    accent: 'badge-accent',
  }
  return map[props.variant]
})
</script>
