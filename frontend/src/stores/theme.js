import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

const STORAGE_KEY = 'rsp-theme'

function systemPrefersDark() {
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export const useThemeStore = defineStore('theme', () => {
  const mode = ref('system')

  const isDark = computed(() => {
    if (mode.value === 'dark') return true
    if (mode.value === 'light') return false
    return systemPrefersDark()
  })

  function apply() {
    const dark = isDark.value
    document.documentElement.classList.toggle('dark', dark)
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
  }

  function setMode(next) {
    if (!['light', 'dark', 'system'].includes(next)) return
    mode.value = next
    localStorage.setItem(STORAGE_KEY, next)
    apply()
  }

  function toggle() {
    setMode(isDark.value ? 'light' : 'dark')
  }

  function init() {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved === 'light' || saved === 'dark' || saved === 'system') {
      mode.value = saved
    }

    apply()

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (mode.value === 'system') apply()
    })
  }

  return { mode, isDark, setMode, toggle, init }
})

export function initThemeBeforeMount() {
  const saved = localStorage.getItem(STORAGE_KEY)
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  const dark = saved === 'dark' || (saved !== 'light' && prefersDark)

  document.documentElement.classList.toggle('dark', dark)
  document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
}
