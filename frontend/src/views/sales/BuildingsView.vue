<template>
  <section>
    <PageHeader
      title="Sale buildings"
      subtitle="Property groupings for apartment sales."
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: 'Buildings' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add building</button>
      </template>
    </PageHeader>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      empty-message="No sale buildings yet."
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="remove(item)">Delete</button>
      </template>
    </DataTable>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit building' : 'Add building'" size="sm">
      <FormField label="Name" :error="error" required>
        <input v-model="form.name" class="input-field" required />
      </FormField>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" :disabled="saving" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import FormField from '../../components/ui/FormField.vue'
import DataTable from '../../components/data/DataTable.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import { createBuilding, deleteBuilding, fetchBuildings, updateBuilding } from '../../api/sales'

const { confirm } = useConfirm()
const toast = useToast()

const { items, loading, search, pagination, load, reload, goToPage, setPerPage, onSearchChange } = usePaginatedList(
  (params) => fetchBuildings(params),
)
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const form = reactive({ name: '' })

const columns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'units_count', label: 'Units', mobileCard: true, align: 'right', format: (row) => row.units_count ?? 0 },
]

function openCreate() {
  editing.value = null
  form.name = ''
  error.value = ''
  showForm.value = true
}

function openEdit(building) {
  editing.value = building
  form.name = building.name
  error.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

async function save() {
  error.value = ''
  saving.value = true
  try {
    if (editing.value) {
      await updateBuilding(editing.value.id, { name: form.name })
      toast.success('Building updated.')
    } else {
      await createBuilding({ name: form.name })
      toast.success('Building created.')
    }
    closeForm()
    await reload()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save building.'
  } finally {
    saving.value = false
  }
}

async function remove(building) {
  const ok = await confirm({
    title: 'Delete building',
    message: `Delete "${building.name}"? This cannot be undone if units are linked.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteBuilding(building.id)
    toast.success('Building deleted.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete building.')
  }
}

onMounted(load)
</script>
