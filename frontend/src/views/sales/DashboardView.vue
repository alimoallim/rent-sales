<template>
  <section>
    <PageHeader title="Sales dashboard" subtitle="Overview of sale buildings, units, and client balances." />

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="card in cards" :key="card.label" class="stat-card">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ card.label }}</p>
        <p class="mt-1 text-xl font-semibold tabular-nums tracking-tight text-zinc-900">{{ card.value }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import { fetchDashboard } from '../../api/sales'

const stats = ref(null)

const cards = computed(() => [
  { label: 'Sale buildings', value: stats.value?.buildings ?? '—' },
  { label: 'Active clients', value: stats.value?.active_clients ?? '—' },
  { label: 'Available units', value: stats.value?.available_units ?? '—' },
  { label: 'Sold units', value: stats.value?.sold_units ?? '—' },
  { label: 'Clients with balance', value: stats.value?.clients_with_balance ?? '—' },
  { label: 'Disabled clients', value: stats.value?.disabled_clients ?? '—' },
])

onMounted(async () => {
  stats.value = await fetchDashboard()
})
</script>
