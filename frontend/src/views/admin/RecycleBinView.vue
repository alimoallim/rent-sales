<template>
  <section>
    <PageHeader
      title="Recycle bin"
      subtitle="Restore deleted records from rental, sales, and admin."
      :breadcrumbs="[{ label: 'Administration' }, { label: 'Recycle bin' }]"
    />

    <FilterBar class="mb-4">
      <select v-model="selectedType" class="input-field" @change="reload">
        <option v-for="type in types" :key="type.key" :value="type.key">
          {{ type.label }}
        </option>
      </select>
      <input
        v-model="search"
        type="search"
        class="input-field"
        placeholder="Search…"
        @input="onSearchInput"
      />
    </FilterBar>

    <DataTable
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      :searchable="false"
      empty-message="Nothing in the recycle bin for this type."
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #cell-deleted_at="{ item }">
        <DateCell :value="item.deleted_at" format="datetime" />
      </template>
      <template #cell-module="{ item }">
        <StatusBadge variant="neutral" :label="item.module" />
      </template>
      <template #actions="{ item }">
        <RowActionButton
          icon="restore"
          label="Restore"
          :disabled="restoringId === item.id"
          @click="restore(item)"
        />
      </template>
    </DataTable>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { fetchRecycleBin, fetchRecycleBinTypes, restoreRecycleBinItem } from '../../api/admin'
import DataTable from '../../components/data/DataTable.vue'
import DateCell from '../../components/data/DateCell.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import PageHeader from '../../components/PageHeader.vue'
import RowActionButton from '../../components/ui/RowActionButton.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const types = ref([])
const selectedType = ref('rental_buildings')
const search = ref('')
const restoringId = ref(null)

const columns = [
  { key: 'label', label: 'Name', cardTitle: true },
  { key: 'type_label', label: 'Type', mobileCard: true },
  { key: 'module', label: 'Module', tabletCard: true },
  { key: 'deleted_at', label: 'Deleted', mobileCard: true },
]

const {
  items,
  loading,
  pagination,
  reload,
  goToPage,
  setPerPage,
} = usePaginatedList(async (params) => {
  const response = await fetchRecycleBin({
    ...params,
    type: selectedType.value,
    search: search.value || undefined,
  })
  return {
    data: response.data,
    meta: response.meta,
  }
})

let searchTimer = null

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 300)
}

async function restore(item) {
  restoringId.value = item.id
  try {
    const response = await restoreRecycleBinItem(item.type, item.id)
    toast.success(response.message || 'Record restored.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not restore record.')
  } finally {
    restoringId.value = null
  }
}

onMounted(async () => {
  const response = await fetchRecycleBinTypes()
  types.value = response.data ?? []
  if (types.value.length && !types.value.some((t) => t.key === selectedType.value)) {
    selectedType.value = types.value[0].key
  }
  await reload()
})
</script>
