<template>
  <div class="inline-block max-w-full">
    <button
      type="button"
      class="max-w-full truncate text-left text-sm font-medium text-indigo-600 transition-colors duration-200 hover:text-indigo-800 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 rounded"
      :title="`View payment history for ${clientName}`"
      @click.stop="historyOpen = true"
    >
      {{ clientName }}
    </button>

    <ClientHistoryModal
      v-model:open="historyOpen"
      :client-id="clientId"
      :client-name="clientName"
      :building-id="buildingId"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ClientHistoryModal from './ClientHistoryModal.vue'

defineProps({
  clientId: { type: [Number, String], required: true },
  clientName: { type: String, required: true },
  buildingId: { type: [Number, String], default: null },
})

const historyOpen = ref(false)
</script>
