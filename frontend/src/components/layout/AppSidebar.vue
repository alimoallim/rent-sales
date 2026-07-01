<template>
  <aside
    class="app-sidebar"
    :class="drawerClass"
    :aria-hidden="!mobileOpen && isDrawerMode ? true : undefined"
  >
    <div class="app-sidebar-brand">
      <RouterLink :to="homePath" class="app-sidebar-brand-link" @click="$emit('navigate')">
        <span class="app-sidebar-logo" aria-hidden="true">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 21V9.75A2.25 2.25 0 0 1 6.75 7.5h10.5a2.25 2.25 0 0 1 2.25 2.25V21M9.75 21v-4.5a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5V21" />
          </svg>
        </span>
        <span class="min-w-0">
          <span class="block truncate text-sm font-semibold tracking-tight text-white">Rent & Sales</span>
          <span class="block truncate text-[11px] font-medium text-zinc-500">{{ moduleLabel }}</span>
        </span>
      </RouterLink>

      <button
        type="button"
        class="app-sidebar-close lg:hidden"
        aria-label="Close navigation menu"
        @click="$emit('close')"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <nav class="app-sidebar-nav" aria-label="Main navigation">
      <div v-for="(section, index) in sections" :key="section.label" class="app-sidebar-section">
        <p v-if="index > 0" class="app-sidebar-divider" aria-hidden="true" />
        <p class="app-sidebar-section-label">{{ section.label }}</p>
        <ul class="space-y-0.5">
          <li v-for="item in section.items" :key="item.to">
            <RouterLink
              :to="item.to"
              class="nav-link"
              :class="{ 'nav-link-active': isActive(item) }"
              @click="$emit('navigate')"
            >
              <NavIcon v-if="item.icon" :name="item.icon" class="nav-link-icon" />
              <span class="truncate">{{ item.label }}</span>
              <span
                v-if="item.badgeKey && badges[item.badgeKey] > 0"
                class="nav-link-badge"
              >
                {{ badges[item.badgeKey] > 99 ? '99+' : badges[item.badgeKey] }}
              </span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <div v-if="userName" class="app-sidebar-footer">
      <div class="app-sidebar-user">
        <span class="app-sidebar-avatar" aria-hidden="true">{{ userInitials }}</span>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-zinc-200">{{ userName }}</p>
          <p class="truncate text-[11px] text-zinc-500">{{ roleLabel }}</p>
        </div>
      </div>
    </div>
  </aside>

  <Transition name="sidebar-backdrop">
    <div
      v-if="mobileOpen"
      class="app-sidebar-backdrop lg:hidden"
      aria-hidden="true"
      @click="$emit('close')"
    />
  </Transition>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import NavIcon from './NavIcon.vue'

const props = defineProps({
  sections: { type: Array, required: true },
  badges: { type: Object, default: () => ({}) },
  mobileOpen: { type: Boolean, default: false },
  homePath: { type: String, default: '/rental' },
  moduleLabel: { type: String, default: 'Rental' },
  userName: { type: String, default: '' },
  userRole: { type: String, default: '' },
})

defineEmits(['navigate', 'close'])

const route = useRoute()

const isDrawerMode = computed(() => true)

const drawerClass = computed(() => {
  if (props.mobileOpen) {
    return 'app-sidebar-open'
  }
  return 'app-sidebar-collapsed'
})

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

function isActive(item) {
  if (item.exact) {
    return route.path === item.to
  }
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}
</script>
