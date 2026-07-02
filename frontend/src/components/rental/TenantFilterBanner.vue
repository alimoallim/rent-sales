<template>
  <div
    v-if="tenantId"
    class="alert-info mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
  >
    <span class="text-sm">
      Showing {{ label }} for
      <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ tenantName || `tenant #${tenantId}` }}</span>
    </span>
    <button type="button" class="btn-ghost !min-h-8 self-start sm:self-auto" @click="$emit('clear')">
      Show all tenants
    </button>
  </div>
</template>

<script setup>
defineProps({
  tenantId: { type: [Number, String], default: '' },
  tenantName: { type: String, default: '' },
  label: { type: String, default: 'records' },
})

defineEmits(['clear'])
</script>
