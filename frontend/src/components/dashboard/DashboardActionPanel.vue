<template>
  <section class="dashboard-actions" :class="{ 'dashboard-actions-alert': hasHighPriority }">
    <header class="dashboard-actions-header">
      <div class="dashboard-actions-heading">
        <span class="dashboard-actions-icon" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
          </svg>
        </span>
        <div>
          <h3 class="dashboard-actions-title">Action required</h3>
          <p class="dashboard-actions-subtitle">
            {{ actionRequired.total_count }} item{{ actionRequired.total_count === 1 ? '' : 's' }} need attention
            <span v-if="actionRequired.high_priority_count > 0" class="dashboard-actions-urgent">
              · {{ actionRequired.high_priority_count }} urgent
            </span>
          </p>
        </div>
      </div>
    </header>

    <div class="dashboard-actions-body">
      <div v-for="category in actionRequired.categories" :key="category.key" class="dashboard-actions-group">
        <p class="dashboard-actions-group-label">
          {{ category.label }}
          <span class="dashboard-actions-group-count">{{ category.count }}</span>
        </p>
        <ul class="dashboard-actions-list">
          <li v-for="item in category.items" :key="item.id">
            <RouterLink :to="actionTo(item)" class="dashboard-action-item" :class="`dashboard-action-${item.severity}`">
              <span class="dashboard-action-badge" :class="`dashboard-action-badge-${item.severity}`">
                {{ severityLabel(item.severity) }}
              </span>
              <span class="min-w-0 flex-1">
                <span class="dashboard-action-title">{{ item.title }}</span>
                <span class="dashboard-action-description">{{ item.description }}</span>
              </span>
              <span class="dashboard-action-cta">{{ item.action_label }}</span>
            </RouterLink>
          </li>
        </ul>
        <p v-if="category.count > category.items.length" class="dashboard-actions-more">
          +{{ category.count - category.items.length }} more in this category
        </p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  actionRequired: { type: Object, required: true },
})

const hasHighPriority = computed(() => (props.actionRequired?.high_priority_count ?? 0) > 0)

function severityLabel(severity) {
  if (severity === 'high') return 'Urgent'
  if (severity === 'medium') return 'Review'
  return 'Follow up'
}

function actionTo(item) {
  if (!item.action_query) {
    return item.action_path
  }

  return {
    path: item.action_path,
    query: Object.fromEntries(
      Object.entries(item.action_query).map(([key, value]) => [key, String(value)]),
    ),
  }
}
</script>
