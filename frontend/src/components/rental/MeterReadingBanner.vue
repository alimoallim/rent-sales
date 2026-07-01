<template>
  <div
    v-for="reminder in reminders"
    :key="`${reminder.utility}-${reminder.billing_month}-${reminder.billing_year}`"
    class="alert-warning"
  >
    <p class="text-sm font-semibold text-amber-950">Meter reading required before payment</p>
    <p class="mt-1 text-sm">{{ reminder.message }}</p>
    <button
      v-if="reminder.can_record_reading"
      type="button"
      class="btn-primary mt-2 !min-h-8 !py-1.5"
      @click="$emit('record-reading', reminder)"
    >
      {{ reminder.action_label }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  reminders: { type: Array, default: () => [] },
})

defineEmits(['record-reading'])
</script>
