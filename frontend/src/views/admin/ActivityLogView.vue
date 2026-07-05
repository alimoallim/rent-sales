<template>
  <section>
    <PageHeader
      title="Activity log"
      subtitle="Who changed what across rental, sales, and admin."
      :breadcrumbs="[{ label: 'Administration' }, { label: 'Activity log' }]"
    />

    <FilterBar class="mb-4">
      <select v-model="filters.action" class="input-field" @change="reload">
        <option value="">All actions</option>
        <option value="created">Created</option>
        <option value="updated">Updated</option>
        <option value="deleted">Deleted</option>
        <option value="restored">Restored</option>
      </select>
      <input
        v-model="filters.search"
        type="search"
        class="input-field"
        placeholder="Search label…"
        @input="onSearchInput"
      />
    </FilterBar>

    <DataTable
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      :searchable="false"
      empty-message="No activity recorded yet."
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #cell-action="{ item }">
        <StatusBadge :variant="actionVariant(item.action)" :label="item.action" />
      </template>
      <template #cell-subject="{ item }">
        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ item.subject_label || '—' }}</span>
        <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ item.subject_type }} #{{ item.subject_id }}</span>
      </template>
      <template #cell-changes="{ item }">
        <span v-if="!item.changes" class="text-zinc-400">—</span>
        <span v-else class="text-xs text-zinc-600 dark:text-zinc-400">{{ formatChanges(item.changes) }}</span>
      </template>
      <template #cell-created_at="{ item }">
        <DateCell :value="item.created_at" format="datetime" />
      </template>
    </DataTable>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { fetchActivityLog } from '../../api/admin'
import DataTable from '../../components/data/DataTable.vue'
import DateCell from '../../components/data/DateCell.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import PageHeader from '../../components/PageHeader.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import { usePaginatedList } from '../../composables/usePaginatedList'

const filters = reactive({
  action: '',
  search: '',
})

const columns = [
  { key: 'created_at', label: 'When', mobileCard: true },
  { key: 'user_name', label: 'User', cardTitle: true },
  { key: 'action', label: 'Action', mobileCard: true },
  { key: 'subject', label: 'Record', tabletCard: true },
  { key: 'changes', label: 'Changes', desktopOnly: true },
]

const {
  items,
  loading,
  pagination,
  reload,
  goToPage,
  setPerPage,
} = usePaginatedList(async (params) => {
  const response = await fetchActivityLog({
    ...params,
    action: filters.action || undefined,
    search: filters.search || undefined,
  })
  return response
})

let searchTimer = null

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 300)
}

function actionVariant(action) {
  if (action === 'created') return 'success'
  if (action === 'deleted') return 'danger'
  if (action === 'restored') return 'info'
  return 'neutral'
}

function formatChanges(changes) {
  if (!changes?.after) return 'Updated'
  const keys = Object.keys(changes.after)
  if (keys.length === 0) return 'Updated'
  if (keys.length <= 2) {
    return keys.map((key) => `${key}: ${changes.before?.[key] ?? '—'} → ${changes.after[key]}`).join('; ')
  }
  return `${keys.length} fields changed`
}

onMounted(reload)
</script>
