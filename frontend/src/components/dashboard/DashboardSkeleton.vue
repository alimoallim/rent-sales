<template>
  <div class="content-panel overflow-hidden p-4 sm:p-5" role="status" aria-label="Loading">
    <KpiStrip>
      <div v-for="n in 4" :key="n" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <SkeletonBlock size="sm" class="mb-3" />
        <SkeletonBlock size="lg" class="mb-2" />
        <SkeletonBlock size="sm" width="60%" />
      </div>
    </KpiStrip>
    <div class="mt-5 grid gap-4 xl:grid-cols-2">
      <div v-for="panel in 2" :key="panel" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <SkeletonBlock size="sm" class="mb-4 w-40" />
        <div class="space-y-3">
          <SkeletonBlock v-for="row in 4" :key="row" size="md" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import KpiStrip from '../ui/KpiStrip.vue'
import SkeletonBlock from '../ui/SkeletonBlock.vue'
</script>
