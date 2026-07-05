<template>
  <div
    class="animate-pulse rounded-md bg-zinc-200 dark:bg-zinc-800"
    :class="sizeClass"
    :style="customStyle"
    aria-hidden="true"
  />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  width: { type: String, default: '' },
  height: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg', 'full'].includes(v),
  },
})

const sizeClass = computed(() => ({
  xs: 'h-3 w-12',
  sm: 'h-4 w-20',
  md: 'h-5 w-32',
  lg: 'h-8 w-full',
  full: 'h-full w-full min-h-[1rem]',
}[props.size]))

const customStyle = computed(() => ({
  width: props.width || undefined,
  height: props.height || undefined,
}))
</script>
