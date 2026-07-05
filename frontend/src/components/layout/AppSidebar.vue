<template>
  <aside
    class="app-sidebar"
    :class="[drawerClass, collapsed ? 'app-sidebar-rail' : '']"
    :aria-hidden="!mobileOpen && isDrawerMode ? true : undefined"
  >
    <button
      type="button"
      class="app-sidebar-collapse-toggle hidden lg:flex"
      :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="$emit('toggle-collapse')"
    >
      <svg
        class="h-3.5 w-3.5 transition-transform duration-300"
        :class="collapsed ? 'rotate-180' : ''"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.5"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7" />
      </svg>
    </button>

    <div class="app-sidebar-brand" :class="collapsed ? 'lg:justify-center lg:px-2' : ''">
      <RouterLink
        :to="homePath"
        class="app-sidebar-brand-link"
        :class="collapsed ? 'lg:flex-none lg:justify-center' : ''"
        :title="collapsed ? 'Rent & Sales' : undefined"
        @click="$emit('navigate')"
      >
        <span class="app-sidebar-logo" aria-hidden="true">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 21V9.75A2.25 2.25 0 0 1 6.75 7.5h10.5a2.25 2.25 0 0 1 2.25 2.25V21M9.75 21v-4.5a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5V21" />
          </svg>
        </span>
        <span class="min-w-0" :class="collapsed ? 'lg:hidden' : ''">
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

    <div v-if="showModuleSwitcher" class="app-sidebar-module-switch px-3 pb-2 lg:hidden">
      <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-zinc-500">Switch module</p>
      <ModuleSwitcher
        :current-module="currentModule"
        @switch="$emit('switch-module', $event)"
      />
    </div>

    <nav class="app-sidebar-nav" aria-label="Main navigation">
      <div class="app-sidebar-nav-stack">
        <template v-for="section in sections" :key="section.id ?? section.label">
          <div v-if="section.separated" class="app-sidebar-divider" aria-hidden="true" />

          <div v-if="isStandaloneNavSection(section)" class="app-sidebar-standalone">
            <RouterLink
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="nav-link nav-link-primary"
              :class="[{ 'nav-link-active': isActive(item) }, collapsed ? 'lg:justify-center' : '']"
              :title="collapsed ? item.label : undefined"
              @click="$emit('navigate')"
            >
              <NavIcon v-if="item.icon" :name="item.icon" class="nav-link-icon" />
              <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">{{ item.label }}</span>
              <span
                v-if="item.badgeKey && badges[item.badgeKey] > 0"
                class="nav-link-badge"
                :class="collapsed ? 'lg:hidden' : ''"
              >
                {{ badges[item.badgeKey] > 99 ? '99+' : badges[item.badgeKey] }}
              </span>
              <span
                v-if="collapsed && item.badgeKey && badges[item.badgeKey] > 0"
                class="nav-rail-dot hidden lg:block"
                aria-hidden="true"
              />
            </RouterLink>
          </div>

          <AppSidebarNavGroup
            v-else-if="isCollapsibleNavSection(section)"
            :section="section"
            :badges="badges"
            :open="isOpen(section.id)"
            :rail="collapsed"
            :badge-count="sectionBadgeCount(section, badges)"
            :has-active-child="isNavSectionActive(route, section)"
            @toggle="onGroupToggle(section)"
            @navigate="$emit('navigate')"
          />

          <div v-else class="app-sidebar-standalone">
            <RouterLink
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="nav-link nav-link-primary"
              :class="[{ 'nav-link-active': isActive(item) }, collapsed ? 'lg:justify-center' : '']"
              :title="collapsed ? item.label : undefined"
              @click="$emit('navigate')"
            >
              <NavIcon v-if="item.icon" :name="item.icon" class="nav-link-icon" />
              <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">{{ item.label }}</span>
              <span
                v-if="item.badgeKey && badges[item.badgeKey] > 0"
                class="nav-link-badge"
                :class="collapsed ? 'lg:hidden' : ''"
              >
                {{ badges[item.badgeKey] > 99 ? '99+' : badges[item.badgeKey] }}
              </span>
              <span
                v-if="collapsed && item.badgeKey && badges[item.badgeKey] > 0"
                class="nav-rail-dot hidden lg:block"
                aria-hidden="true"
              />
            </RouterLink>
          </div>
        </template>
      </div>
    </nav>

    <div v-if="userName" class="app-sidebar-footer" :class="collapsed ? 'lg:p-2' : ''">
      <div
        class="app-sidebar-user"
        :class="collapsed ? 'lg:justify-center lg:bg-transparent lg:px-0' : ''"
        :title="collapsed ? `${userName} — ${roleLabel}` : undefined"
      >
        <span class="app-sidebar-avatar" aria-hidden="true">{{ userInitials }}</span>
        <div class="min-w-0 flex-1" :class="collapsed ? 'lg:hidden' : ''">
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
import { computed, toRef } from 'vue'
import { useRoute } from 'vue-router'
import NavIcon from './NavIcon.vue'
import ModuleSwitcher from './ModuleSwitcher.vue'
import AppSidebarNavGroup from './AppSidebarNavGroup.vue'
import { useSidebarNav } from '../../composables/useSidebarNav'
import {
  isCollapsibleNavSection,
  isNavItemActive,
  isNavSectionActive,
  isStandaloneNavSection,
  sectionBadgeCount,
} from '../../config/rentalNav'

const props = defineProps({
  sections: { type: Array, required: true },
  badges: { type: Object, default: () => ({}) },
  mobileOpen: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
  homePath: { type: String, default: '/rental' },
  moduleLabel: { type: String, default: 'Rental' },
  userName: { type: String, default: '' },
  userRole: { type: String, default: '' },
  showModuleSwitcher: { type: Boolean, default: false },
  currentModule: { type: String, default: 'rental' },
})

const emit = defineEmits(['navigate', 'close', 'switch-module', 'toggle-collapse', 'expand'])

const route = useRoute()

const moduleKey = computed(() => props.currentModule)
const sectionsRef = toRef(props, 'sections')

const { isOpen, toggleSection } = useSidebarNav(moduleKey, sectionsRef)

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
  return isNavItemActive(route, item)
}

function onGroupToggle(section) {
  // In desktop rail mode, group panels are hidden; expand the sidebar and
  // make sure the clicked group ends up open.
  const isDesktopRail = props.collapsed && window.matchMedia('(min-width: 1024px)').matches

  if (isDesktopRail) {
    emit('expand')
    if (!isOpen(section.id)) {
      toggleSection(section.id)
    }
    return
  }

  toggleSection(section.id)
}
</script>
