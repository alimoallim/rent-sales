<template>
  <div
    class="sidebar-nav-group"
    :class="{
      'sidebar-nav-group-open': open,
      'sidebar-nav-group-active': hasActiveChild,
    }"
  >
    <button
      type="button"
      class="sidebar-nav-group-trigger"
      :class="rail ? 'lg:justify-center' : ''"
      :aria-expanded="open"
      :aria-controls="panelId"
      :title="rail ? section.label : undefined"
      @click="$emit('toggle')"
    >
      <NavIcon v-if="section.icon" :name="section.icon" class="sidebar-nav-group-icon" />
      <span class="sidebar-nav-group-label" :class="rail ? 'lg:hidden' : ''">{{ section.label }}</span>
      <span
        v-if="badgeCount > 0"
        class="sidebar-nav-group-badge"
        :class="rail ? 'lg:hidden' : ''"
      >
        {{ badgeCount > 99 ? '99+' : badgeCount }}
      </span>
      <span
        v-if="rail && badgeCount > 0"
        class="nav-rail-dot hidden lg:block"
        aria-hidden="true"
      />
      <svg
        class="sidebar-nav-group-chevron"
        :class="rail ? 'lg:hidden' : ''"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
        aria-hidden="true"
      >
        <path stroke-linecap="round" d="m6 9 6 6 6-6" />
      </svg>
    </button>

    <div :id="panelId" class="sidebar-nav-group-panel" :class="rail ? 'lg:hidden' : ''" :hidden="!open">
      <ul class="sidebar-nav-group-list">
        <li v-for="item in section.items" :key="item.to">
          <RouterLink
            :to="item.to"
            class="nav-link nav-link-nested"
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
  </div>
</template>

<script setup>
import { useId } from 'vue'
import { useRoute } from 'vue-router'
import NavIcon from './NavIcon.vue'
import { isNavItemActive } from '../../config/rentalNav'

defineProps({
  section: { type: Object, required: true },
  badges: { type: Object, default: () => ({}) },
  open: { type: Boolean, default: false },
  rail: { type: Boolean, default: false },
  badgeCount: { type: Number, default: 0 },
  hasActiveChild: { type: Boolean, default: false },
})

defineEmits(['toggle', 'navigate'])

const route = useRoute()
const panelId = useId()

function isActive(item) {
  return isNavItemActive(route, item)
}
</script>
