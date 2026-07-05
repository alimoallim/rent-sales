<template>
  <div class="theme-toggle" role="group" aria-label="Color theme">
    <button
      type="button"
      class="theme-toggle-btn"
      :class="{ 'theme-toggle-btn-active': mode === 'light' }"
      title="Light mode"
      aria-label="Light mode"
      :aria-pressed="mode === 'light'"
      @click="theme.setMode('light')"
    >
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" d="M12 3v2.25M12 18.75V21M4.219 4.219l1.591 1.591M18.19 18.19l1.591 1.591M3 12h2.25M18.75 12H21M4.219 19.781l1.591-1.591M18.19 5.81l1.591-1.591M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
      </svg>
    </button>
    <button
      type="button"
      class="theme-toggle-btn"
      :class="{ 'theme-toggle-btn-active': mode === 'dark' }"
      title="Dark mode"
      aria-label="Dark mode"
      :aria-pressed="mode === 'dark'"
      @click="theme.setMode('dark')"
    >
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" d="M21.752 15.002A9.718 9.718 0 0 1 12 21.75c-5.385 0-9.75-4.365-9.75-9.75 0-4.106 2.554-7.62 6.155-9.047a.75.75 0 0 1 .97.742 7.5 7.5 0 0 0 9.108 8.962.75.75 0 0 1 .369.595Z" />
      </svg>
    </button>
    <button
      type="button"
      class="theme-toggle-btn hidden sm:inline-flex"
      :class="{ 'theme-toggle-btn-active': mode === 'system' }"
      title="System theme"
      aria-label="System theme"
      :aria-pressed="mode === 'system'"
      @click="theme.setMode('system')"
    >
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { useThemeStore } from '../../stores/theme'

const theme = useThemeStore()
const { mode } = storeToRefs(theme)
</script>
