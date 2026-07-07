<template>
  <component
    :is="to ? 'RouterLink' : 'div'"
    :to="to"
    class="rounded-xl border p-4 shadow-sm transition-all duration-200"
    :class="[
      accentBorderClass,
      accentBgClass,
      to ? 'hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-950' : '',
      to && accentHoverBorderClass,
    ]"
  >
    <div class="flex items-start justify-between gap-2">
      <p class="text-[11px] font-semibold uppercase tracking-[0.08em]" :class="accentLabelClass">{{ label }}</p>
      <span v-if="icon" class="text-zinc-400 dark:text-zinc-500" aria-hidden="true">
        <component :is="icon" />
      </span>
    </div>
    <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-100">{{ value }}</p>
    <p v-if="hint" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ hint }}</p>
    <p
      v-if="trend !== null && trend !== undefined"
      class="mt-2 text-xs font-medium"
      :class="trend >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
    >
      <span aria-hidden="true">{{ trend >= 0 ? '↑' : '↓' }}</span>
      {{ Math.abs(trend) }}% vs last period
    </p>
  </component>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: String, required: true },
  hint: { type: String, default: '' },
  trend: { type: Number, default: null },
  accent: {
    type: String,
    default: 'neutral',
    validator: (v) => ['neutral', 'success', 'warning', 'danger', 'info', 'accent'].includes(v),
  },
  to: { type: [String, Object], default: null },
  icon: { type: [Object, Function], default: null },
})

const accentBorderClass = computed(() => ({
  neutral: 'border-zinc-200/90 dark:border-zinc-600/80',
  success: 'border-emerald-200 dark:border-emerald-800/70',
  warning: 'border-amber-200 dark:border-amber-800/70',
  danger: 'border-red-200 dark:border-red-900/70',
  info: 'border-sky-200 dark:border-sky-800/70',
  accent: 'border-indigo-200 dark:border-indigo-800/70',
}[props.accent] ?? 'border-zinc-200/90 dark:border-zinc-600/80'))

const accentHoverBorderClass = computed(() => ({
  neutral: 'hover:border-zinc-300 dark:hover:border-zinc-500',
  success: 'hover:border-emerald-300 dark:hover:border-emerald-700',
  warning: 'hover:border-amber-300 dark:hover:border-amber-700',
  danger: 'hover:border-red-300 dark:hover:border-red-800',
  info: 'hover:border-sky-300 dark:hover:border-sky-700',
  accent: 'hover:border-indigo-300 dark:hover:border-indigo-700',
}[props.accent] ?? 'hover:border-zinc-300 dark:hover:border-zinc-500'))

const accentBgClass = computed(() => ({
  neutral: 'bg-gradient-to-br from-zinc-50 to-zinc-100/90 dark:from-zinc-800/90 dark:to-zinc-900/90',
  success: 'bg-gradient-to-br from-emerald-50 to-emerald-100/70 dark:from-emerald-950/70 dark:to-emerald-900/40',
  warning: 'bg-gradient-to-br from-amber-50 to-amber-100/70 dark:from-amber-950/70 dark:to-amber-900/40',
  danger: 'bg-gradient-to-br from-red-50 to-red-100/70 dark:from-red-950/70 dark:to-red-900/40',
  info: 'bg-gradient-to-br from-sky-50 to-sky-100/70 dark:from-sky-950/70 dark:to-sky-900/40',
  accent: 'bg-gradient-to-br from-indigo-50 to-indigo-100/70 dark:from-indigo-950/70 dark:to-indigo-900/40',
}[props.accent] ?? 'bg-gradient-to-br from-zinc-50 to-zinc-100/90 dark:from-zinc-800/90 dark:to-zinc-900/90'))

const accentLabelClass = computed(() => ({
  neutral: 'text-zinc-600 dark:text-zinc-400',
  success: 'text-emerald-800 dark:text-emerald-300',
  warning: 'text-amber-800 dark:text-amber-300',
  danger: 'text-red-800 dark:text-red-300',
  info: 'text-sky-800 dark:text-sky-300',
  accent: 'text-indigo-800 dark:text-indigo-300',
}[props.accent] ?? 'text-zinc-600 dark:text-zinc-400'))
</script>
