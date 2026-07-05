<template>
  <div ref="root" class="relative inline-block max-w-full text-left">
    <button
      type="button"
      class="max-w-full truncate text-left text-sm font-medium text-indigo-600 transition-colors duration-200 hover:text-indigo-800 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 rounded"
      :aria-expanded="open"
      aria-haspopup="true"
      @click.stop="toggle"
    >
      {{ clientName }}
    </button>
    <div
      v-if="open"
      class="absolute left-0 z-30 mt-1 min-w-[12rem] rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
      role="menu"
    >
      <button
        type="button"
        class="block w-full px-3 py-2 text-left text-sm text-zinc-700 transition-colors duration-200 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800"
        role="menuitem"
        @click="openHistory"
      >
        Payment history
      </button>
      <RouterLink
        :to="{ path: '/sales/payments', query: { client_id: clientId, building_id: buildingId, action: 'new' } }"
        class="block w-full px-3 py-2 text-left text-sm text-zinc-700 transition-colors duration-200 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800"
        role="menuitem"
        @click="close"
      >
        Record payment
      </RouterLink>
    </div>

    <ClientHistoryModal
      v-model:open="historyOpen"
      :client-id="clientId"
      :client-name="clientName"
      :building-id="buildingId"
    />
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import ClientHistoryModal from './ClientHistoryModal.vue'

defineProps({
  clientId: { type: [Number, String], required: true },
  clientName: { type: String, required: true },
  buildingId: { type: [Number, String], default: null },
})

const open = ref(false)
const historyOpen = ref(false)
const root = ref(null)

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function openHistory() {
  historyOpen.value = true
  close()
}

function onDocumentClick(event) {
  if (!open.value || !root.value) return
  if (!root.value.contains(event.target)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>
