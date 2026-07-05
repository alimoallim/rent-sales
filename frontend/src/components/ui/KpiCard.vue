<template>
  <component
    :is="to ? 'RouterLink' : 'div'"
    :to="to"
    class="rounded-xl border bg-white p-4 shadow-sm transition-all duration-200 dark:bg-zinc-900"
    :class="[
      accentBorderClass,
      accentBgClass,
      to ? 'hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:hover:border-zinc-600 dark:focus-visible:ring-offset-zinc-950' : '',
    ]"
  >
    <div class="flex items-start justify-between gap-2">
      <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-zinc-500 dark:text-zinc-400">{{ label }}</p>
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
  neutral: 'border-zinc-200 dark:border-zinc-700',
  success: 'border-emerald-200/80 dark:border-emerald-800/60',
  warning: 'border-amber-200/80 dark:border-amber-800/60',
  danger: 'border-red-200/80 dark:border-red-900/60',
  info: 'border-sky-200/80 dark:border-sky-800/60',
  accent: 'border-indigo-200/80 dark:border-indigo-800/60',
}[props.accent] ?? 'border-zinc-200 dark:border-zinc-700'))

const accentBgClass = computed(() => ({
  neutral: '',
  success: 'bg-gradient-to-br from-white to-emerald-50/40 dark:from-zinc-900 dark:to-emerald-950/20',
  warning: 'bg-gradient-to-br from-white to-amber-50/40 dark:from-zinc-900 dark:to-amber-950/20',
  danger: 'bg-gradient-to-br from-white to-red-50/40 dark:from-zinc-900 dark:to-red-950/20',
  info: 'bg-gradient-to-br from-white to-sky-50/40 dark:from-zinc-900 dark:to-sky-950/20',
  accent: 'bg-gradient-to-br from-white to-indigo-50/40 dark:from-zinc-900 dark:to-indigo-950/20',
}[props.accent] ?? ''))
</script>
