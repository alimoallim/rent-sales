<template>
  <div ref="root" class="relative inline-flex max-w-full items-center gap-0.5 text-left">
    <button
      type="button"
      class="max-w-full truncate text-left text-sm font-medium text-indigo-600 transition-colors duration-200 hover:text-indigo-800 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 rounded"
      @click.stop="openProfile"
    >
      {{ clientName }}
    </button>
    <button
      type="button"
      class="shrink-0 rounded p-0.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
      :aria-expanded="open"
      aria-haspopup="true"
      aria-label="Client actions"
      @click.stop="toggle"
    >
      <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
        <path d="M6 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" />
      </svg>
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
        @click="openHistory('profile')"
      >
        Personal details
      </button>
      <button
        type="button"
        class="block w-full px-3 py-2 text-left text-sm text-zinc-700 transition-colors duration-200 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800"
        role="menuitem"
        @click="openHistory('payments')"
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
      v-model:tab="historyTab"
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
const historyTab = ref('profile')
const root = ref(null)

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function openProfile() {
  historyTab.value = 'profile'
  historyOpen.value = true
  close()
}

function openHistory(tab) {
  historyTab.value = tab
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
