<template>
  <div class="app-shell">
    <AppSidebar
      :sections="navSections"
      :badges="navBadges"
      :mobile-open="sidebarOpen"
      :home-path="homePath"
      :module-label="sidebarModuleLabel"
      :user-name="auth.user?.name ?? ''"
      :user-role="auth.role ?? ''"
      :show-module-switcher="auth.isAdmin"
      :current-module="navModule"
      @navigate="sidebarOpen = false"
      @close="sidebarOpen = false"
      @switch-module="onSwitchModule"
    />

    <div class="app-shell-main">
      <AppTopbar
        :page-title="pageTitle"
        :module-label="topbarModuleLabel"
        :user-name="auth.user?.name ?? ''"
        :user-role="auth.role ?? ''"
        :show-module-switcher="auth.isAdmin"
        :current-module="navModule"
        @toggle-menu="sidebarOpen = !sidebarOpen"
        @logout="logout"
        @switch-module="switchModule"
      />

      <main class="app-shell-content">
        <div class="page-view mx-auto max-w-7xl">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppTopbar from '../components/layout/AppTopbar.vue'
import { adminNavSections, moduleLabels, rentalNavSections, salesNavSections } from '../config/rentalNav'
import { fetchChargeBatchPendingCount } from '../api/rental'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const navBadges = ref({ chargeBatches: 0 })

const navModule = computed(() => {
  if (route.path.startsWith('/sales')) return 'sales'
  if (route.path.startsWith('/rental')) return 'rental'
  return auth.preferredModule() === 'sales' ? 'sales' : 'rental'
})

const isAdminRoute = computed(() => route.path.startsWith('/admin'))

const sidebarModuleLabel = computed(() => `${moduleLabels[navModule.value]} module`)

const topbarModuleLabel = computed(() => {
  if (isAdminRoute.value) return 'Administration'
  return moduleLabels[navModule.value]
})

const homePath = computed(() => (navModule.value === 'sales' ? '/sales' : '/rental'))

const navSections = computed(() => {
  const sections = navModule.value === 'sales' ? [...salesNavSections] : [...rentalNavSections]

  if (auth.isAdmin) {
    sections.push(...adminNavSections)
  }

  return sections
})

const pageTitle = computed(() => {
  const meta = route.meta?.title
  if (meta) return meta

  for (const section of navSections.value) {
    for (const item of section.items) {
      if (item.exact && route.path === item.to) return item.label
      if (!item.exact && (route.path === item.to || route.path.startsWith(`${item.to}/`))) {
        return item.label
      }
    }
  }

  return 'Dashboard'
})

async function logout() {
  await auth.logout()
  await router.push({ name: 'login' })
}

async function switchModule(module) {
  auth.setPreferredModule(module)
  sidebarOpen.value = false
  await router.push(module === 'sales' ? '/sales' : '/rental')
}

async function onSwitchModule(module) {
  await switchModule(module)
}

async function loadChargeBatchBadge() {
  if (!auth.canAccessRental || navModule.value !== 'rental') {
    navBadges.value.chargeBatches = 0
    return
  }

  try {
    const response = await fetchChargeBatchPendingCount()
    navBadges.value.chargeBatches = response.count || 0
  } catch {
    navBadges.value.chargeBatches = 0
  }
}

watch(navModule, loadChargeBatchBadge)

onMounted(loadChargeBatchBadge)
</script>
