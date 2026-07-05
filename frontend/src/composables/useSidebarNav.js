import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { isNavSectionActive, isCollapsibleNavSection } from '../config/rentalNav'

const STORAGE_PREFIX = 'sidebar-nav-open'

function storageKey(moduleKey) {
  return `${STORAGE_PREFIX}:${moduleKey}`
}

function readStoredOpenSections(moduleKey) {
  try {
    const raw = localStorage.getItem(storageKey(moduleKey))
    if (!raw) return null
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : null
  } catch {
    return null
  }
}

function writeStoredOpenSections(moduleKey, openIds) {
  try {
    localStorage.setItem(storageKey(moduleKey), JSON.stringify(openIds))
  } catch {
    // Ignore quota / private mode errors.
  }
}

/**
 * @param {import('vue').Ref<string>} moduleKey
 * @param {import('vue').Ref<Array<{ id?: string, defaultOpen?: boolean, items: unknown[] }>>} sections
 */
export function useSidebarNav(moduleKey, sections) {
  const route = useRoute()
  const openSections = ref(new Set())

  function collapsibleSectionIds() {
    return sections.value
      .filter((section) => section.id && isCollapsibleNavSection(section))
      .map((section) => section.id)
  }

  function defaultOpenIds() {
    return sections.value
      .filter((section) => section.id && section.defaultOpen)
      .map((section) => section.id)
  }

  function activeSectionIds() {
    return sections.value
      .filter((section) => section.id && isNavSectionActive(route, section))
      .map((section) => section.id)
  }

  function hydrateOpenSections() {
    const ids = collapsibleSectionIds()
    const stored = readStoredOpenSections(moduleKey.value)
    const next = new Set(stored?.filter((id) => ids.includes(id)) ?? defaultOpenIds())

    activeSectionIds().forEach((id) => next.add(id))
    openSections.value = next
  }

  function isOpen(sectionId) {
    return openSections.value.has(sectionId)
  }

  function toggleSection(sectionId) {
    const next = new Set(openSections.value)
    if (next.has(sectionId)) {
      next.delete(sectionId)
    } else {
      next.add(sectionId)
    }
    openSections.value = next
    writeStoredOpenSections(moduleKey.value, [...next])
  }

  function openSection(sectionId) {
    if (openSections.value.has(sectionId)) return
    const next = new Set(openSections.value)
    next.add(sectionId)
    openSections.value = next
    writeStoredOpenSections(moduleKey.value, [...next])
  }

  watch([moduleKey, sections], hydrateOpenSections, { immediate: true, deep: true })

  watch(
    () => route.path,
    () => {
      activeSectionIds().forEach((id) => openSection(id))
    },
  )

  const openSectionIds = computed(() => [...openSections.value])

  return {
    isOpen,
    toggleSection,
    openSectionIds,
  }
}
