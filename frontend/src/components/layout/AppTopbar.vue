<template>
  <header class="app-topbar">
    <div class="app-topbar-start">
      <button
        type="button"
        class="app-topbar-icon-btn lg:hidden"
        aria-label="Open navigation menu"
        @click="$emit('toggle-menu')"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
        </svg>
      </button>

      <div class="app-topbar-context">
        <p class="app-topbar-mobile-title">
          <span class="app-topbar-module-chip">{{ moduleLabel }}</span>
          <span class="app-topbar-mobile-page">{{ pageTitle }}</span>
        </p>

        <nav class="app-topbar-breadcrumb hidden md:flex" aria-label="Breadcrumb">
          <span class="app-topbar-breadcrumb-module">{{ moduleLabel }}</span>
          <svg class="h-3.5 w-3.5 shrink-0 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" d="m9 6 6 6-6 6" />
          </svg>
          <span class="app-topbar-breadcrumb-page">{{ pageTitle }}</span>
        </nav>
      </div>
    </div>

    <div class="app-topbar-end">
      <ModuleSwitcher
        v-if="showModuleSwitcher"
        class="md:hidden"
        compact
        :current-module="currentModule"
        @switch="$emit('switch-module', $event)"
      />

      <ModuleSwitcher
        v-if="showModuleSwitcher"
        class="hidden md:inline-flex"
        :current-module="currentModule"
        @switch="$emit('switch-module', $event)"
      />

      <ThemeToggle class="app-topbar-theme" />

      <div class="app-topbar-user hidden md:flex">
        <span class="app-topbar-avatar" aria-hidden="true">{{ userInitials }}</span>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ userName }}</p>
          <p class="truncate text-[11px] text-zinc-500 dark:text-zinc-400">{{ roleLabel }}</p>
        </div>
      </div>

      <button
        type="button"
        class="app-topbar-signout hidden md:inline-flex"
        @click="$emit('logout')"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
        </svg>
        <span>Sign out</span>
      </button>

      <AppTopbarMenu
        class="md:hidden"
        :user-name="userName"
        :user-role="userRole"
        @logout="$emit('logout')"
      />
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import ThemeToggle from '../ui/ThemeToggle.vue'
import ModuleSwitcher from './ModuleSwitcher.vue'
import AppTopbarMenu from './AppTopbarMenu.vue'

const props = defineProps({
  pageTitle: { type: String, default: 'Dashboard' },
  moduleLabel: { type: String, default: '' },
  userName: { type: String, default: '' },
  userRole: { type: String, default: '' },
  showModuleSwitcher: { type: Boolean, default: false },
  currentModule: { type: String, default: 'rental' },
})

defineEmits(['toggle-menu', 'logout', 'switch-module'])

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
</script>
