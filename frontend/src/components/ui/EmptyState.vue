<template>
  <div class="empty-state-panel">
    <div
      v-if="icon"
      class="empty-state-icon"
      aria-hidden="true"
    >
      <slot name="icon" />
    </div>
    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'No records found' },
  description: { type: String, default: '' },
  icon: { type: Boolean, default: false },
})
</script>
