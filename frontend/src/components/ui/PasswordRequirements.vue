<template>
  <div
    v-if="password || showWhenEmpty"
    class="rounded-lg border border-zinc-200 bg-zinc-100/80 p-2.5 dark:border-zinc-600 dark:bg-zinc-900/90"
    :class="compact ? 'mt-1.5' : 'mt-3'"
  >
    <div v-if="!compact && (password || showWhenEmpty)" class="mb-2 flex items-center justify-between gap-2">
      <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300">Password strength</span>
      <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="badgeClasses">
        {{ strengthLabel }}
      </span>
    </div>

    <p v-else-if="compact" class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300">
      Password requirements
    </p>

    <div v-if="!compact && password" class="mb-2 h-1 overflow-hidden rounded-full bg-zinc-300 dark:bg-zinc-600" aria-hidden="true">
      <span class="block h-full rounded-full transition-all duration-200" :class="meterClasses" :style="{ width: `${meterPercent}%` }" />
    </div>

    <ul class="grid gap-1" :class="compact ? 'grid-cols-2 gap-x-3' : 'grid-cols-1'">
      <li
        v-for="requirement in requirements"
        :key="requirement.id"
        class="flex items-center gap-1.5 text-[11px] leading-tight"
        :class="requirement.passed ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-300'"
      >
        <svg v-if="requirement.passed" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
        <svg v-else class="h-3 w-3 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="12" r="9" />
        </svg>
        <span>{{ requirement.label }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  evaluatePasswordRequirements,
  passwordRequirements,
  passwordStrengthLabel,
  passwordStrengthScore,
} from '../../utils/passwordPolicy'

const props = defineProps({
  password: { type: String, default: '' },
  compact: { type: Boolean, default: false },
  showWhenEmpty: { type: Boolean, default: false },
})

const requirements = computed(() => {
  if (!props.password && props.showWhenEmpty) {
    return passwordRequirements.map((requirement) => ({
      ...requirement,
      passed: false,
    }))
  }

  return evaluatePasswordRequirements(props.password)
})

const strengthLabel = computed(() => {
  if (!props.password && props.showWhenEmpty) return 'Required'
  return passwordStrengthLabel(props.password)
})

const score = computed(() => {
  if (!props.password && props.showWhenEmpty) return 0
  return passwordStrengthScore(props.password)
})

const meterPercent = computed(() => (score.value / 5) * 100)

const badgeClasses = computed(() => {
  if (score.value <= 2) return 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300'
  if (score.value <= 3) return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
  if (score.value <= 4) return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'
  return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
})

const meterClasses = computed(() => {
  if (score.value <= 2) return 'bg-red-500'
  if (score.value <= 3) return 'bg-amber-500'
  if (score.value <= 4) return 'bg-blue-500'
  return 'bg-emerald-500'
})
</script>
