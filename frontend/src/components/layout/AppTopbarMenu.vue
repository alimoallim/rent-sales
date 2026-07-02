<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="app-topbar-icon-btn"
      :aria-expanded="open"
      aria-haspopup="menu"
      aria-label="Account menu"
      @click="toggle"
    >
      <span class="app-topbar-avatar">{{ userInitials }}</span>
    </button>

    <Transition name="topbar-menu">
      <div
        v-if="open"
        class="app-topbar-menu"
        role="menu"
        aria-label="Account"
      >
        <div class="app-topbar-menu-header">
          <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ userName }}</p>
          <p class="truncate text-[11px] text-zinc-500 dark:text-zinc-400">{{ roleLabel }}</p>
        </div>
        <button
          type="button"
          class="app-topbar-menu-item"
          role="menuitem"
          @click="signOut"
        >
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
          </svg>
          Sign out
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  userName: { type: String, default: '' },
  userRole: { type: String, default: '' },
})

const emit = defineEmits(['logout'])

const open = ref(false)
const root = ref(null)

const userInitials = computed(() => {
  const parts = props.userName.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
})

const roleLabel = computed(() => {
  const labels = {
    admin: 'Administrator',
    rental: 'Rental staff',
    sales: 'Sales staff',
  }
  return labels[props.userRole] ?? 'Staff'
})

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function signOut() {
  close()
  emit('logout')
}

function onDocumentClick(event) {
  if (!root.value?.contains(event.target)) {
    close()
  }
}

function onEscape(event) {
  if (event.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onEscape)
})
</script>
