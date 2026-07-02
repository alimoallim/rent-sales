<template>
  <div class="mb-4 flex flex-col gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
    <div>
      <h2 class="page-title">{{ title }}</h2>
      <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap lg:w-auto">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>
