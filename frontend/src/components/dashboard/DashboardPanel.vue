<template>
  <section class="dashboard-panel">
    <header class="dashboard-panel-header">
      <div>
        <h3 class="dashboard-panel-title">{{ title }}</h3>
        <p v-if="subtitle" class="dashboard-panel-subtitle">{{ subtitle }}</p>
      </div>
      <RouterLink v-if="actionTo" :to="actionTo" class="dashboard-panel-action">
        {{ actionLabel }}
      </RouterLink>
    </header>
    <div class="dashboard-panel-body">
      <slot />
    </div>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  actionTo: { type: [String, Object], default: null },
  actionLabel: { type: String, default: 'View all' },
})
</script>
