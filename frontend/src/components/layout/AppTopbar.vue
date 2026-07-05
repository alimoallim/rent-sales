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

      <RouterLink
        to="/settings"
        class="app-topbar-icon-btn hidden md:inline-flex"
        aria-label="Settings"
        title="Settings"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
      </RouterLink>

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
