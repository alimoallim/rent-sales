<template>
  <component
    :is="to ? 'RouterLink' : 'div'"
    :to="to"
    class="dashboard-metric"
    :class="[accentClass, { 'dashboard-metric-link': !!to }]"
  >
    <div class="flex items-start justify-between gap-2">
      <p class="dashboard-metric-label">{{ label }}</p>
      <span v-if="icon" class="dashboard-metric-icon" aria-hidden="true">
        <component :is="icon" />
      </span>
    </div>
    <p class="dashboard-metric-value">{{ value }}</p>
    <p v-if="hint" class="dashboard-metric-hint">{{ hint }}</p>
    <p
      v-if="trend !== null && trend !== undefined"
      class="dashboard-metric-trend"
      :class="trend >= 0 ? 'text-emerald-700' : 'text-red-700'"
    >
      {{ trend >= 0 ? '+' : '' }}{{ trend }}% vs last month
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
  accent: { type: String, default: 'neutral' },
  to: { type: [String, Object], default: null },
  icon: { type: [Object, Function], default: null },
})

const accentClass = computed(() => `dashboard-metric-${props.accent}`)
</script>
