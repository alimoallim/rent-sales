<template>
  <label class="block text-sm">
    <span class="label-field">
      {{ label }}
      <span v-if="required" class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
    </span>
    <slot />
    <p v-if="error" class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ hint }}</p>
  </label>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
})
</script>
