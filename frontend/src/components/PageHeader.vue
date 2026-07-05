<template>
  <header class="page-header">
    <div class="page-header-main">
      <div class="min-w-0 flex-1">
        <nav v-if="breadcrumbs.length" class="page-header-breadcrumb md:hidden" aria-label="Breadcrumb">
          <ol class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
            <li v-for="(crumb, index) in breadcrumbs" :key="index" class="flex items-center gap-1.5">
              <RouterLink
                v-if="crumb.to"
                :to="crumb.to"
                class="font-medium transition-colors hover:text-zinc-800 dark:hover:text-zinc-200"
              >
                {{ crumb.label }}
              </RouterLink>
              <span v-else :class="index === breadcrumbs.length - 1 ? 'font-medium text-zinc-700 dark:text-zinc-300' : ''">
                {{ crumb.label }}
              </span>
              <span v-if="index < breadcrumbs.length - 1" class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">/</span>
            </li>
          </ol>
        </nav>
        <h1 class="page-title">{{ title }}</h1>
        <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="page-header-actions">
        <slot name="actions" />
      </div>
    </div>

    <div v-if="$slots.kpis" class="page-header-kpis">
      <slot name="kpis" />
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  breadcrumbs: { type: Array, default: () => [] },
})
</script>
