<template>
  <div class="content-panel overflow-hidden" role="status" aria-label="Loading">
    <div class="border-b border-zinc-200 bg-zinc-50 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900/50">
      <div class="flex gap-4">
        <SkeletonBlock v-for="n in columns" :key="n" size="sm" class="flex-1" />
      </div>
    </div>
    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
      <div v-for="row in rows" :key="row" class="flex gap-4 px-3 py-3">
        <SkeletonBlock v-for="col in columns" :key="col" size="md" class="flex-1" />
      </div>
    </div>
  </div>
</template>

<script setup>
import SkeletonBlock from '../ui/SkeletonBlock.vue'

defineProps({
  rows: { type: Number, default: 5 },
  columns: { type: Number, default: 4 },
})
</script>
