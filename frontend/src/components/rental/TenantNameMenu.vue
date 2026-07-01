<template>
  <div ref="root" class="relative inline-block max-w-full text-left">
    <button
      type="button"
      class="max-w-full truncate text-left text-sm font-medium text-indigo-600 transition-colors duration-200 hover:text-indigo-800 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 rounded"
      :aria-expanded="open"
      aria-haspopup="true"
      @click.stop="toggle"
    >
      {{ tenantName }}
    </button>
    <div
      v-if="open"
      class="absolute left-0 z-30 mt-1 min-w-[11rem] rounded-md border border-zinc-200 bg-white py-1 shadow-lg"
      role="menu"
    >
      <RouterLink
        :to="paymentsTo"
        class="block px-3 py-2 text-sm text-zinc-700 transition-colors duration-200 hover:bg-zinc-50"
        role="menuitem"
        @click="close"
      >
        Payment history
      </RouterLink>
      <RouterLink
        :to="chargesTo"
        class="block px-3 py-2 text-sm text-zinc-700 transition-colors duration-200 hover:bg-zinc-50"
        role="menuitem"
        @click="close"
      >
        Charges
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { tenantChargesRoute, tenantPaymentsRoute } from '../../utils/tenantRoutes'

const props = defineProps({
  tenantId: { type: [Number, String], required: true },
  tenantName: { type: String, required: true },
  buildingId: { type: [Number, String], default: null },
})

const open = ref(false)
const root = ref(null)

const paymentsTo = computed(() =>
  tenantPaymentsRoute(props.tenantId, props.buildingId, props.tenantName),
)

const chargesTo = computed(() =>
  tenantChargesRoute(props.tenantId, props.buildingId, props.tenantName),
)

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
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
